#!/usr/bin/env python3
"""
Show Metadata Management CLI
Command-line interface for managing show metadata detection and enrichment
"""

import sys
import os
import argparse
import json
import logging
from datetime import datetime

# Add project root to path
sys.path.insert(0, '/opt/radiograb')

from backend.services.show_manager import ShowManager
from backend.services.show_metadata_detection import ShowMetadataDetector, detect_show_metadata_batch
from backend.config.database import SessionLocal
from backend.models.station import Station, Show

logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)


def list_stations():
    """List all stations with show counts"""
    try:
        db = SessionLocal()
        try:
            stations = db.query(Station).filter(Station.status == 'active').all()
            
            print(f"\n{'='*80}")
            print(f"{'RADIOGRAB STATIONS':<80}")
            print(f"{'='*80}")
            print(f"{'ID':<4} {'Call Letters':<12} {'Name':<30} {'Shows':<8} {'Calendar URL':<20}")
            print(f"{'-'*80}")
            
            for station in stations:
                show_count = db.query(Show).filter(Show.station_id == station.id, Show.active == True).count()
                calendar_status = "Yes" if station.calendar_url else "No"
                
                print(f"{station.id:<4} {station.call_letters or 'N/A':<12} {station.name[:30]:<30} {show_count:<8} {calendar_status:<20}")
            
            print(f"{'-'*80}")
            print(f"Total: {len(stations)} active stations")
            print()
            
        finally:
            db.close()
    except Exception as e:
        logger.error(f"Error listing stations: {e}")


def list_shows(station_id=None):
    """List shows with metadata status"""
    try:
        db = SessionLocal()
        try:
            query = db.query(Show).filter(Show.active == True)
            if station_id:
                query = query.filter(Show.station_id == station_id)
            
            shows = query.all()
            
            print(f"\n{'='*100}")
            if station_id:
                station = db.query(Station).filter(Station.id == station_id).first()
                station_name = station.name if station else f"Station {station_id}"
                print(f"{'SHOWS FOR ' + station_name.upper():<100}")
            else:
                print(f"{'ALL SHOWS':<100}")
            print(f"{'='*100}")
            print(f"{'ID':<4} {'Station':<12} {'Show Name':<25} {'Description':<12} {'Image':<12} {'Host':<15} {'Updated':<18}")
            print(f"{'-'*100}")
            
            for show in shows:
                # Check metadata status
                desc_status = "Yes" if show.description else "No"
                if hasattr(show, 'description_source') and show.description_source:
                    desc_status += f" ({show.description_source})"
                
                image_status = "Yes" if getattr(show, 'image_url', None) else "No"
                if hasattr(show, 'image_source') and show.image_source:
                    image_status += f" ({show.image_source})"
                
                host_status = show.host[:15] if show.host else "N/A"
                
                updated = "N/A"
                if hasattr(show, 'metadata_updated') and show.metadata_updated:
                    updated = show.metadata_updated.strftime('%Y-%m-%d %H:%M')
                
                print(f"{show.id:<4} {show.station.call_letters or 'N/A':<12} {show.name[:25]:<25} {desc_status:<12} {image_status:<12} {host_status:<15} {updated:<18}")
            
            print(f"{'-'*100}")
            print(f"Total: {len(shows)} shows")
            print()
            
        finally:
            db.close()
    except Exception as e:
        logger.error(f"Error listing shows: {e}")


def detect_metadata_single(show_id):
    """Detect metadata for a single show"""
    try:
        db = SessionLocal()
        try:
            show = db.query(Show).filter(Show.id == show_id).first()
            if not show:
                print(f"❌ Show {show_id} not found")
                return
            
            print(f"\nDetecting metadata for show: {show.name} (ID: {show_id})")
            print(f"Station: {show.station.name} ({show.station.call_letters})")
            print(f"{'='*60}")
            
            detector = ShowMetadataDetector()
            metadata = detector.detect_and_enrich_show_metadata(show_id, show.station_id)
            
            print(f"\n✅ Metadata Detection Results:")
            print(f"   Name: {metadata.name}")
            print(f"   Description: {metadata.description[:100] + '...' if metadata.description and len(metadata.description) > 100 else metadata.description or 'None'}")
            print(f"   Description Source: {metadata.description_source or 'None'}")
            print(f"   Host: {metadata.host or 'None'}")
            print(f"   Genre: {metadata.genre or 'None'}")
            print(f"   Image URL: {metadata.image_url or 'None'}")
            print(f"   Image Source: {metadata.image_source or 'None'}")
            print(f"   Website URL: {metadata.website_url or 'None'}")
            print(f"   Last Updated: {metadata.last_updated}")
            print()
            
        finally:
            db.close()
    except Exception as e:
        logger.error(f"Error detecting metadata for show {show_id}: {e}")


def detect_metadata_batch_station(station_id, force_update=False):
    """Detect metadata for all shows in a station"""
    try:
        db = SessionLocal()
        try:
            station = db.query(Station).filter(Station.id == station_id).first()
            if not station:
                print(f"❌ Station {station_id} not found")
                return
            
            print(f"\nBatch metadata detection for station: {station.name} ({station.call_letters})")
            print(f"Force update: {'Yes' if force_update else 'No'}")
            print(f"{'='*80}")
            
            manager = ShowManager()
            result = manager.enrich_existing_shows_metadata(station_id=station_id, force_update=force_update)
            
            print(f"\n📊 Batch Processing Results:")
            print(f"   Total processed: {result['total_processed']}")
            print(f"   Successfully updated: {result['total_updated']}")
            print(f"   Skipped: {len(result['skipped_shows'])}")
            print(f"   Failed: {len(result['failed_shows'])}")
            
            if result['processed_shows']:
                print(f"\n✅ Processed Shows:")
                for show_result in result['processed_shows']:
                    metadata = show_result['metadata']
                    print(f"   - {show_result['show_name']}: desc={metadata['description_source']}, image={metadata['image_source']}")
            
            if result['failed_shows']:
                print(f"\n❌ Failed Shows:")
                for failed in result['failed_shows']:
                    print(f"   - {failed['show_name']}: {failed['error']}")
            
            print()
            
        finally:
            db.close()
    except Exception as e:
        logger.error(f"Error in batch metadata detection: {e}")


def detect_metadata_all(force_update=False):
    """Detect metadata for all shows across all stations"""
    try:
        db = SessionLocal()
        try:
            stations = db.query(Station).filter(Station.status == 'active').all()
            
            print(f"\nBatch metadata detection for ALL stations")
            print(f"Force update: {'Yes' if force_update else 'No'}")
            print(f"{'='*80}")
            
            total_processed = 0
            total_updated = 0
            total_failed = 0
            
            for station in stations:
                print(f"\nProcessing station: {station.name} ({station.call_letters})")
                
                manager = ShowManager()
                result = manager.enrich_existing_shows_metadata(station_id=station.id, force_update=force_update)
                
                total_processed += result['total_processed']
                total_updated += result['total_updated']
                total_failed += len(result['failed_shows'])
                
                print(f"   Processed: {result['total_processed']}, Updated: {result['total_updated']}, Failed: {len(result['failed_shows'])}")
            
            print(f"\n📊 Overall Results:")
            print(f"   Stations processed: {len(stations)}")
            print(f"   Total shows processed: {total_processed}")
            print(f"   Total shows updated: {total_updated}")
            print(f"   Total failures: {total_failed}")
            print()
            
        finally:
            db.close()
    except Exception as e:
        logger.error(f"Error in all-stations metadata detection: {e}")


def show_metadata_status():
    """Show overall metadata status statistics"""
    try:
        db = SessionLocal()
        try:
            print(f"\n{'='*80}")
            print(f"{'SHOW METADATA STATUS REPORT':<80}")
            print(f"{'='*80}")
            
            # Overall counts
            total_shows = db.query(Show).filter(Show.active == True).count()
            shows_with_description = db.query(Show).filter(
                Show.active == True,
                Show.description.isnot(None),
                Show.description != ''
            ).count()
            
            # For new fields, check if they exist
            try:
                shows_with_image = db.query(Show).filter(
                    Show.active == True,
                    Show.image_url.isnot(None),
                    Show.image_url != ''
                ).count()
                shows_with_host = db.query(Show).filter(
                    Show.active == True,
                    Show.host.isnot(None),
                    Show.host != ''
                ).count()
            except:
                shows_with_image = 0
                shows_with_host = 0
                print("Note: New metadata fields not yet available in database")
            
            print(f"Total active shows: {total_shows}")
            print(f"Shows with descriptions: {shows_with_description} ({shows_with_description/total_shows*100 if total_shows > 0 else 0:.1f}%)")
            print(f"Shows with images: {shows_with_image} ({shows_with_image/total_shows*100 if total_shows > 0 else 0:.1f}%)")
            print(f"Shows with host info: {shows_with_host} ({shows_with_host/total_shows*100 if total_shows > 0 else 0:.1f}%)")
            
            # Station breakdown
            print(f"\n{'Station Breakdown:':<40}")
            print(f"{'-'*60}")
            print(f"{'Station':<20} {'Shows':<8} {'With Desc':<10} {'With Image':<12}")
            print(f"{'-'*60}")
            
            stations = db.query(Station).filter(Station.status == 'active').all()
            for station in stations:
                station_shows = db.query(Show).filter(Show.station_id == station.id, Show.active == True).count()
                station_desc = db.query(Show).filter(
                    Show.station_id == station.id,
                    Show.active == True,
                    Show.description.isnot(None),
                    Show.description != ''
                ).count()
                
                try:
                    station_image = db.query(Show).filter(
                        Show.station_id == station.id,
                        Show.active == True,
                        Show.image_url.isnot(None),
                        Show.image_url != ''
                    ).count()
                except:
                    station_image = 0
                
                name = (station.call_letters or station.name)[:20]
                print(f"{name:<20} {station_shows:<8} {station_desc:<10} {station_image:<12}")
            
            print(f"{'-'*60}")
            print()
            
        finally:
            db.close()
    except Exception as e:
        logger.error(f"Error showing metadata status: {e}")


def main():
    parser = argparse.ArgumentParser(description='Show Metadata Management CLI')
    subparsers = parser.add_subparsers(dest='command', help='Available commands')
    
    # List commands
    subparsers.add_parser('list-stations', help='List all stations with show counts')
    
    list_shows_parser = subparsers.add_parser('list-shows', help='List shows with metadata status')
    list_shows_parser.add_argument('--station-id', type=int, help='Filter by station ID')
    
    # Metadata detection commands
    detect_single_parser = subparsers.add_parser('detect-show', help='Detect metadata for a single show')
    detect_single_parser.add_argument('show_id', type=int, help='Show ID to process')
    
    detect_station_parser = subparsers.add_parser('detect-station', help='Detect metadata for all shows in a station')
    detect_station_parser.add_argument('station_id', type=int, help='Station ID to process')
    detect_station_parser.add_argument('--force', action='store_true', help='Force update existing metadata')
    
    detect_all_parser = subparsers.add_parser('detect-all', help='Detect metadata for all shows across all stations')
    detect_all_parser.add_argument('--force', action='store_true', help='Force update existing metadata')
    
    # Status command
    subparsers.add_parser('status', help='Show metadata status report')
    
    args = parser.parse_args()
    
    if not args.command:
        parser.print_help()
        return
    
    # Execute commands
    if args.command == 'list-stations':
        list_stations()
    elif args.command == 'list-shows':
        list_shows(args.station_id)
    elif args.command == 'detect-show':
        detect_metadata_single(args.show_id)
    elif args.command == 'detect-station':
        detect_metadata_batch_station(args.station_id, args.force)
    elif args.command == 'detect-all':
        detect_metadata_all(args.force)
    elif args.command == 'status':
        show_metadata_status()


if __name__ == '__main__':
    main()