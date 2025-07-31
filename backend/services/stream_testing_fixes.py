"""
Provides fixes and enhancements for common stream testing issues.

This service includes strategies for handling HTTP 403 errors by rotating User-Agents,
discovering alternative stream URLs, and verifying recording tool paths.

Key Variables:
- `stream_url`: The URL of the stream to test.
- `duration`: The duration of the test recording in seconds.
- `station_id`: The ID of the station for User-Agent persistence.

Inter-script Communication:
- This script is used by `station_auto_test.py`.
- It interacts with the `Station` model from `backend/models/station.py` to save User-Agents.
"""
"""
Stream Testing Fixes and Enhancements
Collection of fixes for common stream testing issues and improved discovery methods
"""
import os
import sys
import subprocess
import logging
from pathlib import Path
from typing import Dict, List, Tuple, Optional

# Add project root to path
sys.path.insert(0, '/opt/radiograb')

logger = logging.getLogger(__name__)

class StreamTestingFixes:
    """Collection of fixes and enhancements for stream testing"""
    
    def __init__(self):
        self.recording_tools = {
            'streamripper': '/usr/bin/streamripper',
            'ffmpeg': '/usr/bin/ffmpeg', 
            'wget': '/usr/bin/wget'
        }
        
        # Common User-Agents that work with problematic streams
        self.user_agents = [
            None,  # Default
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'iTunes/12.0.0 (Macintosh; OS X 10.10.5) AppleWebKit/600.8.9',
            'VLC/3.0.0 LibVLC/3.0.0',
            'Radio-Browser/1.0',
            'Winamp/5.0'
        ]
    
    def fix_tool_path_issues(self) -> Dict[str, str]:
        """
        Fix common tool path issues by verifying tool locations
        
        Returns:
            Dictionary of verified tool paths
        """
        verified_tools = {}
        
        for tool_name, default_path in self.recording_tools.items():
            # First try default path
            if os.path.exists(default_path) and os.access(default_path, os.X_OK):
                verified_tools[tool_name] = default_path
                continue
            
            # Try using 'which' command as fallback    
            try:
                result = subprocess.run(['which', tool_name], 
                                      capture_output=True, text=True, timeout=5)
                if result.returncode == 0:
                    tool_path = result.stdout.strip()
                    if os.path.exists(tool_path) and os.access(tool_path, os.X_OK):
                        verified_tools[tool_name] = tool_path
                        logger.info(f"Found {tool_name} at {tool_path} via 'which'")
                        continue
            except Exception as e:
                logger.warning(f"Failed to locate {tool_name} with 'which': {e}")
            
            # Try common alternative paths
            alt_paths = [
                f'/usr/local/bin/{tool_name}',
                f'/bin/{tool_name}',
                f'/usr/sbin/{tool_name}'
            ]
            
            for alt_path in alt_paths:
                if os.path.exists(alt_path) and os.access(alt_path, os.X_OK):
                    verified_tools[tool_name] = alt_path 
                    logger.info(f"Found {tool_name} at alternative path {alt_path}")
                    break
            else:
                logger.error(f"Could not locate {tool_name} - recording may fail")
        
        return verified_tools
    
    def test_stream_with_enhanced_error_handling(self, stream_url: str, 
                                               duration: int = 10,
                                               station_id: Optional[int] = None) -> Tuple[bool, str, Dict]:
        """
        Test stream with enhanced error handling and multiple recovery strategies
        
        Args:
            stream_url: URL to test
            duration: Test duration in seconds
            station_id: Optional station ID for User-Agent persistence
            
        Returns:
            Tuple of (success, error_message, details_dict)
        """
        details = {
            'tools_tried': [],
            'user_agents_tried': [],
            'final_tool': None,
            'final_user_agent': None,
            'file_size': 0,
            'strategies_used': []
        }
        
        # Verify tools are available
        tools = self.fix_tool_path_issues()
        if not tools:
            return False, "No recording tools available", details
        
        # Create temp file for testing
        temp_dir = Path('/var/radiograb/temp')
        temp_dir.mkdir(parents=True, exist_ok=True)
        
        import time
        timestamp = int(time.time())
        test_file = temp_dir / f"stream_test_{timestamp}.mp3"
        
        try:
            # Strategy 1: Try saved User-Agent if station_id provided
            if station_id:
                saved_ua = self._get_saved_user_agent(station_id)
                if saved_ua:
                    details['strategies_used'].append('saved_user_agent')
                    success, error = self._try_with_user_agent(
                        stream_url, str(test_file), duration, saved_ua, tools
                    )
                    if success:
                        details.update({
                            'final_user_agent': saved_ua,
                            'file_size': test_file.stat().st_size if test_file.exists() else 0
                        })
                        return True, "Success with saved User-Agent", details
                    details['user_agents_tried'].append(saved_ua)
            
            # Strategy 2: Try default (no User-Agent)
            details['strategies_used'].append('default_no_user_agent')
            success, error = self._try_with_user_agent(
                stream_url, str(test_file), duration, None, tools
            )
            if success:
                details.update({
                    'final_user_agent': None,
                    'file_size': test_file.stat().st_size if test_file.exists() else 0
                })
                return True, "Success with default settings", details
            
            # Strategy 3: If HTTP 403, try different User-Agents
            if self._is_http_403_error(error):
                details['strategies_used'].append('user_agent_rotation')
                for user_agent in self.user_agents[1:]:  # Skip None (already tried)
                    if user_agent in details['user_agents_tried']:
                        continue
                        
                    success, ua_error = self._try_with_user_agent(
                        stream_url, str(test_file), duration, user_agent, tools
                    )
                    details['user_agents_tried'].append(user_agent)
                    
                    if success:
                        # Save successful User-Agent
                        if station_id:
                            self._save_user_agent(station_id, user_agent)
                        
                        details.update({
                            'final_user_agent': user_agent,
                            'file_size': test_file.stat().st_size if test_file.exists() else 0
                        })
                        return True, "Success with User-Agent rotation", details
                    
                    error = ua_error  # Keep last error
            
            # Strategy 4: Enhanced stream URL discovery
            details['strategies_used'].append('url_discovery')
            discovered_urls = self._discover_alternative_stream_urls(stream_url)
            for alt_url in discovered_urls:
                if alt_url == stream_url:
                    continue
                    
                success, disc_error = self._try_with_user_agent(
                    alt_url, str(test_file), duration, None, tools
                )
                if success:
                    details.update({
                        'final_url': alt_url,
                        'file_size': test_file.stat().st_size if test_file.exists() else 0
                    })
                    return True, f"Success with discovered URL: {alt_url}", details
            
            return False, error, details
            
        finally:
            # Cleanup test file
            if test_file.exists():
                try:
                    test_file.unlink()
                except Exception as e:
                    logger.warning(f"Could not remove test file: {e}")
    
    def _try_with_user_agent(self, stream_url: str, output_file: str, 
                           duration: int, user_agent: Optional[str], 
                           tools: Dict[str, str]) -> Tuple[bool, str]:
        """Try recording with specific User-Agent using multiple tools"""
        
        # Tool priority order
        tool_order = ['streamripper', 'ffmpeg', 'wget']
        
        for tool_name in tool_order:
            if tool_name not in tools:
                continue
                
            try:
                if tool_name == 'streamripper':
                    # streamripper doesn't support User-Agent, skip if User-Agent needed
                    if user_agent:
                        continue
                    success, error = self._record_with_streamripper(
                        stream_url, output_file, duration, tools[tool_name]
                    )
                elif tool_name == 'ffmpeg':
                    success, error = self._record_with_ffmpeg(
                        stream_url, output_file, duration, user_agent, tools[tool_name]
                    )
                elif tool_name == 'wget':
                    success, error = self._record_with_wget(
                        stream_url, output_file, duration, user_agent, tools[tool_name]
                    )
                else:
                    continue
                    
                if success:
                    return True, f"Success with {tool_name}"
                    
            except Exception as e:
                error = f"{tool_name} exception: {str(e)}"
                logger.warning(f"Tool {tool_name} failed: {error}")
        
        return False, error
    
    def _record_with_streamripper(self, stream_url: str, output_file: str, 
                                duration: int, tool_path: str) -> Tuple[bool, str]:
        """Record with streamripper using full path"""
        cmd = [tool_path, stream_url, '-l', str(duration), '-a', output_file, '-A', '-s', '-q']
        
        try:
            result = subprocess.run(cmd, capture_output=True, text=True, timeout=duration + 30)
            if result.stderr and ('error' in result.stderr.lower() or 'forbidden' in result.stderr.lower()):
                return False, result.stderr
            return result.returncode == 0, result.stderr
        except subprocess.TimeoutExpired:
            return False, "streamripper timeout"
        except Exception as e:
            return False, str(e)
    
    def _record_with_ffmpeg(self, stream_url: str, output_file: str, duration: int,
                          user_agent: Optional[str], tool_path: str) -> Tuple[bool, str]:
        """Record with ffmpeg using full path and optional User-Agent"""
        cmd = [tool_path, '-i', stream_url, '-t', str(duration), '-acodec', 'mp3', '-y', output_file]
        
        if user_agent:
            cmd.insert(1, '-user_agent')
            cmd.insert(2, user_agent)
        
        try:
            result = subprocess.run(cmd, capture_output=True, text=True, timeout=duration + 30)
            return result.returncode == 0, result.stderr
        except subprocess.TimeoutExpired:
            return False, "ffmpeg timeout"
        except Exception as e:
            return False, str(e)
    
    def _record_with_wget(self, stream_url: str, output_file: str, duration: int,
                        user_agent: Optional[str], tool_path: str) -> Tuple[bool, str]:
        """Record with wget using full path and optional User-Agent"""
        cmd = ['timeout', str(duration), tool_path, '-O', output_file, '--timeout=10', '--tries=3']
        
        if user_agent:
            cmd.extend(['--user-agent', user_agent])
        
        cmd.append(stream_url)
        
        try:
            result = subprocess.run(cmd, capture_output=True, text=True)
            return result.returncode in [0, 124], result.stderr  # 124 = timeout reached (expected)
        except Exception as e:
            return False, str(e)
    
    def _is_http_403_error(self, error_message: str) -> bool:
        """Check if error indicates HTTP 403 Access Forbidden"""
        if not error_message:
            return False
        
        error_lower = error_message.lower()
        return any(indicator in error_lower for indicator in [
            'http:403', 'access forbidden', 'forbidden', 'error -56',
            '403 forbidden', 'access denied', 'authorization failed'
        ])
    
    def _get_saved_user_agent(self, station_id: int) -> Optional[str]:
        """Get saved User-Agent for station"""
        try:
            from backend.config.database import SessionLocal
            from backend.models.station import Station
            
            db = SessionLocal()
            try:
                station = db.query(Station).filter(Station.id == station_id).first()
                return station.user_agent if station else None
            finally:
                db.close()
        except Exception as e:
            logger.warning(f"Could not get saved User-Agent: {e}")
            return None
    
    def _save_user_agent(self, station_id: int, user_agent: str) -> bool:
        """Save successful User-Agent to database"""
        try:
            from backend.config.database import SessionLocal
            from backend.models.station import Station
            
            db = SessionLocal()
            try:
                station = db.query(Station).filter(Station.id == station_id).first()
                if station:
                    station.user_agent = user_agent
                    db.commit()
                    logger.info(f"Saved User-Agent for station {station_id}")
                    return True
                return False
            finally:
                db.close()
        except Exception as e:
            logger.error(f"Error saving User-Agent: {e}")
            return False
    
    def _discover_alternative_stream_urls(self, original_url: str) -> List[str]:
        """Discover alternative stream URLs for problematic streams"""
        alternatives = []
        
        # Common URL transformations for known problematic patterns
        url_lower = original_url.lower()
        
        # StreamTheWorld URL variations
        if 'streamtheworld.com' in url_lower:
            base_url = original_url
            # For streamtheworld.com URLs, try different quality variants
            if 'HD2.mp3' in original_url:
                # Try different quality streams for HD2 variants
                base_stream = original_url.replace('HD2.mp3', '.mp3')
                hd1_stream = original_url.replace('HD2.mp3', 'HD1.mp3')
                alternatives.extend([
                    base_stream,
                    hd1_stream,
                    original_url.replace('https://', 'http://'),
                    original_url.replace(':443', ':80')
                ])
        
        # Icecast/Shoutcast variations
        if any(keyword in url_lower for keyword in ['icecast', 'shoutcast', '.streamguys1.com']):
            # Try different endpoints
            if '/live' in original_url:
                alternatives.extend([
                    original_url.replace('/live', '/stream'),
                    original_url.replace('/live', '/listen'),
                    original_url.replace('/live', '/radio')
                ])
            
            # Try HTTP vs HTTPS
            if original_url.startswith('https://'):
                alternatives.append(original_url.replace('https://', 'http://'))
            elif original_url.startswith('http://'):
                alternatives.append(original_url.replace('http://', 'https://'))
        
        return alternatives


def apply_stream_testing_fixes():
    """Apply stream testing fixes to existing stations with failed tests"""
    try:
        from backend.config.database import SessionLocal
        from backend.models.station import Station
        
        fixer = StreamTestingFixes()
        db = SessionLocal()
        
        try:
            # Get stations with failed tests
            failed_stations = db.query(Station).filter(
                Station.status == 'active',
                Station.last_test_result == 'failed'
            ).all()
            
            logger.info(f"Applying fixes to {len(failed_stations)} stations with failed tests")
            
            for station in failed_stations:
                logger.info(f"Testing fixes for station {station.id}: {station.name}")
                
                success, message, details = fixer.test_stream_with_enhanced_error_handling(
                    station.stream_url, duration=10, station_id=station.id
                )
                
                if success:
                    # Update station test status
                    station.last_test_result = 'success'
                    station.last_test_error = None
                    db.commit()
                    
                    logger.info(f"✅ Fixed station {station.name}: {message}")
                    logger.info(f"   Strategies used: {', '.join(details['strategies_used'])}")
                    logger.info(f"   Final tool: {details.get('final_tool', 'N/A')}")
                    logger.info(f"   User-Agent: {details.get('final_user_agent', 'Default')}")
                else:
                    logger.warning(f"❌ Still failing: {station.name} - {message}")
                    
        finally:
            db.close()
            
    except Exception as e:
        logger.error(f"Error applying stream testing fixes: {e}")


if __name__ == '__main__':
    import argparse
    
    parser = argparse.ArgumentParser(description='Stream Testing Fixes')
    parser.add_argument('--apply-fixes', action='store_true', 
                       help='Apply fixes to stations with failed tests')
    parser.add_argument('--test-url', help='Test a specific stream URL')
    parser.add_argument('--station-id', type=int, help='Station ID for User-Agent persistence')
    
    args = parser.parse_args()
    
    logging.basicConfig(level=logging.INFO)
    
    if args.apply_fixes:
        apply_stream_testing_fixes()
    elif args.test_url:
        fixer = StreamTestingFixes()
        success, message, details = fixer.test_stream_with_enhanced_error_handling(
            args.test_url, station_id=args.station_id
        )
        
        print(f"Test result: {'SUCCESS' if success else 'FAILED'}")
        print(f"Message: {message}")
        print(f"Details: {details}")
    else:
        parser.print_help()