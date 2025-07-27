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
    
    def extract_frequency(self, station_name: str) -> Optional[str]:
        """Extract frequency from station name"""
        import re
        
        # Look for FM frequencies (87.5 - 108.0)
        fm_match = re.search(r'\b(8[7-9]|9[0-9]|10[0-8])\.\d\s*FM\b', station_name, re.IGNORECASE)
        if fm_match:
            return fm_match.group()
        
        # Look for AM frequencies (530 - 1700)
        am_match = re.search(r'\b(5[3-9][0-9]|[6-9][0-9][0-9]|1[0-6][0-9][0-9]|1700)\s*AM\b', station_name, re.IGNORECASE)
        if am_match:
            return am_match.group()
        
        return None
    
    def extract_location(self, station_name: str) -> Optional[str]:
        """Extract location from station name"""
        import re
        
        # Look for common location patterns
        location_patterns = [
            r'\b([A-Z][a-z]+ (?:County|City|College|University|Schools?))\b',
            r'\b([A-Z][a-z]+),?\s*[A-Z]{2}\b',  # City, State
            r'\b([A-Z][a-z]+ [A-Z][a-z]+),?\s*[A-Z]{2}\b',  # City Name, State
        ]
        
        for pattern in location_patterns:
            match = re.search(pattern, station_name)
            if match:
                return match.group(1).strip()
        
        return None
    
    def find_best_stream_match(self, station_name: str, existing_url: str = None) -> Optional[Dict]:
        """Find the best stream match for a station using multiple search strategies"""
        
        logger.info(f"Starting comprehensive search for: {station_name}")
        
        # Extract components for multi-strategy search
        call_letters = self.extract_call_letters(station_name)
        frequency = self.extract_frequency(station_name)
        location = self.extract_location(station_name)
        
        logger.info(f"Extracted - Call letters: {call_letters}, Frequency: {frequency}, Location: {location}")
        
        # Strategy 1: Direct station name search
        logger.info("Strategy 1: Direct name search")
        radio_browser_results = self.search_radio_browser(station_name)
        best_result = self._evaluate_search_results(radio_browser_results, station_name, 'radio_browser_name')
        if best_result:
            return best_result
        
        # Strategy 2: Call letters search
        if call_letters:
            logger.info(f"Strategy 2: Call letters search - {call_letters}")
            call_results = self.search_radio_browser(call_letters)
            best_result = self._evaluate_search_results(call_results, station_name, 'radio_browser_call_letters', min_confidence=0.7)
            if best_result:
                return best_result
        
        # Strategy 3: Frequency search
        if frequency:
            logger.info(f"Strategy 3: Frequency search - {frequency}")
            freq_results = self.search_radio_browser(frequency)
            best_result = self._evaluate_search_results(freq_results, station_name, 'radio_browser_frequency', min_confidence=0.5)
            if best_result:
                return best_result
        
        # Strategy 4: Location + frequency search
        if location and frequency:
            search_term = f"{location} {frequency}"
            logger.info(f"Strategy 4: Location + frequency search - {search_term}")
            location_results = self.search_radio_browser(search_term)
            best_result = self._evaluate_search_results(location_results, station_name, 'radio_browser_location_freq', min_confidence=0.4)
            if best_result:
                return best_result
        
        # Strategy 5: Simplified name search (remove common words)
        simplified_name = self._simplify_station_name(station_name)
        if simplified_name != station_name:
            logger.info(f"Strategy 5: Simplified name search - {simplified_name}")
            simple_results = self.search_radio_browser(simplified_name)
            best_result = self._evaluate_search_results(simple_results, station_name, 'radio_browser_simplified', min_confidence=0.4)
            if best_result:
                return best_result
        
        # Strategy 6: Location-only search (for local/community stations)
        if location:
            logger.info(f"Strategy 6: Location-only search - {location}")
            location_only_results = self.search_radio_browser(location)
            best_result = self._evaluate_search_results(location_only_results, station_name, 'radio_browser_location_only', min_confidence=0.3)
            if best_result:
                return best_result
        
        logger.warning(f"No suitable stream found for '{station_name}' after trying all strategies")
        return None
    
    def _simplify_station_name(self, station_name: str) -> str:
        """Simplify station name by removing common descriptive words"""
        import re
        
        # Remove common descriptive words that might not be in Radio Browser
        words_to_remove = [
            r'\b(?:Community|Public|Schools?|College|University|Radio|Broadcasting|Network|Media|Communications?)\b',
            r'\b(?:FM|AM)\b',
            r'\b\d+\.\d+\b',  # Remove frequency
            r'\s*-\s*\d+\.\d+.*$',  # Remove everything after frequency dash
            r'\s*,.*$',  # Remove everything after comma
        ]
        
        simplified = station_name
        for pattern in words_to_remove:
            simplified = re.sub(pattern, '', simplified, flags=re.IGNORECASE)
        
        # Clean up extra whitespace
        simplified = re.sub(r'\s+', ' ', simplified).strip()
        
        return simplified if simplified else station_name
    
    def _evaluate_search_results(self, results: List[Dict], target_name: str, source: str, min_confidence: float = 0.3) -> Optional[Dict]:
        """Evaluate search results and return best match if above confidence threshold"""
        
        if not results:
            return None
        
        # Score results based on name similarity and quality
        scored_results = []
        
        for station in results:
            score = self._calculate_match_score(target_name, station)
            scored_results.append((score, station))
        
        # Sort by score (highest first)
        scored_results.sort(key=lambda x: x[0], reverse=True)
        
        best_match = scored_results[0][1] if scored_results else None
        best_score = scored_results[0][0] if scored_results else 0
        
        if best_match and best_score >= min_confidence:
            logger.info(f"Found match via {source}: {best_match.get('name')} (confidence: {best_score:.2f})")
            return {
                'source': source,
                'stream_url': best_match.get('url_resolved') or best_match.get('url'),
                'name': best_match.get('name'),
                'bitrate': best_match.get('bitrate'),
                'codec': best_match.get('codec'),
                'country': best_match.get('country'),
                'confidence': best_score,
                'homepage': best_match.get('homepage'),
                'lastcheckok': best_match.get('lastcheckok')
            }
        
        logger.info(f"No suitable match via {source} (best confidence: {best_score:.2f})")
        return None
    
    def _calculate_match_score(self, target_name: str, station: Dict) -> float:
        """Calculate how well a station matches the target name"""
        station_name = station.get('name', '').lower()
        target_lower = target_name.lower()
        
        score = 0.0
        
        # Exact match gets highest score
        if station_name == target_lower:
            score += 1.0
            return min(score, 1.0)  # Early return for perfect match
        
        # Call letters match (high priority)
        target_call = self.extract_call_letters(target_name)
        station_call = self.extract_call_letters(station_name)
        if target_call and station_call and target_call == station_call:
            score += 0.8
        
        # Frequency match (high priority for radio stations)
        target_freq = self.extract_frequency(target_name)
        station_freq = self.extract_frequency(station_name)
        if target_freq and station_freq:
            # Extract just the numeric part for comparison
            import re
            target_num = re.search(r'(\d+\.?\d*)', target_freq)
            station_num = re.search(r'(\d+\.?\d*)', station_freq)
            if target_num and station_num:
                try:
                    target_val = float(target_num.group(1))
                    station_val = float(station_num.group(1))
                    if abs(target_val - station_val) < 0.1:  # Very close frequencies
                        score += 0.7
                    elif abs(target_val - station_val) < 0.5:  # Close frequencies
                        score += 0.4
                except ValueError:
                    pass
        
        # Location match
        target_location = self.extract_location(target_name)
        station_location = self.extract_location(station_name)
        if target_location and station_location:
            if target_location.lower() in station_location.lower() or station_location.lower() in target_location.lower():
                score += 0.5
        
        # Name similarity (enhanced word matching)
        target_words = set(target_lower.split())
        station_words = set(station_name.split())
        
        # Remove common filler words for better matching
        filler_words = {'radio', 'fm', 'am', 'the', 'a', 'an', 'of', 'and', 'or', '-'}
        target_words = target_words - filler_words
        station_words = station_words - filler_words
        
        if target_words:
            common_words = target_words.intersection(station_words)
            word_score = len(common_words) / len(target_words)
            score += word_score * 0.6
            
            # Bonus for partial word matches (e.g., "Pittsfield" matches "Pittsfield")
            for target_word in target_words:
                for station_word in station_words:
                    if len(target_word) > 3 and len(station_word) > 3:
                        if target_word in station_word or station_word in target_word:
                            score += 0.1
                            break
        
        # Quality indicators
        
        # Boost for US stations
        if station.get('countrycode') == 'US':
            score += 0.2
        
        # Boost for working stations (very important)
        if station.get('lastcheckok') == 1:
            score += 0.3
        elif station.get('lastcheckok') == 0:
            score -= 0.2  # Penalty for known broken streams
        
        # Boost for higher bitrate (quality indicator)
        bitrate = station.get('bitrate', 0)
        if bitrate >= 128:
            score += 0.15
        elif bitrate >= 64:
            score += 0.1
        elif bitrate > 0:
            score += 0.05
        
        # Boost for popularity (but less important than working status)
        clickcount = station.get('clickcount', 0)
        if clickcount > 1000:
            score += 0.1
        elif clickcount > 100:
            score += 0.05
        
        # Boost for more recent activity
        lastchangetime = station.get('lastchangetime', '')
        if lastchangetime:
            try:
                from datetime import datetime, timedelta
                # Radio Browser uses ISO format
                last_change = datetime.fromisoformat(lastchangetime.replace('Z', '+00:00'))
                days_ago = (datetime.now(last_change.tzinfo) - last_change).days
                if days_ago < 30:  # Updated within last month
                    score += 0.1
                elif days_ago < 90:  # Updated within last 3 months
                    score += 0.05
            except:
                pass  # Ignore date parsing errors
        
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