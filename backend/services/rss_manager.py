#!/usr/bin/env python3
"""
RSS Feed Manager
Handles RSS feed generation, updates, and management
"""

import os
import sys
import logging
from datetime import datetime
from typing import Dict, List, Optional

# Add the backend directory to the Python path
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from backend.services.rss_service import RSSManager
from backend.config.database import SessionLocal
from backend.models.station import Show


class RSSFeedManager:
    """High-level RSS feed management"""
    
    def __init__(self, base_url: str = "http://localhost"):
        self.rss_manager = RSSManager(base_url)
        self.logger = logging.getLogger(__name__)
        
    def update_feeds_for_new_recording(self, show_id: int) -> bool:
        """Update RSS feed when a new recording is added"""
        try:
            return self.rss_manager.update_show_feed(show_id)
        except Exception as e:
            self.logger.error(f"Error updating feed for new recording in show {show_id}: {e}")
            return False
    
    def update_feeds_for_show_change(self, show_id: int) -> bool:
        """Update RSS feed when show details change"""
        try:
            return self.rss_manager.update_show_feed(show_id)
        except Exception as e:
            self.logger.error(f"Error updating feed for show change {show_id}: {e}")
            return False
    
    def daily_feed_update(self) -> Dict[str, int]:
        """Daily RSS feed maintenance"""
        results = {'updated': 0, 'errors': 0}
        
        try:
            # Update all active show feeds
            feed_results = self.rss_manager.update_all_feeds()
            
            for show_id, success in feed_results.items():
                if success:
                    results['updated'] += 1
                else:
                    results['errors'] += 1
            
            # Cleanup orphaned feeds
            self.rss_manager.cleanup_orphaned_feeds()
            
            self.logger.info(f"Daily RSS update completed: {results['updated']} updated, {results['errors']} errors")
            
        except Exception as e:
            self.logger.error(f"Error in daily RSS feed update: {e}")
            results['errors'] += 1
            
        return results
    
    def get_feed_status(self) -> List[Dict]:
        """Get status of all RSS feeds"""
        try:
            db = SessionLocal()
            shows = db.query(Show).filter(Show.active == True).all()
            
            feed_status = []
            for show in shows:
                feeds_dir = os.environ.get('FEEDS_DIR', '/var/radiograb/feeds')
                feed_file = os.path.join(feeds_dir, f"{show.id}.xml")
                
                status = {
                    'show_id': show.id,
                    'show_name': show.name,
                    'feed_exists': os.path.exists(feed_file),
                    'feed_url': f"/api/feeds.php?show_id={show.id}",
                    'last_modified': None
                }
                
                if status['feed_exists']:
                    try:
                        stat = os.stat(feed_file)
                        status['last_modified'] = datetime.fromtimestamp(stat.st_mtime)
                    except OSError:
                        pass
                
                feed_status.append(status)
            
            db.close()
            return feed_status
            
        except Exception as e:
            self.logger.error(f"Error getting feed status: {e}")
            return []
    
    def validate_feeds(self) -> Dict[str, List]:
        """Validate all RSS feeds for errors"""
        validation_results = {
            'valid': [],
            'invalid': [],
            'missing': []
        }
        
        try:
            feeds_dir = os.environ.get('FEEDS_DIR', '/var/radiograb/feeds')
            
            for status in self.get_feed_status():
                show_id = status['show_id']
                feed_file = os.path.join(feeds_dir, f"{show_id}.xml")
                
                if not status['feed_exists']:
                    validation_results['missing'].append(status)
                    continue
                
                # Basic XML validation
                try:
                    with open(feed_file, 'r', encoding='utf-8') as f:
                        content = f.read()
                    
                    # Check for basic RSS structure
                    if '<rss' in content and '<channel>' in content and '</channel>' in content:
                        validation_results['valid'].append(status)
                    else:
                        validation_results['invalid'].append(status)
                        
                except Exception as e:
                    self.logger.error(f"Error validating feed {feed_file}: {e}")
                    validation_results['invalid'].append(status)
            
        except Exception as e:
            self.logger.error(f"Error in feed validation: {e}")
            
        return validation_results


def main():
    """Command line interface for RSS management"""
    import argparse
    
    parser = argparse.ArgumentParser(description='RadioGrab RSS Feed Manager')
    parser.add_argument('--base-url', default='http://localhost', 
                       help='Base URL for the RadioGrab installation')
    parser.add_argument('--action', choices=['update-all', 'update-show', 'update-master', 'validate', 'status'], 
                       default='update-all', help='Action to perform')
    parser.add_argument('--show-id', type=int, help='Show ID for show-specific actions')
    parser.add_argument('--verbose', '-v', action='store_true', help='Verbose logging')
    
    args = parser.parse_args()
    
    # Setup logging
    log_level = logging.DEBUG if args.verbose else logging.INFO
    logging.basicConfig(
        level=log_level,
        format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
    )
    
    manager = RSSFeedManager(args.base_url)
    
    if args.action == 'update-all':
        results = manager.daily_feed_update()
        print(f"RSS Update Results: {results['updated']} updated, {results['errors']} errors")
        
    elif args.action == 'update-show':
        if not args.show_id:
            print("Error: --show-id required for update-show action")
            sys.exit(1)
        
        success = manager.update_feeds_for_show_change(args.show_id)
        print(f"Show {args.show_id} feed update: {'Success' if success else 'Failed'}")
        
    elif args.action == 'update-master':
        success = manager.rss_manager.update_master_feed()
        print(f"Master feed update: {'Success' if success else 'Failed'}")
        
    elif args.action == 'validate':
        results = manager.validate_feeds()
        print(f"Feed Validation Results:")
        print(f"  Valid: {len(results['valid'])}")
        print(f"  Invalid: {len(results['invalid'])}")
        print(f"  Missing: {len(results['missing'])}")
        
        if args.verbose:
            for status in results['invalid']:
                print(f"    Invalid: {status['show_name']} (ID: {status['show_id']})")
            for status in results['missing']:
                print(f"    Missing: {status['show_name']} (ID: {status['show_id']})")
                
    elif args.action == 'status':
        status_list = manager.get_feed_status()
        print(f"RSS Feed Status ({len(status_list)} shows):")
        for status in status_list:
            exists = "✓" if status['feed_exists'] else "✗"
            modified = status['last_modified'].strftime('%Y-%m-%d %H:%M') if status['last_modified'] else 'Never'
            print(f"  {exists} {status['show_name']} (Last: {modified})")


if __name__ == "__main__":
    main()