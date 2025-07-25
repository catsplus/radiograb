#!/usr/bin/env python3
"""
Housekeeping Service for RadioGrab
Performs regular maintenance tasks like cleaning up empty recordings
"""

import os
import sys
import logging
import time
from pathlib import Path
from datetime import datetime, timedelta
from typing import List, Dict

# Add the project root to Python path
sys.path.insert(0, '/opt/radiograb')

from backend.config.database import SessionLocal
from backend.models.station import Recording

logger = logging.getLogger(__name__)

class HousekeepingService:
    """Handles regular maintenance tasks for RadioGrab"""
    
    def __init__(self, recordings_dir: str = "/var/radiograb/recordings"):
        self.recordings_dir = Path(recordings_dir)
        self.stats = {
            'empty_files_removed': 0,
            'old_files_removed': 0,
            'database_records_cleaned': 0,
            'errors': 0
        }
    
    def cleanup_empty_recordings(self) -> Dict:
        """Remove empty recording files and update database"""
        logger.info("Starting cleanup of empty recordings...")
        
        try:
            # Find all empty MP3 files
            empty_files = []
            if self.recordings_dir.exists():
                for file_path in self.recordings_dir.glob("*.mp3"):
                    if file_path.stat().st_size == 0:
                        empty_files.append(file_path)
            
            logger.info(f"Found {len(empty_files)} empty recording files")
            
            # Remove empty files
            for file_path in empty_files:
                try:
                    file_path.unlink()
                    self.stats['empty_files_removed'] += 1
                    logger.debug(f"Removed empty file: {file_path.name}")
                except Exception as e:
                    logger.error(f"Failed to remove {file_path.name}: {e}")
                    self.stats['errors'] += 1
            
            # Clean up database records for non-existent files
            self._cleanup_orphaned_database_records()
            
            logger.info(f"Cleanup complete: removed {self.stats['empty_files_removed']} empty files")
            
        except Exception as e:
            logger.error(f"Error during cleanup: {e}")
            self.stats['errors'] += 1
        
        return self.stats
    
    def cleanup_old_recordings(self, max_age_days: int = 90) -> Dict:
        """Remove recordings older than specified days (emergency cleanup)"""
        logger.info(f"Starting cleanup of recordings older than {max_age_days} days...")
        
        try:
            cutoff_date = datetime.now() - timedelta(days=max_age_days)
            old_files = []
            
            if self.recordings_dir.exists():
                for file_path in self.recordings_dir.glob("*.mp3"):
                    file_mtime = datetime.fromtimestamp(file_path.stat().st_mtime)
                    if file_mtime < cutoff_date:
                        old_files.append(file_path)
            
            logger.info(f"Found {len(old_files)} files older than {max_age_days} days")
            
            # Remove old files
            for file_path in old_files:
                try:
                    file_path.unlink()
                    self.stats['old_files_removed'] += 1
                    logger.debug(f"Removed old file: {file_path.name}")
                except Exception as e:
                    logger.error(f"Failed to remove {file_path.name}: {e}")
                    self.stats['errors'] += 1
            
            logger.info(f"Old file cleanup complete: removed {self.stats['old_files_removed']} files")
            
        except Exception as e:
            logger.error(f"Error during old file cleanup: {e}")
            self.stats['errors'] += 1
        
        return self.stats
    
    def _cleanup_orphaned_database_records(self):
        """Remove database records for recordings that no longer exist on disk"""
        db = SessionLocal()
        try:
            recordings = db.query(Recording).all()
            
            for recording in recordings:
                if recording.filename:
                    file_path = self.recordings_dir / recording.filename
                    if not file_path.exists():
                        logger.debug(f"Removing orphaned database record: {recording.filename}")
                        db.delete(recording)
                        self.stats['database_records_cleaned'] += 1
            
            db.commit()
            logger.info(f"Cleaned up {self.stats['database_records_cleaned']} orphaned database records")
            
        except Exception as e:
            logger.error(f"Error cleaning orphaned database records: {e}")
            db.rollback()
            self.stats['errors'] += 1
        finally:
            db.close()
    
    def get_disk_usage_stats(self) -> Dict:
        """Get disk usage statistics for recordings directory"""
        stats = {
            'total_files': 0,
            'total_size_mb': 0,
            'empty_files': 0,
            'recordings_dir': str(self.recordings_dir)
        }
        
        try:
            if self.recordings_dir.exists():
                for file_path in self.recordings_dir.glob("*.mp3"):
                    stats['total_files'] += 1
                    file_size = file_path.stat().st_size
                    stats['total_size_mb'] += file_size / (1024 * 1024)
                    
                    if file_size == 0:
                        stats['empty_files'] += 1
            
        except Exception as e:
            logger.error(f"Error getting disk usage stats: {e}")
        
        return stats

def run_housekeeping_cycle():
    """Run a complete housekeeping cycle"""
    logger.info("=== Starting RadioGrab Housekeeping Cycle ===")
    
    housekeeping = HousekeepingService()
    
    # Get initial stats
    initial_stats = housekeeping.get_disk_usage_stats()
    logger.info(f"Initial stats: {initial_stats['total_files']} files, "
               f"{initial_stats['total_size_mb']:.1f} MB, "
               f"{initial_stats['empty_files']} empty files")
    
    # Clean up empty recordings
    cleanup_stats = housekeeping.cleanup_empty_recordings()
    
    # Get final stats
    final_stats = housekeeping.get_disk_usage_stats()
    logger.info(f"Final stats: {final_stats['total_files']} files, "
               f"{final_stats['total_size_mb']:.1f} MB, "
               f"{final_stats['empty_files']} empty files")
    
    # Summary
    logger.info(f"Housekeeping complete: "
               f"removed {cleanup_stats['empty_files_removed']} empty files, "
               f"cleaned {cleanup_stats['database_records_cleaned']} database records, "
               f"{cleanup_stats['errors']} errors")
    
    return cleanup_stats

def main():
    """Main housekeeping service entry point"""
    import argparse
    
    parser = argparse.ArgumentParser(description='RadioGrab Housekeeping Service')
    parser.add_argument('--run-once', action='store_true', help='Run once and exit')
    parser.add_argument('--daemon', action='store_true', help='Run as daemon (every 6 hours)')
    parser.add_argument('--cleanup-old', type=int, metavar='DAYS', help='Remove files older than N days')
    parser.add_argument('--stats-only', action='store_true', help='Show disk usage stats only')
    parser.add_argument('--log-level', default='INFO', choices=['DEBUG', 'INFO', 'WARNING', 'ERROR'])
    
    args = parser.parse_args()
    
    # Set up logging
    logging.basicConfig(
        level=getattr(logging, args.log_level),
        format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
    )
    
    housekeeping = HousekeepingService()
    
    if args.stats_only:
        stats = housekeeping.get_disk_usage_stats()
        print(f"Disk Usage Stats:")
        print(f"  Directory: {stats['recordings_dir']}")
        print(f"  Total files: {stats['total_files']}")
        print(f"  Total size: {stats['total_size_mb']:.1f} MB")
        print(f"  Empty files: {stats['empty_files']}")
        return
    
    if args.cleanup_old:
        logger.info(f"Emergency cleanup: removing files older than {args.cleanup_old} days")
        stats = housekeeping.cleanup_old_recordings(args.cleanup_old)
        print(f"Removed {stats['old_files_removed']} old files")
        return
    
    if args.run_once:
        logger.info("Running single housekeeping cycle...")
        run_housekeeping_cycle()
        logger.info("Housekeeping cycle complete.")
        return
    
    if args.daemon:
        logger.info("Starting housekeeping daemon (runs every 6 hours)...")
        try:
            while True:
                run_housekeeping_cycle()
                logger.info("Sleeping for 6 hours...")
                time.sleep(6 * 60 * 60)  # 6 hours
                
        except KeyboardInterrupt:
            logger.info("Housekeeping daemon stopped.")
        return
    
    # Default: show help
    parser.print_help()

if __name__ == "__main__":
    main()