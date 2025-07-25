#!/usr/bin/env python3
"""
Feed Updater Service
Automatically updates RSS feeds when recordings are added or shows are modified
"""

import os
import sys
import logging
from datetime import datetime
from typing import Optional

# Add the backend directory to the Python path
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from backend.services.rss_manager import RSSFeedManager
from backend.config.database import SessionLocal
from backend.models.station import Recording, Show


class FeedUpdater:
    """Service to automatically update RSS feeds"""
    
    def __init__(self, base_url: str = None):
        # Get base URL from environment or use default
        self.base_url = base_url or os.environ.get('RADIOGRAB_BASE_URL', 'http://localhost')
        self.rss_manager = RSSFeedManager(self.base_url)
        self.logger = logging.getLogger(__name__)
        
    def on_recording_added(self, recording_id: int) -> bool:
        """Called when a new recording is added"""
        try:
            db = SessionLocal()
            recording = db.query(Recording).filter(Recording.id == recording_id).first()
            
            if not recording:
                self.logger.error(f"Recording {recording_id} not found")
                return False
            
            show_id = recording.show_id
            self.logger.info(f"Updating RSS feed for show {show_id} after new recording {recording_id}")
            
            success = self.rss_manager.update_feeds_for_new_recording(show_id)
            db.close()
            
            return success
            
        except Exception as e:
            self.logger.error(f"Error updating feed for new recording {recording_id}: {e}")
            return False
    
    def on_show_updated(self, show_id: int) -> bool:
        """Called when show information is updated"""
        try:
            self.logger.info(f"Updating RSS feed for show {show_id} after show update")
            return self.rss_manager.update_feeds_for_show_change(show_id)
            
        except Exception as e:
            self.logger.error(f"Error updating feed for show update {show_id}: {e}")
            return False
    
    def on_recording_deleted(self, show_id: int) -> bool:
        """Called when a recording is deleted"""
        try:
            self.logger.info(f"Updating RSS feed for show {show_id} after recording deletion")
            return self.rss_manager.update_feeds_for_show_change(show_id)
            
        except Exception as e:
            self.logger.error(f"Error updating feed for recording deletion in show {show_id}: {e}")
            return False
    
    def update_all_feeds(self) -> dict:
        """Update all RSS feeds (for scheduled maintenance)"""
        try:
            self.logger.info("Starting scheduled RSS feed update")
            return self.rss_manager.daily_feed_update()
            
        except Exception as e:
            self.logger.error(f"Error in scheduled RSS feed update: {e}")
            return {'updated': 0, 'errors': 1}


# Global feed updater instance
_feed_updater = None

def get_feed_updater() -> FeedUpdater:
    """Get the global feed updater instance"""
    global _feed_updater
    if _feed_updater is None:
        _feed_updater = FeedUpdater()
    return _feed_updater


def trigger_feed_update_for_recording(recording_id: int) -> bool:
    """Convenience function to trigger feed update for a new recording"""
    updater = get_feed_updater()
    return updater.on_recording_added(recording_id)


def trigger_feed_update_for_show(show_id: int) -> bool:
    """Convenience function to trigger feed update for show changes"""
    updater = get_feed_updater()
    return updater.on_show_updated(show_id)


if __name__ == "__main__":
    # Command line usage
    import argparse
    
    parser = argparse.ArgumentParser(description='RadioGrab Feed Updater')
    parser.add_argument('--recording-id', type=int, help='Update feed for new recording')
    parser.add_argument('--show-id', type=int, help='Update feed for show changes')
    parser.add_argument('--update-all', action='store_true', help='Update all feeds')
    parser.add_argument('--base-url', help='Base URL for RSS feeds')
    parser.add_argument('--verbose', '-v', action='store_true', help='Verbose logging')
    
    args = parser.parse_args()
    
    # Setup logging
    log_level = logging.DEBUG if args.verbose else logging.INFO
    logging.basicConfig(
        level=log_level,
        format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
    )
    
    updater = FeedUpdater(args.base_url)
    
    if args.recording_id:
        success = updater.on_recording_added(args.recording_id)
        print(f"Feed update for recording {args.recording_id}: {'Success' if success else 'Failed'}")
        
    elif args.show_id:
        success = updater.on_show_updated(args.show_id)
        print(f"Feed update for show {args.show_id}: {'Success' if success else 'Failed'}")
        
    elif args.update_all:
        results = updater.update_all_feeds()
        print(f"All feeds update: {results['updated']} updated, {results['errors']} errors")
        
    else:
        parser.print_help()