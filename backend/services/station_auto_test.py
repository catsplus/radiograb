#!/usr/bin/env python3
"""
RadioGrab Automated Station Testing Service
Periodically tests all stations to verify stream availability
"""

import sys
import os
import argparse
import time
import logging
from datetime import datetime, timedelta
from pathlib import Path

# Add the project root to Python path
sys.path.insert(0, '/opt/radiograb')

from backend.config.database import SessionLocal
from backend.models.station import Station
from backend.services.test_recording_service import perform_recording, update_station_test_status

# Set up logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

class StationAutoTester:
    """Automated station testing service"""
    
    def __init__(self, test_duration=10):
        self.test_duration = test_duration
        self.temp_dir = Path('/var/radiograb/temp')
        self.temp_dir.mkdir(parents=True, exist_ok=True)
        
    def get_stations_to_test(self, max_age_hours=24):
        """Get stations that haven't been tested recently"""
        try:
            db = SessionLocal()
            try:
                cutoff_time = datetime.now() - timedelta(hours=max_age_hours)
                
                # Get stations that either:
                # 1. Have never been tested (last_tested is NULL)
                # 2. Haven't been tested in the specified time period
                # 3. Last test failed/errored
                stations = db.query(Station).filter(
                    Station.status == 'active',
                    Station.stream_url.isnot(None),
                    Station.stream_url != '',
                    # Include stations that need testing
                    (Station.last_tested.is_(None) |
                     (Station.last_tested < cutoff_time) |
                     (Station.last_test_result.in_(['failed', 'error'])))
                ).all()
                
                return stations
            finally:
                db.close()
        except Exception as e:
            logger.error(f"Error getting stations to test: {e}")
            return []
    
    def test_station(self, station, attempt_rediscovery=True):
        """Test a single station"""
        logger.info(f"Testing station {station.id}: {station.name}")
        
        try:
            # Generate test filename
            timestamp = datetime.now().strftime('%Y-%m-%d-%H%M%S')
            call_letters = station.call_letters or f"STN{station.id}"
            filename = f"{call_letters}_autotest_{timestamp}.mp3"
            output_file = self.temp_dir / filename
            
            # Perform the test recording
            success, message = perform_recording(
                station.stream_url, 
                str(output_file), 
                self.test_duration
            )
            
            # If test failed and we haven't tried rediscovery yet, attempt stream rediscovery
            if not success and attempt_rediscovery:
                logger.info(f"Test failed for {station.name}, attempting stream rediscovery...")
                
                try:
                    from backend.services.stream_discovery import RadioStreamDiscovery
                    discovery = RadioStreamDiscovery()
                    
                    # Try to find a new stream
                    stream_info = discovery.find_best_stream_match(station.name, station.stream_url)
                    
                    if stream_info and stream_info['stream_url'] != station.stream_url:
                        logger.info(f"Found new stream for {station.name}: {stream_info['stream_url']}")
                        
                        # Update the station with new stream
                        if discovery.update_station_stream(station.id, stream_info):
                            logger.info(f"Updated {station.name} with new stream, retesting...")
                            
                            # Refresh station object with new stream URL
                            db = SessionLocal()
                            try:
                                updated_station = db.query(Station).filter(Station.id == station.id).first()
                                if updated_station and updated_station.stream_url != station.stream_url:
                                    # Retry test with new stream (no rediscovery on retry)
                                    return self.test_station(updated_station, attempt_rediscovery=False)
                            finally:
                                db.close()
                    else:
                        logger.warning(f"No better stream found for {station.name}")
                        
                except ImportError:
                    logger.warning("Stream discovery service not available")
                except Exception as e:
                    logger.error(f"Stream rediscovery failed for {station.name}: {e}")
            
            # Update station test status
            update_station_test_status(station.id, success, message if not success else None)
            
            # Clean up test file (keep only if test failed for debugging)
            if success and output_file.exists():
                try:
                    output_file.unlink()
                    logger.info(f"Cleaned up test file: {filename}")
                except Exception as e:
                    logger.warning(f"Could not remove test file {filename}: {e}")
            
            result = {
                'station_id': station.id,
                'station_name': station.name,
                'success': success,
                'message': message,
                'filename': filename if not success else None,
                'rediscovery_attempted': attempt_rediscovery
            }
            
            if success:
                logger.info(f"✅ Station {station.name} test successful")
            else:
                logger.warning(f"❌ Station {station.name} test failed: {message}")
                
            return result
            
        except Exception as e:
            error_message = f"Test error: {str(e)}"
            logger.error(f"Error testing station {station.name}: {error_message}")
            
            # Update station with error status
            update_station_test_status(station.id, False, error_message)
            
            return {
                'station_id': station.id,
                'station_name': station.name,
                'success': False,
                'message': error_message,
                'filename': None,
                'rediscovery_attempted': attempt_rediscovery
            }
    
    def test_all_stations(self, max_age_hours=24, delay_between_tests=5, auto_rediscovery=True):
        """Test all stations that need testing"""
        logger.info(f"Starting automated station testing (max_age: {max_age_hours}h)")
        
        stations = self.get_stations_to_test(max_age_hours)
        
        if not stations:
            logger.info("No stations need testing at this time")
            return []
        
        logger.info(f"Found {len(stations)} stations to test")
        
        results = []
        
        for i, station in enumerate(stations):
            result = self.test_station(station, attempt_rediscovery=auto_rediscovery)
            results.append(result)
            
            # Add delay between tests to avoid overwhelming streams
            if i < len(stations) - 1:  # Don't delay after the last test
                logger.info(f"Waiting {delay_between_tests}s before next test...")
                time.sleep(delay_between_tests)
        
        # Summary
        successful = sum(1 for r in results if r['success'])
        failed = len(results) - successful
        
        logger.info(f"Testing complete: {successful} successful, {failed} failed")
        
        if failed > 0:
            logger.info("Failed stations:")
            for result in results:
                if not result['success']:
                    logger.info(f"  - {result['station_name']}: {result['message']}")
        
        return results
    
    def get_station_status_summary(self):
        """Get summary of all station test statuses"""
        try:
            db = SessionLocal()
            try:
                stations = db.query(Station).filter(
                    Station.status == 'active',
                    Station.stream_url.isnot(None),
                    Station.stream_url != ''
                ).all()
                
                summary = {
                    'total': len(stations),
                    'never_tested': 0,
                    'success': 0,
                    'failed': 0,
                    'error': 0,
                    'outdated': 0  # Tested more than 24h ago
                }
                
                cutoff_time = datetime.now() - timedelta(hours=24)
                
                for station in stations:
                    if not station.last_tested:
                        summary['never_tested'] += 1
                    elif station.last_tested < cutoff_time:
                        summary['outdated'] += 1
                    elif station.last_test_result == 'success':
                        summary['success'] += 1
                    elif station.last_test_result == 'failed':
                        summary['failed'] += 1
                    else:
                        summary['error'] += 1
                
                return summary
            finally:
                db.close()
        except Exception as e:
            logger.error(f"Error getting status summary: {e}")
            return None

def main():
    parser = argparse.ArgumentParser(description='RadioGrab Automated Station Testing')
    parser.add_argument('--max-age', type=int, default=24, 
                       help='Test stations not tested in this many hours (default: 24)')
    parser.add_argument('--test-duration', type=int, default=10,
                       help='Test recording duration in seconds (default: 10)')
    parser.add_argument('--delay', type=int, default=5,
                       help='Delay between tests in seconds (default: 5)')
    parser.add_argument('--summary-only', action='store_true',
                       help='Show status summary without testing')
    parser.add_argument('--daemon', action='store_true',
                       help='Run as daemon (test every 6 hours)')
    parser.add_argument('--no-rediscovery', action='store_true',
                       help='Disable automatic stream rediscovery for failed stations')
    
    args = parser.parse_args()
    
    tester = StationAutoTester(test_duration=args.test_duration)
    
    if args.summary_only:
        summary = tester.get_station_status_summary()
        if summary:
            print(f"Station Test Status Summary:")
            print(f"  Total stations: {summary['total']}")
            print(f"  Never tested: {summary['never_tested']}")
            print(f"  Recent success: {summary['success']}")
            print(f"  Recent failed: {summary['failed']}")
            print(f"  Recent error: {summary['error']}")
            print(f"  Outdated (>24h): {summary['outdated']}")
        return
    
    if args.daemon:
        logger.info("Starting station auto-test daemon (testing every 6 hours)")
        while True:
            try:
                tester.test_all_stations(args.max_age, args.delay, auto_rediscovery=not args.no_rediscovery)
                logger.info("Sleeping for 6 hours...")
                time.sleep(6 * 60 * 60)  # 6 hours
            except KeyboardInterrupt:
                logger.info("Daemon stopped by user")
                break
            except Exception as e:
                logger.error(f"Daemon error: {e}")
                logger.info("Sleeping for 1 hour before retry...")
                time.sleep(60 * 60)  # 1 hour
    else:
        # Single run
        tester.test_all_stations(args.max_age, args.delay, auto_rediscovery=not args.no_rediscovery)

if __name__ == '__main__':
    main()