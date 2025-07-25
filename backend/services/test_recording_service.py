#!/usr/bin/env python3
"""
RadioGrab Test Recording Service
Handles short-duration test recordings and on-demand recordings
"""

import sys
import os
import argparse
import subprocess
import time
from pathlib import Path

# Add the project root to Python path
sys.path.insert(0, '/opt/radiograb')

# Set up paths
RECORDINGS_DIR = '/var/radiograb/recordings'
TEMP_DIR = '/var/radiograb/temp'
LOGS_DIR = '/var/radiograb/logs'

def ensure_directory(directory):
    """Ensure directory exists with proper permissions"""
    try:
        Path(directory).mkdir(parents=True, exist_ok=True)
        # Set permissions for www-data
        os.system(f"chown -R www-data:www-data {directory}")
        os.system(f"chmod -R 755 {directory}")
        return True
    except Exception as e:
        print(f"Error creating directory {directory}: {e}")
        return False

def get_recording_tool():
    """Determine the best available recording tool"""
    tools = [
        ('/usr/bin/streamripper', 'streamripper'),
        ('/usr/bin/ffmpeg', 'ffmpeg'),
        ('/usr/bin/wget', 'wget')
    ]
    
    for tool_path, tool_name in tools:
        if os.path.exists(tool_path):
            return tool_path, tool_name
    
    # Fallback - use which to find tools
    for _, tool_name in tools:
        try:
            result = subprocess.run(['which', tool_name], capture_output=True, text=True)
            if result.returncode == 0:
                return result.stdout.strip(), tool_name
        except:
            continue
    
    return None, None

def record_with_streamripper(stream_url, output_file, duration):
    """Record using streamripper"""
    cmd = [
        'streamripper',
        stream_url,
        '-l', str(duration),
        '-a', output_file,
        '-A', '-s', '-q'
    ]
    
    try:
        result = subprocess.run(cmd, capture_output=True, text=True, timeout=duration + 30)
        return result.returncode == 0, result.stderr
    except subprocess.TimeoutExpired:
        return False, "Recording timed out"
    except Exception as e:
        return False, str(e)

def record_with_ffmpeg(stream_url, output_file, duration):
    """Record using ffmpeg"""
    cmd = [
        'ffmpeg',
        '-i', stream_url,
        '-t', str(duration),
        '-acodec', 'mp3',
        '-y',  # Overwrite output files
        output_file
    ]
    
    try:
        result = subprocess.run(cmd, capture_output=True, text=True, timeout=duration + 30)
        return result.returncode == 0, result.stderr
    except subprocess.TimeoutExpired:
        return False, "Recording timed out"
    except Exception as e:
        return False, str(e)

def record_with_wget(stream_url, output_file, duration):
    """Record using wget with timeout"""
    cmd = [
        'timeout', str(duration),
        'wget', '-O', output_file,
        '--timeout=10',
        '--tries=3',
        stream_url
    ]
    
    try:
        result = subprocess.run(cmd, capture_output=True, text=True)
        # wget with timeout returns 124 when timeout is reached (expected)
        return result.returncode in [0, 124], result.stderr
    except Exception as e:
        return False, str(e)

def perform_recording(stream_url, output_file, duration):
    """Perform the actual recording using the best available tool"""
    tool_path, tool_name = get_recording_tool()
    
    if not tool_path:
        return False, "No recording tools available (streamripper, ffmpeg, wget)"
    
    print(f"Using {tool_name} at {tool_path}")
    
    # Ensure output directory exists
    output_dir = os.path.dirname(output_file)
    if not ensure_directory(output_dir):
        return False, f"Failed to create output directory {output_dir}"
    
    # Record based on available tool
    if tool_name == 'streamripper':
        success, error = record_with_streamripper(stream_url, output_file, duration)
    elif tool_name == 'ffmpeg':
        success, error = record_with_ffmpeg(stream_url, output_file, duration)
    elif tool_name == 'wget':
        success, error = record_with_wget(stream_url, output_file, duration)
    else:
        return False, f"Unsupported tool: {tool_name}"
    
    # Check if file was created and has content
    if success and os.path.exists(output_file):
        file_size = os.path.getsize(output_file)
        if file_size > 0:
            print(f"Recording successful: {output_file} ({file_size} bytes)")
            return True, f"Recorded {file_size} bytes"
        else:
            print(f"Recording failed: Empty file created")
            # Remove empty file
            try:
                os.remove(output_file)
            except:
                pass
            return False, "Recording produced empty file"
    else:
        print(f"Recording failed: {error}")
        return False, error

def main():
    parser = argparse.ArgumentParser(description='RadioGrab Test Recording Service')
    parser.add_argument('--station-id', type=int, required=True, help='Station ID')
    parser.add_argument('--duration', type=int, default=30, help='Recording duration in seconds')
    parser.add_argument('--output-dir', required=True, help='Output directory')
    parser.add_argument('--filename', required=True, help='Output filename')
    parser.add_argument('--stream-url', required=True, help='Stream URL to record')
    parser.add_argument('--show-name', help='Show name for logging')
    
    args = parser.parse_args()
    
    # Ensure output directory exists
    if not ensure_directory(args.output_dir):
        print(f"Error: Failed to create output directory {args.output_dir}")
        sys.exit(1)
    
    # Build full output path
    output_file = os.path.join(args.output_dir, args.filename)
    
    print(f"Starting recording:")
    print(f"  Station ID: {args.station_id}")
    print(f"  Duration: {args.duration} seconds")
    print(f"  Stream URL: {args.stream_url}")
    print(f"  Output: {output_file}")
    print(f"  Show: {args.show_name or 'Test Recording'}")
    
    # Perform the recording
    success, message = perform_recording(args.stream_url, output_file, args.duration)
    
    if success:
        print(f"✅ Recording completed successfully: {message}")
        sys.exit(0)
    else:
        print(f"❌ Recording failed: {message}")
        sys.exit(1)

if __name__ == '__main__':
    main()