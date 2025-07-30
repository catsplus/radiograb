#!/usr/bin/env python3
"""
RadioGrab Version Management Service
Manages version information stored in database
"""

import sys
import os
import argparse
from datetime import datetime
from typing import Optional

# Add project root to path
sys.path.insert(0, '/opt/radiograb')

from backend.config.database import SessionLocal
from sqlalchemy import text

class VersionManager:
    """
    Manages version information in database
    """
    
    def __init__(self):
        self.db = SessionLocal()
    
    def get_current_version(self) -> Optional[str]:
        """Get current version from database"""
        try:
            result = self.db.execute(text(
                "SELECT version FROM system_info WHERE key_name = 'current_version' LIMIT 1"
            )).fetchone()
            
            if result:
                return result[0]
            else:
                # Return default version if not found
                return "v2.12.0"
                
        except Exception as e:
            print(f"Error getting version: {e}")
            return "v2.12.0"
    
    def set_version(self, version: str, description: str = "") -> bool:
        """Set new version in database"""
        try:
            # Check if version entry exists
            existing = self.db.execute(text(
                "SELECT id FROM system_info WHERE key_name = 'current_version'"
            )).fetchone()
            
            if existing:
                # Update existing
                self.db.execute(text(
                    "UPDATE system_info SET version = :version, description = :desc, updated_at = NOW() WHERE key_name = 'current_version'"
                ), {"version": version, "desc": description})
            else:
                # Insert new
                self.db.execute(text(
                    "INSERT INTO system_info (key_name, version, description, created_at, updated_at) VALUES ('current_version', :version, :desc, NOW(), NOW())"
                ), {"version": version, "desc": description})
            
            self.db.commit()
            print(f"Version updated to {version}")
            return True
            
        except Exception as e:
            print(f"Error setting version: {e}")
            self.db.rollback()
            return False
    
    def get_version_history(self, limit: int = 10) -> list:
        """Get version history"""
        try:
            result = self.db.execute(text(
                "SELECT version, description, updated_at FROM system_info WHERE key_name = 'current_version' ORDER BY updated_at DESC LIMIT :limit"
            ), {"limit": limit}).fetchall()
            
            return [{"version": row[0], "description": row[1], "updated_at": row[2]} for row in result]
            
        except Exception as e:
            print(f"Error getting version history: {e}")
            return []
    
    def auto_increment_version(self, type: str = "minor") -> str:
        """Auto-increment version based on current version"""
        try:
            current = self.get_current_version()
            if not current or not current.startswith('v'):
                current = "v2.12.0"
            
            # Parse version (e.g., v2.12.0)
            version_parts = current[1:].split('.')
            major = int(version_parts[0]) if len(version_parts) > 0 else 2
            minor = int(version_parts[1]) if len(version_parts) > 1 else 12
            patch = int(version_parts[2]) if len(version_parts) > 2 else 0
            
            if type == "major":
                major += 1
                minor = 0
                patch = 0
            elif type == "minor":
                minor += 1
                patch = 0
            else:  # patch
                patch += 1
            
            new_version = f"v{major}.{minor}.{patch}"
            return new_version
            
        except Exception as e:
            print(f"Error auto-incrementing version: {e}")
            return "v2.13.0"
    
    def __del__(self):
        if hasattr(self, 'db'):
            self.db.close()

def main():
    """Command line interface for version management"""
    parser = argparse.ArgumentParser(description='RadioGrab Version Management')
    parser.add_argument('--get', action='store_true', help='Get current version')
    parser.add_argument('--set', type=str, help='Set version (e.g., v2.13.0)')
    parser.add_argument('--description', type=str, help='Version description')
    parser.add_argument('--auto-increment', choices=['major', 'minor', 'patch'], help='Auto-increment version')
    parser.add_argument('--history', action='store_true', help='Show version history')
    
    args = parser.parse_args()
    
    manager = VersionManager()
    
    try:
        if args.get:
            version = manager.get_current_version()
            print(f"Current version: {version}")
            
        elif args.set:
            description = args.description or f"Manual version update to {args.set}"
            success = manager.set_version(args.set, description)
            if success:
                print(f"Version set to {args.set}")
            else:
                print("Failed to set version")
                
        elif args.auto_increment:
            current = manager.get_current_version()
            new_version = manager.auto_increment_version(args.auto_increment)
            description = args.description or f"Auto-incremented {args.auto_increment} version from {current}"
            success = manager.set_version(new_version, description)
            if success:
                print(f"Version auto-incremented from {current} to {new_version}")
            else:
                print("Failed to auto-increment version")
                
        elif args.history:
            history = manager.get_version_history()
            print("=== Version History ===")
            for entry in history:
                print(f"{entry['version']} - {entry['updated_at']} - {entry['description']}")
                
        else:
            parser.print_help()
            
    except Exception as e:
        print(f"Error: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()