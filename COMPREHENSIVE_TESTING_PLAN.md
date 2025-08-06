# 🧪 COMPREHENSIVE BROWSER TESTING PLAN - RadioGrab v3.17.0

## 🎯 **Testing Philosophy & Approach**
**"Break the System, Find the Victory!" - Test as an adversarial QA professional**

### **Testing Method: Real Browser Testing Only**
- ✅ **Use Chrome/Chromium browser** at https://radiograb.svaha.com
- ✅ **Click every link and button** like a real user
- ✅ **Fill forms via browser interface** (not API calls)
- ✅ **Test complete user workflows** end-to-end
- ✅ **Create GitHub issues immediately** when problems found

### **Testing Credentials**
- **Test User**: `testuser123` / `NewTest123\!`
- **Test Email**: `matt@baya.net` (for verification codes if needed)

---

## 📋 **PHASE 1: PUBLIC PAGES (Unauthenticated Testing)**

### **1.1 Homepage/Dashboard** (https://radiograb.svaha.com/)
**Browser Testing Checklist:**
- [ ] Page loads without 500/404 errors
- [ ] All CSS/JavaScript resources load (check browser dev tools)
- [ ] No console errors in browser dev tools  
- [ ] Page renders correctly (no broken layout)
- [ ] Statistics display correctly
- [ ] ON-AIR indicators function (if any recordings active)
- [ ] Recent recordings section loads
- [ ] All navigation menu links clickable
- [ ] Page responsive on mobile (browser dev tools device mode)
- [ ] Hover effects work on interactive elements

**Links to Test by Clicking:**
- [ ] Logo/brand link (should go to dashboard)
- [ ] All main navigation links (Stations, Shows, Playlists, etc.)
- [ ] All footer links
- [ ] All breadcrumb links
- [ ] Any "View More" or pagination links

### **1.2 Stations Page** (/stations.php)
**Browser Testing Checklist:**
- [ ] Page loads correctly
- [ ] Station cards display properly with images
- [ ] Filter dropdown populated and functional
- [ ] Search box accepts input and filters results
- [ ] Test recording buttons work (10-second tests)
- [ ] Edit station links work
- [ ] Station health indicators display (✅/❌/⚠️)
- [ ] Pagination works if many stations
- [ ] "Add Station" button accessible

**Interactive Elements:**
- [ ] Click each "Test Recording" button on different stations
- [ ] Try filtering by different station statuses
- [ ] Test search with various station names
- [ ] Click "Edit" on different stations
- [ ] Test hover effects on station cards

### **1.3 Shows Page** (/shows.php)
**Browser Testing Checklist:**
- [ ] Shows listing loads correctly
- [ ] Station filter dropdown works
- [ ] Filter by specific station (try WERU, WYSO, etc.)
- [ ] Show active/inactive status displayed
- [ ] Edit show links work
- [ ] Schedule information accurate
- [ ] Table view toggle works (if applicable)
- [ ] Pagination works with many shows

**Specific Test Cases:**
- [ ] Select "WERU" from station dropdown and click Filter
- [ ] Verify ONLY WERU shows appear in results
- [ ] Test filter persistence across page reloads
- [ ] Try "Show All" to reset filters
- [ ] Click "Edit" on different shows

### **1.4 Playlists Page** (/playlists.php)
**Browser Testing Checklist:**
- [ ] Playlists listing displays
- [ ] "Create Playlist" button works
- [ ] Playlist cards show correct information
- [ ] Click through to individual playlists
- [ ] Test hover effects and interactions

### **1.5 Recordings Page** (/recordings.php)
**Browser Testing Checklist:**
- [ ] Recordings list displays (or "No recordings" message)
- [ ] Audio player controls work (if recordings exist)
- [ ] Download links function
- [ ] Search/filter functionality works
- [ ] Pagination works if many recordings

### **1.6 RSS Feeds Page** (/feeds.php)
**Browser Testing Checklist:**
- [ ] All feed tabs load (Universal, Station, Show, Playlist, Custom)
- [ ] Copy URL buttons work and copy to clipboard
- [ ] Feed URLs actually work when clicked
- [ ] QR codes generate properly
- [ ] "Create Custom Feed" functionality works

**Test Each Tab:**
- [ ] Universal Feeds tab - test "All Shows" and "All Playlists" links
- [ ] Station Feeds tab - test station feed links
- [ ] Show Feeds tab - test individual show feeds
- [ ] Playlist Feeds tab - test playlist feeds
- [ ] Custom Feeds tab - test custom feed creation

---

## 🔐 **PHASE 2: AUTHENTICATION TESTING**

### **2.1 Login Page** (/login.php)
**Browser Testing Checklist:**
- [ ] Form displays without database errors
- [ ] Email/username field accepts input
- [ ] Password field masks input properly
- [ ] Form validation works with empty fields
- [ ] Valid credentials work (testuser123 / NewTest123\!)
- [ ] Invalid credentials show appropriate error
- [ ] User redirected to dashboard after successful login
- [ ] "Remember me" checkbox functions (if present)
- [ ] "Forgot password" link works (if present)
- [ ] CSRF protection working

### **2.2 Registration Page** (/register.php - if exists)
**Browser Testing Checklist:**
- [ ] Registration form displays correctly
- [ ] All fields accept appropriate input
- [ ] Password strength validation works
- [ ] Email format validation works
- [ ] Form submission creates user account
- [ ] Email verification flow works
- [ ] Error handling for duplicate accounts

### **2.3 Logout Functionality**
**Browser Testing Checklist:**
- [ ] Logout button/link works
- [ ] User properly redirected after logout
- [ ] Session cleared (cannot access protected pages)
- [ ] Login required for protected content

---

## 🔒 **PHASE 3: PROTECTED PAGES (Authenticated Testing)**

### **3.1 Authenticated Dashboard Access**
**Browser Testing Checklist:**
- [ ] Login first with testuser123 credentials
- [ ] Dashboard shows "Welcome back, [name]" message
- [ ] All navigation links work when logged in
- [ ] User-specific content displays
- [ ] Logout option available

### **3.2 Settings/Admin Pages**
**Browser Testing Checklist:**
- [ ] Settings page loads (/settings.php)
- [ ] Admin password field works
- [ ] Settings changes save properly
- [ ] User preferences work
- [ ] Admin functions accessible (if admin user)

---

## 📝 **PHASE 4: FORMS AND CRUD OPERATIONS**

### **4.1 Add Station Form** (/add-station.php)
**CRITICAL: Test with REAL Radio Stations**

**Real Station Testing (MANDATORY):**
- [ ] **KEXP.org** - Test JavaScript-heavy site with complex player
- [ ] **WNYC.org** - Test NPR affiliate with standard streaming  
- [ ] **WFMU.org** - Test independent station with unique setup
- [ ] **wjffradio.org** - Test domain-only URL validation
- [ ] **Local college radio station** - Find and test a smaller station

**Form Testing for Each Station:**
- [ ] Enter website URL in browser form
- [ ] Click "Discover" button and wait for results
- [ ] Verify stream URL discovered or see clear error message
- [ ] Test manual entry if auto-discovery fails
- [ ] Fill all required fields via browser
- [ ] Click "Save" button and verify success
- [ ] Check that new station appears on stations page

**Edge Case Testing:**
- [ ] Test with malformed URLs (javascript:alert(1))
- [ ] Test with non-existent domains
- [ ] Test with empty form submission
- [ ] Test with extremely long inputs
- [ ] Test with special characters in station names

**Streaming Controls Testing:**
- [ ] Test "Default Stream Mode" dropdown
- [ ] Verify options: Inherit, Allow Downloads, Stream Only
- [ ] Save station and verify streaming setting persists

### **4.2 Edit Station Form** (/edit-station.php?id=X)
**Browser Testing Checklist:**
- [ ] Click "Edit" from stations page
- [ ] Form pre-populates with existing data
- [ ] Make changes via browser form fields
- [ ] Click "Save" and verify changes persist
- [ ] Test stream URL testing functionality
- [ ] Test logo upload/URL functionality
- [ ] Test "Delete Station" with confirmation

### **4.3 Add Show Form** (/add-show.php)
**Browser Testing Checklist:**
- [ ] Station selection dropdown populated
- [ ] Enter show name via browser
- [ ] Test schedule creation ("Mondays at 7 PM")
- [ ] Test multiple airing support ("Mon 7PM and Thu 3PM")
- [ ] Test "Find Shows" button for station schedule discovery
- [ ] Set retention settings
- [ ] Test streaming mode dropdown (inherit/allow/stream-only)
- [ ] Click "Save" and verify show created

### **4.4 Edit Show Form** (/edit-show.php?id=X)  
**Browser Testing Checklist:**
- [ ] Click "Edit" from shows page
- [ ] Form pre-populates correctly
- [ ] Modify schedule via browser interface
- [ ] Test show activation/deactivation toggle
- [ ] Test streaming controls inheritance
- [ ] Save changes and verify persistence
- [ ] Test "Delete Show" functionality

---

## 🎵 **PHASE 5: REAL STATION TESTING**

### **5.1 Station Discovery Testing**
**Test Each Real Station Through Browser:**

**KEXP.org Testing:**
- [ ] Go to Add Station page
- [ ] Enter "kexp.org" in Website URL field
- [ ] Click "Discover" button
- [ ] Wait for JavaScript processing
- [ ] Verify stream URL discovered or document error
- [ ] If fails, create GitHub issue immediately

**WNYC.org Testing:**
- [ ] Enter "wnyc.org" in Website URL field  
- [ ] Click "Discover" button
- [ ] Test NPR-style stream discovery
- [ ] Verify results or document issues

**WFMU.org Testing:**
- [ ] Test independent radio station discovery
- [ ] Check for unique streaming setup handling
- [ ] Document any discovery failures

**Domain-Only URL Testing:**
- [ ] Enter "wjffradio.org" (without http/https)
- [ ] Verify URL validation accepts domain-only format
- [ ] Test discovery functionality

### **5.2 Show Schedule Discovery**
**Browser Testing:**
- [ ] After adding station, go to Add Show page
- [ ] Select the added station from dropdown
- [ ] Click "Find Shows" button (if available)
- [ ] Test automatic show schedule discovery
- [ ] Verify shows discovered or error handling
- [ ] Test clicking "Add" on discovered shows

---

## 🔥 **PHASE 6: DESTRUCTIVE/SECURITY TESTING**

### **6.1 XSS Testing via Browser Forms**
**Test in Add Station Form:**
- [ ] Enter `<script>alert('XSS')</script>` in station name field
- [ ] Submit form via browser
- [ ] Verify script does NOT execute
- [ ] Check that input is properly escaped on stations page

**Test in Show Forms:**
- [ ] Enter malicious scripts in show description
- [ ] Verify proper input sanitization
- [ ] Test HTML injection attempts

### **6.2 SQL Injection Testing via Browser**
**Test in Search Fields:**
- [ ] Enter `'; DROP TABLE stations; --` in station search
- [ ] Verify search fails gracefully without database errors
- [ ] Test various SQL injection payloads

### **6.3 CSRF Testing**
**Test Forms Without Valid Tokens:**
- [ ] Open browser dev tools
- [ ] Modify or remove CSRF token from form
- [ ] Submit form and verify it's rejected
- [ ] Test with expired tokens

### **6.4 Access Control Testing**
**Test Unauthorized Access:**
- [ ] Logout and try to access protected pages directly
- [ ] Verify proper redirect to login page
- [ ] Test accessing admin functions as regular user
- [ ] Try accessing other users' data (if multi-user)

---

## 🔧 **PHASE 7: API ENDPOINTS VIA BROWSER**

### **7.1 Test Recording API via Browser**
**Use Station Test Buttons:**
- [ ] Click "Test Recording" button on different stations
- [ ] Verify 10-second test recordings work
- [ ] Check for proper success/error messages
- [ ] Wait for recording completion
- [ ] Verify test file created and playable

### **7.2 RSS Feed API via Browser**
**Test Feed URLs by Clicking:**
- [ ] Go to RSS Feeds page
- [ ] Click each feed URL directly
- [ ] Verify XML format loads correctly
- [ ] Test feed URLs in podcast apps
- [ ] Check feed validation (if available)

### **7.3 Station Discovery API via Browser Forms**
**Already covered in Phase 4.1 - Add Station Form testing**

---

## ⚠️ **PHASE 8: EDGE CASES AND ERROR HANDLING**

### **8.1 Network Error Testing**
**Test with Slow/Broken Networks:**
- [ ] Use browser dev tools to throttle network
- [ ] Test form submissions with slow connections
- [ ] Test discovery with network interruptions
- [ ] Verify proper error messages display

### **8.2 Large Data Testing**
**Test System Limits:**
- [ ] Enter very long station names (1000+ characters)
- [ ] Test with many stations/shows loaded
- [ ] Test pagination with large datasets
- [ ] Check performance with extensive data

### **8.3 Browser Compatibility**
**Test in Different Browsers:**
- [ ] Test core functionality in Chrome
- [ ] Test in Firefox (if available)
- [ ] Test in Safari (if on Mac)
- [ ] Test mobile responsiveness

### **8.4 JavaScript Disabled Testing**
**Test Graceful Degradation:**
- [ ] Disable JavaScript in browser settings
- [ ] Test core functionality still works
- [ ] Verify forms still submit
- [ ] Check for proper fallbacks

---

## 📊 **TESTING SUCCESS CRITERIA**

### **Required Testing Coverage:**
- ✅ ALL pages in checklist tested via browser
- ✅ ALL navigation links clicked and verified
- ✅ ALL forms tested with valid and invalid data
- ✅ ALL real radio stations tested (KEXP, WNYC, WFMU, wjffradio)
- ✅ ALL destructive/security scenarios attempted
- ✅ ALL issues found documented as GitHub issues
- ✅ ALL user workflows tested end-to-end

### **GitHub Issue Creation Protocol:**
**IMMEDIATE issue creation when ANY problem found:**
1. **Stop testing current feature**
2. **Create GitHub issue immediately** using:
   ```bash
   gh issue create --title "Problem Title" --body "Detailed description" --label bug
   ```
3. **Include exact reproduction steps from browser testing**
4. **Continue testing other features** (don't fix immediately)
5. **Only fix all issues after complete testing cycle**

### **Testing Documentation Format:**
```markdown
## Browser Testing Results - [Date]

### 🧪 Testing Approach
- Browser: Chrome/Chromium
- Method: Real user workflows, clicking every link/button
- Real Stations Tested: [List stations]

### 📋 Pages/Features Tested
- [x] Dashboard - ✅ All links work, displays correctly
- [x] Add Station - ❌ KEXP discovery fails (Issue #XX created)
- [List ALL pages with results]

### 🐛 Issues Found (GitHub Issues Created)
1. Issue #XX: [Title and brief description]
2. Issue #YY: [Title and brief description]

### ✅ Successful Tests
- [List what worked correctly]
- [Confirms no regressions]

### 📊 Statistics  
- Pages tested: X/Y
- Links clicked: X
- Forms tested: X  
- Real stations tested: X
- Issues found: X (VICTORY!)
```

---

## 🚨 **CRITICAL REMINDERS**

1. **USE BROWSER ONLY** - No curl/API shortcuts
2. **CLICK EVERY LINK** - Don't assume anything works
3. **CREATE GITHUB ISSUES IMMEDIATELY** - When problems found
4. **TEST REAL STATIONS** - KEXP, WNYC, WFMU are mandatory
5. **DOCUMENT EVERYTHING** - Every test result matters
6. **BREAK THE SYSTEM** - QA mindset, find problems

**"If it's not tested like a real user with real browser clicks, it's not tested!"**

---

This comprehensive plan follows TESTING.md requirements for complete browser-based testing of every page, link, and feature in RadioGrab.