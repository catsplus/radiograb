"""
Tests stream URLs for compatibility with various recording tools.

This service performs comprehensive or quick tests of a given stream URL using
`streamripper`, `ffmpeg`, and `wget`. It analyzes the results to recommend the
best tool for recording and provides detailed insights into stream quality.

Key Variables:
- `stream_url`: The URL of the stream to test.
- `station_name`: The name of the station associated with the stream.
- `test_duration`: The duration of the test recording in seconds.

Inter-script Communication:
- This script is used by `station_discovery.py` and `station_auto_test.py`.
- It does not directly interact with the database.
"""
"""
Stream Testing Service for RadioGrab
Automatically tests stream URLs when adding new stations
Integrated with station discovery workflow
"""

import subprocess
import tempfile
import os
import time
from datetime import datetime
from typing import Dict, List, Optional, Tuple
import logging

logger = logging.getLogger(__name__)

class StreamTester:
    """Comprehensive stream testing for new stations"""
    
    def __init__(self):
        self.tools = {
            'streamripper': '/usr/bin/streamripper',
            'ffmpeg': '/usr/bin/ffmpeg',
            'wget': '/usr/bin/wget'
        }
        self.test_duration = 5  # seconds for quick test
    
    def test_stream_comprehensive(self, stream_url: str, station_name: str = "Unknown") -> Dict:
        """
        Comprehensive test of stream URL with all tools
        Returns detailed results and recommendations
        """
        results = {
            'stream_url': stream_url,
            'station_name': station_name,
            'timestamp': datetime.now().isoformat(),
            'overall_status': 'unknown',
            'recommended_tool': None,
            'tools_tested': {},
            'stream_info': {},
            'quality_metrics': {},
            'errors': []
        }
        
        logger.info(f"🔍 Testing stream for {station_name}: {stream_url}")
        
        # Basic connectivity test
        connectivity = self._test_connectivity(stream_url)
        results['connectivity'] = connectivity
        
        if not connectivity['success']:
            results['overall_status'] = 'failed'
            results['errors'].append('Stream URL not accessible')
            return results
        
        # Test with all recording tools
        tool_results = {}
        
        # Test streamripper
        logger.info("Testing with streamripper...")
        tool_results['streamripper'] = self._test_streamripper(stream_url)
        
        # Test ffmpeg
        logger.info("Testing with ffmpeg...")
        tool_results['ffmpeg'] = self._test_ffmpeg(stream_url)
        
        # Test wget
        logger.info("Testing with wget...")
        tool_results['wget'] = self._test_wget(stream_url)
        
        results['tools_tested'] = tool_results
        
        # Analyze results and make recommendation
        recommendation = self._analyze_results(tool_results)
        results.update(recommendation)
        
        # Stream quality analysis
        quality_info = self._analyze_stream_quality(stream_url)
        results['stream_info'] = quality_info
        
        logger.info(f"✅ Stream test completed for {station_name}")
        logger.info(f"🎯 Recommended tool: {results['recommended_tool']}")
        logger.info(f"📊 Overall status: {results['overall_status']}")
        
        return results
    
    def test_stream_quick(self, stream_url: str) -> Dict:
        """
        Quick connectivity and basic tool test (for discovery workflow)
        Returns simplified results for immediate use
        """
        # Basic connectivity test
        connectivity = self._test_connectivity(stream_url)
        
        if not connectivity['success']:
            return {
                'compatible': False,
                'recommended_tool': None,
                'error': 'Stream not accessible'
            }
        
        # Try tools in order of preference
        tools_to_try = ['streamripper', 'ffmpeg', 'wget']
        
        for tool in tools_to_try:
            logger.info(f"Quick test with {tool}...")
            
            if tool == 'streamripper':
                result = self._test_streamripper_quick(stream_url)
            elif tool == 'ffmpeg':
                result = self._test_ffmpeg_quick(stream_url)
            else:  # wget
                result = self._test_wget_quick(stream_url)
            
            if result.get('success', False):
                return {
                    'compatible': True,
                    'recommended_tool': tool,
                    'file_size': result.get('file_size', 0),
                    'quality_score': result.get('score', 0)
                }
        
        return {
            'compatible': False,
            'recommended_tool': None,
            'error': 'No compatible recording tools found'
        }
    
    def _test_connectivity(self, stream_url: str) -> Dict:
        """Test basic connectivity to stream URL"""
        try:
            cmd = ['curl', '-I', '--connect-timeout', '10', '--max-time', '15', stream_url]
            result = subprocess.run(cmd, capture_output=True, text=True, timeout=20)
            
            return {
                'success': result.returncode == 0,
                'status_code': self._extract_http_status(result.stdout) if result.returncode == 0 else None,
                'headers': result.stdout if result.returncode == 0 else None,
                'error': result.stderr if result.returncode != 0 else None
            }
        except Exception as e:
            return {
                'success': False,
                'error': str(e)
            }
    
    def _test_streamripper(self, stream_url: str) -> Dict:
        """Test recording with streamripper"""
        try:
            with tempfile.TemporaryDirectory() as temp_dir:
                output_file = os.path.join(temp_dir, "test_streamripper.mp3")
                
                cmd = [
                    self.tools['streamripper'],
                    stream_url,
                    '-l', str(self.test_duration),
                    '-a', 'test_streamripper.mp3',
                    '-d', temp_dir,
                    '-A', '-s', '--quiet'
                ]
                
                start_time = time.time()
                result = subprocess.run(cmd, capture_output=True, text=True, timeout=self.test_duration + 10)
                duration = time.time() - start_time
                
                success = result.returncode == 0 and os.path.exists(output_file)
                file_size = os.path.getsize(output_file) if success else 0
                
                return {
                    'success': success and file_size > 0,
                    'file_size': file_size,
                    'duration': duration,
                    'bitrate_estimate': (file_size * 8) / duration / 1000 if duration > 0 else 0,  # kbps
                    'stdout': result.stdout,
                    'stderr': result.stderr,
                    'score': self._calculate_tool_score('streamripper', success, file_size, duration)
                }
                
        except subprocess.TimeoutExpired:
            return {'success': False, 'error': 'Timeout', 'score': 0}
        except Exception as e:
            return {'success': False, 'error': str(e), 'score': 0}
    
    def _test_streamripper_quick(self, stream_url: str) -> Dict:
        """Quick streamripper test (2 seconds)"""
        try:
            with tempfile.TemporaryDirectory() as temp_dir:
                output_file = os.path.join(temp_dir, "quick_test.mp3")
                
                cmd = [
                    self.tools['streamripper'],
                    stream_url,
                    '-l', '2',
                    '-a', 'quick_test.mp3',
                    '-d', temp_dir,
                    '-A', '-s', '--quiet'
                ]
                
                result = subprocess.run(cmd, capture_output=True, text=True, timeout=8)
                success = result.returncode == 0 and os.path.exists(output_file)
                file_size = os.path.getsize(output_file) if success else 0
                
                return {
                    'success': success and file_size > 0,
                    'file_size': file_size,
                    'score': 50 if success and file_size > 0 else 0
                }
                
        except Exception:
            return {'success': False, 'score': 0}
    
    def _test_ffmpeg(self, stream_url: str) -> Dict:
        """Test recording with ffmpeg"""
        try:
            with tempfile.TemporaryDirectory() as temp_dir:
                output_file = os.path.join(temp_dir, "test_ffmpeg.mp3")
                
                cmd = [
                    self.tools['ffmpeg'], '-y',
                    '-i', stream_url,
                    '-t', str(self.test_duration),
                    '-acodec', 'mp3',
                    '-ab', '128k',
                    '-f', 'mp3',
                    '-loglevel', 'error',
                    output_file
                ]
                
                start_time = time.time()
                result = subprocess.run(cmd, capture_output=True, text=True, timeout=self.test_duration + 10)
                duration = time.time() - start_time
                
                success = result.returncode == 0 and os.path.exists(output_file)
                file_size = os.path.getsize(output_file) if success else 0
                
                return {
                    'success': success and file_size > 0,
                    'file_size': file_size,
                    'duration': duration,
                    'bitrate_estimate': (file_size * 8) / duration / 1000 if duration > 0 else 0,  # kbps
                    'stdout': result.stdout,
                    'stderr': result.stderr,
                    'score': self._calculate_tool_score('ffmpeg', success, file_size, duration)
                }
                
        except subprocess.TimeoutExpired:
            return {'success': False, 'error': 'Timeout', 'score': 0}
        except Exception as e:
            return {'success': False, 'error': str(e), 'score': 0}
    
    def _test_ffmpeg_quick(self, stream_url: str) -> Dict:
        """Quick ffmpeg test (2 seconds)"""
        try:
            with tempfile.TemporaryDirectory() as temp_dir:
                output_file = os.path.join(temp_dir, "quick_test.mp3")
                
                cmd = [
                    self.tools['ffmpeg'], '-y',
                    '-i', stream_url,
                    '-t', '2',
                    '-acodec', 'mp3',
                    '-ab', '128k',
                    '-f', 'mp3',
                    '-loglevel', 'error',
                    output_file
                ]
                
                result = subprocess.run(cmd, capture_output=True, text=True, timeout=8)
                success = result.returncode == 0 and os.path.exists(output_file)
                file_size = os.path.getsize(output_file) if success else 0
                
                return {
                    'success': success and file_size > 0,
                    'file_size': file_size,
                    'score': 45 if success and file_size > 0 else 0
                }
                
        except Exception:
            return {'success': False, 'score': 0}
    
    def _test_wget(self, stream_url: str) -> Dict:
        """Test recording with wget"""
        try:
            with tempfile.TemporaryDirectory() as temp_dir:
                output_file = os.path.join(temp_dir, "test_wget.mp3")
                
                cmd = [
                    'timeout', str(self.test_duration),
                    self.tools['wget'],
                    '-O', output_file,
                    '--user-agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    '--no-check-certificate',
                    '--timeout=10',
                    stream_url
                ]
                
                start_time = time.time()
                result = subprocess.run(cmd, capture_output=True, text=True)
                duration = time.time() - start_time
                
                success = os.path.exists(output_file)
                file_size = os.path.getsize(output_file) if success else 0
                
                return {
                    'success': success and file_size > 0,
                    'file_size': file_size,
                    'duration': duration,
                    'bitrate_estimate': (file_size * 8) / duration / 1000 if duration > 0 else 0,  # kbps
                    'stdout': result.stdout,
                    'stderr': result.stderr,
                    'score': self._calculate_tool_score('wget', success, file_size, duration)
                }
                
        except Exception as e:
            return {'success': False, 'error': str(e), 'score': 0}
    
    def _test_wget_quick(self, stream_url: str) -> Dict:
        """Quick wget test (2 seconds)"""
        try:
            with tempfile.TemporaryDirectory() as temp_dir:
                output_file = os.path.join(temp_dir, "quick_test.mp3")
                
                cmd = [
                    'timeout', '2',
                    self.tools['wget'],
                    '-O', output_file,
                    '--user-agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    '--no-check-certificate',
                    '--timeout=5',
                    stream_url
                ]
                
                result = subprocess.run(cmd, capture_output=True, text=True)
                success = os.path.exists(output_file)
                file_size = os.path.getsize(output_file) if success else 0
                
                return {
                    'success': success and file_size > 0,
                    'file_size': file_size,
                    'score': 40 if success and file_size > 0 else 0
                }
                
        except Exception:
            return {'success': False, 'score': 0}
    
    def _calculate_tool_score(self, tool_name: str, success: bool, file_size: int, duration: float) -> float:
        """Calculate a score for tool performance"""
        if not success or file_size == 0:
            return 0.0
        
        # Base score for success
        score = 50.0
        
        # File size factor (expect ~20KB per second for 128kbps stream)
        expected_size = duration * 20 * 1024  # 20KB/s
        if file_size > expected_size * 0.5:  # At least 50% of expected
            score += 30.0
        
        # Duration factor
        if duration <= self.test_duration + 2:  # Completed within reasonable time
            score += 15.0
        
        # Tool-specific bonuses
        tool_bonuses = {
            'streamripper': 5.0,  # Designed for radio
            'ffmpeg': 3.0,        # Professional tool
            'wget': 2.0           # Simple but effective
        }
        score += tool_bonuses.get(tool_name, 0.0)
        
        return min(score, 100.0)
    
    def _analyze_results(self, tool_results: Dict) -> Dict:
        """Analyze tool test results and make recommendation"""
        working_tools = []
        best_tool = None
        best_score = 0.0
        
        for tool_name, result in tool_results.items():
            if result.get('success', False):
                working_tools.append(tool_name)
                score = result.get('score', 0)
                if score > best_score:
                    best_score = score
                    best_tool = tool_name
        
        # Overall status
        if len(working_tools) == 0:
            status = 'failed'
        elif len(working_tools) >= 2:
            status = 'excellent'
        else:
            status = 'good'
        
        return {
            'overall_status': status,
            'recommended_tool': best_tool,
            'working_tools': working_tools,
            'best_score': best_score
        }
    
    def _analyze_stream_quality(self, stream_url: str) -> Dict:
        """Analyze stream format and quality using ffprobe"""
        try:
            cmd = [
                'ffprobe', '-v', 'quiet',
                '-print_format', 'json',
                '-show_format',
                '-show_streams',
                stream_url
            ]
            
            result = subprocess.run(cmd, capture_output=True, text=True, timeout=15)
            
            if result.returncode == 0:
                import json
                data = json.loads(result.stdout)
                
                # Extract relevant information
                format_info = data.get('format', {})
                streams = data.get('streams', [])
                
                audio_stream = None
                for stream in streams:
                    if stream.get('codec_type') == 'audio':
                        audio_stream = stream
                        break
                
                return {
                    'format_name': format_info.get('format_name'),
                    'duration': format_info.get('duration'),
                    'bit_rate': format_info.get('bit_rate'),
                    'audio_codec': audio_stream.get('codec_name') if audio_stream else None,
                    'sample_rate': audio_stream.get('sample_rate') if audio_stream else None,
                    'channels': audio_stream.get('channels') if audio_stream else None,
                    'analysis_successful': True
                }
            else:
                return {'analysis_successful': False, 'error': result.stderr}
                
        except Exception as e:
            return {'analysis_successful': False, 'error': str(e)}
    
    def _extract_http_status(self, headers: str) -> Optional[int]:
        """Extract HTTP status code from headers"""
        try:
            first_line = headers.split('\n')[0]
            if 'HTTP/' in first_line:
                return int(first_line.split()[1])
        except:
            pass
        return None

def generate_test_report(test_results: Dict) -> str:
    """Generate a human-readable test report"""
    station_name = test_results.get('station_name', 'Unknown')
    status = test_results.get('overall_status', 'unknown')
    recommended_tool = test_results.get('recommended_tool', 'none')
    working_tools = test_results.get('working_tools', [])
    
    # Status emoji
    status_emojis = {
        'excellent': '✅ EXCELLENT',
        'good': '✅ GOOD', 
        'failed': '❌ FAILED',
        'unknown': '⚠️ UNKNOWN'
    }
    
    report = f"""
🎵 STREAM TEST REPORT: {station_name}
{'='*60}

📡 Stream URL: {test_results.get('stream_url', 'N/A')}
🎯 Overall Status: {status_emojis.get(status, status.upper())}
🔧 Recommended Tool: {recommended_tool or 'NONE WORKING'}
✅ Working Tools: {', '.join(working_tools) if working_tools else 'NONE'}

📊 TOOL TEST RESULTS:
"""
    
    tools_tested = test_results.get('tools_tested', {})
    for tool_name, result in tools_tested.items():
        success = result.get('success', False)
        file_size = result.get('file_size', 0)
        score = result.get('score', 0)
        
        status_icon = '✅' if success else '❌'
        report += f"   {status_icon} {tool_name}: "
        
        if success:
            report += f"{file_size} bytes, Score: {score:.1f}/100\n"
        else:
            error = result.get('error', 'Unknown error')
            report += f"FAILED - {error}\n"
    
    # Stream info
    stream_info = test_results.get('stream_info', {})
    if stream_info.get('analysis_successful'):
        report += f"\n🎼 STREAM QUALITY:\n"
        report += f"   Format: {stream_info.get('format_name', 'Unknown')}\n"
        report += f"   Codec: {stream_info.get('audio_codec', 'Unknown')}\n"
        report += f"   Bitrate: {stream_info.get('bit_rate', 'Unknown')} bps\n"
        report += f"   Sample Rate: {stream_info.get('sample_rate', 'Unknown')} Hz\n"
        report += f"   Channels: {stream_info.get('channels', 'Unknown')}\n"
    
    # Recommendations
    report += f"\n💡 RECOMMENDATIONS:\n"
    if recommended_tool:
        report += f"   • Use {recommended_tool} for recording this stream\n"
        if len(working_tools) > 1:
            report += f"   • Fallback tools available: {', '.join([t for t in working_tools if t != recommended_tool])}\n"
    else:
        report += f"   • Stream not compatible with current recording tools\n"
        report += f"   • Check stream URL and authentication requirements\n"
    
    report += f"\n⏰ Test completed: {test_results.get('timestamp', 'Unknown')}\n"
    
    return report

if __name__ == "__main__":
    # Example usage
    tester = StreamTester()
    
    test_urls = [
        ("WEHC", "https://wehc.streamguys1.com/live"),
        ("WERU", "https://stream.pacificaservice.org:9000/weru_128"),
        ("WYSO", "https://playerservices.streamtheworld.com/api/livestream-redirect/WYSOHD2.mp3")
    ]
    
    for station_name, stream_url in test_urls:
        print(f"Testing {station_name}...")
        results = tester.test_stream_comprehensive(stream_url, station_name)
        report = generate_test_report(results)
        print(report)
        print("\n" + "="*80 + "\n")