#!/usr/bin/env python3
"""
"""
Reads and writes MP3 metadata (ID3 tags) to audio files.

This service uses the `mutagen` library to handle ID3 tags. It can write
metadata such as title, artist, album, and genre to MP3 files, and can also
be used to read existing metadata from files.

Key Variables:
- `file_path`: The path to the MP3 file.
- `metadata`: A dictionary containing the metadata to be written.

Inter-script Communication:
- This script is used by `recording_service.py` and `upload_service.py` to
  add metadata to audio files.
- It interacts with the `Recording` and `Show` models from `backend/models/station.py`.
"""

"""

import os
import sys
import logging
import subprocess
from pathlib import Path
from datetime import datetime
from typing import Dict, Optional, Any

# Add project root to path
sys.path.insert(0, '/opt/radiograb')

from backend.config.database import SessionLocal
from backend.models.station import Show, Recording, Station

logger = logging.getLogger(__name__)


class MP3MetadataService:
    """Service for writing MP3 metadata to audio files"""
    
    def __init__(self):
        self.recordings_dir = Path('/var/radiograb/recordings')
    
    def write_metadata_for_recording(self, recording_id: int) -> bool:
        """
        Write proper MP3 metadata for a recording
        
        Args:
            recording_id: Recording ID to update metadata for
            
        Returns:
            bool: True if successful, False otherwise
        """
        try:
            db = SessionLocal()
            try:
                # Get recording with show and station info
                recording = db.query(Recording).join(Show).join(Station).filter(
                    Recording.id == recording_id
                ).first()
                
                if not recording:
                    logger.error(f"Recording {recording_id} not found")
                    return False
                
                file_path = self.recordings_dir / recording.filename
                if not file_path.exists():
                    logger.error(f"Audio file not found: {file_path}")
                    return False
                
                # Prepare metadata
                metadata = self._build_metadata(recording)
                
                # Write metadata using ffmpeg
                return self._write_mp3_metadata(file_path, metadata)
                
            finally:
                db.close()
                
        except Exception as e:
            logger.error(f"Error writing metadata for recording {recording_id}: {e}")
            return False
    
    def write_metadata_for_recorded_show(self, show_id: int, filename: str, 
                                       recorded_at: datetime) -> bool:
        """
        Write metadata for a newly recorded show
        
        Args:
            show_id: Show ID
            filename: Recording filename
            recorded_at: Recording timestamp
            
        Returns:
            bool: True if successful
        """
        try:
            db = SessionLocal()
            try:
                show = db.query(Show).join(Station).filter(Show.id == show_id).first()
                if not show:
                    logger.error(f"Show {show_id} not found")
                    return False
                
                file_path = self.recordings_dir / filename
                if not file_path.exists():
                    logger.error(f"Audio file not found: {file_path}")
                    return False
                
                # Build metadata for recorded show
                metadata = {
                    'title': f"{show.name} - {recorded_at.strftime('%B %d, %Y')}",
                    'artist': show.name,
                    'album': show.station.name,
                    'albumartist': show.station.name,
                    'comment': show.description or f"Recorded from {show.station.name}",
                    'date': recorded_at.strftime('%Y-%m-%d'),
                    'genre': show.genre or 'Radio Show',
                    'track': None  # No track number for recorded shows
                }
                
                if show.host:
                    metadata['composer'] = show.host  # Use composer field for host
                
                return self._write_mp3_metadata(file_path, metadata)
                
            finally:
                db.close()
                
        except Exception as e:
            logger.error(f"Error writing metadata for recorded show {show_id}: {e}")
            return False
    
    def _build_metadata(self, recording: Recording) -> Dict[str, Any]:
        """Build metadata dictionary for a recording"""
        show = recording.show
        station = show.station
        
        # Base metadata
        metadata = {
            'artist': show.name,
            'album': station.name,
            'albumartist': station.name,
            'date': recording.recorded_at.strftime('%Y-%m-%d'),
            'genre': show.genre or 'Radio Show'
        }
        
        # Title handling
        if recording.title:
            metadata['title'] = recording.title
        elif recording.source_type == 'uploaded':
            metadata['title'] = recording.original_filename or f"Upload - {recording.recorded_at.strftime('%B %d, %Y')}"
        else:
            metadata['title'] = f"{show.name} - {recording.recorded_at.strftime('%B %d, %Y')}"
        
        # Description/comment
        if recording.description:
            metadata['comment'] = recording.description
        elif show.description:
            metadata['comment'] = show.description
        else:
            metadata['comment'] = f"From {station.name}"
        
        # Host information
        if show.host:
            metadata['composer'] = show.host
        
        # Track number for playlists
        if recording.source_type == 'uploaded' and recording.track_number:
            metadata['track'] = f"{recording.track_number}"
        
        # Additional fields for uploads
        if recording.source_type == 'uploaded':
            if show.show_type == 'playlist':
                metadata['album'] = f"{station.name} - {show.name}"
            
            # Add playlist info to comment
            if recording.track_number:
                track_info = f" (Track {recording.track_number})"
                metadata['comment'] = (metadata.get('comment', '') + track_info).strip()
        
        return metadata
    
    def _write_mp3_metadata(self, file_path: Path, metadata: Dict[str, Any]) -> bool:
        """
        Write metadata to MP3 file using ffmpeg
        
        Args:
            file_path: Path to MP3 file
            metadata: Metadata dictionary
            
        Returns:
            bool: True if successful
        """
        try:
            # Create temporary output file
            temp_path = file_path.with_suffix('.tmp.mp3')
            
            # Build ffmpeg command
            cmd = [
                'ffmpeg', '-i', str(file_path),
                '-c', 'copy',  # Copy audio without re-encoding
                '-y',  # Overwrite output file
            ]
            
            # Add metadata parameters
            for key, value in metadata.items():
                if value is not None:
                    # Map our keys to ffmpeg metadata keys
                    ffmpeg_key = self._map_metadata_key(key)
                    cmd.extend(['-metadata', f'{ffmpeg_key}={value}'])
            
            cmd.append(str(temp_path))
            
            # Execute ffmpeg
            result = subprocess.run(cmd, capture_output=True, text=True, timeout=60)
            
            if result.returncode == 0 and temp_path.exists():
                # Replace original file with updated version
                temp_path.replace(file_path)
                logger.info(f"Successfully updated metadata for {file_path.name}")
                return True
            else:
                logger.error(f"ffmpeg failed for {file_path.name}: {result.stderr}")
                if temp_path.exists():
                    temp_path.unlink()
                return False
                
        except subprocess.TimeoutExpired:
            logger.error(f"Timeout writing metadata for {file_path.name}")
            return False
        except Exception as e:
            logger.error(f"Error writing metadata for {file_path.name}: {e}")
            return False
    
    def _map_metadata_key(self, key: str) -> str:
        """Map our metadata keys to ffmpeg metadata keys"""
        mapping = {
            'title': 'title',
            'artist': 'artist', 
            'album': 'album',
            'albumartist': 'album_artist',
            'date': 'date',
            'genre': 'genre',
            'comment': 'comment',
            'composer': 'composer',
            'track': 'track'
        }
        return mapping.get(key, key)
    
    def update_all_recordings_metadata(self, show_id: int = None) -> int:
        """
        Update metadata for all recordings (or recordings for a specific show)
        
        Args:
            show_id: Optional show ID to limit updates to
            
        Returns:
            int: Number of recordings updated
        """
        try:
            db = SessionLocal()
            try:
                query = db.query(Recording)
                if show_id:
                    query = query.filter(Recording.show_id == show_id)
                
                recordings = query.all()
                updated_count = 0
                
                for recording in recordings:
                    if self.write_metadata_for_recording(recording.id):
                        updated_count += 1
                
                logger.info(f"Updated metadata for {updated_count}/{len(recordings)} recordings")
                return updated_count
                
            finally:
                db.close()
                
        except Exception as e:
            logger.error(f"Error updating recordings metadata: {e}")
            return 0


def main():
    """Command line interface for metadata service"""
    import argparse
    
    parser = argparse.ArgumentParser(description='MP3 Metadata Service')
    parser.add_argument('--recording-id', type=int, help='Update metadata for specific recording')
    parser.add_argument('--show-id', type=int, help='Update metadata for all recordings in show')
    parser.add_argument('--all', action='store_true', help='Update metadata for all recordings')
    parser.add_argument('--write-recorded', help='Write metadata for newly recorded file')
    parser.add_argument('--recorded-at', help='Recording timestamp (ISO format)')
    
    args = parser.parse_args()
    
    logging.basicConfig(level=logging.INFO)
    service = MP3MetadataService()
    
    if args.recording_id:
        success = service.write_metadata_for_recording(args.recording_id)
        print(f"✅ Metadata updated" if success else "❌ Failed to update metadata")
    
    elif args.show_id and args.all:
        count = service.update_all_recordings_metadata(args.show_id)
        print(f"✅ Updated metadata for {count} recordings")
    
    elif args.all:
        count = service.update_all_recordings_metadata()
        print(f"✅ Updated metadata for {count} recordings")
    
    elif args.write_recorded and args.show_id and args.recorded_at:
        recorded_at = datetime.fromisoformat(args.recorded_at.replace('Z', '+00:00'))
        success = service.write_metadata_for_recorded_show(
            args.show_id, args.write_recorded, recorded_at
        )
        print(f"✅ Metadata written" if success else "❌ Failed to write metadata")
    
    else:
        parser.print_help()


if __name__ == '__main__':
    main()