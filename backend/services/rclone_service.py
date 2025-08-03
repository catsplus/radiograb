#!/usr/bin/env python3
"""
Rclone Remote Storage Service
Issue #42 - Support for Google Drive, SFTP, and other rclone backends
"""

import os
import sys
import json
import logging
import subprocess
import pymysql
from pathlib import Path
from datetime import datetime
from typing import List, Dict, Optional, Tuple

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

class RcloneService:
    def __init__(self):
        self.rclone_config_dir = "/var/radiograb/rclone"
        self.recordings_dir = os.environ.get('RECORDINGS_DIR', '/var/radiograb/recordings')
        
        # Ensure rclone config directory exists
        os.makedirs(self.rclone_config_dir, exist_ok=True)
        
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
    
    def check_rclone_installation(self) -> bool:
        """Check if rclone is installed and available"""
        try:
            result = subprocess.run(['rclone', 'version'], 
                                  capture_output=True, text=True, timeout=10)
            if result.returncode == 0:
                logger.info(f"Rclone available: {result.stdout.split()[1]}")
                return True
            else:
                logger.error("Rclone not found or not working")
                return False
        except (subprocess.TimeoutExpired, FileNotFoundError) as e:
            logger.error(f"Rclone check failed: {str(e)}")
            return False
    
    def get_user_rclone_remotes(self, user_id: int, active_only: bool = True) -> List[Dict]:
        """Get rclone remote configurations for a user"""
        connection = self.get_db_connection()
        try:
            with connection.cursor(pymysql.cursors.DictCursor) as cursor:
                query = """
                    SELECT * FROM user_rclone_remotes 
                    WHERE user_id = %s
                """
                params = [user_id]
                
                if active_only:
                    query += " AND is_active = 1"
                
                query += " ORDER BY created_at DESC"
                
                cursor.execute(query, params)
                return cursor.fetchall()
        finally:
            connection.close()
    
    def create_rclone_remote(self, user_id: int, remote_name: str, backend_type: str, 
                           config_data: Dict, role: str = 'backup') -> Dict:
        """Create a new rclone remote configuration"""
        try:
            # Validate role
            if role not in ['primary', 'backup', 'off']:
                return {'success': False, 'error': 'Invalid role. Must be primary, backup, or off'}
            
            # Check if remote name already exists for user
            existing = self.get_remote_by_name(user_id, remote_name)
            if existing:
                return {'success': False, 'error': f'Remote "{remote_name}" already exists'}
            
            # Create rclone config file for this user
            config_file = self.get_user_config_file(user_id)
            
            # Generate rclone configuration
            rclone_config = self.generate_rclone_config(remote_name, backend_type, config_data)
            
            # Write or update rclone config file
            self.write_rclone_config(config_file, remote_name, rclone_config)
            
            # Test the remote configuration
            test_result = self.test_rclone_remote(config_file, remote_name)
            if not test_result['success']:
                return {'success': False, 'error': f'Remote test failed: {test_result["error"]}'}
            
            # Store in database
            connection = self.get_db_connection()
            try:
                with connection.cursor() as cursor:
                    cursor.execute("""
                        INSERT INTO user_rclone_remotes 
                        (user_id, remote_name, backend_type, config_data, role, 
                         config_file_path, is_active, created_at)
                        VALUES (%s, %s, %s, %s, %s, %s, %s, NOW())
                    """, [
                        user_id, remote_name, backend_type, 
                        json.dumps(config_data), role, config_file, 1
                    ])
                    connection.commit()
                    
                    remote_id = cursor.lastrowid
                    
                    logger.info(f"Created rclone remote {remote_name} for user {user_id}")
                    
                    return {
                        'success': True,
                        'remote_id': remote_id,
                        'message': f'Remote "{remote_name}" created successfully',
                        'test_result': test_result
                    }
            finally:
                connection.close()
                
        except Exception as e:
            error_msg = f"Failed to create rclone remote: {str(e)}"
            logger.error(error_msg)
            return {'success': False, 'error': error_msg}
    
    def get_user_config_file(self, user_id: int) -> str:
        """Get the rclone config file path for a user"""
        return os.path.join(self.rclone_config_dir, f"user_{user_id}.conf")
    
    def generate_rclone_config(self, remote_name: str, backend_type: str, config_data: Dict) -> Dict:
        """Generate rclone configuration section for a backend type"""
        config = {'type': backend_type}
        
        # Backend-specific configuration
        if backend_type == 'drive':
            # Google Drive configuration
            config.update({
                'client_id': config_data.get('client_id', ''),
                'client_secret': config_data.get('client_secret', ''),
                'scope': config_data.get('scope', 'drive'),
                'token': config_data.get('token', '{}'),
                'team_drive': config_data.get('team_drive', ''),
                'root_folder_id': config_data.get('root_folder_id', '')
            })
        
        elif backend_type == 'sftp':
            # SFTP configuration
            config.update({
                'host': config_data.get('host', ''),
                'user': config_data.get('user', ''),
                'port': config_data.get('port', '22'),
                'pass': config_data.get('password', ''),
                'key_file': config_data.get('key_file', ''),
                'key_file_pass': config_data.get('key_file_pass', ''),
                'pubkey_file': config_data.get('pubkey_file', ''),
                'known_hosts_file': config_data.get('known_hosts_file', ''),
                'skip_links': config_data.get('skip_links', 'false')
            })
        
        elif backend_type == 'dropbox':
            # Dropbox configuration
            config.update({
                'client_id': config_data.get('client_id', ''),
                'client_secret': config_data.get('client_secret', ''),
                'token': config_data.get('token', '{}'),
                'chunk_size': config_data.get('chunk_size', '48M')
            })
        
        elif backend_type == 'onedrive':
            # OneDrive configuration
            config.update({
                'client_id': config_data.get('client_id', ''),
                'client_secret': config_data.get('client_secret', ''),
                'token': config_data.get('token', '{}'),
                'drive_id': config_data.get('drive_id', ''),
                'drive_type': config_data.get('drive_type', 'personal')
            })
        
        # Remove empty values
        return {k: v for k, v in config.items() if v}
    
    def write_rclone_config(self, config_file: str, remote_name: str, rclone_config: Dict):
        """Write or update rclone configuration file"""
        try:
            # Read existing config if it exists
            existing_config = {}
            if os.path.exists(config_file):
                with open(config_file, 'r') as f:
                    current_section = None
                    for line in f:
                        line = line.strip()
                        if line.startswith('[') and line.endswith(']'):
                            current_section = line[1:-1]
                            existing_config[current_section] = {}
                        elif '=' in line and current_section:
                            key, value = line.split('=', 1)
                            existing_config[current_section][key.strip()] = value.strip()
            
            # Update with new remote
            existing_config[remote_name] = rclone_config
            
            # Write updated config
            with open(config_file, 'w') as f:
                for section_name, section_config in existing_config.items():
                    f.write(f"[{section_name}]\n")
                    for key, value in section_config.items():
                        f.write(f"{key} = {value}\n")
                    f.write("\n")
            
            logger.info(f"Updated rclone config file: {config_file}")
            
        except Exception as e:
            logger.error(f"Failed to write rclone config: {str(e)}")
            raise
    
    def test_rclone_remote(self, config_file: str, remote_name: str) -> Dict:
        """Test rclone remote configuration"""
        try:
            cmd = [
                'rclone', 'lsd', f'{remote_name}:',
                '--config', config_file,
                '--timeout', '30s',
                '--retries', '1'
            ]
            
            result = subprocess.run(cmd, capture_output=True, text=True, timeout=45)
            
            if result.returncode == 0:
                return {
                    'success': True,
                    'message': 'Remote connection successful',
                    'output': result.stdout
                }
            else:
                return {
                    'success': False,
                    'error': f'Remote test failed: {result.stderr}',
                    'output': result.stdout
                }
                
        except subprocess.TimeoutExpired:
            return {'success': False, 'error': 'Remote test timed out'}
        except Exception as e:
            return {'success': False, 'error': f'Remote test error: {str(e)}'}
    
    def upload_file_to_remote(self, user_id: int, remote_name: str, local_file: str, 
                            remote_path: str = None) -> Dict:
        """Upload a file to a specific rclone remote"""
        try:
            # Get remote configuration
            remote_config = self.get_remote_by_name(user_id, remote_name)
            if not remote_config:
                return {'success': False, 'error': f'Remote "{remote_name}" not found'}
            
            if not remote_config['is_active']:
                return {'success': False, 'error': f'Remote "{remote_name}" is not active'}
            
            # Check local file exists
            if not os.path.exists(local_file):
                return {'success': False, 'error': f'Local file not found: {local_file}'}
            
            # Determine remote path
            if not remote_path:
                filename = os.path.basename(local_file)
                remote_path = f"radiograb/recordings/{filename}"
            
            # Get config file
            config_file = remote_config['config_file_path']
            
            # Upload file
            start_time = datetime.now()
            file_size = os.path.getsize(local_file)
            
            cmd = [
                'rclone', 'copy', local_file,
                f'{remote_name}:{os.path.dirname(remote_path)}',
                '--config', config_file,
                '--progress',
                '--transfers', '1',
                '--checkers', '1'
            ]
            
            result = subprocess.run(cmd, capture_output=True, text=True, timeout=3600)
            upload_time = (datetime.now() - start_time).total_seconds()
            
            if result.returncode == 0:
                # Log successful upload
                self.log_rclone_usage(user_id, remote_config['id'], 'upload', {
                    'file_size': file_size,
                    'upload_time': upload_time,
                    'remote_path': remote_path,
                    'success': True
                })
                
                return {
                    'success': True,
                    'message': f'File uploaded successfully to {remote_name}',
                    'remote_path': remote_path,
                    'upload_time': upload_time,
                    'file_size': file_size
                }
            else:
                error_msg = f'Upload failed: {result.stderr}'
                self.log_rclone_usage(user_id, remote_config['id'], 'upload', {
                    'success': False,
                    'error': error_msg
                })
                return {'success': False, 'error': error_msg}
                
        except Exception as e:
            error_msg = f"Upload error: {str(e)}"
            logger.error(error_msg)
            return {'success': False, 'error': error_msg}
    
    def get_remote_by_name(self, user_id: int, remote_name: str) -> Optional[Dict]:
        """Get remote configuration by name"""
        connection = self.get_db_connection()
        try:
            with connection.cursor(pymysql.cursors.DictCursor) as cursor:
                cursor.execute("""
                    SELECT * FROM user_rclone_remotes 
                    WHERE user_id = %s AND remote_name = %s
                """, [user_id, remote_name])
                return cursor.fetchone()
        finally:
            connection.close()
    
    def log_rclone_usage(self, user_id: int, remote_id: int, operation: str, metrics: Dict):
        """Log rclone usage for tracking and debugging"""
        connection = self.get_db_connection()
        try:
            with connection.cursor() as cursor:
                cursor.execute("""
                    INSERT INTO rclone_usage_log 
                    (user_id, remote_id, operation_type, metrics, created_at)
                    VALUES (%s, %s, %s, %s, NOW())
                """, [user_id, remote_id, operation, json.dumps(metrics)])
                connection.commit()
        except Exception as e:
            logger.error(f"Failed to log rclone usage: {str(e)}")
        finally:
            connection.close()
    
    def auto_upload_recording(self, user_id: int, recording_id: int) -> Dict:
        """Auto-upload recording to all active remotes based on their roles"""
        try:
            # Get recording info
            connection = self.get_db_connection()
            try:
                with connection.cursor(pymysql.cursors.DictCursor) as cursor:
                    cursor.execute("""
                        SELECT r.*, s.name as show_name, st.call_letters
                        FROM recordings r
                        JOIN shows s ON r.show_id = s.id
                        JOIN stations st ON s.station_id = st.id
                        WHERE r.id = %s
                    """, [recording_id])
                    recording = cursor.fetchone()
            finally:
                connection.close()
            
            if not recording:
                return {'success': False, 'error': 'Recording not found'}
            
            # Get active remotes for user
            remotes = self.get_user_rclone_remotes(user_id, active_only=True)
            upload_remotes = [r for r in remotes if r['role'] in ['primary', 'backup']]
            
            if not upload_remotes:
                return {'success': True, 'message': 'No active upload remotes configured'}
            
            # Upload to each remote
            results = []
            local_file = os.path.join(self.recordings_dir, recording['filename'])
            
            for remote in upload_remotes:
                remote_path = f"radiograb/recordings/{recording['filename']}"
                
                result = self.upload_file_to_remote(
                    user_id, remote['remote_name'], local_file, remote_path
                )
                
                results.append({
                    'remote_name': remote['remote_name'],
                    'role': remote['role'],
                    'result': result
                })
            
            return {
                'success': True,
                'message': f'Recording uploaded to {len(results)} remote(s)',
                'results': results
            }
            
        except Exception as e:
            error_msg = f"Auto-upload error: {str(e)}"
            logger.error(error_msg)
            return {'success': False, 'error': error_msg}

def main():
    """Command line interface for rclone service"""
    import argparse
    
    parser = argparse.ArgumentParser(description='Rclone Remote Storage Service')
    parser.add_argument('--user-id', type=int, help='User ID')
    parser.add_argument('--recording-id', type=int, help='Recording ID to upload')
    parser.add_argument('--remote-name', help='Remote name')
    parser.add_argument('--list-remotes', action='store_true', help='List user remotes')
    parser.add_argument('--test-remote', help='Test remote connection')
    
    args = parser.parse_args()
    
    service = RcloneService()
    
    if not service.check_rclone_installation():
        print("Error: rclone is not installed or not accessible")
        return 1
    
    if args.list_remotes and args.user_id:
        remotes = service.get_user_rclone_remotes(args.user_id, active_only=False)
        print(f"Remotes for user {args.user_id}:")
        for remote in remotes:
            print(f"  - {remote['remote_name']} ({remote['backend_type']}) - Role: {remote['role']}")
    
    elif args.test_remote and args.user_id:
        remote = service.get_remote_by_name(args.user_id, args.test_remote)
        if remote:
            result = service.test_rclone_remote(remote['config_file_path'], args.test_remote)
            print(f"Test result: {result}")
        else:
            print(f"Remote {args.test_remote} not found")
    
    elif args.recording_id and args.user_id:
        result = service.auto_upload_recording(args.user_id, args.recording_id)
        print(f"Upload result: {json.dumps(result, indent=2)}")
    
    else:
        parser.print_help()

if __name__ == '__main__':
    main()