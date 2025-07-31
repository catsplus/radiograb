"""
Manages Time-to-Live (TTL) for recordings, including automatic expiration and cleanup.

This service calculates expiry dates for recordings based on show retention policies
or individual recording overrides. It can identify and clean up expired recordings,
and also provides functionality to extend recording TTLs.

Key Variables:
- `recording_id`: The ID of the recording to manage.
- `show_id`: The ID of the show to manage.
- `ttl_value`: The numeric value for the TTL (e.g., 30).
- `ttl_type`: The unit for the TTL (e.g., 'days', 'weeks', 'months', 'indefinite').

Inter-script Communication:
- This script is typically run as a cron job for cleanup.
- It interacts with the `Recording` and `Show` models from `backend/models/station.py`.
- It uses `file_manager.py` to delete physical recording files.
"""
"""
TTL (Time-to-Live) Manager for RadioGrab
Handles automatic expiration and cleanup of recordings
"""

import os
import sys
import logging
from datetime import datetime, timedelta
from typing import List, Optional, Dict, Any
import argparse

# Add the project root to the path
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))

from backend.services.database import DatabaseManager
from backend.services.file_manager import FileManager

logger = logging.getLogger(__name__)

class TTLManager:
    """Manages Time-to-Live for recordings"""
    
    def __init__(self):
        self.db = DatabaseManager()
        self.file_manager = FileManager()
        
    def calculate_expiry_date(self, recorded_at: datetime, ttl_value: int, ttl_type: str) -> Optional[datetime]:
        """
        Calculate the expiry date for a recording
        
        Args:
            recorded_at: When the recording was made
            ttl_value: TTL value (number)
            ttl_type: Type of TTL ('days', 'weeks', 'months', 'indefinite')
            
        Returns:
            Expiry datetime or None for indefinite
        """
        if ttl_type == 'indefinite' or ttl_value <= 0:
            return None
            
        try:
            if ttl_type == 'days':
                return recorded_at + timedelta(days=ttl_value)
            elif ttl_type == 'weeks':
                return recorded_at + timedelta(weeks=ttl_value)
            elif ttl_type == 'months':
                # Approximate months as 30 days
                return recorded_at + timedelta(days=ttl_value * 30)
            else:
                logger.warning(f"Unknown TTL type: {ttl_type}, defaulting to days")
                return recorded_at + timedelta(days=ttl_value)
        except Exception as e:
            logger.error(f"Error calculating expiry date: {e}")
            return None
    
    def update_recording_expiry(self, recording_id: int, ttl_days: Optional[int] = None, ttl_type: str = 'days') -> bool:
        """
        Update the expiry date for a specific recording
        
        Args:
            recording_id: Recording ID
            ttl_days: TTL override in specified units (None = use show default)
            ttl_type: Type of TTL ('days', 'weeks', 'months', 'indefinite')
            
        Returns:
            True if successful, False otherwise
        """
        try:
            # Get recording and show info
            query = """
                SELECT r.recorded_at, r.ttl_override_days, r.ttl_type,
                       s.retention_days, s.default_ttl_type
                FROM recordings r
                JOIN shows s ON r.show_id = s.id
                WHERE r.id = ?
            """
            
            result = self.db.fetch_one(query, (recording_id,))
            if not result:
                logger.error(f"Recording {recording_id} not found")
                return False
            
            recorded_at = result['recorded_at']
            
            # Determine TTL to use
            if ttl_days is not None:
                # Use override
                effective_ttl = ttl_days
                effective_type = ttl_type
                
                # Update the override in database
                self.db.execute("""
                    UPDATE recordings 
                    SET ttl_override_days = ?, ttl_type = ?
                    WHERE id = ?
                """, (ttl_days if ttl_days > 0 else None, ttl_type, recording_id))
                
            else:
                # Use show default
                effective_ttl = result['retention_days']
                effective_type = result['default_ttl_type'] or 'days'
            
            # Calculate expiry date
            expires_at = self.calculate_expiry_date(recorded_at, effective_ttl, effective_type)
            
            # Update expiry date
            self.db.execute("""
                UPDATE recordings 
                SET expires_at = ?
                WHERE id = ?
            """, (expires_at, recording_id))
            
            logger.info(f"Updated recording {recording_id} expiry to {expires_at}")
            return True
            
        except Exception as e:
            logger.error(f"Error updating recording expiry: {e}")
            return False
    
    def update_show_default_ttl(self, show_id: int, retention_days: int, ttl_type: str = 'days') -> bool:
        """
        Update the default TTL for a show and recalculate existing recordings
        
        Args:
            show_id: Show ID
            retention_days: New retention period
            ttl_type: Type of TTL ('days', 'weeks', 'months', 'indefinite')
            
        Returns:
            True if successful, False otherwise
        """
        try:
            # Update show TTL settings
            self.db.execute("""
                UPDATE shows 
                SET retention_days = ?, default_ttl_type = ?
                WHERE id = ?
            """, (retention_days, ttl_type, show_id))
            
            # Update all recordings for this show that don't have overrides
            query = """
                SELECT id, recorded_at
                FROM recordings 
                WHERE show_id = ? AND ttl_override_days IS NULL
            """
            
            recordings = self.db.fetch_all(query, (show_id,))
            
            for recording in recordings:
                expires_at = self.calculate_expiry_date(
                    recording['recorded_at'], 
                    retention_days, 
                    ttl_type
                )
                
                self.db.execute("""
                    UPDATE recordings 
                    SET expires_at = ?, ttl_type = ?
                    WHERE id = ?
                """, (expires_at, ttl_type, recording['id']))
            
            logger.info(f"Updated show {show_id} TTL and {len(recordings)} recordings")
            return True
            
        except Exception as e:
            logger.error(f"Error updating show TTL: {e}")
            return False
    
    def get_expired_recordings(self) -> List[Dict[str, Any]]:
        """
        Get all recordings that have expired
        
        Returns:
            List of expired recording dictionaries
        """
        try:
            query = """
                SELECT r.id, r.filename, r.recorded_at, r.expires_at,
                       s.name as show_name, st.name as station_name
                FROM recordings r
                JOIN shows s ON r.show_id = s.id
                JOIN stations st ON s.station_id = st.id
                WHERE r.expires_at IS NOT NULL 
                AND r.expires_at <= NOW()
                ORDER BY r.expires_at ASC
            """
            
            expired = self.db.fetch_all(query)
            logger.info(f"Found {len(expired)} expired recordings")
            return expired
            
        except Exception as e:
            logger.error(f"Error getting expired recordings: {e}")
            return []
    
    def cleanup_expired_recordings(self, dry_run: bool = False) -> Dict[str, int]:
        """
        Clean up expired recordings
        
        Args:
            dry_run: If True, don't actually delete anything
            
        Returns:
            Dictionary with cleanup statistics
        """
        stats = {
            'found': 0,
            'deleted_files': 0,
            'deleted_records': 0,
            'errors': 0
        }
        
        try:
            expired_recordings = self.get_expired_recordings()
            stats['found'] = len(expired_recordings)
            
            if not expired_recordings:
                logger.info("No expired recordings found")
                return stats
            
            for recording in expired_recordings:
                recording_id = recording['id']
                filename = recording['filename']
                
                try:
                    if not dry_run:
                        # Delete physical file
                        file_deleted = self.file_manager.delete_recording_file(filename)
                        if file_deleted:
                            stats['deleted_files'] += 1
                        
                        # Delete database record
                        self.db.execute("DELETE FROM recordings WHERE id = ?", (recording_id,))
                        stats['deleted_records'] += 1
                        
                        logger.info(f"Deleted expired recording: {filename}")
                    else:
                        logger.info(f"Would delete expired recording: {filename}")
                        stats['deleted_files'] += 1
                        stats['deleted_records'] += 1
                        
                except Exception as e:
                    logger.error(f"Error deleting recording {filename}: {e}")
                    stats['errors'] += 1
            
            if not dry_run:
                logger.info(f"Cleanup complete: {stats['deleted_records']} recordings deleted")
            else:
                logger.info(f"Dry run complete: {stats['deleted_records']} recordings would be deleted")
                
        except Exception as e:
            logger.error(f"Error during cleanup: {e}")
            stats['errors'] += 1
        
        return stats
    
    def get_recordings_expiring_soon(self, days_ahead: int = 7) -> List[Dict[str, Any]]:
        """
        Get recordings that will expire within the specified number of days
        
        Args:
            days_ahead: Number of days to look ahead
            
        Returns:
            List of recordings expiring soon
        """
        try:
            query = """
                SELECT r.id, r.filename, r.recorded_at, r.expires_at,
                       s.name as show_name, st.name as station_name,
                       DATEDIFF(r.expires_at, NOW()) as days_until_expiry
                FROM recordings r
                JOIN shows s ON r.show_id = s.id
                JOIN stations st ON s.station_id = st.id
                WHERE r.expires_at IS NOT NULL 
                AND r.expires_at > NOW()
                AND r.expires_at <= DATE_ADD(NOW(), INTERVAL ? DAY)
                ORDER BY r.expires_at ASC
            """
            
            expiring_soon = self.db.fetch_all(query, (days_ahead,))
            return expiring_soon
            
        except Exception as e:
            logger.error(f"Error getting recordings expiring soon: {e}")
            return []
    
    def extend_recording_ttl(self, recording_id: int, additional_days: int) -> bool:
        """
        Extend the TTL of a recording by additional days
        
        Args:
            recording_id: Recording ID
            additional_days: Days to add to current expiry
            
        Returns:
            True if successful, False otherwise
        """
        try:
            # Get current expiry
            query = "SELECT expires_at FROM recordings WHERE id = ?"
            result = self.db.fetch_one(query, (recording_id,))
            
            if not result:
                logger.error(f"Recording {recording_id} not found")
                return False
            
            current_expiry = result['expires_at']
            if current_expiry is None:
                logger.info(f"Recording {recording_id} has indefinite TTL, no extension needed")
                return True
            
            # Calculate new expiry
            new_expiry = current_expiry + timedelta(days=additional_days)
            
            # Update expiry
            self.db.execute("""
                UPDATE recordings 
                SET expires_at = ?
                WHERE id = ?
            """, (new_expiry, recording_id))
            
            logger.info(f"Extended recording {recording_id} TTL to {new_expiry}")
            return True
            
        except Exception as e:
            logger.error(f"Error extending recording TTL: {e}")
            return False

def main():
    """Command line interface for TTL management"""
    parser = argparse.ArgumentParser(description='RadioGrab TTL Manager')
    parser.add_argument('--cleanup', action='store_true', help='Clean up expired recordings')
    parser.add_argument('--dry-run', action='store_true', help='Dry run (don\'t actually delete)')
    parser.add_argument('--list-expired', action='store_true', help='List expired recordings')
    parser.add_argument('--list-expiring-soon', type=int, metavar='DAYS', help='List recordings expiring within N days')
    parser.add_argument('--update-recording', type=int, metavar='ID', help='Update recording TTL')
    parser.add_argument('--update-show-ttl', type=int, metavar='ID', help='Update show TTL and existing recordings')
    parser.add_argument('--ttl-days', type=int, metavar='DAYS', help='TTL in specified units')
    parser.add_argument('--ttl-type', choices=['days', 'weeks', 'months', 'indefinite'], default='days', help='TTL unit type')
    parser.add_argument('--extend-recording', type=int, metavar='ID', help='Extend recording TTL')
    parser.add_argument('--extend-days', type=int, metavar='DAYS', help='Days to extend')
    parser.add_argument('--verbose', '-v', action='store_true', help='Verbose logging')
    
    args = parser.parse_args()
    
    # Setup logging
    level = logging.DEBUG if args.verbose else logging.INFO
    logging.basicConfig(level=level, format='%(asctime)s - %(levelname)s - %(message)s')
    
    ttl_manager = TTLManager()
    
    try:
        if args.cleanup:
            stats = ttl_manager.cleanup_expired_recordings(dry_run=args.dry_run)
            print(f"Cleanup results: {stats}")
            
        elif args.list_expired:
            expired = ttl_manager.get_expired_recordings()
            if expired:
                print(f"Found {len(expired)} expired recordings:")
                for rec in expired:
                    print(f"  {rec['filename']} (expired {rec['expires_at']})")
            else:
                print("No expired recordings found")
                
        elif args.list_expiring_soon is not None:
            expiring = ttl_manager.get_recordings_expiring_soon(args.list_expiring_soon)
            if expiring:
                print(f"Found {len(expiring)} recordings expiring within {args.list_expiring_soon} days:")
                for rec in expiring:
                    print(f"  {rec['filename']} (expires {rec['expires_at']}, {rec['days_until_expiry']} days)")
            else:
                print(f"No recordings expiring within {args.list_expiring_soon} days")
                
        elif args.update_recording:
            if args.ttl_days is None:
                print("Error: --ttl-days required with --update-recording")
                sys.exit(1)
            success = ttl_manager.update_recording_expiry(args.update_recording, args.ttl_days, args.ttl_type)
            if success:
                print(f"Updated recording {args.update_recording} TTL to {args.ttl_days} {args.ttl_type}")
            else:
                print(f"Failed to update recording {args.update_recording}")
                
        elif args.update_show_ttl:
            if args.ttl_days is None:
                print("Error: --ttl-days required with --update-show-ttl")
                sys.exit(1)
            success = ttl_manager.update_show_default_ttl(args.update_show_ttl, args.ttl_days, args.ttl_type)
            if success:
                print(f"Updated show {args.update_show_ttl} TTL to {args.ttl_days} {args.ttl_type}")
            else:
                print(f"Failed to update show {args.update_show_ttl}")
                
        elif args.extend_recording:
            if args.extend_days is None:
                print("Error: --extend-days required with --extend-recording")
                sys.exit(1)
            success = ttl_manager.extend_recording_ttl(args.extend_recording, args.extend_days)
            if success:
                print(f"Extended recording {args.extend_recording} by {args.extend_days} days")
            else:
                print(f"Failed to extend recording {args.extend_recording}")
                
        else:
            parser.print_help()
            
    except KeyboardInterrupt:
        print("\nOperation cancelled by user")
        sys.exit(1)
    except Exception as e:
        logger.error(f"Error: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()