#!/usr/bin/env python3
"""
Test script for multiple airings detection and parsing
"""
import sys
import os
import json

# Add project root to path
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

from backend.services.multiple_airings_parser import MultipleAiringsParser

def test_multiple_airings():
    """Test the multiple airings parser with various input formats"""
    
    parser = MultipleAiringsParser()
    
    test_cases = [
        # Single airings
        "Mondays at 7 PM",
        "Tuesdays at noon",
        "Weekdays at 6:30 AM",
        
        # Multiple airings with 'and'
        "Mondays at 7 PM and Thursdays at 3 PM",
        "Tuesdays at 9 AM and Saturdays at 2 PM",
        "Wednesday at noon and Friday at 6 PM",
        
        # Multiple airings with commas
        "Mon 7PM, Thu 3PM",
        "Tuesday 9AM, Saturday 2PM",
        "Wed noon, Fri 6PM",
        
        # Multiple airings with repeat keywords
        "Mondays at 7 PM, repeat on Thursdays at 3 PM",
        "Original broadcast Tuesday 9 AM, encore Friday 6 PM",
        "Live Wednesdays at noon, rerun Sundays at 8 PM",
        
        # Complex multiple airings
        "First airing Monday 7PM, also Tuesday 3PM and Saturday noon",
        "Original Wednesday 9AM, repeat Thursday 2PM, encore Sunday 6PM",
        
        # Edge cases
        "Daily at 6 AM",
        "Weekends at 10 PM",
        "Monday through Friday at 8:30 AM",
    ]
    
    print("🎵 Testing Multiple Airings Parser")
    print("=" * 50)
    
    for i, test_case in enumerate(test_cases, 1):
        print(f"\n{i}. Testing: '{test_case}'")
        print("-" * 40)
        
        try:
            result = parser.parse_multiple_airings(test_case)
            
            if result['success']:
                print(f"✅ Success! Found {len(result['airings'])} airing(s)")
                print(f"   Has multiple: {result['has_multiple']}")
                
                for j, airing in enumerate(result['airings'], 1):
                    print(f"   Airing {j} ({airing['airing_type']}, priority {airing['priority']}):")
                    print(f"     Pattern: {airing['schedule_pattern']}")
                    print(f"     Description: {airing['schedule_description']}")
            else:
                print(f"❌ Failed: {result.get('error', 'Unknown error')}")
                
        except Exception as e:
            print(f"💥 Exception: {str(e)}")
    
    print("\n" + "=" * 50)
    print("Testing complete!")

def test_specific_case():
    """Test a specific case with detailed output"""
    parser = MultipleAiringsParser()
    
    test_input = input("\nEnter schedule text to test: ").strip()
    if not test_input:
        test_input = "Mondays at 7 PM and Thursdays at 3 PM"
        print(f"Using default: '{test_input}'")
    
    print(f"\n🔍 Detailed Analysis of: '{test_input}'")
    print("=" * 60)
    
    result = parser.parse_multiple_airings(test_input)
    print(f"Raw result: {json.dumps(result, indent=2)}")
    
    if result['success']:
        print(f"\n✅ Parsing successful!")
        print(f"   Multiple airings detected: {result['has_multiple']}")
        print(f"   Number of airings: {len(result['airings'])}")
        
        for i, airing in enumerate(result['airings'], 1):
            print(f"\n   Airing #{i}:")
            print(f"     Type: {airing['airing_type']}")
            print(f"     Priority: {airing['priority']}")
            print(f"     Cron Pattern: {airing['schedule_pattern']}")
            print(f"     Description: {airing['schedule_description']}")
    else:
        print(f"\n❌ Parsing failed: {result.get('error', 'Unknown error')}")

def main():
    """Main CLI interface"""
    if len(sys.argv) > 1 and sys.argv[1] == '--interactive':
        test_specific_case()
    else:
        test_multiple_airings()
        
        # Ask if user wants to test a specific case (only in interactive mode)
        try:
            print(f"\nWould you like to test a specific case? (y/n): ", end="")
            response = input()
            if response.lower().startswith('y'):
                test_specific_case()
        except (EOFError, KeyboardInterrupt):
            # Handle non-interactive environments gracefully
            print("\n(Non-interactive mode detected - skipping interactive test)")
            pass

if __name__ == '__main__':
    main()