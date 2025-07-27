#!/usr/bin/env python3
"""
Debug WTBR recording issue
"""
import sys
import os
from datetime import datetime

# Add the project root to Python path
sys.path.insert(0, '/opt/radiograb')

from backend.services.test_recording_service import perform_recording

def test_wtbr_stream():
    """Test WTBR stream manually"""
    
    stream_url = "https://streams.radiomast.io/15c0b63b-cfaf-4cdf-9e46-4b14c3a56b01"
    timestamp = datetime.now().strftime('%Y-%m-%d-%H%M%S')
    output_file = f"/var/radiograb/temp/WTBR_debug_{timestamp}.mp3"
    
    print(f"Testing stream: {stream_url}")
    print(f"Output file: {output_file}")
    
    success, message = perform_recording(stream_url, output_file, 10)
    
    print(f"Success: {success}")
    print(f"Message: {message}")
    
    if os.path.exists(output_file):
        size = os.path.getsize(output_file)
        print(f"File created: {output_file} ({size} bytes)")
        
        # Check if it's a valid audio file
        try:
            with open(output_file, 'rb') as f:
                header = f.read(16)
                print(f"File header: {header}")
        except Exception as e:
            print(f"Error reading file: {e}")
    else:
        print("No file created")

if __name__ == "__main__":
    test_wtbr_stream()