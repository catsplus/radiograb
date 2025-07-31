"""
Handles user audio file uploads for playlists and shows.

This service provides functionality for uploading, validating, and processing audio files.
It supports various audio formats, converts them to MP3, extracts metadata, and saves
the recording details to the database.

Key Variables:
- `file_path`: The local path to the uploaded audio file.
- `show_id`: The database ID of the show or playlist to which the file is being uploaded.
- `title`: An optional title for the recording.
- `description`: An optional description for the recording.
- `original_filename`: The original name of the uploaded file.

Inter-script Communication:
- This script is called by the frontend API endpoint `frontend/public/api/upload.php` to handle file uploads.
- It interacts with the `MP3MetadataService` (`mp3_metadata_service.py`) to write metadata to the processed audio files.
- It uses the `Recording` and `Show` models from `backend/models/station.py`.
- It relies on the database session from `backend/config/database.py`.
"""
#!/usr/bin/env python3
"""
Upload Service
Handles user audio file uploads for playlists and shows
"""

import os
import sys
import logging
import uuid
import subprocess
from pathlib import Path
from datetime import datetime
from typing import Dict, Optional, Tuple, Any
from dataclasses import dataclass

# Add project root to path
sys.path.insert(0, '/opt/radiograb')

from backend.config.database import SessionLocal
from backend.models.station import Show, Recording

logger = logging.getLogger(__name__)

@dataclass
class UploadResult:
    """Result of file upload operation"""
    success: bool
    recording_id: Optional[int] = None
    filename: Optional[str] = None
    error: Optional[str] = None
    file_size: int = 0
    duration: int = 0
    metadata: Optional[Dict[str, Any]] = None


class AudioUploadService:
    """Service for handling audio file uploads"""
    
    def __init__(self):
        self.recordings_dir = Path('/var/radiograb/recordings')
        self.temp_dir = Path('/var/radiograb/temp')
        
        # Supported audio formats
        self.supported_formats = {
            '.mp3': 'audio/mpeg',
            '.wav': 'audio/wav',
            '.m4a': 'audio/mp4',
            '.aac': 'audio/aac',
            '.ogg': 'audio/ogg',
            '.flac': 'audio/flac'
        }
        
        # Ensure directories exist
        self.recordings_dir.mkdir(parents=True, exist_ok=True)
        self.temp_dir.mkdir(parents=True, exist_ok=True)
    
    def upload_file(self, file_path: str, show_id: int, title: str = None, 
                   description: str = None, original_filename: str = None) -> UploadResult:
        """
        Upload an audio file to a show/playlist
        
        Args:
            file_path: Path to the uploaded file
            show_id: ID of the show/playlist to add to
            title: Optional title for the recording
            description: Optional description
            original_filename: Original filename from upload
            
        Returns:
            UploadResult with success status and details
        """
        try:
            # Validate show exists and allows uploads
            db = SessionLocal()
            try:
                show = db.query(Show).filter(Show.id == show_id).first()
                if not show:
                    return UploadResult(success=False, error="Show not found")
                
                if show.show_type == 'scheduled' and not show.allow_uploads:
                    return UploadResult(success=False, error="Show does not accept uploads")
                
                # Validate file
                validation_result = self._validate_audio_file(file_path, show.max_file_size_mb or 100)
                if not validation_result['valid']:
                    return UploadResult(success=False, error=validation_result['error'])
                
                # Extract metadata from audio file
                metadata = self._extract_audio_metadata(file_path)
                
                # Generate unique filename
                file_extension = Path(file_path).suffix.lower()
                if not file_extension:
                    file_extension = '.mp3'  # Default
                
                # Use call letters for consistency with recorded shows
                call_letters = show.station.call_letters or f"PL{show.station_id:02d}"
                timestamp = datetime.now().strftime('%Y-%m-%d-%H%M%S')
                unique_id = str(uuid.uuid4())[:8]
                filename = f"{call_letters}_upload_{timestamp}_{unique_id}{file_extension}"
                
                # Create station-specific subdirectory
                station_dir = self.recordings_dir / call_letters
                station_dir.mkdir(parents=True, exist_ok=True)
                
                # Move file to station-specific directory  
                final_path = station_dir / filename
                self._move_file(file_path, final_path)
                
                # Convert to MP3 if needed
                if file_extension != '.mp3':
                    mp3_filename = filename.replace(file_extension, '.mp3')
                    mp3_path = station_dir / mp3_filename
                    
                    if self._convert_to_mp3(final_path, mp3_path):
                        # Remove original and use MP3
                        final_path.unlink()
                        final_path = mp3_path
                        filename = mp3_filename
                
                # Get final file info
                file_size = final_path.stat().st_size
                duration = metadata.get('duration', 0)
                
                # Use metadata for title if not provided
                if not title:
                    title = (metadata.get('title') or 
                            original_filename or 
                            f"Upload {datetime.now().strftime('%Y-%m-%d %H:%M')}")
                
                if not description and metadata.get('comment'):
                    description = metadata['comment']
                
                # Get next track number for playlists
                track_number = None
                if show.show_type == 'playlist':
                    from sqlalchemy import func
                    max_track = db.query(func.max(Recording.track_number)).filter(
                        Recording.show_id == show_id,
                        Recording.source_type == 'uploaded'
                    ).scalar()
                    track_number = (max_track or 0) + 1
                
                # Create recording entry with relative path from recordings directory
                relative_path = f"{call_letters}/{filename}"
                recording = Recording(
                    show_id=show_id,
                    filename=relative_path,
                    title=title,
                    description=description,
                    duration_seconds=int(duration),
                    file_size_bytes=file_size,
                    recorded_at=datetime.now(),
                    source_type='uploaded',
                    original_filename=original_filename,
                    track_number=track_number
                )
                
                db.add(recording)
                db.commit()
                db.refresh(recording)
                
                # Write MP3 metadata
                try:
                    from backend.services.mp3_metadata_service import MP3MetadataService
                    metadata_service = MP3MetadataService()
                    metadata_service.write_metadata_for_recording(recording.id)
                except Exception as e:
                    logger.warning(f"Failed to write MP3 metadata for upload {recording.id}: {e}")
                
                logger.info(f"Successfully uploaded file to show {show_id}: {filename}")
                
                return UploadResult(
                    success=True,
                    recording_id=recording.id,
                    filename=relative_path,
                    file_size=file_size,
                    duration=int(duration),
                    metadata=metadata
                )
                
            finally:
                db.close()
                
        except Exception as e:
            logger.error(f"Error uploading file: {e}")
            return UploadResult(success=False, error=str(e))
    
    def _validate_audio_file(self, file_path: str, max_size_mb: int) -> Dict[str, Any]:
        """Validate uploaded audio file"""
        try:
            file_path = Path(file_path)
            
            # Check file exists
            if not file_path.exists():
                return {'valid': False, 'error': 'File does not exist'}
            
            # Check file size
            file_size = file_path.stat().st_size
            max_size_bytes = max_size_mb * 1024 * 1024
            
            if file_size > max_size_bytes:
                return {'valid': False, 'error': f'File too large. Maximum size is {max_size_mb}MB'}
            
            if file_size < 1024:  # Less than 1KB
                return {'valid': False, 'error': 'File too small'}
            
            # Check file extension
            extension = file_path.suffix.lower()
            if extension not in self.supported_formats:
                supported = ', '.join(self.supported_formats.keys())
                return {'valid': False, 'error': f'Unsupported format. Supported: {supported}'}
            
            # Try to get basic audio info using ffprobe
            try:
                cmd = [
                    'ffprobe', '-v', 'quiet', '-print_format', 'json',
                    '-show_format', '-show_streams', str(file_path)
                ]
                result = subprocess.run(cmd, capture_output=True, text=True, timeout=30)
                
                if result.returncode != 0:
                    return {'valid': False, 'error': 'Invalid audio file format'}
                
                import json
                probe_data = json.loads(result.stdout)
                
                # Check if it has audio streams
                audio_streams = [s for s in probe_data.get('streams', []) if s.get('codec_type') == 'audio']
                if not audio_streams:
                    return {'valid': False, 'error': 'No audio streams found in file'}
                
            except (subprocess.TimeoutExpired, json.JSONDecodeError, FileNotFoundError):
                # ffprobe not available or timeout, allow upload
                logger.warning(f"Could not validate audio file {file_path} with ffprobe")
            
            return {'valid': True}
            
        except Exception as e:
            return {'valid': False, 'error': f'Validation error: {str(e)}'}
    
    def _extract_audio_metadata(self, file_path: str) -> Dict[str, Any]:
        """Extract metadata from audio file"""
        metadata = {}
        
        try:
            cmd = [
                'ffprobe', '-v', 'quiet', '-print_format', 'json',
                '-show_format', '-show_streams', str(file_path)
            ]
            result = subprocess.run(cmd, capture_output=True, text=True, timeout=30)
            
            if result.returncode == 0:
                import json
                probe_data = json.loads(result.stdout)
                
                # Extract format metadata
                format_info = probe_data.get('format', {})
                tags = format_info.get('tags', {})
                
                # Common tag variations
                metadata['title'] = (tags.get('title') or tags.get('TITLE') or 
                                   tags.get('Title'))
                metadata['artist'] = (tags.get('artist') or tags.get('ARTIST') or 
                                    tags.get('Artist'))
                metadata['album'] = (tags.get('album') or tags.get('ALBUM') or 
                                   tags.get('Album'))
                metadata['comment'] = (tags.get('comment') or tags.get('COMMENT') or 
                                     tags.get('Comment'))
                metadata['genre'] = (tags.get('genre') or tags.get('GENRE') or 
                                   tags.get('Genre'))
                
                # Duration
                duration_str = format_info.get('duration')
                if duration_str:
                    try:
                        metadata['duration'] = float(duration_str)
                    except ValueError:
                        pass
                
                # Bitrate
                bitrate_str = format_info.get('bit_rate')
                if bitrate_str:
                    try:
                        metadata['bitrate'] = int(bitrate_str)
                    except ValueError:
                        pass
                
        except Exception as e:
            logger.warning(f"Could not extract metadata from {file_path}: {e}")
        
        return metadata
    
    def _move_file(self, src_path: Path, dest_path: Path) -> None:
        """Safely move uploaded file"""
        import shutil
        
        # Ensure destination directory exists
        dest_path.parent.mkdir(parents=True, exist_ok=True)
        
        # Move file
        shutil.move(str(src_path), str(dest_path))
        
        # Set proper permissions
        os.chmod(dest_path, 0o644)
    
    def _convert_to_mp3(self, input_path: Path, output_path: Path) -> bool:
        """Convert audio file to MP3"""
        try:
            cmd = [
                'ffmpeg', '-i', str(input_path),
                '-acodec', 'libmp3lame', '-ab', '128k',
                '-y', str(output_path)
            ]
            
            result = subprocess.run(cmd, capture_output=True, text=True, timeout=300)
            
            if result.returncode == 0 and output_path.exists():
                logger.info(f"Successfully converted {input_path.name} to MP3")
                return True
            else:
                logger.error(f"Failed to convert {input_path.name}: {result.stderr}")
                return False
                
        except subprocess.TimeoutExpired:
            logger.error(f"Timeout converting {input_path.name} to MP3")
            return False
        except Exception as e:
            logger.error(f"Error converting {input_path.name}: {e}")
            return False
    
    def delete_upload(self, recording_id: int) -> bool:
        """Delete an uploaded recording"""
        try:
            db = SessionLocal()
            try:
                recording = db.query(Recording).filter(Recording.id == recording_id).first()
                if not recording:
                    return False
                
                if recording.source_type != 'uploaded':
                    logger.error(f"Attempted to delete non-uploaded recording {recording_id}")
                    return False
                
                # Delete file (handle both new and old path formats)
                file_path = self.recordings_dir / recording.filename
                if not file_path.exists():
                    # Try old format without subdirectory
                    filename_only = Path(recording.filename).name
                    file_path = self.recordings_dir / filename_only
                
                if file_path.exists():
                    file_path.unlink()
                
                # Delete database record
                db.delete(recording)
                db.commit()
                
                logger.info(f"Deleted uploaded recording {recording_id}: {recording.filename}")
                return True
                
            finally:
                db.close()
                
        except Exception as e:
            logger.error(f"Error deleting upload {recording_id}: {e}")
            return False


def create_playlist_show(station_id: int, name: str, description: str = None, 
                        host: str = None, max_file_size_mb: int = 100) -> Dict[str, Any]:
    """
    Create a new playlist-type show for uploads
    
    Args:
        station_id: Station ID to associate with
        name: Playlist name
        description: Optional description
        host: Optional host/creator name
        max_file_size_mb: Maximum upload size
        
    Returns:
        Dictionary with creation result
    """
    try:
        db = SessionLocal()
        try:
            # Check if station exists
            from backend.models.station import Station
            station = db.query(Station).filter(Station.id == station_id).first()
            if not station:
                return {'success': False, 'error': 'Station not found'}
            
            # Create playlist show
            show = Show(
                station_id=station_id,
                name=name,
                description=description,
                host=host,
                show_type='playlist',
                schedule_pattern=None,  # No schedule for playlists
                schedule_description=f"User upload playlist: {name}",
                retention_days=0,  # Never expire
                allow_uploads=True,
                max_file_size_mb=max_file_size_mb,
                active=True
            )
            
            db.add(show)
            db.commit()
            db.refresh(show)
            
            logger.info(f"Created playlist show '{name}' (ID: {show.id})")
            
            return {
                'success': True,
                'show_id': show.id,
                'show': {
                    'id': show.id,
                    'name': show.name,
                    'description': show.description,
                    'show_type': show.show_type,
                    'allow_uploads': show.allow_uploads,
                    'max_file_size_mb': show.max_file_size_mb
                }
            }
            
        finally:
            db.close()
            
    except Exception as e:
        logger.error(f"Error creating playlist show: {e}")
        return {'success': False, 'error': str(e)}


if __name__ == '__main__':
    import argparse
    
    parser = argparse.ArgumentParser(description='Audio Upload Service')
    parser.add_argument('--upload', help='Upload audio file')
    parser.add_argument('--show-id', type=int, help='Show ID to upload to')
    parser.add_argument('--title', help='Recording title')
    parser.add_argument('--description', help='Recording description')
    parser.add_argument('--create-playlist', help='Create new playlist show')
    parser.add_argument('--station-id', type=int, help='Station ID for new playlist')
    
    args = parser.parse_args()
    
    logging.basicConfig(level=logging.INFO)
    
    if args.upload and args.show_id:
        service = AudioUploadService()
        result = service.upload_file(
            args.upload, 
            args.show_id, 
            args.title, 
            args.description,
            Path(args.upload).name
        )
        
        if result.success:
            print(f"✅ Upload successful: {result.filename}")
            print(f"   Recording ID: {result.recording_id}")
            print(f"   File size: {result.file_size} bytes")
            print(f"   Duration: {result.duration} seconds")
        else:
            print(f"❌ Upload failed: {result.error}")
    
    elif args.create_playlist and args.station_id:
        result = create_playlist_show(
            args.station_id,
            args.create_playlist,
            "User created playlist"
        )
        
        if result['success']:
            print(f"✅ Playlist created: {result['show']['name']} (ID: {result['show_id']})")
        else:
            print(f"❌ Failed to create playlist: {result['error']}")
    
    else:
        parser.print_help()