# 🌐 BROWSER TESTING SCRIPT - Phase 1: Public Pages

## 🚨 **CRITICAL: Use Real Browser Only**
- Open Chrome/Chromium browser
- Navigate to: https://radiograb.svaha.com  
- Follow this script exactly, clicking every link
- Create GitHub issues immediately when problems found

---

## **TEST 1.1: Homepage/Dashboard** 
**URL:** https://radiograb.svaha.com/

### **Step-by-Step Browser Actions:**
1. **Load Page**
   - [ ] Enter URL in browser address bar
   - [ ] Press Enter and wait for page to load
   - [ ] Check: Page loads without errors (no 500/404 messages)

2. **Check Browser Console** (F12 → Console tab)
   - [ ] Verify: No JavaScript errors in console
   - [ ] Verify: No 404 errors for CSS/JS files
   - [ ] **If errors found: Create GitHub issue immediately**

3. **Visual Inspection**
   - [ ] Check: Page layout renders correctly
   - [ ] Check: Images load properly
   - [ ] Check: Statistics display correctly
   - [ ] Check: ON-AIR indicators show (if any active recordings)

4. **Test Navigation Menu** (Click each link)
   - [ ] Click "Dashboard" - Should stay on current page or refresh
   - [ ] Click "Stations" - Should load /stations.php
   - [ ] Click "Browse Templates" - Should load /browse-templates.php
   - [ ] Click "Shows" - Should load /shows.php  
   - [ ] Click "Playlists" - Should load /playlists.php
   - [ ] Click "Recordings" - Should load /recordings.php
   - [ ] Click "RSS Feeds" - Should load /feeds.php
   - [ ] **Test Result: Document any broken links**

5. **Test Footer Links** (Scroll to bottom)
   - [ ] Click each footer link
   - [ ] Verify links work or open in new tabs
   - [ ] **Test Result: Document any broken links**

6. **Mobile Responsiveness**
   - [ ] Press F12 → Toggle device toolbar
   - [ ] Test mobile view (iPhone/iPad sizes)
   - [ ] Verify layout adjusts properly
   - [ ] Test navigation menu on mobile

---

## **TEST 1.2: Stations Page**
**URL:** https://radiograb.svaha.com/stations.php

### **Step-by-Step Browser Actions:**
1. **Load Stations Page**
   - [ ] Click "Stations" from navigation menu
   - [ ] Check: Page loads without errors
   - [ ] Check: Station cards display with images

2. **Test Station Cards**
   - [ ] Verify: Station names and images visible
   - [ ] Verify: Health indicators show (✅/❌/⚠️)
   - [ ] Check: Test recording buttons present on cards

3. **Test Filtering**
   - [ ] Locate filter dropdown (if present)
   - [ ] Click dropdown and select different options
   - [ ] Verify: Results update when filter applied
   - [ ] Test "Show All" or reset functionality

4. **Test Search**
   - [ ] Find search box
   - [ ] Type station name (e.g., "WERU")
   - [ ] Verify: Results filter as you type
   - [ ] Test clearing search

5. **Test Interactive Elements**
   - [ ] Click "Test Recording" button on first station
   - [ ] Wait for response - should show success/error message
   - [ ] Click "Edit" button on station (if present)
   - [ ] Should navigate to edit station page
   - [ ] **Document any non-functional buttons**

6. **Test Pagination**
   - [ ] If pagination present, click "Next" page
   - [ ] Verify: Different stations load
   - [ ] Test "Previous" button
   - [ ] Test direct page number links

---

## **TEST 1.3: Shows Page**
**URL:** https://radiograb.svaha.com/shows.php

### **Step-by-Step Browser Actions:**
1. **Load Shows Page**
   - [ ] Click "Shows" from navigation
   - [ ] Check: Page loads and shows listing appears

2. **Test Station Filter**
   - [ ] Find "Station" dropdown
   - [ ] Click dropdown - should populate with station names
   - [ ] Select "WERU" from dropdown
   - [ ] Click "Filter" or "Apply" button
   - [ ] **CRITICAL TEST:** Verify ONLY WERU shows appear
   - [ ] **If filter doesn't work: Create GitHub issue immediately**

3. **Test Filter Persistence**
   - [ ] After filtering by WERU, refresh page (F5)
   - [ ] Check: Does WERU filter remain selected?
   - [ ] Test "Show All" or reset filters

4. **Test Show Interactions**
   - [ ] Click "Edit" link on a show
   - [ ] Should navigate to edit show page
   - [ ] Use browser back button to return
   - [ ] Test any "View" or detail links

5. **Test Table/Card View** (if toggle present)
   - [ ] Look for view toggle buttons
   - [ ] Click between different view modes
   - [ ] Verify: Layout changes appropriately

---

## **TEST 1.4: Playlists Page**
**URL:** https://radiograb.svaha.com/playlists.php

### **Step-by-Step Browser Actions:**
1. **Load Playlists Page**
   - [ ] Click "Playlists" from navigation
   - [ ] Check: Page loads correctly

2. **Test Playlist Display**
   - [ ] Verify: Playlist cards/list displays
   - [ ] Check: "Create Playlist" button present
   - [ ] Test clicking on individual playlists

3. **Test Create Functionality**
   - [ ] Click "Create Playlist" button
   - [ ] Should open form or modal
   - [ ] **Don't submit yet - just test UI loads**

---

## **TEST 1.5: Recordings Page**
**URL:** https://radiograb.svaha.com/recordings.php

### **Step-by-Step Browser Actions:**
1. **Load Recordings Page**
   - [ ] Click "Recordings" from navigation
   - [ ] Check: Page loads (may show "No recordings" if empty)

2. **Test Audio Players** (if recordings exist)
   - [ ] Click play button on audio player
   - [ ] Verify: Audio starts playing
   - [ ] Test pause, seek controls
   - [ ] Test download links

3. **Test Filters/Search**
   - [ ] Test any search or filter options
   - [ ] Verify functionality works

---

## **TEST 1.6: RSS Feeds Page**
**URL:** https://radiograb.svaha.com/feeds.php

### **Step-by-Step Browser Actions:**
1. **Load RSS Feeds Page**
   - [ ] Click "RSS Feeds" from navigation
   - [ ] Check: Page loads with tabs

2. **Test Each Tab**
   - [ ] Click "Universal Feeds" tab
   - [ ] Verify: "All Shows" and "All Playlists" links present
   - [ ] Click "Station Feeds" tab
   - [ ] Verify: Station feed links display
   - [ ] Click "Show Feeds" tab
   - [ ] Verify: Individual show feeds display
   - [ ] **Continue for all tabs**

3. **Test Feed URLs**
   - [ ] Right-click on a feed URL → "Copy link address"
   - [ ] Open new browser tab
   - [ ] Paste and visit the feed URL directly
   - [ ] **CRITICAL:** Verify XML feed loads correctly
   - [ ] **If feed broken: Create GitHub issue immediately**

4. **Test Copy Buttons**
   - [ ] Click "Copy URL" buttons
   - [ ] Should copy to clipboard
   - [ ] Test pasting copied URL

5. **Test QR Codes**
   - [ ] Look for QR code generation
   - [ ] Click QR code buttons
   - [ ] Verify: QR codes display

---

## **TEST 1.7: Browse Templates Page**
**URL:** https://radiograb.svaha.com/browse-templates.php

### **Step-by-Step Browser Actions:**
1. **Load Browse Templates Page**
   - [ ] Click "Browse Templates" from navigation
   - [ ] Check: Page loads with template cards

2. **Test Template Browsing**
   - [ ] Verify: Template cards display with station info
   - [ ] Test search functionality
   - [ ] Test category filters
   - [ ] Test sorting options

3. **Test Template Details**
   - [ ] Click "View Details" on templates
   - [ ] Should open modal or detail view
   - [ ] Test "Copy Template" functionality
   - [ ] **Don't actually copy yet - just test UI**

---

## **🚨 ISSUE CREATION PROTOCOL**

**When ANY problem found during testing:**

1. **Stop current testing**
2. **Immediately create GitHub issue:**
   ```bash
   gh issue create --title "[Page] - Specific problem found" --body "
   URL: [exact URL]
   Steps to reproduce:
   1. [Step 1]  
   2. [Step 2]
   3. [Step 3]
   Expected: [What should happen]
   Actual: [What actually happened]
   Browser: Chrome
   Impact: [High/Medium/Low]
   " --label bug
   ```
3. **Continue testing other features**
4. **Document issue in testing log**

---

## **PHASE 1 COMPLETION CHECKLIST**

**All items must be checked before proceeding to Phase 2:**
- [ ] Homepage/Dashboard fully tested
- [ ] Stations page fully tested  
- [ ] Shows page fully tested (including WERU filter test)
- [ ] Playlists page fully tested
- [ ] Recordings page fully tested
- [ ] RSS Feeds page fully tested (including actual feed URL testing)
- [ ] Browse Templates page fully tested
- [ ] All navigation links tested
- [ ] All footer links tested
- [ ] Mobile responsiveness tested
- [ ] All GitHub issues created for problems found
- [ ] Testing results documented

**Only proceed to Phase 2 when ALL Phase 1 items completed through real browser testing.**