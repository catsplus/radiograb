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

def get_recording_tool(stream_url=None):
    """Determine the best available recording tool based on stream type"""
    # Check if tools exist
    tools_available = {}
    tools = [
        ('/usr/bin/streamripper', 'streamripper'),
        ('/usr/bin/ffmpeg', 'ffmpeg'),
        ('/usr/bin/wget', 'wget')
    ]
    
    for tool_path, tool_name in tools:
        if os.path.exists(tool_path):
            tools_available[tool_name] = tool_path
        else:
            # Fallback - use which to find tools
            try:
                result = subprocess.run(['which', tool_name], capture_output=True, text=True)
                if result.returncode == 0:
                    tools_available[tool_name] = result.stdout.strip()
            except:
                continue
    
    if not tools_available:
        return None, None
    
    # Smart tool selection based on stream URL
    if stream_url:
        # StreamTheWorld streams work better with ffmpeg
        if 'streamtheworld.com' in stream_url.lower() or 'live.streamtheworld.com' in stream_url.lower():
            if 'ffmpeg' in tools_available:
                return tools_available['ffmpeg'], 'ffmpeg'
        
        # .m3u8 streams require ffmpeg
        if '.m3u8' in stream_url.lower():
            if 'ffmpeg' in tools_available:
                return tools_available['ffmpeg'], 'ffmpeg'
    
    # Default order preference
    preferred_order = ['streamripper', 'ffmpeg', 'wget']
    for tool_name in preferred_order:
        if tool_name in tools_available:
            return tools_available[tool_name], tool_name
    
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

def convert_aac_to_mp3(input_file, output_file):
    """Convert AAC file to MP3 using ffmpeg"""
    try:
        cmd = [
            'ffmpeg',
            '-i', input_file,
            '-acodec', 'libmp3lame',
            '-ab', '128k',
            '-y',  # Overwrite output files
            output_file
        ]
        
        result = subprocess.run(cmd, capture_output=True, text=True, timeout=60)
        
        if result.returncode == 0 and os.path.exists(output_file):
            # Conversion successful, remove original AAC file
            try:
                os.remove(input_file)
                print(f"Converted AAC to MP3: {output_file}")
                return True, "AAC converted to MP3"
            except Exception as e:
                print(f"Warning: Could not remove original AAC file: {e}")
                return True, "AAC converted to MP3 (original file remains)"
        else:
            return False, f"Conversion failed: {result.stderr}"
            
    except subprocess.TimeoutExpired:
        return False, "Conversion timed out"
    except Exception as e:
        return False, f"Conversion error: {str(e)}"

def post_process_recording(output_file):
    """Post-process recording file (convert AAC to MP3 if needed)"""
    if not os.path.exists(output_file):
        return False, "File does not exist"
    
    # Check if file is AAC format (by extension or content)
    is_aac = False
    
    # Check by extension
    if output_file.endswith('.aac') or output_file.endswith('.mp3.aac'):
        is_aac = True
    else:
        # Check by file content using file command
        try:
            result = subprocess.run(['file', output_file], capture_output=True, text=True)
            if 'AAC' in result.stdout or 'ADTS' in result.stdout:
                is_aac = True
        except:
            pass
    
    if is_aac:
        # Create MP3 filename
        if output_file.endswith('.mp3.aac'):
            mp3_file = output_file[:-4]  # Remove .aac, keeping .mp3
        elif output_file.endswith('.aac'):
            mp3_file = output_file[:-4] + '.mp3'  # Replace .aac with .mp3
        else:
            mp3_file = output_file + '.mp3'  # Add .mp3 extension
        
        print(f"Detected AAC file, converting to MP3: {mp3_file}")
        return convert_aac_to_mp3(output_file, mp3_file)
    
    return True, "No conversion needed"

def perform_recording(stream_url, output_file, duration):
    """Perform the actual recording using the best available tool"""
    tool_path, tool_name = get_recording_tool(stream_url)
    
    if not tool_path:
        return False, "No recording tools available (streamripper, ffmpeg, wget)"
    
    print(f"Using {tool_name} at {tool_path} for stream: {stream_url}")
    
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
            
            # Post-process the recording (convert AAC to MP3 if needed)
            post_success, post_message = post_process_recording(output_file)
            if post_success:
                # Update output_file path if conversion happened
                if output_file.endswith('.mp3.aac'):
                    output_file = output_file[:-4]  # Remove .aac, keeping .mp3
                elif output_file.endswith('.aac'):
                    output_file = output_file[:-4] + '.mp3'  # Replace .aac with .mp3
                
                final_size = os.path.getsize(output_file) if os.path.exists(output_file) else file_size
                return True, f"Recorded {final_size} bytes ({post_message})"
            else:
                print(f"Warning: Post-processing failed: {post_message}")
                return True, f"Recorded {file_size} bytes (conversion failed: {post_message})"
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