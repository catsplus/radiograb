#!/usr/bin/env python3
"""
RadioGrab Enhanced Stream Discovery Service
Uses Radio Browser API and other methods to discover radio station streams
"""

import sys
import os
import requests
import time
import logging
from typing import Dict, List, Optional, Tuple
from urllib.parse import urlparse
import json

# Add the project root to Python path
sys.path.insert(0, '/opt/radiograb')

from backend.config.database import SessionLocal
from backend.models.station import Station

# Set up logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

class RadioStreamDiscovery:
    """Enhanced radio stream discovery using multiple sources"""
    
    def __init__(self):
        self.radio_browser_servers = [
            "all.api.radio-browser.info",
            "de1.api.radio-browser.info", 
            "nl1.api.radio-browser.info"
        ]
        self.session = requests.Session()
        self.session.headers.update({
            'User-Agent': 'RadioGrab/1.0 (Stream Discovery)'
        })
    
    def _get_radio_browser_base_url(self) -> str:
        """Get a working Radio Browser API server"""
        for server in self.radio_browser_servers:
            try:
                response = self.session.get(f"https://{server}/json/stats", timeout=5)
                if response.status_code == 200:
                    logger.info(f"Using Radio Browser server: {server}")
                    return f"https://{server}"
            except Exception as e:
                logger.warning(f"Server {server} unavailable: {e}")
                continue
        
        logger.error("No Radio Browser servers available")
        return f"https://{self.radio_browser_servers[0]}"  # Fallback
    
    def search_radio_browser(self, station_name: str, limit: int = 10) -> List[Dict]:
        """Search Radio Browser API for station by name"""
        try:
            base_url = self._get_radio_browser_base_url()
            
            # Clean station name for search
            clean_name = station_name.replace(" FM", "").replace(" AM", "").strip()
            
            params = {
                'name': clean_name,
                'countrycode': 'US',  # Focus on US stations
                'hidebroken': 'true',  # Only working stations
                'order': 'clickcount',  # Popular stations first
                'reverse': 'true',  # Highest first
                'limit': limit
            }
            
            response = self.session.get(
                f"{base_url}/json/stations/search",
                params=params,
                timeout=10
            )
            
            if response.status_code == 200:
                stations = response.json()
                logger.info(f"Radio Browser found {len(stations)} stations for '{station_name}'")
                return stations
            else:
                logger.error(f"Radio Browser API error: {response.status_code}")
                return []
                
        except Exception as e:
            logger.error(f"Radio Browser search error: {e}")
            return []
    
    def extract_call_letters(self, station_name: str) -> Optional[str]:
        """Extract call letters from station name"""
        import re
        
        # Look for 4-letter call signs (WXYZ pattern)
        call_match = re.search(r'\b[KWCN][A-Z]{3}\b', station_name.upper())
        if call_match:
            return call_match.group()
        
        # Look for 3-letter call signs with numbers (KNX, etc.)
        call_match = re.search(r'\b[KWCN][A-Z0-9]{2,3}\b', station_name.upper())
        if call_match:
            return call_match.group()
        
        return None
    
    def find_best_stream_match(self, station_name: str, existing_url: str = None) -> Optional[Dict]:
        """Find the best stream match for a station"""
        
        # First try Radio Browser search
        radio_browser_results = self.search_radio_browser(station_name)
        
        if radio_browser_results:
            # Score results based on name similarity and quality
            scored_results = []
            
            for station in radio_browser_results:
                score = self._calculate_match_score(station_name, station)
                scored_results.append((score, station))
            
            # Sort by score (highest first)
            scored_results.sort(key=lambda x: x[0], reverse=True)
            
            best_match = scored_results[0][1] if scored_results else None
            if best_match and scored_results[0][0] > 0.3:  # Minimum confidence threshold
                return {
                    'source': 'radio_browser',
                    'stream_url': best_match.get('url_resolved') or best_match.get('url'),
                    'name': best_match.get('name'),
                    'bitrate': best_match.get('bitrate'),
                    'codec': best_match.get('codec'),
                    'country': best_match.get('country'),
                    'confidence': scored_results[0][0],
                    'homepage': best_match.get('homepage'),
                    'lastcheckok': best_match.get('lastcheckok')
                }
        
        # Try search by call letters if name search failed
        call_letters = self.extract_call_letters(station_name)
        if call_letters and call_letters.lower() not in station_name.lower():
            logger.info(f"Trying search with call letters: {call_letters}")
            call_results = self.search_radio_browser(call_letters)
            
            if call_results:
                best_call_match = call_results[0]  # Take first result for call letters
                return {
                    'source': 'radio_browser_call_letters',
                    'stream_url': best_call_match.get('url_resolved') or best_call_match.get('url'),
                    'name': best_call_match.get('name'),
                    'bitrate': best_call_match.get('bitrate'),
                    'codec': best_call_match.get('codec'),
                    'country': best_call_match.get('country'),
                    'confidence': 0.8,  # High confidence for call letter matches
                    'homepage': best_call_match.get('homepage'),
                    'lastcheckok': best_call_match.get('lastcheckok')
                }
        
        logger.warning(f"No suitable stream found for '{station_name}'")
        return None
    
    def _calculate_match_score(self, target_name: str, station: Dict) -> float:
        """Calculate how well a station matches the target name"""
        station_name = station.get('name', '').lower()
        target_lower = target_name.lower()
        
        score = 0.0
        
        # Exact match gets highest score
        if station_name == target_lower:
            score += 1.0
        
        # Call letters match
        target_call = self.extract_call_letters(target_name)
        station_call = self.extract_call_letters(station_name)
        if target_call and station_call and target_call == station_call:
            score += 0.8
        
        # Name similarity (basic word matching)
        target_words = set(target_lower.split())
        station_words = set(station_name.split())
        common_words = target_words.intersection(station_words)
        if target_words:
            word_score = len(common_words) / len(target_words)
            score += word_score * 0.6
        
        # Boost for US stations
        if station.get('countrycode') == 'US':
            score += 0.2
        
        # Boost for working stations
        if station.get('lastcheckok') == 1:
            score += 0.3
        
        # Boost for higher bitrate (quality indicator)
        bitrate = station.get('bitrate', 0)
        if bitrate >= 128:
            score += 0.2
        elif bitrate >= 64:
            score += 0.1
        
        # Boost for popularity
        clickcount = station.get('clickcount', 0)
        if clickcount > 100:
            score += 0.1
        
        return min(score, 1.0)  # Cap at 1.0
    
    def update_station_stream(self, station_id: int, stream_info: Dict) -> bool:
        """Update station with new stream information"""
        try:
            db = SessionLocal()
            try:
                station = db.query(Station).filter(Station.id == station_id).first()
                if not station:
                    logger.error(f"Station {station_id} not found")
                    return False
                
                # Store old stream URL for comparison
                old_url = station.stream_url
                
                # Update stream information
                station.stream_url = stream_info['stream_url']
                
                # Update discovery metadata
                discovery_metadata = {
                    'source': stream_info['source'],
                    'confidence': stream_info['confidence'],
                    'discovery_date': time.strftime('%Y-%m-%d %H:%M:%S'),
                    'old_url': old_url,
                    'radio_browser_name': stream_info.get('name'),
                    'bitrate': stream_info.get('bitrate'),
                    'codec': stream_info.get('codec')
                }
                
                # Update stream test results with discovery info
                station.stream_test_results = json.dumps(discovery_metadata)
                station.stream_compatibility = 'unknown'  # Reset to test new stream
                station.recommended_recording_tool = None  # Reset to rediscover best tool
                
                db.commit()
                
                logger.info(f"Updated station {station_id} stream: {stream_info['stream_url']}")
                logger.info(f"Discovery source: {stream_info['source']} (confidence: {stream_info['confidence']:.2f})")
                
                return True
                
            finally:
                db.close()
                
        except Exception as e:
            logger.error(f"Error updating station {station_id}: {e}")
            return False
    
    def rediscover_failed_stations(self) -> Dict:
        """Rediscover streams for all failed stations"""
        try:
            db = SessionLocal()
            try:
                # Get stations that failed recent tests
                failed_stations = db.query(Station).filter(
                    Station.status == 'active',
                    Station.last_test_result.in_(['failed', 'error'])
                ).all()
                
                results = {
                    'total_failed': len(failed_stations),
                    'rediscovered': 0,
                    'updated': 0,
                    'not_found': 0,
                    'stations': []
                }
                
                for station in failed_stations:
                    logger.info(f"Rediscovering stream for station {station.id}: {station.name}")
                    
                    stream_info = self.find_best_stream_match(station.name, station.stream_url)
                    
                    station_result = {
                        'id': station.id,
                        'name': station.name,
                        'old_url': station.stream_url,
                        'new_url': None,
                        'source': None,
                        'confidence': 0,
                        'updated': False
                    }
                    
                    if stream_info:
                        results['rediscovered'] += 1
                        station_result.update({
                            'new_url': stream_info['stream_url'],
                            'source': stream_info['source'],
                            'confidence': stream_info['confidence']
                        })
                        
                        # Only update if we found a different/better stream
                        if stream_info['stream_url'] != station.stream_url:
                            if self.update_station_stream(station.id, stream_info):
                                results['updated'] += 1
                                station_result['updated'] = True
                    else:
                        results['not_found'] += 1
                    
                    results['stations'].append(station_result)
                    
                    # Be nice to the API
                    time.sleep(1)
                
                return results
                
            finally:
                db.close()
                
        except Exception as e:
            logger.error(f"Error in rediscover_failed_stations: {e}")
            return {'error': str(e)}

def main():
    import argparse
    
    parser = argparse.ArgumentParser(description='RadioGrab Stream Discovery Service')
    parser.add_argument('--station-id', type=int, help='Rediscover stream for specific station')
    parser.add_argument('--station-name', help='Search for stream by station name')
    parser.add_argument('--rediscover-failed', action='store_true', 
                       help='Rediscover streams for all failed stations')
    parser.add_argument('--test-search', help='Test search without updating database')
    
    args = parser.parse_args()
    
    discovery = RadioStreamDiscovery()
    
    if args.test_search:
        print(f"Testing search for: {args.test_search}")
        result = discovery.find_best_stream_match(args.test_search)
        if result:
            print(f"Found stream: {result['stream_url']}")
            print(f"Source: {result['source']}")
            print(f"Confidence: {result['confidence']:.2f}")
            print(f"Details: {json.dumps(result, indent=2)}")
        else:
            print("No stream found")
    
    elif args.station_id:
        # Rediscover for specific station
        db = SessionLocal()
        try:
            station = db.query(Station).filter(Station.id == args.station_id).first()
            if station:
                print(f"Rediscovering stream for: {station.name}")
                result = discovery.find_best_stream_match(station.name, station.stream_url)
                if result:
                    print(f"Found: {result['stream_url']} (confidence: {result['confidence']:.2f})")
                    if discovery.update_station_stream(station.id, result):
                        print("✅ Station updated successfully")
                    else:
                        print("❌ Failed to update station")
                else:
                    print("❌ No suitable stream found")
            else:
                print(f"Station {args.station_id} not found")
        finally:
            db.close()
    
    elif args.rediscover_failed:
        print("Rediscovering streams for all failed stations...")
        results = discovery.rediscover_failed_stations()
        
        if 'error' in results:
            print(f"❌ Error: {results['error']}")
            return
        
        print(f"\n📊 Results:")
        print(f"  Total failed stations: {results['total_failed']}")
        print(f"  Streams rediscovered: {results['rediscovered']}")
        print(f"  Stations updated: {results['updated']}")
        print(f"  No streams found: {results['not_found']}")
        
        print(f"\n📋 Details:")
        for station in results['stations']:
            status = "✅ Updated" if station['updated'] else "🔍 Found" if station['new_url'] else "❌ Not found"
            print(f"  {station['name']} ({station['id']}): {status}")
            if station['new_url']:
                print(f"    New: {station['new_url']}")
                print(f"    Source: {station['source']} (confidence: {station['confidence']:.2f})")
    
    else:
        parser.print_help()

if __name__ == '__main__':
    main()