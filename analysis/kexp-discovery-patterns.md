# KEXP Analysis: Streaming and Calendar Discovery Patterns

## Overview
Analysis of KEXP.org's website structure to identify general patterns that can improve RadioGrab's auto-discovery capabilities for radio stations with similar architectures.

**Date**: August 5, 2025  
**Objective**: Improve general auto-discovery system (not hardcode KEXP-specific solutions)

## Key Findings

### 1. Streaming Discovery Patterns

#### Current Limitations
- KEXP uses JavaScript-based player initialization with JWPlayer
- Streaming URLs are not directly visible in HTML source
- Player configuration is loaded dynamically via JavaScript

#### KEXP-Specific Patterns Found
```
- JWPlayer key detected: `jwplayer.key="he/p2qVf1tP5R34IL9xJdRVkuIYVHLQMJ6tXscYHkxTyu9JR"`
- Listen page at: `/listen`
- Archive streaming at: `/archive/`
- Google Tag Manager integration for analytics
```

#### General Patterns for Improvement
1. **JavaScript Player Detection**:
   - Look for JWPlayer configurations in `<script>` tags
   - Search for `jwplayer.key` patterns
   - Identify player initialization JavaScript functions

2. **Common Stream Page Patterns**:
   - `/listen` - Primary streaming page
   - `/player` - Dedicated player page
   - `/stream` - Direct streaming interface
   - `/live` - Live streaming section

3. **Stream URL Discovery Enhancement Needed**:
   - Current `_extract_streams_from_scripts()` should be enhanced to handle JWPlayer configs
   - Need better JavaScript execution for dynamic content
   - Should follow player page redirects more aggressively

### 2. Schedule/Calendar Discovery Patterns

#### KEXP Schedule Structure
- **URL**: `/schedule` with day parameters (`?day=tuesday`)
- **Format**: Static HTML with structured time blocks
- **Time Slots**: 3-hour blocks (5AM-7AM, 7AM-10AM, etc.)
- **Structure**: 
  ```html
  <div>Show Time</div>
  <h3>Show Name</h3>
  <h5>DJ and Genre</h5>
  ```

#### Shows Page Structure
- **URL**: `/shows`
- **Format**: Grid layout with show cards
- **Structure**:
  ```html
  Show Card:
  - Logo/image
  - Show name
  - Brief description
  - "Listen Now" button
  - URL format: /shows/[show-name]
  ```

#### General Patterns for All Stations
1. **Common Schedule URLs**:
   - `/schedule` (KEXP pattern)
   - `/programming`
   - `/shows`
   - `/calendar`
   - `/lineup`

2. **Schedule Page Indicators**:
   - Day-based navigation (`?day=`, `?date=`)
   - Time-based structure (hourly/block format)
   - Show name in `<h3>`, `<h2>`, or `.show-title` elements
   - DJ/host information in secondary elements

3. **JavaScript-Rendered Calendars**:
   - KEXP uses static HTML (good for parsing)
   - But many stations use JavaScript calendars (need WebDriver)
   - Look for calendar framework indicators (FullCalendar, WordPress events, etc.)

## Recommended Improvements

### 1. Enhanced Stream Discovery

#### A. JavaScript Player Pattern Detection
```python
# Add to StreamingURLPattern.get_stream_patterns()
r'jwplayer\.setup\([^)]+src[^)]+["\']([^"\']+)["\']',
r'jwplayer\.key\s*=\s*["\']([^"\']+)["\']',
r'player\.load\([^)]+["\']([^"\']+)["\']',
```

#### B. Player Page Deep Discovery
- Enhance `_crawl_listen_page()` to handle JavaScript-based players
- Add retry mechanism for player pages that take time to load
- Implement WebDriver fallback for JavaScript-heavy player interfaces

#### C. Common Stream Service Patterns
```python
# New streaming service patterns to add
KEXP_LIKE_PATTERNS = [
    r'https?://[^"\']*\.kexp\.org/[^"\']*stream[^"\']*',
    r'jwplayer\.setup\([^)]*"file":\s*"([^"]+)"',
    r'audio\.src\s*=\s*["\']([^"\']+)["\']',
]
```

### 2. Enhanced Schedule Discovery

#### A. Static HTML Schedule Parsing Improvements
```python
# New schedule detection patterns for KEXP-like structures
SCHEDULE_PATTERNS = {
    'time_block': r'(\d{1,2}(?::\d{2})?\s*(?:AM|PM))',
    'show_in_h3': 'h3',
    'dj_in_h5': 'h5',
    'time_range': r'(\d{1,2}(?::\d{2})?\s*(?:AM|PM))\s*-\s*(\d{1,2}(?::\d{2})?\s*(?:AM|PM))',
}
```

#### B. Multi-Day Schedule Handling
- Add support for `?day=` parameter variations
- Implement week-based schedule crawling
- Handle timezone detection (KEXP explicitly mentions Pacific time)

#### C. Show Detail Page Discovery
```python
# Pattern for show detail pages like /shows/[show-name]
SHOW_DETAIL_PATTERNS = [
    r'/shows?/([^/\s]+)',
    r'/programs?/([^/\s]+)', 
    r'/schedule/([^/\s]+)',
]
```

### 3. General Discovery System Enhancements

#### A. WebDriver Integration for JavaScript Content
- Enhance `js_calendar_parser.py` to handle player pages
- Add fallback from static parsing to JavaScript parsing
- Implement caching for JavaScript-discovered content

#### B. Multi-Stage Discovery Process
```python
DISCOVERY_STAGES = [
    1. Static HTML parsing (current method)
    2. JavaScript execution for dynamic content  
    3. Player page deep-crawling
    4. AJAX endpoint probing
    5. Social media fallback discovery
]
```

#### C. Pattern Learning System
- Store successful discovery patterns per station
- Use saved patterns for faster re-discovery
- Learn from failed attempts to improve future discovery

## Implementation Priority

### High Priority (Immediate Impact)
1. **Enhanced JavaScript Stream Extraction**:
   - Improve `_extract_streams_from_scripts()` with JWPlayer patterns
   - Add player page crawling with retry logic
   - Implement common streaming service detection

2. **Schedule URL Pattern Expansion**:
   - Add `/shows` as alternative to `/schedule`
   - Implement day-parameter URL testing
   - Add multi-day schedule aggregation

### Medium Priority (Next Phase)
1. **WebDriver Player Discovery**:
   - Integrate Selenium for JavaScript-heavy player pages
   - Add player configuration extraction
   - Implement dynamic content waiting

2. **Show Detail Page Parsing**:
   - Extract individual show schedules from detail pages
   - Parse show descriptions and metadata
   - Build comprehensive show database

### Low Priority (Future Enhancement)
1. **Pattern Learning System**:
   - Store successful patterns per station type
   - Machine learning for pattern recognition
   - Automatic pattern adaptation

## Testing Recommendations

### Test Cases for KEXP-Like Stations
1. **Stations with JWPlayer**: Test JavaScript player detection
2. **Static HTML Schedules**: Test time-block parsing
3. **Show Detail Pages**: Test individual show discovery
4. **Multi-day Schedules**: Test week-based schedule building

### Validation Criteria
- Stream URLs must be directly playable
- Schedule data must include valid times/dates
- Show names must pass quality filters (avoid navigation elements)
- Discovery should work without station-specific hardcoding

## Conclusion

KEXP's architecture reveals common patterns used by professional radio stations:
- JavaScript-based streaming players (JWPlayer is very common)
- Static HTML schedules with time-block structure
- Show detail pages with individual URLs
- Day-based schedule navigation

These patterns can be generalized to improve discovery for similar stations without hardcoding KEXP-specific solutions. The key is enhancing our existing discovery methods to handle JavaScript content and follow more discovery paths.