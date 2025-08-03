#!/usr/bin/env python3
"""
S3 Migration Service
Migrate existing recordings from local storage to S3 cloud storage
"""

import os
import sys
import json
import logging
import pymysql
from pathlib import Path
from datetime import datetime
from typing import List, Dict, Optional

# Add the project root to Python path
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), '../..')))

# Import S3 upload service
from s3_upload_service_fixed import S3UploadService

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

class S3MigrationService:
    def __init__(self):
        self.s3_service = S3UploadService()
        self.recordings_dir = os.environ.get('RECORDINGS_DIR', '/var/radiograb/recordings')
        
        # Database connection
        self.db_config = {
            'host': os.environ.get('DB_HOST', 'mysql'),
            'port': int(os.environ.get('DB_PORT', '3306')),
            'user': os.environ.get('DB_USER', 'radiograb'),
            'password': os.environ.get('DB_PASSWORD', 'radiograb_pass_2024'),
            'database': os.environ.get('DB_NAME', 'radiograb'),
            'charset': 'utf8mb4'
        }
    
    def get_db_connection(self):
        """Get database connection"""
        return pymysql.connect(**self.db_config)
    
    def get_user_recordings(self, user_id: int, limit: Optional[int] = None) -> List[Dict]:
        """Get all recordings for a user that exist locally"""
        connection = self.get_db_connection()
        try:
            with connection.cursor(pymysql.cursors.DictCursor) as cursor:
                query = """
                    SELECT r.*, s.name as show_name, st.call_letters
                    FROM recordings r
                    JOIN shows s ON r.show_id = s.id
                    JOIN stations st ON s.station_id = st.id
                    WHERE s.user_id = %s
                    ORDER BY r.recorded_at DESC
                """
                params = [user_id]
                
                if limit:
                    query += " LIMIT %s"
                    params.append(limit)
                
                cursor.execute(query, params)
                recordings = cursor.fetchall()
                
                # Filter for recordings that exist locally
                existing_recordings = []
                for recording in recordings:
                    local_path = os.path.join(self.recordings_dir, recording['filename'])
                    if os.path.exists(local_path):
                        recording['local_path'] = local_path
                        recording['file_size'] = os.path.getsize(local_path)
                        existing_recordings.append(recording)
                
                return existing_recordings
        finally:
            connection.close()
    
    def is_recording_migrated(self, user_id: int, recording_id: int) -> bool:
        """Check if a recording has already been migrated to S3"""
        connection = self.get_db_connection()
        try:
            with connection.cursor() as cursor:
                cursor.execute("""
                    SELECT COUNT(*) as count
                    FROM user_api_usage_log 
                    WHERE user_id = %s 
                    AND service_type = 's3_storage' 
                    AND operation_type = 'upload' 
                    AND success = 1
                    AND resource_type = 'recording'
                    AND resource_id LIKE %s
                """, [user_id, f'%_{recording_id}'])
                
                result = cursor.fetchone()
                return result[0] > 0
        finally:
            connection.close()
    
    def migrate_recording(self, user_id: int, recording: Dict) -> Dict:
        """Migrate a single recording to S3"""
        try:
            recording_id = recording['id']
            local_path = recording['local_path']
            
            # Check if already migrated
            if self.is_recording_migrated(user_id, recording_id):
                return {
                    'success': True,
                    'skipped': True,
                    'message': f"Recording {recording['filename']} already migrated"
                }
            
            # Generate organized remote key
            date_str = recording['recorded_at'].strftime('%Y/%m/%d')
            remote_key = f"recordings/{date_str}/{recording['filename']}"
            
            # Upload to S3
            result = self.s3_service.upload_file(user_id, local_path, remote_key)
            
            if result['success']:
                # Update recording with S3 URL if primary storage
                if result.get('storage_mode') == 'primary':
                    self.update_recording_url(recording_id, result.get('public_url'))
                
                logger.info(f"Successfully migrated recording {recording['filename']}")
                return {
                    'success': True,
                    'skipped': False,
                    'message': f"Recording {recording['filename']} migrated successfully",
                    'remote_key': result['remote_key'],
                    'public_url': result.get('public_url'),
                    'upload_time': result['upload_time']
                }
            else:
                logger.error(f"Failed to migrate recording {recording['filename']}: {result['error']}")
                return {
                    'success': False,
                    'message': f"Failed to migrate {recording['filename']}: {result['error']}"
                }
                
        except Exception as e:
            error_msg = f"Migration error for {recording['filename']}: {str(e)}"
            logger.error(error_msg)
            return {'success': False, 'message': error_msg}
    
    def update_recording_url(self, recording_id: int, s3_url: str) -> None:
        """Update recording with S3 URL for primary storage mode"""
        connection = self.get_db_connection()
        try:
            with connection.cursor() as cursor:
                cursor.execute("""
                    UPDATE recordings 
                    SET s3_url = %s, migrated_to_s3 = 1
                    WHERE id = %s
                """, [s3_url, recording_id])
                connection.commit()
        except Exception as e:
            logger.error(f"Failed to update recording URL: {str(e)}")
        finally:
            connection.close()
    
    def migrate_user_recordings(self, user_id: int, batch_size: int = 10, dry_run: bool = False) -> Dict:
        """Migrate all recordings for a user to S3"""
        try:
            # Get user's S3 configuration
            s3_configs = self.s3_service.get_user_s3_configs(user_id, active_only=True)
            if not s3_configs:
                return {
                    'success': False,
                    'error': 'No active S3 configuration found for user'
                }
            
            # Get recordings to migrate
            recordings = self.get_user_recordings(user_id)
            if not recordings:
                return {
                    'success': True,
                    'message': 'No local recordings found to migrate',
                    'total_recordings': 0,
                    'migrated': 0,
                    'skipped': 0,
                    'failed': 0
                }
            
            if dry_run:
                return {
                    'success': True,
                    'dry_run': True,
                    'message': f'Dry run: Would migrate {len(recordings)} recordings',
                    'total_recordings': len(recordings),
                    'total_size_mb': sum(r['file_size'] for r in recordings) / (1024 * 1024)
                }
            
            # Migrate recordings in batches
            migrated_count = 0
            skipped_count = 0
            failed_count = 0
            total_size = 0
            
            for i in range(0, len(recordings), batch_size):
                batch = recordings[i:i + batch_size]
                
                for recording in batch:
                    result = self.migrate_recording(user_id, recording)
                    
                    if result['success']:
                        if result.get('skipped', False):
                            skipped_count += 1
                        else:
                            migrated_count += 1
                            total_size += recording['file_size']
                    else:
                        failed_count += 1
                
                # Log progress
                logger.info(f"Batch {i//batch_size + 1} complete: {migrated_count} migrated, {skipped_count} skipped, {failed_count} failed")
            
            return {
                'success': True,
                'message': f'Migration complete: {migrated_count} migrated, {skipped_count} skipped, {failed_count} failed',
                'total_recordings': len(recordings),
                'migrated': migrated_count,
                'skipped': skipped_count,
                'failed': failed_count,
                'total_size_mb': total_size / (1024 * 1024)
            }
            
        except Exception as e:
            error_msg = f"Migration error: {str(e)}"
            logger.error(error_msg)
            return {'success': False, 'error': error_msg}
    
    def get_migration_status(self, user_id: int) -> Dict:
        """Get migration status for a user"""
        try:
            recordings = self.get_user_recordings(user_id)
            total_recordings = len(recordings)
            total_size = sum(r['file_size'] for r in recordings)
            
            migrated_count = 0
            for recording in recordings:
                if self.is_recording_migrated(user_id, recording['id']):
                    migrated_count += 1
            
            return {
                'success': True,
                'total_recordings': total_recordings,
                'migrated_recordings': migrated_count,
                'pending_recordings': total_recordings - migrated_count,
                'total_size_mb': total_size / (1024 * 1024),
                'migration_complete': migrated_count == total_recordings
            }
            
        except Exception as e:
            error_msg = f"Status check error: {str(e)}"
            logger.error(error_msg)
            return {'success': False, 'error': error_msg}

def main():
    """Command line interface for S3 migration service"""
    import argparse
    
    parser = argparse.ArgumentParser(description='S3 Migration Service')
    parser.add_argument('--user-id', type=int, required=True, help='User ID for migration')
    parser.add_argument('--dry-run', action='store_true', help='Show what would be migrated without doing it')
    parser.add_argument('--batch-size', type=int, default=10, help='Number of recordings to migrate per batch')
    parser.add_argument('--status', action='store_true', help='Check migration status')
    
    args = parser.parse_args()
    
    service = S3MigrationService()
    
    if args.status:
        result = service.get_migration_status(args.user_id)
        print(f"Migration status: {json.dumps(result, indent=2)}")
    else:
        result = service.migrate_user_recordings(args.user_id, args.batch_size, args.dry_run)
        print(f"Migration result: {json.dumps(result, indent=2)}")

if __name__ == '__main__':
    main()