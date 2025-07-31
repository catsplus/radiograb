"""
Station Manager Service
High-level service for managing radio stations and their discovery
"""
from sqlalchemy.orm import Session
from backend.config.database import get_db, SessionLocal
from backend.models.station import Station, Show
from backend.services.station_discovery import StationDiscovery
from backend.utils.stream_validator import StreamValidator, PlaylistParser
import logging
from typing import Dict, List, Optional

logger = logging.getLogger(__name__)

class StationManager:
    """
Manages radio stations, including adding, updating, and deleting them.

This service integrates with discovery services to enrich station data and
ensures that station information is consistent and up-to-date in the database.

Key Variables:
- `name`: The name of the station.
- `website_url`: The URL of the station's website.
- `station_id`: The ID of the station to manage.

Inter-script Communication:
- This script is called by the frontend API to manage stations.
- It uses `station_discovery.py` to discover station information.
- It uses `logo_storage_service.py` to handle logo downloads and storage.
- It interacts with the `Station`, `Show`, and `Recording` models from `backend/models/station.py`.
"""
    
    def __init__(self):
        self.discovery = StationDiscovery()
        self.validator = StreamValidator()
    
    def add_station(self, website_url: str, name: str = None) -> Dict:
        """
        Add a new radio station by discovering its streaming information
        
        Args:
            website_url: The station's website URL
            name: Optional station name override
            
        Returns:
            Dictionary with operation result and station data
        """
        result = {
            'success': False,
            'station_id': None,
            'errors': [],
            'warnings': [],
            'discovered_data': {}
        }
        
        try:
            # Discover station information
            logger.info(f"Adding new station: {website_url}")
            discovery_result = self.discovery.discover_station(website_url)
            result['discovered_data'] = discovery_result
            
            if discovery_result['errors']:
                result['errors'].extend(discovery_result['errors'])
            
            # Use provided name or discovered name
            station_name = name or discovery_result['station_name'] or website_url
            
            # Validate streaming URLs
            stream_url = None
            if discovery_result['stream_urls']:
                stream_url = self._find_best_validated_stream(discovery_result['stream_urls'])
                
            if not stream_url:
                result['warnings'].append("No valid streaming URL found")
            
            # Save to database
            db = SessionLocal()
            try:
                # Check if station already exists
                existing = db.query(Station).filter(Station.website_url == website_url).first()
                if existing:
                    result['errors'].append("Station already exists")
                    return result
                
                # Create new station
                station = Station(
                    name=station_name,
                    website_url=website_url,
                    stream_url=stream_url,
                    logo_url=discovery_result['logo_url'],
                    calendar_url=discovery_result['calendar_url'],
                    status='active'
                )
                
                db.add(station)
                db.commit()
                db.refresh(station)
                
                result['success'] = True
                result['station_id'] = station.id
                logger.info(f"Station added successfully: {station_name} (ID: {station.id})")
                
            finally:
                db.close()
                
        except Exception as e:
            logger.error(f"Error adding station {website_url}: {str(e)}")
            result['errors'].append(f"Database error: {str(e)}")
        
        return result
    
    def update_station_streams(self, station_id: int) -> Dict:
        """
        Re-discover and update streaming URLs for an existing station
        
        Args:
            station_id: Database ID of the station to update
            
        Returns:
            Dictionary with operation result
        """
        result = {
            'success': False,
            'updated_fields': [],
            'errors': [],
            'warnings': []
        }
        
        db = SessionLocal()
        try:
            station = db.query(Station).filter(Station.id == station_id).first()
            if not station:
                result['errors'].append("Station not found")
                return result
            
            # Re-discover station information
            discovery_result = self.discovery.discover_station(station.website_url)
            
            # Update streaming URL if found
            if discovery_result['stream_urls']:
                new_stream_url = self._find_best_validated_stream(discovery_result['stream_urls'])
                if new_stream_url and new_stream_url != station.stream_url:
                    station.stream_url = new_stream_url
                    result['updated_fields'].append('stream_url')
            
            # Update other fields if they were empty
            if discovery_result['logo_url'] and not station.logo_url:
                station.logo_url = discovery_result['logo_url']
                result['updated_fields'].append('logo_url')
            
            if discovery_result['calendar_url'] and not station.calendar_url:
                station.calendar_url = discovery_result['calendar_url']
                result['updated_fields'].append('calendar_url')
            
            if result['updated_fields']:
                db.commit()
                result['success'] = True
                logger.info(f"Updated station {station.name}: {result['updated_fields']}")
            else:
                result['warnings'].append("No updates needed")
                result['success'] = True
                
        except Exception as e:
            logger.error(f"Error updating station {station_id}: {str(e)}")
            result['errors'].append(f"Update error: {str(e)}")
        finally:
            db.close()
        
        return result
    
    def validate_station_stream(self, station_id: int) -> Dict:
        """
        Validate the current streaming URL for a station
        
        Args:
            station_id: Database ID of the station
            
        Returns:
            Dictionary with validation results
        """
        db = SessionLocal()
        try:
            station = db.query(Station).filter(Station.id == station_id).first()
            if not station:
                return {'error': 'Station not found'}
            
            if not station.stream_url:
                return {'error': 'No streaming URL configured'}
            
            # Validate the stream
            validation_result = self.validator.validate_stream(station.stream_url)
            validation_result['quality_score'] = self.validator.get_stream_quality_score(validation_result)
            
            # Update station status based on validation
            if validation_result['is_valid']:
                if station.status != 'active':
                    station.status = 'active'
                    db.commit()
            else:
                if station.status == 'active':
                    station.status = 'stream_error'
                    db.commit()
            
            return validation_result
            
        except Exception as e:
            logger.error(f"Error validating station {station_id}: {str(e)}")
            return {'error': f"Validation error: {str(e)}"}
        finally:
            db.close()
    
    def get_stations(self, status: str = None) -> List[Dict]:
        """
        Get list of all stations
        
        Args:
            status: Optional status filter
            
        Returns:
            List of station dictionaries
        """
        db = SessionLocal()
        try:
            query = db.query(Station)
            if status:
                query = query.filter(Station.status == status)
            
            stations = query.all()
            
            result = []
            for station in stations:
                station_dict = {
                    'id': station.id,
                    'name': station.name,
                    'website_url': station.website_url,
                    'stream_url': station.stream_url,
                    'logo_url': station.logo_url,
                    'calendar_url': station.calendar_url,
                    'status': station.status,
                    'created_at': station.created_at.isoformat() if station.created_at else None,
                    'show_count': len(station.shows)
                }
                result.append(station_dict)
            
            return result
            
        except Exception as e:
            logger.error(f"Error getting stations: {str(e)}")
            return []
        finally:
            db.close()
    
    def delete_station(self, station_id: int) -> Dict:
        """
        Delete a station and all its shows
        
        Args:
            station_id: Database ID of the station
            
        Returns:
            Dictionary with operation result
        """
        result = {
            'success': False,
            'error': None
        }
        
        db = SessionLocal()
        try:
            station = db.query(Station).filter(Station.id == station_id).first()
            if not station:
                result['error'] = "Station not found"
                return result
            
            # Delete the station (cascade will handle shows)
            db.delete(station)
            db.commit()
            
            result['success'] = True
            logger.info(f"Station deleted: {station.name} (ID: {station_id})")
            
        except Exception as e:
            logger.error(f"Error deleting station {station_id}: {str(e)}")
            result['error'] = f"Delete error: {str(e)}"
        finally:
            db.close()
        
        return result
    
    def _find_best_validated_stream(self, stream_urls: List[str]) -> Optional[str]:
        """
        Find the best streaming URL from a list by validating each
        
        Args:
            stream_urls: List of potential streaming URLs
            
        Returns:
            Best validated streaming URL or None
        """
        best_url = None
        best_score = 0
        
        for url in stream_urls:
            # Check if it's a playlist first
            if any(ext in url.lower() for ext in ['.m3u', '.pls']):
                # Parse playlist to get actual stream URLs
                playlist_urls = PlaylistParser.parse_playlist_url(url, self.validator.session)
                if playlist_urls:
                    # Recursively validate playlist URLs
                    playlist_best = self._find_best_validated_stream(playlist_urls)
                    if playlist_best:
                        return playlist_best
                continue
            
            # Validate direct stream URL
            validation_result = self.validator.validate_stream(url)
            if validation_result['is_valid']:
                score = self.validator.get_stream_quality_score(validation_result)
                if score > best_score:
                    best_score = score
                    best_url = url
        
        return best_url

def test_station_manager():
    """Test function for the station manager"""
    manager = StationManager()
    
    # Test adding a station
    print("=== Testing Station Manager ===")
    result = manager.add_station('https://wehc.com', 'WEHC Test')
    print(f"Add station result: {result}")
    
    if result['success']:
        station_id = result['station_id']
        
        # Test validation
        validation = manager.validate_station_stream(station_id)
        print(f"Stream validation: {validation}")
        
        # Test getting stations
        stations = manager.get_stations()
        print(f"Found {len(stations)} stations")

if __name__ == "__main__":
    test_station_manager()