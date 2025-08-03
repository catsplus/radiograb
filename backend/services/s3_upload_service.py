#!/usr/bin/env python3
"""
S3 Upload Service
Issue #13 - Automatic cloud backup of recordings using user-configured S3 credentials
"""

import os
import sys
import boto3
import json
import logging
from pathlib import Path
from datetime import datetime
from botocore.exceptions import ClientError, NoCredentialsError

# Add the project root to Python path
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), '../..')))

# Import database modules
from backend.config.database import SessionLocal
from backend.models.station import Recording, Show

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

class S3UploadService:
    def __init__(self):
        pass
        
    def get_user_s3_configs(self, user_id, active_only=True):
        """Get S3 configurations for a user"""
        import mysql.connector
        
        try:
            # Direct MySQL connection
            db = mysql.connector.connect(
                host='mysql',
                port=3306,
                user='radiograb',
                password='radiograb_pass_2024',
                database='radiograb'
            )
            cursor = db.cursor(dictionary=True)
            
            query = """
                SELECT s3c.*, ak.encrypted_credentials
                FROM user_s3_configs s3c
                JOIN user_api_keys ak ON s3c.api_key_id = ak.id
                WHERE s3c.user_id = %s
            """
            params = [user_id]
            
            if active_only:
                query += " AND s3c.is_active = 1 AND ak.is_active = 1"
            
            query += " ORDER BY s3c.created_at"
            
            cursor.execute(query, params)
            results = cursor.fetchall()
            
            return results
            
        finally:
            if 'cursor' in locals():
                cursor.close()
            if 'db' in locals():
                db.close()
    
    def create_s3_client(self, credentials, config):
        """Create S3 client with user credentials"""
        try:
            # Get decrypted credentials - decode from base64 JSON
            import base64
            creds = json.loads(base64.b64decode(credentials).decode('utf-8'))
            
            session = boto3.Session(
                aws_access_key_id=creds['access_key_id'],
                aws_secret_access_key=creds['secret_access_key'],
                region_name=config['region']
            )
            
            # Use custom endpoint if specified (for S3-compatible services)
            if config['endpoint_url']:
                s3_client = session.client('s3', endpoint_url=config['endpoint_url'])
            else:
                s3_client = session.client('s3')
                
            return s3_client
            
        except Exception as e:
            logger.error(f"Failed to create S3 client: {str(e)}")
            return None
    
    def upload_file(self, user_id, local_file_path, remote_key=None, config_name=None):
        """Upload a file to user's S3 storage"""
        try:
            if not os.path.exists(local_file_path):
                return {'success': False, 'error': 'Local file does not exist'}
            
            # Get S3 configurations for user
            s3_configs = self.get_user_s3_configs(user_id, active_only=True)
            
            if not s3_configs:
                return {'success': False, 'error': 'No active S3 configuration found'}
            
            # Use specific config if requested, otherwise use first active config
            config = None
            if config_name:
                for cfg in s3_configs:
                    if cfg['config_name'] == config_name:
                        config = cfg
                        break
            else:
                config = s3_configs[0]
                
            if not config:
                return {'success': False, 'error': f'S3 configuration "{config_name}" not found'}
            
            # Create S3 client
            s3_client = self.create_s3_client(config['encrypted_credentials'], config)
            if not s3_client:
                return {'success': False, 'error': 'Failed to create S3 client'}
            
            # Generate remote key if not provided
            if not remote_key:
                filename = os.path.basename(local_file_path)
                remote_key = f"{config['path_prefix']}{filename}"
            
            # Upload file
            start_time = datetime.now()
            file_size = os.path.getsize(local_file_path)
            
            s3_client.upload_file(
                local_file_path,
                config['bucket_name'],
                remote_key,
                ExtraArgs={
                    'StorageClass': config['storage_class']
                }
            )
            
            upload_time = (datetime.now() - start_time).total_seconds()
            
            # Update S3 config stats
            import mysql.connector
            try:
                db = mysql.connector.connect(
                    host='mysql',
                    port=3306,
                    user='radiograb',
                    password='radiograb_pass_2024',
                    database='radiograb'
                )
                cursor = db.cursor()
                cursor.execute("""
                    UPDATE user_s3_configs 
                    SET total_uploaded_bytes = total_uploaded_bytes + %s,
                        total_uploaded_files = total_uploaded_files + 1,
                        last_upload_at = NOW()
                    WHERE id = %s
                """, [file_size, config['id']])
                db.commit()
            finally:
                if 'cursor' in locals():
                    cursor.close()
                if 'db' in locals():
                    db.close()
            
            logger.info(f"Successfully uploaded {local_file_path} to S3 as {remote_key}")
            
            return {
                'success': True,
                'message': 'File uploaded successfully',
                'remote_key': remote_key,
                'file_size': file_size,
                'upload_time': upload_time
            }
            
        except ClientError as e:
            error_msg = f"S3 upload failed: {str(e)}"
            logger.error(error_msg)
            
            # Log failed upload (simplified for now)
            
            return {'success': False, 'error': error_msg}
            
        except Exception as e:
            error_msg = f"Upload error: {str(e)}"
            logger.error(error_msg)
            return {'success': False, 'error': error_msg}
    
    def upload_recording(self, user_id, recording_id):
        """Upload a specific recording to S3"""
        try:
            # Get recording information
            recording = self.db.fetchOne("""
                SELECT r.*, s.name as show_name, st.call_letters
                FROM recordings r
                JOIN shows s ON r.show_id = s.id
                JOIN stations st ON s.station_id = st.id
                WHERE r.id = ? AND s.user_id = ?
            """, [recording_id, user_id])
            
            if not recording:
                return {'success': False, 'error': 'Recording not found'}
            
            # Check if user has auto-upload enabled
            s3_configs = self.get_user_s3_configs(user_id, active_only=True)
            upload_configs = [c for c in s3_configs if c['auto_upload_recordings']]
            
            if not upload_configs:
                return {'success': False, 'error': 'Auto-upload not enabled'}
            
            # Construct file path
            recordings_dir = os.environ.get('RECORDINGS_DIR', '/var/radiograb/recordings')
            file_path = os.path.join(recordings_dir, recording['filename'])
            
            if not os.path.exists(file_path):
                return {'success': False, 'error': f'Recording file not found: {recording["filename"]}'}
            
            # Generate organized remote key
            date_str = recording['recorded_at'].strftime('%Y/%m/%d')
            remote_key = f"recordings/{date_str}/{recording['filename']}"
            
            # Upload to each configured S3 service
            results = []
            for config in upload_configs:
                result = self.upload_file(user_id, file_path, remote_key, config['config_name'])
                results.append({
                    'config_name': config['config_name'],
                    'result': result
                })
            
            return {
                'success': True,
                'message': f'Recording uploaded to {len(results)} S3 service(s)',
                'results': results
            }
            
        except Exception as e:
            error_msg = f"Recording upload error: {str(e)}"
            logger.error(error_msg)
            return {'success': False, 'error': error_msg}
    
    def auto_upload_new_recordings(self, user_id=None):
        """Upload any new recordings that haven't been uploaded yet"""
        try:
            # Get users with auto-upload enabled
            if user_id:
                users_query = """
                    SELECT DISTINCT s3c.user_id
                    FROM user_s3_configs s3c
                    WHERE s3c.user_id = ? AND s3c.auto_upload_recordings = 1 AND s3c.is_active = 1
                """
                users = self.db.fetchAll(users_query, [user_id])
            else:
                users_query = """
                    SELECT DISTINCT s3c.user_id
                    FROM user_s3_configs s3c
                    WHERE s3c.auto_upload_recordings = 1 AND s3c.is_active = 1
                """
                users = self.db.fetchAll(users_query)
            
            total_uploaded = 0
            
            for user in users:
                user_id = user['user_id']
                
                # Get recordings that haven't been uploaded
                unuploaded_recordings = self.db.fetchAll("""
                    SELECT r.id, r.filename, r.recorded_at
                    FROM recordings r
                    JOIN shows s ON r.show_id = s.id
                    WHERE s.user_id = ? 
                    AND r.id NOT IN (
                        SELECT DISTINCT CAST(SUBSTRING_INDEX(resource_id, '_', -1) AS UNSIGNED)
                        FROM user_api_usage_log 
                        WHERE user_id = ? 
                        AND service_type = 's3_storage' 
                        AND operation_type = 'upload' 
                        AND success = 1
                        AND resource_type = 'recording'
                    )
                    ORDER BY r.recorded_at ASC
                    LIMIT 50
                """, [user_id, user_id])
                
                logger.info(f"Found {len(unuploaded_recordings)} unuploaded recordings for user {user_id}")
                
                for recording in unuploaded_recordings:
                    result = self.upload_recording(user_id, recording['id'])
                    if result['success']:
                        total_uploaded += 1
                        logger.info(f"Auto-uploaded recording {recording['filename']}")
                    else:
                        logger.warning(f"Failed to auto-upload recording {recording['filename']}: {result['error']}")
            
            return {
                'success': True,
                'message': f'Auto-uploaded {total_uploaded} recordings',
                'uploaded_count': total_uploaded
            }
            
        except Exception as e:
            error_msg = f"Auto-upload error: {str(e)}"
            logger.error(error_msg)
            return {'success': False, 'error': error_msg}

def main():
    """Command line interface for S3 upload service"""
    import argparse
    
    parser = argparse.ArgumentParser(description='S3 Upload Service')
    parser.add_argument('--user-id', type=int, help='User ID for uploads')
    parser.add_argument('--recording-id', type=int, help='Specific recording ID to upload')
    parser.add_argument('--auto-upload', action='store_true', help='Auto-upload new recordings')
    parser.add_argument('--file-path', help='Local file path to upload')
    parser.add_argument('--remote-key', help='Remote S3 key (optional)')
    
    args = parser.parse_args()
    
    service = S3UploadService()
    
    if args.recording_id and args.user_id:
        result = service.upload_recording(args.user_id, args.recording_id)
        print(f"Upload result: {result}")
        
    elif args.auto_upload:
        result = service.auto_upload_new_recordings(args.user_id)
        print(f"Auto-upload result: {result}")
        
    elif args.file_path and args.user_id:
        result = service.upload_file(args.user_id, args.file_path, args.remote_key)
        print(f"File upload result: {result}")
        
    else:
        parser.print_help()

if __name__ == '__main__':
    main()