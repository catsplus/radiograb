"""
Show Schedules Manager
Manages multiple schedule patterns for shows (original + repeat airings)
"""
import logging
from typing import List, Dict, Optional, Any
from datetime import datetime
import os
import sys

# Add project root to path
sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))

from backend.database.database_manager import DatabaseManager
from backend.services.multiple_airings_parser import MultipleAiringsParser

logger = logging.getLogger(__name__)

class ShowSchedulesManager:
    """Manager for handling multiple show schedules and airings"""
    
    def __init__(self, db_manager: DatabaseManager = None):
        self.db = db_manager or DatabaseManager()
        self.parser = MultipleAiringsParser()
    
    def add_multiple_schedules(self, show_id: int, schedule_text: str) -> Dict[str, Any]:
        """
        Parse and add multiple schedules for a show
        
        Args:
            show_id: Show ID
            schedule_text: Schedule text that may contain multiple airings
            
        Returns:
            Result dictionary with success status and details
        """
        result = {
            'success': False,
            'schedules_added': 0,
            'schedules': [],
            'error': None
        }
        
        try:
            # Parse the schedule text for multiple airings
            parse_result = self.parser.parse_multiple_airings(schedule_text)
            
            if not parse_result['success']:
                result['error'] = parse_result.get('error', 'Failed to parse schedule')
                return result
            
            # Clear existing schedules for this show
            self.clear_show_schedules(show_id)
            
            # Add each schedule
            schedules_added = []
            for airing in parse_result['airings']:
                schedule_id = self._add_single_schedule(
                    show_id=show_id,
                    schedule_pattern=airing['schedule_pattern'],
                    schedule_description=airing['schedule_description'],
                    airing_type=airing['airing_type'],
                    priority=airing['priority']
                )
                
                if schedule_id:
                    schedules_added.append({
                        'id': schedule_id,
                        'pattern': airing['schedule_pattern'],
                        'description': airing['schedule_description'],
                        'type': airing['airing_type'],
                        'priority': airing['priority']
                    })
            
            # Update the show to indicate it uses multiple schedules
            if len(schedules_added) > 1:
                self._update_show_multiple_flag(show_id, True)
            
            result.update({
                'success': True,
                'schedules_added': len(schedules_added),
                'schedules': schedules_added,
                'has_multiple': parse_result['has_multiple']
            })
            
            logger.info(f"Added {len(schedules_added)} schedules for show {show_id}")
            
        except Exception as e:
            result['error'] = f"Database error: {str(e)}"
            logger.error(f"Error adding multiple schedules for show {show_id}: {e}")
        
        return result
    
    def get_show_schedules(self, show_id: int) -> List[Dict]:
        """Get all schedules for a show"""
        try:
            schedules = self.db.fetch_all("""
                SELECT id, schedule_pattern, schedule_description, 
                       airing_type, priority, active
                FROM show_schedules 
                WHERE show_id = ? AND active = 1
                ORDER BY priority ASC, id ASC
            """, [show_id])
            
            return schedules or []
            
        except Exception as e:
            logger.error(f"Error fetching schedules for show {show_id}: {e}")
            return []
    
    def get_all_active_schedules(self) -> List[Dict]:
        """Get all active schedules with show information"""
        try:
            schedules = self.db.fetch_all("""
                SELECT ss.id, ss.show_id, ss.schedule_pattern, ss.schedule_description,
                       ss.airing_type, ss.priority, ss.active,
                       s.name as show_name, s.duration_minutes,
                       st.name as station_name, st.call_letters
                FROM show_schedules ss
                JOIN shows s ON ss.show_id = s.id
                JOIN stations st ON s.station_id = st.id
                WHERE ss.active = 1 AND s.active = 1
                ORDER BY s.name, ss.priority ASC
            """)
            
            return schedules or []
            
        except Exception as e:
            logger.error(f"Error fetching all active schedules: {e}")
            return []
    
    def clear_show_schedules(self, show_id: int) -> bool:
        """Clear all schedules for a show"""
        try:
            self.db.execute(
                "DELETE FROM show_schedules WHERE show_id = ?",
                [show_id]
            )
            
            # Update the show multiple flag
            self._update_show_multiple_flag(show_id, False)
            
            return True
            
        except Exception as e:
            logger.error(f"Error clearing schedules for show {show_id}: {e}")
            return False
    
    def update_schedule(self, schedule_id: int, **kwargs) -> bool:
        """Update a specific schedule"""
        try:
            # Build update query
            allowed_fields = ['schedule_pattern', 'schedule_description', 'airing_type', 'priority', 'active']
            updates = []
            values = []
            
            for field, value in kwargs.items():
                if field in allowed_fields:
                    updates.append(f"{field} = ?")
                    values.append(value)
            
            if not updates:
                return False
            
            values.append(schedule_id)
            
            query = f"UPDATE show_schedules SET {', '.join(updates)}, updated_at = NOW() WHERE id = ?"
            self.db.execute(query, values)
            
            return True
            
        except Exception as e:
            logger.error(f"Error updating schedule {schedule_id}: {e}")
            return False
    
    def delete_schedule(self, schedule_id: int) -> bool:
        """Delete a specific schedule"""
        try:
            # Get show_id before deleting
            schedule = self.db.fetch_one(
                "SELECT show_id FROM show_schedules WHERE id = ?",
                [schedule_id]
            )
            
            if not schedule:
                return False
            
            show_id = schedule['show_id']
            
            # Delete the schedule
            self.db.execute(
                "DELETE FROM show_schedules WHERE id = ?",
                [schedule_id]
            )
            
            # Check if show still has multiple schedules
            remaining_count = self.db.fetch_one(
                "SELECT COUNT(*) as count FROM show_schedules WHERE show_id = ? AND active = 1",
                [show_id]
            )['count']
            
            if remaining_count <= 1:
                self._update_show_multiple_flag(show_id, False)
            
            return True
            
        except Exception as e:
            logger.error(f"Error deleting schedule {schedule_id}: {e}")
            return False
    
    def migrate_legacy_schedules(self) -> Dict[str, Any]:
        """Migrate shows that still use the legacy schedule_pattern field"""
        result = {
            'success': False,
            'shows_migrated': 0,
            'schedules_created': 0,
            'errors': []
        }
        
        try:
            # Find shows with legacy schedule patterns that aren't in show_schedules
            legacy_shows = self.db.fetch_all("""
                SELECT s.id, s.schedule_pattern, s.schedule_description
                FROM shows s
                WHERE s.schedule_pattern IS NOT NULL 
                AND s.schedule_pattern != ''
                AND s.uses_multiple_schedules = 0
                AND NOT EXISTS (
                    SELECT 1 FROM show_schedules ss WHERE ss.show_id = s.id
                )
            """)
            
            for show in legacy_shows:
                try:
                    migration_result = self.add_multiple_schedules(
                        show['id'], 
                        show['schedule_description'] or show['schedule_pattern']
                    )
                    
                    if migration_result['success']:
                        result['shows_migrated'] += 1
                        result['schedules_created'] += migration_result['schedules_added']
                    else:
                        result['errors'].append(f"Show {show['id']}: {migration_result['error']}")
                        
                except Exception as e:
                    result['errors'].append(f"Show {show['id']}: {str(e)}")
            
            result['success'] = True
            logger.info(f"Migrated {result['shows_migrated']} legacy shows to new schedule system")
            
        except Exception as e:
            result['error'] = f"Migration error: {str(e)}"
            logger.error(f"Error during legacy schedule migration: {e}")
        
        return result
    
    def _add_single_schedule(self, show_id: int, schedule_pattern: str, 
                           schedule_description: str, airing_type: str, priority: int) -> Optional[int]:
        """Add a single schedule entry"""
        try:
            cursor = self.db.execute("""
                INSERT INTO show_schedules 
                (show_id, schedule_pattern, schedule_description, airing_type, priority, active)
                VALUES (?, ?, ?, ?, ?, 1)
            """, [show_id, schedule_pattern, schedule_description, airing_type, priority])
            
            return cursor.lastrowid
            
        except Exception as e:
            logger.error(f"Error adding single schedule: {e}")
            return None
    
    def _update_show_multiple_flag(self, show_id: int, uses_multiple: bool) -> bool:
        """Update the uses_multiple_schedules flag for a show"""
        try:
            self.db.execute(
                "UPDATE shows SET uses_multiple_schedules = ? WHERE id = ?",
                [uses_multiple, show_id]
            )
            return True
            
        except Exception as e:
            logger.error(f"Error updating multiple flag for show {show_id}: {e}")
            return False

def main():
    """CLI interface for schedule management"""
    import argparse
    
    parser = argparse.ArgumentParser(description='Show Schedules Manager')
    parser.add_argument('--migrate', action='store_true', 
                       help='Migrate legacy schedule patterns to new system')
    parser.add_argument('--show-id', type=int, 
                       help='Show ID for operations')
    parser.add_argument('--schedule-text', 
                       help='Schedule text to parse and add')
    parser.add_argument('--list-schedules', action='store_true',
                       help='List all active schedules')
    
    args = parser.parse_args()
    
    manager = ShowSchedulesManager()
    
    if args.migrate:
        print("Migrating legacy schedules...")
        result = manager.migrate_legacy_schedules()
        print(f"Migration complete: {result}")
        
    elif args.show_id and args.schedule_text:
        print(f"Adding schedules for show {args.show_id}...")
        result = manager.add_multiple_schedules(args.show_id, args.schedule_text)
        print(f"Result: {result}")
        
    elif args.list_schedules:
        schedules = manager.get_all_active_schedules()
        print(f"Found {len(schedules)} active schedules:")
        for schedule in schedules:
            print(f"  {schedule['show_name']} ({schedule['airing_type']}): {schedule['schedule_description']}")
            
    else:
        parser.print_help()

if __name__ == '__main__':
    main()