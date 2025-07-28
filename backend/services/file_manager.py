#!/usr/bin/env python3
"""
File Manager for RadioGrab
Handles file operations for recordings
"""

import os
import logging
from typing import Optional, Dict, Any
from pathlib import Path

logger = logging.getLogger(__name__)

class FileManager:
    """Manages file operations for recordings"""
    
    def __init__(self):
        self.recordings_dir = '/var/radiograb/recordings'
        self.temp_dir = '/var/radiograb/temp'
        
    def delete_recording_file(self, filename: str) -> bool:
        """
        Delete a recording file
        
        Args:
            filename: Name of the file to delete
            
        Returns:
            True if deleted successfully, False otherwise
        """
        try:
            # Check in recordings directory first
            file_path = os.path.join(self.recordings_dir, filename)
            
            if os.path.exists(file_path):
                os.remove(file_path)
                logger.info(f"Deleted recording file: {file_path}")
                return True
            
            # Check in temp directory
            temp_file_path = os.path.join(self.temp_dir, filename)
            if os.path.exists(temp_file_path):
                os.remove(temp_file_path)
                logger.info(f"Deleted temp file: {temp_file_path}")
                return True
            
            logger.warning(f"File not found: {filename}")
            return False
            
        except Exception as e:
            logger.error(f"Error deleting file {filename}: {e}")
            return False
    
    def get_file_info(self, filename: str) -> Optional[Dict[str, Any]]:
        """
        Get information about a recording file
        
        Args:
            filename: Name of the file
            
        Returns:
            Dictionary with file info or None if not found
        """
        try:
            # Check in recordings directory first
            file_path = os.path.join(self.recordings_dir, filename)
            
            if not os.path.exists(file_path):
                # Check in temp directory
                file_path = os.path.join(self.temp_dir, filename)
                
            if not os.path.exists(file_path):
                return None
            
            stat = os.stat(file_path)
            
            return {
                'path': file_path,
                'size': stat.st_size,
                'created': stat.st_ctime,
                'modified': stat.st_mtime,
                'exists': True
            }
            
        except Exception as e:
            logger.error(f"Error getting file info for {filename}: {e}")
            return None
    
    def ensure_directories_exist(self):
        """Ensure recording directories exist"""
        try:
            Path(self.recordings_dir).mkdir(parents=True, exist_ok=True)
            Path(self.temp_dir).mkdir(parents=True, exist_ok=True)
            logger.debug("Recording directories verified")
        except Exception as e:
            logger.error(f"Error creating directories: {e}")
    
    def get_directory_size(self, directory: str) -> int:
        """
        Get total size of files in a directory
        
        Args:
            directory: Directory path
            
        Returns:
            Total size in bytes
        """
        total_size = 0
        try:
            for dirpath, dirnames, filenames in os.walk(directory):
                for filename in filenames:
                    file_path = os.path.join(dirpath, filename)
                    if os.path.exists(file_path):
                        total_size += os.path.getsize(file_path)
        except Exception as e:
            logger.error(f"Error calculating directory size for {directory}: {e}")
        
        return total_size
    
    def cleanup_empty_files(self, min_size_bytes: int = 1024) -> int:
        """
        Clean up files smaller than minimum size
        
        Args:
            min_size_bytes: Minimum file size to keep
            
        Returns:
            Number of files deleted
        """
        deleted_count = 0
        
        for directory in [self.recordings_dir, self.temp_dir]:
            try:
                if not os.path.exists(directory):
                    continue
                    
                for filename in os.listdir(directory):
                    file_path = os.path.join(directory, filename)
                    
                    if os.path.isfile(file_path):
                        file_size = os.path.getsize(file_path)
                        
                        if file_size < min_size_bytes:
                            try:
                                os.remove(file_path)
                                logger.info(f"Deleted small file: {filename} ({file_size} bytes)")
                                deleted_count += 1
                            except Exception as e:
                                logger.error(f"Error deleting small file {filename}: {e}")
                                
            except Exception as e:
                logger.error(f"Error cleaning up directory {directory}: {e}")
        
        return deleted_count