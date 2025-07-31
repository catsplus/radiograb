#!/usr/bin/env python3
"""
Provides utility functions for updating station information in the database.

This helper script is used by other services to update specific fields of a
station's record, such as stream URL, logo information, or calendar URL.

Key Variables:
- `station_id`: The ID of the station to update.
- `stream_info`: A dictionary containing new stream details.
- `logo_info`: A dictionary containing new logo details.
- `calendar_url`: The new calendar URL.

Inter-script Communication:
- This script is used by `stream_discovery.py` and `station_auto_test.py`.
- It interacts with the `Station` model from `backend/models/station.py`.
"""


import sys
import os
from datetime import datetime

# Add the project root to Python path
sys.path.insert(0, '/opt/radiograb')

try:
    import mysql.connector
except ImportError as e:
    print(f"Error importing database module: {e}")
    sys.exit(1)

def update_station_last_tested(station_id, success=True, error_msg=None):
    """
    Update station's last_tested timestamp when a recording occurs
    Call this whenever a show recording succeeds for a station
    """
    try:
        # Connect to MySQL using environment variables
        db_config = {
            'host': os.environ.get('DB_HOST', 'mysql'),
            'port': int(os.environ.get('DB_PORT', '3306')),
            'user': os.environ.get('DB_USER', 'radiograb'),
            'password': os.environ.get('DB_PASSWORD', 'radiograb_pass_2024'),
            'database': os.environ.get('DB_NAME', 'radiograb'),
            'autocommit': True
        }
        
        db = mysql.connector.connect(**db_config)
        cursor = db.cursor()
        
        result = 'success' if success else 'failed'
        test_time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        
        cursor.execute("""
            UPDATE stations 
            SET last_tested = %s, 
                last_test_result = %s, 
                last_test_error = %s
            WHERE id = %s
        """, (test_time, result, error_msg, station_id))
        
        db.close()
        
        print(f"Updated station {station_id} last_tested to {test_time} (result: {result})")
        return True
        
    except Exception as e:
        print(f"Error updating station {station_id} last_tested: {e}")
        return False

def mark_station_recording_success(station_id):
    """
    Mark that a station had a successful recording
    This should be called by the recording system when any show records successfully
    """
    return update_station_last_tested(station_id, success=True)

def mark_station_recording_failure(station_id, error_msg):
    """
    Mark that a station recording failed
    """
    return update_station_last_tested(station_id, success=False, error_msg=error_msg)

if __name__ == "__main__":
    # Command line interface for manual testing
    import argparse
    
    parser = argparse.ArgumentParser(description='Update station test status')
    parser.add_argument('station_id', type=int, help='Station ID to update')
    parser.add_argument('--success', action='store_true', help='Mark as success (default)')
    parser.add_argument('--failed', action='store_true', help='Mark as failed')
    parser.add_argument('--error', type=str, help='Error message for failed recordings')
    
    args = parser.parse_args()
    
    if args.failed:
        result = update_station_last_tested(args.station_id, success=False, error_msg=args.error)
    else:
        result = update_station_last_tested(args.station_id, success=True)
    
    if result:
        print("Station status updated successfully")
    else:
        print("Failed to update station status")
        sys.exit(1)