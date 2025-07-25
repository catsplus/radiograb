#!/usr/bin/env python3
"""
RSS Feed Generation Service
Generates iTunes-compatible podcast RSS feeds for each show's recordings
"""

import os
import logging
from datetime import datetime, timezone
from typing import List, Dict, Optional
from xml.etree.ElementTree import Element, SubElement, tostring
from xml.dom import minidom
import requests
from urllib.parse import urljoin

from backend.config.database import SessionLocal
from backend.models.station import Station, Show, Recording


class RSSFeedGenerator:
    """Generates RSS feeds for show recordings"""
    
    def __init__(self, base_url: str = "http://localhost"):
        self.base_url = base_url.rstrip('/')
        self.logger = logging.getLogger(__name__)
        
    def generate_show_feed(self, show_id: int) -> Optional[str]:
        """Generate RSS feed for a specific show"""
        try:
            db = SessionLocal()
            
            # Get show with station and recordings
            show = db.query(Show).filter(Show.id == show_id).first()
            if not show:
                self.logger.error(f"Show with ID {show_id} not found")
                return None
                
            station = db.query(Station).filter(Station.id == show.station_id).first()
            recordings = (db.query(Recording)
                        .filter(Recording.show_id == show_id)
                        .order_by(Recording.recorded_at.desc())
                        .all())
            
            # Generate RSS XML
            rss_xml = self._build_rss_xml(show, station, recordings)
            
            # Save feed to file
            feed_path = self._save_feed_file(show_id, rss_xml)
            
            db.close()
            return feed_path
            
        except Exception as e:
            self.logger.error(f"Error generating RSS feed for show {show_id}: {e}")
            return None
    
    def generate_all_feeds(self) -> Dict[int, str]:
        """Generate RSS feeds for all active shows"""
        results = {}
        
        try:
            db = SessionLocal()
            shows = db.query(Show).filter(Show.active == True).all()
            
            for show in shows:
                feed_path = self.generate_show_feed(show.id)
                if feed_path:
                    results[show.id] = feed_path
                    
            db.close()
            
        except Exception as e:
            self.logger.error(f"Error generating all RSS feeds: {e}")
            
        return results
    
    def _build_rss_xml(self, show: Show, station: Station, recordings: List[Recording]) -> str:
        """Build RSS XML for a show"""
        
        # Create RSS root
        rss = Element('rss', version='2.0')
        rss.set('xmlns:itunes', 'http://www.itunes.com/dtds/podcast-1.0.dtd')
        rss.set('xmlns:content', 'http://purl.org/rss/1.0/modules/content/')
        rss.set('xmlns:atom', 'http://www.w3.org/2005/Atom')
        
        # Channel element
        channel = SubElement(rss, 'channel')
        
        # Basic channel info
        self._add_text_element(channel, 'title', f"{show.name} - {station.name}")
        self._add_text_element(channel, 'description', 
                              show.description or f"Recordings of {show.name} from {station.name}")
        self._add_text_element(channel, 'link', f"{self.base_url}/shows/{show.id}")
        self._add_text_element(channel, 'language', 'en-us')
        self._add_text_element(channel, 'copyright', f"© {station.name}")
        self._add_text_element(channel, 'generator', 'RadioGrab - Radio TiVo')
        self._add_text_element(channel, 'lastBuildDate', 
                              self._format_rfc2822_date(datetime.now(timezone.utc)))
        
        # iTunes specific tags
        self._add_text_element(channel, 'itunes:author', station.name)
        self._add_text_element(channel, 'itunes:summary', 
                              show.description or f"Recordings of {show.name}")
        self._add_text_element(channel, 'itunes:explicit', 'no')
        
        # iTunes category
        category = SubElement(channel, 'itunes:category', text='Arts')
        
        # Station logo as podcast artwork
        if station.logo_url:
            self._add_image_element(channel, station.logo_url, 
                                  f"{show.name} - {station.name}", 
                                  f"{self.base_url}/shows/{show.id}")
            
            itunes_image = SubElement(channel, 'itunes:image')
            itunes_image.set('href', station.logo_url)
        
        # Atom self link
        atom_link = SubElement(channel, 'atom:link')
        atom_link.set('href', f"{self.base_url}/feeds/{show.id}.xml")
        atom_link.set('rel', 'self')
        atom_link.set('type', 'application/rss+xml')
        
        # Add recording items
        for recording in recordings:
            self._add_recording_item(channel, recording, show, station)
        
        # Convert to formatted XML string
        rough_string = tostring(rss, 'utf-8')
        reparsed = minidom.parseString(rough_string)
        return reparsed.toprettyxml(indent="  ", encoding='utf-8').decode('utf-8')
    
    def _add_recording_item(self, channel: Element, recording: Recording, 
                          show: Show, station: Station):
        """Add a recording as an RSS item"""
        
        item = SubElement(channel, 'item')
        
        # Basic item info
        title = recording.title or f"{show.name} - {recording.recorded_at.strftime('%Y-%m-%d')}"
        self._add_text_element(item, 'title', title)
        
        description = (recording.description or 
                      f"Recording of {show.name} from {station.name} on {recording.recorded_at.strftime('%B %d, %Y')}")
        self._add_text_element(item, 'description', description)
        
        # Unique identifier
        guid = SubElement(item, 'guid', isPermaLink='false')
        guid.text = f"radiograb-recording-{recording.id}"
        
        # Publication date
        self._add_text_element(item, 'pubDate', 
                              self._format_rfc2822_date(recording.recorded_at))
        
        # Audio enclosure
        if recording.filename and self._recording_file_exists(recording.filename):
            file_url = f"{self.base_url}/recordings/{recording.filename}"
            file_size = recording.file_size_bytes or 0
            
            enclosure = SubElement(item, 'enclosure')
            enclosure.set('url', file_url)
            enclosure.set('length', str(file_size))
            enclosure.set('type', self._get_mime_type(recording.filename))
            
            # iTunes duration
            if recording.duration_seconds:
                duration = self._format_duration(recording.duration_seconds)
                self._add_text_element(item, 'itunes:duration', duration)
        
        # iTunes specific tags
        self._add_text_element(item, 'itunes:author', station.name)
        self._add_text_element(item, 'itunes:summary', description)
        self._add_text_element(item, 'itunes:explicit', 'no')
    
    def _add_text_element(self, parent: Element, tag: str, text: str):
        """Add a text element to parent"""
        element = SubElement(parent, tag)
        element.text = text
        return element
    
    def _add_image_element(self, channel: Element, url: str, title: str, link: str):
        """Add image element for RSS"""
        image = SubElement(channel, 'image')
        self._add_text_element(image, 'url', url)
        self._add_text_element(image, 'title', title)
        self._add_text_element(image, 'link', link)
    
    def _format_rfc2822_date(self, dt: datetime) -> str:
        """Format datetime for RSS (RFC 2822)"""
        return dt.strftime('%a, %d %b %Y %H:%M:%S %z')
    
    def _format_duration(self, seconds: int) -> str:
        """Format duration for iTunes (HH:MM:SS)"""
        hours = seconds // 3600
        minutes = (seconds % 3600) // 60
        secs = seconds % 60
        return f"{hours:02d}:{minutes:02d}:{secs:02d}"
    
    def _get_mime_type(self, filename: str) -> str:
        """Get MIME type for audio file"""
        ext = os.path.splitext(filename.lower())[1]
        mime_types = {
            '.mp3': 'audio/mpeg',
            '.m4a': 'audio/mp4',
            '.wav': 'audio/wav',
            '.ogg': 'audio/ogg',
            '.flac': 'audio/flac'
        }
        return mime_types.get(ext, 'audio/mpeg')
    
    def _recording_file_exists(self, filename: str) -> bool:
        """Check if recording file exists"""
        recordings_dir = os.environ.get('RECORDINGS_DIR', '/var/radiograb/recordings')
        file_path = os.path.join(recordings_dir, filename)
        return os.path.isfile(file_path)
    
    def _save_feed_file(self, show_id: int, rss_xml: str) -> str:
        """Save RSS feed to file"""
        feeds_dir = os.environ.get('FEEDS_DIR', '/var/radiograb/feeds')
        os.makedirs(feeds_dir, exist_ok=True)
        
        feed_path = os.path.join(feeds_dir, f"{show_id}.xml")
        
        with open(feed_path, 'w', encoding='utf-8') as f:
            f.write(rss_xml)
        
        self.logger.info(f"RSS feed saved: {feed_path}")
        return feed_path
    
    def get_feed_url(self, show_id: int) -> str:
        """Get public URL for a show's RSS feed"""
        return f"{self.base_url}/feeds/{show_id}.xml"
    
    def generate_master_feed(self, max_items: int = 100) -> Optional[str]:
        """Generate master RSS feed combining all shows"""
        try:
            db = SessionLocal()
            
            # Get all active shows with their recordings
            recordings = (db.query(Recording, Show, Station)
                        .join(Show, Recording.show_id == Show.id)
                        .join(Station, Show.station_id == Station.id)
                        .filter(Show.active == True)
                        .order_by(Recording.recorded_at.desc())
                        .limit(max_items)
                        .all())
            
            if not recordings:
                self.logger.warning("No recordings found for master feed")
                return None
            
            # Generate RSS XML for master feed
            rss_xml = self._build_master_rss_xml(recordings)
            
            # Save master feed to file
            feed_path = self._save_master_feed_file(rss_xml)
            
            db.close()
            return feed_path
            
        except Exception as e:
            self.logger.error(f"Error generating master RSS feed: {e}")
            return None
    
    def _build_master_rss_xml(self, recordings: List) -> str:
        """Build master RSS XML combining all shows"""
        
        # Create RSS root
        rss = Element('rss', version='2.0')
        rss.set('xmlns:itunes', 'http://www.itunes.com/dtds/podcast-1.0.dtd')
        rss.set('xmlns:content', 'http://purl.org/rss/1.0/modules/content/')
        rss.set('xmlns:atom', 'http://www.w3.org/2005/Atom')
        
        # Channel element
        channel = SubElement(rss, 'channel')
        
        # Basic channel info
        self._add_text_element(channel, 'title', 'RadioGrab - All Shows Master Feed')
        self._add_text_element(channel, 'description', 
                              'Combined RSS feed of all recorded radio shows from RadioGrab')
        self._add_text_element(channel, 'link', f"{self.base_url}/")
        self._add_text_element(channel, 'language', 'en-us')
        self._add_text_element(channel, 'copyright', '© RadioGrab')
        self._add_text_element(channel, 'generator', 'RadioGrab - Radio TiVo')
        self._add_text_element(channel, 'lastBuildDate', 
                              self._format_rfc2822_date(datetime.now(timezone.utc)))
        
        # iTunes specific tags
        self._add_text_element(channel, 'itunes:author', 'RadioGrab')
        self._add_text_element(channel, 'itunes:summary', 
                              'Combined feed of all recorded radio shows')
        self._add_text_element(channel, 'itunes:explicit', 'no')
        
        # iTunes category
        category = SubElement(channel, 'itunes:category', text='Arts')
        
        # Atom self link
        atom_link = SubElement(channel, 'atom:link')
        atom_link.set('href', f"{self.base_url}/feeds/master.xml")
        atom_link.set('rel', 'self')
        atom_link.set('type', 'application/rss+xml')
        
        # Add recording items
        for recording, show, station in recordings:
            self._add_master_recording_item(channel, recording, show, station)
        
        # Convert to formatted XML string
        rough_string = tostring(rss, 'utf-8')
        reparsed = minidom.parseString(rough_string)
        return reparsed.toprettyxml(indent="  ", encoding='utf-8').decode('utf-8')
    
    def _add_master_recording_item(self, channel: Element, recording: Recording, 
                                 show: Show, station: Station):
        """Add a recording as an RSS item for master feed"""
        
        item = SubElement(channel, 'item')
        
        # Basic item info - include show and station name in title
        title = f"{show.name} - {station.name}"
        if recording.title:
            title = f"{recording.title} ({show.name} - {station.name})"
        else:
            title = f"{show.name} - {station.name} - {recording.recorded_at.strftime('%Y-%m-%d')}"
        
        self._add_text_element(item, 'title', title)
        
        description = (recording.description or 
                      f"Recording of {show.name} from {station.name} on {recording.recorded_at.strftime('%B %d, %Y')}")
        self._add_text_element(item, 'description', description)
        
        # Unique identifier
        guid = SubElement(item, 'guid', isPermaLink='false')
        guid.text = f"radiograb-master-recording-{recording.id}"
        
        # Publication date
        self._add_text_element(item, 'pubDate', 
                              self._format_rfc2822_date(recording.recorded_at))
        
        # Audio enclosure
        if recording.filename and self._recording_file_exists(recording.filename):
            file_url = f"{self.base_url}/recordings/{recording.filename}"
            file_size = recording.file_size_bytes or 0
            
            enclosure = SubElement(item, 'enclosure')
            enclosure.set('url', file_url)
            enclosure.set('length', str(file_size))
            enclosure.set('type', self._get_mime_type(recording.filename))
            
            # iTunes duration
            if recording.duration_seconds:
                duration = self._format_duration(recording.duration_seconds)
                self._add_text_element(item, 'itunes:duration', duration)
        
        # iTunes specific tags
        self._add_text_element(item, 'itunes:author', f"{show.name} - {station.name}")
        self._add_text_element(item, 'itunes:summary', description)
        self._add_text_element(item, 'itunes:explicit', 'no')
        
        # Add category/show info
        category = SubElement(item, 'category')
        category.text = f"{station.name} - {show.name}"
    
    def _save_master_feed_file(self, rss_xml: str) -> str:
        """Save master RSS feed to file"""
        feeds_dir = os.environ.get('FEEDS_DIR', '/var/radiograb/feeds')
        os.makedirs(feeds_dir, exist_ok=True)
        
        feed_path = os.path.join(feeds_dir, "master.xml")
        
        with open(feed_path, 'w', encoding='utf-8') as f:
            f.write(rss_xml)
        
        self.logger.info(f"Master RSS feed saved: {feed_path}")
        return feed_path


class RSSManager:
    """Manages RSS feed generation and updates"""
    
    def __init__(self, base_url: str = "http://localhost"):
        self.generator = RSSFeedGenerator(base_url)
        self.logger = logging.getLogger(__name__)
    
    def update_show_feed(self, show_id: int) -> bool:
        """Update RSS feed for a specific show"""
        try:
            feed_path = self.generator.generate_show_feed(show_id)
            if feed_path:
                self.logger.info(f"Updated RSS feed for show {show_id}")
                return True
            return False
        except Exception as e:
            self.logger.error(f"Failed to update RSS feed for show {show_id}: {e}")
            return False
    
    def update_all_feeds(self) -> Dict[str, bool]:
        """Update RSS feeds for all shows"""
        results = {}
        try:
            feeds = self.generator.generate_all_feeds()
            for show_id, feed_path in feeds.items():
                results[str(show_id)] = bool(feed_path)
            
            # Also update master feed
            master_feed_path = self.generator.generate_master_feed()
            results['master'] = bool(master_feed_path)
                
            self.logger.info(f"Updated {len(feeds)} RSS feeds + master feed")
            
        except Exception as e:
            self.logger.error(f"Error updating all RSS feeds: {e}")
            
        return results
    
    def update_master_feed(self) -> bool:
        """Update master RSS feed only"""
        try:
            feed_path = self.generator.generate_master_feed()
            if feed_path:
                self.logger.info("Updated master RSS feed")
                return True
            return False
        except Exception as e:
            self.logger.error(f"Failed to update master RSS feed: {e}")
            return False
    
    def cleanup_orphaned_feeds(self):
        """Remove RSS feeds for deleted shows"""
        try:
            feeds_dir = os.environ.get('FEEDS_DIR', '/var/radiograb/feeds')
            if not os.path.exists(feeds_dir):
                return
            
            db = SessionLocal()
            active_show_ids = {str(show.id) for show in db.query(Show).all()}
            
            # Find feed files
            for filename in os.listdir(feeds_dir):
                if filename.endswith('.xml'):
                    show_id = filename[:-4]  # Remove .xml extension
                    if show_id not in active_show_ids:
                        feed_path = os.path.join(feeds_dir, filename)
                        os.remove(feed_path)
                        self.logger.info(f"Removed orphaned feed: {filename}")
            
            db.close()
            
        except Exception as e:
            self.logger.error(f"Error cleaning up orphaned feeds: {e}")


if __name__ == "__main__":
    # Example usage
    import sys
    
    logging.basicConfig(level=logging.INFO)
    
    manager = RSSManager("https://your-radiograb-domain.com")
    
    if len(sys.argv) > 1:
        show_id = int(sys.argv[1])
        success = manager.update_show_feed(show_id)
        print(f"Feed update for show {show_id}: {'Success' if success else 'Failed'}")
    else:
        results = manager.update_all_feeds()
        print(f"Updated feeds: {len([r for r in results.values() if r])}/{len(results)}")