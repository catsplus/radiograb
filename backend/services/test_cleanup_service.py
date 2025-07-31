"""
Automatically cleans up test recordings older than a specified age.

This service is designed to be run periodically to remove temporary test recording
files, preventing them from accumulating and consuming disk space.

Key Variables:
- `max_age`: The maximum age in hours for test recordings before they are deleted.

Inter-script Communication:
- This script is typically run as a cron job.
- It uses `show_management.py` to perform the cleanup operation.
"""
"""
RadioGrab Test Recording Cleanup Service
Automatically cleans up test recordings older than 4 hours
"""

import sys
import os
import logging
from datetime import datetime
import argparse

# Add project root to path
sys.path.insert(0, '/opt/radiograb')

from backend.services.show_management import ShowManagementService

# Set up logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

def main():
    """Test recording cleanup service main function"""
    parser = argparse.ArgumentParser(description='RadioGrab Test Recording Cleanup Service')
    parser.add_argument('--max-age', type=int, default=4, help='Maximum age in hours before cleanup (default: 4)')
    parser.add_argument('--daemon', action='store_true', help='Run in daemon mode')
    parser.add_argument('--dry-run', action='store_true', help='Show what would be deleted without actually deleting')
    
    args = parser.parse_args()
    
    logger.info(f"Starting test recording cleanup service (max age: {args.max_age} hours)")
    
    try:
        service = ShowManagementService()
        
        if args.dry_run:
            logger.info("DRY RUN MODE - No files will be deleted")
            # For dry run, we'll just list files that would be deleted
            from pathlib import Path
            from datetime import timedelta
            
            temp_dir = Path('/var/radiograb/temp')
            cutoff_time = datetime.now() - timedelta(hours=args.max_age)
            
            if not temp_dir.exists():
                logger.info("Temp directory does not exist")
                return
            
            test_patterns = ['*_test_*.mp3', '*_test_*.mp3.mp3', '*_manual_*.mp3']
            files_to_delete = []
            total_size = 0
            
            for pattern in test_patterns:
                for file_path in temp_dir.glob(pattern):
                    try:
                        file_mtime = datetime.fromtimestamp(file_path.stat().st_mtime)
                        if file_mtime < cutoff_time:
                            file_size = file_path.stat().st_size
                            files_to_delete.append((file_path.name, file_size, file_mtime))
                            total_size += file_size
                    except Exception as e:
                        logger.warning(f"Error checking file {file_path.name}: {e}")
            
            logger.info(f"Would delete {len(files_to_delete)} files totaling {total_size:,} bytes")
            for filename, size, mtime in files_to_delete:
                logger.info(f"  - {filename} ({size:,} bytes, modified: {mtime})")
        else:
            # Perform actual cleanup
            result = service.cleanup_test_recordings(args.max_age)
            
            if result['success']:
                logger.info(f"Cleanup completed successfully:")
                logger.info(f"  Files deleted: {result['files_deleted']}")
                logger.info(f"  Bytes freed: {result['bytes_freed']:,}")
                
                if result['errors']:
                    logger.warning(f"  Errors encountered: {len(result['errors'])}")
                    for error in result['errors']:
                        logger.warning(f"    - {error}")
            else:
                logger.error("Cleanup failed")
                for error in result['errors']:
                    logger.error(f"  - {error}")
                sys.exit(1)
        
        logger.info("Test recording cleanup service completed")
        
    except Exception as e:
        logger.error(f"Test recording cleanup service error: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()