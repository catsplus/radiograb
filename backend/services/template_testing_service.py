#!/usr/bin/env python3
"""
Template Testing Service
Issue #38 Phase 2 - Station Template Sharing System

Automatically tests submitted templates for stream reliability and quality.
"""

import sys
import os
import time
import subprocess
import tempfile
import argparse
import logging
from datetime import datetime, timedelta
from pathlib import Path

# Add the project root to Python path
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), '../..')))

from backend.includes.database import Database
from backend.includes.config import get_setting

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

class TemplateTestingService:
    def __init__(self):
        self.db = Database()
        self.temp_dir = Path('/var/radiograb/temp')
        self.temp_dir.mkdir(exist_ok=True)
        
    def test_template_stream(self, template_id, timeout_seconds=30):
        """
        Test a template's stream URL for accessibility and quality.
        
        Args:
            template_id (int): The template ID to test
            timeout_seconds (int): Maximum time to test the stream
            
        Returns:
            dict: Test results with success, error, and metadata
        """
        try:
            # Get template information
            template = self.db.fetchone("""
                SELECT id, name, call_letters, stream_url, format, bitrate
                FROM stations_master 
                WHERE id = %s AND is_active = 1
            """, (template_id,))
            
            if not template:
                return {
                    'success': False,
                    'error': 'Template not found',
                    'test_duration': 0
                }
            
            if not template['stream_url']:
                return {
                    'success': False,
                    'error': 'No stream URL provided',
                    'test_duration': 0
                }
            
            logger.info(f"Testing template {template['name']} ({template['call_letters']}) - {template['stream_url']}")
            
            start_time = time.time()
            
            # Create temporary file for test recording
            test_file = self.temp_dir / f"template_test_{template_id}_{int(time.time())}.mp3"
            
            try:
                # Test with streamripper first
                result = self._test_with_streamripper(template['stream_url'], test_file, timeout_seconds)
                
                if not result['success']:
                    # Fallback to ffmpeg
                    logger.info(f"Streamripper failed, trying ffmpeg for template {template_id}")
                    result = self._test_with_ffmpeg(template['stream_url'], test_file, timeout_seconds)
                
                if not result['success']:
                    # Final fallback to wget
                    logger.info(f"FFmpeg failed, trying wget for template {template_id}")
                    result = self._test_with_wget(template['stream_url'], test_file, timeout_seconds)
                
                test_duration = time.time() - start_time
                
                # Analyze the recorded file if successful
                if result['success'] and test_file.exists():
                    file_analysis = self._analyze_audio_file(test_file)
                    result.update(file_analysis)
                
                # Clean up test file
                if test_file.exists():
                    test_file.unlink()
                
                result['test_duration'] = test_duration
                
                # Update template with test results
                self._update_template_test_results(template_id, result)
                
                return result
                
            except Exception as e:
                logger.error(f"Error testing template {template_id}: {str(e)}")
                return {
                    'success': False,
                    'error': f'Test error: {str(e)}',
                    'test_duration': time.time() - start_time
                }
                
        except Exception as e:
            logger.error(f"Database error testing template {template_id}: {str(e)}")
            return {
                'success': False,
                'error': f'Database error: {str(e)}',
                'test_duration': 0
            }
    
    def _test_with_streamripper(self, stream_url, output_file, timeout_seconds):
        """Test stream with streamripper."""
        try:
            cmd = [
                'streamripper', 
                stream_url,
                '-l', str(timeout_seconds),
                '-A', '-s',  # Don't split tracks, use stdout
                '-o', 'never',  # Don't overwrite
                '--quiet'
            ]
            
            process = subprocess.run(
                cmd, 
                timeout=timeout_seconds + 10,
                capture_output=True,
                text=True
            )
            
            if process.returncode == 0:
                return {'success': True, 'method': 'streamripper'}
            else:
                return {
                    'success': False, 
                    'error': f'Streamripper failed: {process.stderr}',
                    'method': 'streamripper'
                }
                
        except subprocess.TimeoutExpired:
            return {'success': False, 'error': 'Streamripper timeout', 'method': 'streamripper'}
        except Exception as e:
            return {'success': False, 'error': f'Streamripper error: {str(e)}', 'method': 'streamripper'}
    
    def _test_with_ffmpeg(self, stream_url, output_file, timeout_seconds):
        """Test stream with ffmpeg."""
        try:
            cmd = [
                'ffmpeg', 
                '-i', stream_url,
                '-t', str(timeout_seconds),
                '-acodec', 'mp3',
                '-ab', '128k',
                '-f', 'mp3',
                '-y',  # Overwrite output file
                str(output_file)
            ]
            
            process = subprocess.run(
                cmd, 
                timeout=timeout_seconds + 15,
                capture_output=True,
                text=True
            )
            
            if process.returncode == 0 and output_file.exists():
                return {'success': True, 'method': 'ffmpeg'}
            else:
                return {
                    'success': False, 
                    'error': f'FFmpeg failed: {process.stderr}',
                    'method': 'ffmpeg'
                }
                
        except subprocess.TimeoutExpired:
            return {'success': False, 'error': 'FFmpeg timeout', 'method': 'ffmpeg'}
        except Exception as e:
            return {'success': False, 'error': f'FFmpeg error: {str(e)}', 'method': 'ffmpeg'}
    
    def _test_with_wget(self, stream_url, output_file, timeout_seconds):
        """Test stream with wget."""
        try:
            cmd = [
                'wget', 
                '--timeout', str(timeout_seconds),
                '--tries', '1',
                '--output-document', str(output_file),
                stream_url
            ]
            
            process = subprocess.run(
                cmd, 
                timeout=timeout_seconds + 10,
                capture_output=True,
                text=True
            )
            
            if process.returncode == 0 and output_file.exists():
                return {'success': True, 'method': 'wget'}
            else:
                return {
                    'success': False, 
                    'error': f'Wget failed: {process.stderr}',
                    'method': 'wget'
                }
                
        except subprocess.TimeoutExpired:
            return {'success': False, 'error': 'Wget timeout', 'method': 'wget'}
        except Exception as e:
            return {'success': False, 'error': f'Wget error: {str(e)}', 'method': 'wget'}
    
    def _analyze_audio_file(self, file_path):
        """Analyze the recorded audio file for quality metrics."""
        try:
            if not file_path.exists():
                return {'file_size': 0, 'duration': 0, 'quality': 'unknown'}
            
            file_size = file_path.stat().st_size
            
            # Use ffprobe to get duration and quality info
            cmd = [
                'ffprobe', 
                '-v', 'quiet',
                '-print_format', 'json',
                '-show_format',
                '-show_streams',
                str(file_path)
            ]
            
            try:
                result = subprocess.run(cmd, capture_output=True, text=True, timeout=10)
                if result.returncode == 0:
                    import json
                    probe_data = json.loads(result.stdout)
                    
                    duration = float(probe_data.get('format', {}).get('duration', 0))
                    bitrate = probe_data.get('format', {}).get('bit_rate', 0)
                    
                    # Determine quality based on file size and duration
                    if duration > 0:
                        bytes_per_second = file_size / duration
                        # Minimum viable quality: 2KB/sec (roughly 16 kbps)
                        if bytes_per_second >= 2048:
                            quality = 'good'
                        elif bytes_per_second >= 1024:
                            quality = 'fair'
                        else:
                            quality = 'poor'
                    else:
                        quality = 'unknown'
                    
                    return {
                        'file_size': file_size,
                        'duration': duration,
                        'bitrate': bitrate,
                        'quality': quality,
                        'bytes_per_second': file_size / duration if duration > 0 else 0
                    }
            except Exception as e:
                logger.warning(f"ffprobe analysis failed: {str(e)}")
            
            # Fallback analysis
            return {
                'file_size': file_size,
                'duration': 0,
                'quality': 'good' if file_size > 1024 else 'poor'
            }
            
        except Exception as e:
            logger.error(f"File analysis error: {str(e)}")
            return {'file_size': 0, 'duration': 0, 'quality': 'unknown'}
    
    def _update_template_test_results(self, template_id, test_result):
        """Update template with test results."""
        try:
            test_status = 'success' if test_result['success'] else 'failed'
            
            self.db.execute("""
                UPDATE stations_master 
                SET last_tested = %s,
                    last_test_result = %s,
                    updated_at = %s
                WHERE id = %s
            """, (
                datetime.now(),
                test_status,
                datetime.now(),
                template_id
            ))
            
            logger.info(f"Updated template {template_id} test status: {test_status}")
            
        except Exception as e:
            logger.error(f"Failed to update template {template_id} test results: {str(e)}")
    
    def test_pending_templates(self, max_age_hours=24):
        """Test templates that haven't been tested recently or have never been tested."""
        try:
            cutoff_time = datetime.now() - timedelta(hours=max_age_hours)
            
            templates = self.db.fetchall("""
                SELECT id, name, call_letters
                FROM stations_master 
                WHERE is_active = 1 
                AND (last_tested IS NULL OR last_tested < %s)
                ORDER BY created_at DESC
                LIMIT 50
            """, (cutoff_time,))
            
            if not templates:
                logger.info("No templates need testing")
                return
            
            logger.info(f"Testing {len(templates)} templates")
            
            for template in templates:
                try:
                    logger.info(f"Testing template: {template['name']} ({template['call_letters']})")
                    result = self.test_template_stream(template['id'])
                    
                    if result['success']:
                        logger.info(f"✅ Template {template['id']} test successful")
                    else:
                        logger.warning(f"❌ Template {template['id']} test failed: {result['error']}")
                    
                    # Small delay between tests to avoid overwhelming streams
                    time.sleep(2)
                    
                except Exception as e:
                    logger.error(f"Error testing template {template['id']}: {str(e)}")
                    continue
            
        except Exception as e:
            logger.error(f"Error in test_pending_templates: {str(e)}")

def main():
    parser = argparse.ArgumentParser(description='Template Testing Service')
    parser.add_argument('--template-id', type=int, help='Test specific template ID')
    parser.add_argument('--test-pending', action='store_true', help='Test all pending templates')
    parser.add_argument('--max-age', type=int, default=24, help='Maximum age in hours for pending test')
    parser.add_argument('--timeout', type=int, default=30, help='Test timeout in seconds')
    
    args = parser.parse_args()
    
    service = TemplateTestingService()
    
    if args.template_id:
        logger.info(f"Testing template ID: {args.template_id}")
        result = service.test_template_stream(args.template_id, args.timeout)
        print(f"Test result: {result}")
        
    elif args.test_pending:
        logger.info(f"Testing pending templates (max age: {args.max_age} hours)")
        service.test_pending_templates(args.max_age)
        
    else:
        parser.print_help()

if __name__ == '__main__':
    main()