#!/usr/bin/env python3
"""
Content Categorization Service
Issue #27 - Streaming vs Download Mode

Automatically categorizes shows as talk, music, or mixed content based on:
- Show names, descriptions, and keywords
- Station genres and metadata
- Syndicated show detection
- Configurable rules from database
"""

import os
import sys
import re
import logging
from typing import Dict, List, Optional, Tuple
from datetime import datetime

# Add project root to path
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), '../../')))

from backend.models.database import Database
from backend.models.show import Show
from backend.models.station import Station

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

class ContentCategorizationService:
    """Service for automatically categorizing radio show content types"""
    
    def __init__(self, db_connection=None):
        """Initialize the categorization service"""
        self.db = db_connection or Database().get_connection()
        self.rules = self._load_categorization_rules()
        
    def _load_categorization_rules(self) -> List[Dict]:
        """Load categorization rules from database"""
        try:
            cursor = self.db.cursor(dictionary=True)
            cursor.execute("""
                SELECT * FROM content_categorization_rules 
                WHERE is_active = 1 
                ORDER BY priority ASC, confidence_score DESC
            """)
            rules = cursor.fetchall()
            logger.info(f"Loaded {len(rules)} categorization rules")
            return rules
        except Exception as e:
            logger.error(f"Failed to load categorization rules: {e}")
            return []
    
    def categorize_show(self, show_id: int, force_recategorize: bool = False) -> Optional[str]:
        """
        Categorize a single show
        
        Args:
            show_id: Database ID of the show
            force_recategorize: Whether to recategorize even if already categorized
            
        Returns:
            Content type ('talk', 'music', 'mixed') or None if categorization failed
        """
        try:
            # Get show details
            cursor = self.db.cursor(dictionary=True)
            cursor.execute("""
                SELECT s.*, st.name as station_name, st.genre as station_genre,
                       st.content_type as station_content_type
                FROM shows s
                JOIN stations st ON s.station_id = st.id
                WHERE s.id = %s
            """, (show_id,))
            
            show = cursor.fetchone()
            if not show:
                logger.warning(f"Show {show_id} not found")
                return None
                
            # Skip if already categorized and not forcing
            if show['content_type'] != 'unknown' and not force_recategorize:
                logger.info(f"Show {show_id} already categorized as {show['content_type']}")
                return show['content_type']
            
            # Perform categorization
            content_type, confidence, matched_rules = self._analyze_show_content(show)
            
            if content_type:
                # Update the show with categorization results
                cursor.execute("""
                    UPDATE shows 
                    SET content_type = %s, auto_categorized = 1
                    WHERE id = %s
                """, (content_type, show_id))
                
                # Check if syndicated show (affects streaming mode)
                is_syndicated = self._detect_syndicated_show(show)
                if is_syndicated:
                    cursor.execute("""
                        UPDATE shows 
                        SET is_syndicated = 1, stream_only = 1
                        WHERE id = %s
                    """, (show_id,))
                
                self.db.commit()
                logger.info(f"Categorized show {show_id} ({show['name']}) as {content_type} "
                           f"(confidence: {confidence:.2f}, syndicated: {is_syndicated})")
                
                return content_type
            else:
                logger.warning(f"Could not categorize show {show_id} ({show['name']})")
                return None
                
        except Exception as e:
            logger.error(f"Error categorizing show {show_id}: {e}")
            return None
    
    def _analyze_show_content(self, show: Dict) -> Tuple[Optional[str], float, List[str]]:
        """
        Analyze show content using various signals
        
        Returns:
            Tuple of (content_type, confidence_score, matched_rules)
        """
        text_to_analyze = f"{show['name']} {show.get('description', '')} {show.get('genre', '')} {show.get('station_genre', '')}"
        text_to_analyze = text_to_analyze.lower()
        
        matched_rules = []
        scores = {'talk': 0, 'music': 0, 'mixed': 0}
        
        # Apply categorization rules
        for rule in self.rules:
            rule_type = rule['rule_type']
            rule_value = rule['rule_value'].lower()
            target_type = rule['target_content_type']
            confidence = float(rule['confidence_score'])
            
            matched = False
            
            if rule_type == 'keyword':
                if rule_value in text_to_analyze:
                    matched = True
            elif rule_type == 'pattern':
                try:
                    if re.search(rule_value, text_to_analyze, re.IGNORECASE):
                        matched = True
                except re.error:
                    logger.warning(f"Invalid regex pattern: {rule_value}")
            elif rule_type == 'genre':
                if rule_value in show.get('station_genre', '').lower():
                    matched = True
            elif rule_type == 'station':
                if rule_value in show.get('station_name', '').lower():
                    matched = True
            
            if matched:
                scores[target_type] += confidence
                matched_rules.append(f"{rule_type}:{rule_value}")
        
        # Determine final categorization
        if not any(scores.values()):
            return None, 0.0, matched_rules
        
        best_type = max(scores, key=scores.get)
        best_score = scores[best_type]
        
        # Require minimum confidence threshold
        if best_score < 0.5:
            return None, best_score, matched_rules
            
        return best_type, best_score, matched_rules
    
    def _detect_syndicated_show(self, show: Dict) -> bool:
        """
        Detect if a show is syndicated (usually requires stream-only mode)
        """
        syndicated_indicators = [
            'npr', 'national public radio', 'pbs', 'public radio',
            'this american life', 'fresh air', 'all things considered',
            'marketplace', 'planet money', 'ted radio hour',
            'bbc', 'cbc', 'syndicated'
        ]
        
        text_to_check = f"{show['name']} {show.get('description', '')}".lower()
        
        for indicator in syndicated_indicators:
            if indicator in text_to_check:
                return True
        
        return False
    
    def categorize_all_shows(self, station_id: Optional[int] = None, 
                           force_recategorize: bool = False) -> Dict[str, int]:
        """
        Categorize all shows or shows from a specific station
        
        Args:
            station_id: Optional station ID to limit categorization
            force_recategorize: Whether to recategorize already categorized shows
            
        Returns:
            Dictionary with categorization statistics
        """
        try:
            cursor = self.db.cursor()
            
            # Build query
            base_query = "SELECT id FROM shows WHERE 1=1"
            params = []
            
            if station_id:
                base_query += " AND station_id = %s"
                params.append(station_id)
            
            if not force_recategorize:
                base_query += " AND content_type = 'unknown'"
            
            cursor.execute(base_query, params)
            show_ids = [row[0] for row in cursor.fetchall()]
            
            logger.info(f"Starting categorization of {len(show_ids)} shows")
            
            stats = {'talk': 0, 'music': 0, 'mixed': 0, 'failed': 0}
            
            for show_id in show_ids:
                result = self.categorize_show(show_id, force_recategorize)
                if result:
                    stats[result] += 1
                else:
                    stats['failed'] += 1
            
            logger.info(f"Categorization complete: {stats}")
            return stats
            
        except Exception as e:
            logger.error(f"Error in bulk categorization: {e}")
            return {'failed': -1}
    
    def update_streaming_modes(self, station_id: Optional[int] = None):
        """
        Update streaming modes based on content types and syndication
        """
        try:
            cursor = self.db.cursor(dictionary=True)
            
            # Get shows to update
            base_query = """
                SELECT id, content_type, is_syndicated, stream_mode 
                FROM shows WHERE 1=1
            """
            params = []
            
            if station_id:
                base_query += " AND station_id = %s"
                params.append(station_id)
            
            cursor.execute(base_query, params)
            shows = cursor.fetchall()
            
            updated_count = 0
            
            for show in shows:
                should_stream_only = False
                
                # Syndicated shows should be stream-only
                if show['is_syndicated']:
                    should_stream_only = True
                
                # Music shows might need stream-only for DMCA compliance
                elif show['content_type'] == 'music':
                    # This could be configurable per station
                    should_stream_only = False  # Default to allow downloads for now
                
                # Update if needed
                current_stream_only = show.get('stream_only', False)
                if should_stream_only != current_stream_only:
                    cursor.execute("""
                        UPDATE shows 
                        SET stream_only = %s
                        WHERE id = %s
                    """, (should_stream_only, show['id']))
                    updated_count += 1
            
            self.db.commit()
            logger.info(f"Updated streaming modes for {updated_count} shows")
            
        except Exception as e:
            logger.error(f"Error updating streaming modes: {e}")

def main():
    """Command line interface"""
    import argparse
    
    parser = argparse.ArgumentParser(description='Content Categorization Service')
    parser.add_argument('--show-id', type=int, help='Categorize specific show')
    parser.add_argument('--station-id', type=int, help='Categorize shows from specific station')
    parser.add_argument('--all', action='store_true', help='Categorize all shows')
    parser.add_argument('--force', action='store_true', help='Force recategorization')
    parser.add_argument('--update-streaming', action='store_true', help='Update streaming modes')
    
    args = parser.parse_args()
    
    service = ContentCategorizationService()
    
    if args.show_id:
        result = service.categorize_show(args.show_id, args.force)
        print(f"Show {args.show_id} categorized as: {result}")
    elif args.all or args.station_id:
        stats = service.categorize_all_shows(args.station_id, args.force)
        print(f"Categorization results: {stats}")
    
    if args.update_streaming:
        service.update_streaming_modes(args.station_id)
        print("Updated streaming modes based on content types")

if __name__ == '__main__':
    main()