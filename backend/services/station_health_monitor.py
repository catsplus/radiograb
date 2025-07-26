#!/usr/bin/env python3
"""
RadioGrab Station Health Monitor
Daily automated testing of all radio stations to ensure streams are working
"""

import sys
import os
import subprocess
import time
import json
from datetime import datetime, timedelta
from pathlib import Path

# Add the project root to Python path
sys.path.insert(0, '/opt/radiograb')

try:
    from backend.config.database import SessionLocal
    from backend.services.test_recording_service import perform_recording
    from backend.services.enhanced_discovery import AdvancedStationDiscovery
except ImportError as e:
    print(f"Error importing modules: {e}")
    sys.exit(1)

TEMP_DIR = '/var/radiograb/temp'
LOGS_DIR = '/var/radiograb/logs'

class StationHealthMonitor:
    def __init__(self):
        self.db = SessionLocal()
        self.results = {
            'timestamp': datetime.now().isoformat(),
            'stations_tested': 0,
            'stations_passed': 0,
            'stations_failed': 0,
            'details': []
        }
    
    def log(self, message):
        """Log message with timestamp"""
        timestamp = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        print(f"[{timestamp}] {message}")
    
    def get_stations_needing_test(self):
        """Get stations that need testing (no successful recording in 24+ hours)"""
        try:
            cursor = self.db.cursor()
            cursor.execute("""
                SELECT id, name, call_letters, stream_url, website_url, status, last_tested
                FROM stations 
                WHERE status = 'active' 
                  AND stream_url IS NOT NULL 
                  AND stream_url != ''
                  AND (
                    last_tested IS NULL 
                    OR last_tested < DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    OR last_test_result != 'success'
                  )
                ORDER BY last_tested ASC, id
            """)
            stations = cursor.fetchall()
            return stations
        except Exception as e:
            self.log(f"Error fetching stations needing test: {e}")
            return []
    
    def test_station_stream(self, station_id, station_name, call_letters, stream_url):
        """Test a single station's stream"""
        self.log(f"Testing {station_name} ({call_letters}) - {stream_url}")
        
        # Generate test filename
        timestamp = datetime.now().strftime('%Y-%m-%d-%H%M%S')
        if not call_letters:
            call_letters = f"STATION{station_id}"
        
        filename = f"{call_letters}_healthcheck_{timestamp}.mp3"
        output_file = os.path.join(TEMP_DIR, filename)
        
        try:
            # Perform 10-second test recording
            success, error_msg = perform_recording(stream_url, output_file, 10)
            
            if success and os.path.exists(output_file):
                file_size = os.path.getsize(output_file)
                if file_size > 1000:  # Minimum 1KB for valid recording
                    self.log(f"✅ {station_name} - Recording successful ({file_size} bytes)")
                    
                    # Clean up test file immediately after successful test
                    try:
                        os.remove(output_file)
                        self.log(f"   Cleaned up test file: {filename}")
                    except Exception as e:
                        self.log(f"   Warning: Could not clean up {filename}: {e}")
                    
                    # Also clean up any .cue files
                    cue_file = output_file.replace('.mp3', '.cue')
                    if os.path.exists(cue_file):
                        try:
                            os.remove(cue_file)
                        except:
                            pass
                    
                    return True, f"Recording successful ({file_size} bytes)"
                else:
                    self.log(f"❌ {station_name} - File too small ({file_size} bytes)")
                    return False, f"Recording file too small ({file_size} bytes)"
            else:
                self.log(f"❌ {station_name} - Recording failed: {error_msg}")
                return False, error_msg or "Recording failed"
                
        except Exception as e:
            self.log(f"❌ {station_name} - Exception during test: {e}")
            return False, str(e)
    
    def attempt_stream_rediscovery(self, station_id, station_name, website_url):
        """Attempt to rediscover working stream URL when test fails"""
        if not website_url:
            self.log(f"   No website URL for {station_name} - cannot rediscover stream")
            return False, None
        
        self.log(f"   Attempting stream rediscovery for {station_name} from {website_url}")
        
        try:
            discovery = AdvancedStationDiscovery()
            result = discovery.discover_station_info(website_url, station_name)
            
            if result.get('stream_url') and result['stream_url'] != '':
                new_stream_url = result['stream_url']
                self.log(f"   Found new stream URL: {new_stream_url}")
                
                # Test the new stream URL
                timestamp = datetime.now().strftime('%Y-%m-%d-%H%M%S')
                call_letters = f"STATION{station_id}"
                filename = f"{call_letters}_rediscovery_{timestamp}.mp3"
                output_file = os.path.join(TEMP_DIR, filename)
                
                success, error_msg = perform_recording(new_stream_url, output_file, 10)
                
                if success and os.path.exists(output_file):
                    file_size = os.path.getsize(output_file)
                    if file_size > 1000:
                        self.log(f"   ✅ New stream URL works! Updating database.")
                        
                        # Update the station's stream URL
                        try:
                            cursor = self.db.cursor()
                            cursor.execute("""
                                UPDATE stations 
                                SET stream_url = %s
                                WHERE id = %s
                            """, (new_stream_url, station_id))
                            self.db.commit()
                            
                            # Clean up test file
                            os.remove(output_file)
                            cue_file = output_file.replace('.mp3', '.cue')
                            if os.path.exists(cue_file):
                                os.remove(cue_file)
                            
                            return True, new_stream_url
                            
                        except Exception as e:
                            self.log(f"   Error updating stream URL: {e}")
                            return False, str(e)
                    else:
                        self.log(f"   New stream test failed: file too small ({file_size} bytes)")
                else:
                    self.log(f"   New stream test failed: {error_msg}")
            else:
                self.log(f"   No new stream URL found during rediscovery")
                
        except Exception as e:
            self.log(f"   Stream rediscovery failed: {e}")
        
        return False, "Stream rediscovery failed"
    
    def update_station_test_result(self, station_id, success, error_msg=None):
        """Update station's test result in database"""
        try:
            cursor = self.db.cursor()
            
            result = 'success' if success else 'failed'
            test_time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            
            cursor.execute("""
                UPDATE stations 
                SET last_tested = %s, 
                    last_test_result = %s, 
                    last_test_error = %s
                WHERE id = %s
            """, (test_time, result, error_msg, station_id))
            
            self.db.commit()
            
        except Exception as e:
            self.log(f"Error updating station {station_id} test result: {e}")
    
    def run_health_check(self):
        """Run health check on stations that need testing"""
        self.log("Starting RadioGrab Station Health Check")
        self.log("=" * 50)
        
        stations = self.get_stations_needing_test()
        if not stations:
            self.log("No stations need testing (all have successful recordings within 24 hours)")
            return
        
        self.log(f"Found {len(stations)} stations that need testing")
        self.results['stations_tested'] = len(stations)
        
        for station in stations:
            station_id, name, call_letters, stream_url, website_url, status, last_tested = station
            
            last_tested_str = last_tested.strftime('%Y-%m-%d %H:%M:%S') if last_tested else 'Never'
            self.log(f"Station {name} - Last tested: {last_tested_str}")
            
            # Test the current stream URL
            success, error_msg = self.test_station_stream(
                station_id, name, call_letters, stream_url
            )
            
            # If test failed, try stream rediscovery
            if not success and website_url:
                self.log(f"   Test failed, attempting stream rediscovery...")
                rediscovery_success, new_stream_url = self.attempt_stream_rediscovery(
                    station_id, name, website_url
                )
                
                if rediscovery_success:
                    # Test the new stream URL
                    success, error_msg = self.test_station_stream(
                        station_id, name, call_letters, new_stream_url
                    )
                    if success:
                        error_msg = f"Stream URL updated to: {new_stream_url}"
            
            # Update database with final result
            self.update_station_test_result(station_id, success, error_msg if not success else None)
            
            # Track results
            if success:
                self.results['stations_passed'] += 1
            else:
                self.results['stations_failed'] += 1
            
            # Add to detailed results
            self.results['details'].append({
                'station_id': station_id,
                'name': name,
                'call_letters': call_letters,
                'stream_url': stream_url,
                'success': success,
                'error': error_msg if not success else None,
                'last_tested': last_tested_str
            })
            
            # Brief pause between tests
            time.sleep(2)
        
        self.log("=" * 50)
        self.log("Health Check Summary:")
        self.log(f"  Stations Tested: {self.results['stations_tested']}")
        self.log(f"  Passed: {self.results['stations_passed']}")
        self.log(f"  Failed: {self.results['stations_failed']}")
        
        # Save results to log file
        self.save_results()
    
    def save_results(self):
        """Save detailed results to log file"""
        try:
            os.makedirs(LOGS_DIR, exist_ok=True)
            
            log_file = os.path.join(LOGS_DIR, 'station_health_check.json')
            with open(log_file, 'w') as f:
                json.dump(self.results, f, indent=2)
            
            self.log(f"Results saved to {log_file}")
            
        except Exception as e:
            self.log(f"Error saving results: {e}")
    
    def cleanup_old_test_files(self):
        """Clean up any old test files that weren't removed"""
        try:
            self.log("Cleaning up old test files...")
            
            # Find test files older than 1 hour
            cutoff_time = time.time() - 3600  # 1 hour ago
            
            for filename in os.listdir(TEMP_DIR):
                filepath = os.path.join(TEMP_DIR, filename)
                
                # Only clean up health check files (not user test files)
                if 'healthcheck' in filename and os.path.isfile(filepath):
                    if os.path.getmtime(filepath) < cutoff_time:
                        try:
                            os.remove(filepath)
                            self.log(f"   Removed old test file: {filename}")
                        except Exception as e:
                            self.log(f"   Could not remove {filename}: {e}")
        
        except Exception as e:
            self.log(f"Error during cleanup: {e}")
    
    def close(self):
        """Close database connection"""
        if self.db:
            self.db.close()

def main():
    monitor = None
    try:
        monitor = StationHealthMonitor()
        
        # Clean up old files first
        monitor.cleanup_old_test_files()
        
        # Run the health check
        monitor.run_health_check()
        
    except Exception as e:
        print(f"Error in station health monitor: {e}")
        sys.exit(1)
    finally:
        if monitor:
            monitor.close()

if __name__ == "__main__":
    main()