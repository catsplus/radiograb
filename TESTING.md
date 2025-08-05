# Testing Guidelines for RadioGrab Development

## 🎯 Overview

This document establishes comprehensive testing requirements for all RadioGrab development work. Every code change must be thoroughly tested using real user workflows to ensure reliability and quality.

**🏆 TESTING PHILOSOPHY: "Break the System, Find the Victory!"**
Your goal is to BREAK the site and FIND errors - every bug found is a VICTORY! Approach testing like a Quality Assurance expert whose job is to find problems. Test like an adversarial user trying to break things.

## 🚨 CRITICAL TESTING REQUIREMENTS

### **🔥 MANDATORY: Test EVERYTHING - No Exceptions**
**YOU MUST TEST ALL LINKS, ALL PAGES, ALL FEATURES, AND ALL FAILURE SCENARIOS WITHOUT EXCEPTION.**

- **Test ALL navigation menu links** - click every single link in the main menu
- **Test ALL page functionality** - every button, form, modal, dropdown, filter, search
- **Test ALL user workflows** - complete end-to-end user journeys 
- **Test ALL interactive elements** - hover effects, clicks, form submissions, validations
- **Test ALL error conditions** - invalid inputs, missing data, network failures, edge cases
- **Test ALL form variations** - valid data, invalid data, missing data, malformed data
- **Test ALL discovery features** - enter real radio stations, test auto-discovery, test failures
- **DO NOT assume API calls working means the page works** - test the actual user interface
- **DO NOT skip any pages or features** - comprehensive means COMPREHENSIVE
- **ACTIVELY TRY TO BREAK THINGS** - test boundary conditions, unusual inputs, error scenarios

### **1. Always Test as a Real User Using Chrome/Chromium Browser**
- Use the actual web browser interface at https://radiograb.svaha.com
- Follow complete user workflows from start to finish  
- Never assume functionality works - verify it through actual usage
- Test using the same methods and interfaces that end users would use
- **Click every single link and button on every page**
- **Fill out and submit every form with both valid and invalid data**
- **Test real radio stations** (KEXP, WNYC, WFMU, local stations, etc.)
- **Test edge cases** (empty forms, malicious input, broken URLs, non-existent stations)
- **Test discovery features** (auto-discovery buttons, manual entry, error handling)
- **Test all interactive elements** (modals, dropdowns, filters, search, pagination)

### **2. Systematic Page-by-Page Testing Protocol**
- **Test immediately after implementing each feature/fix**
- **Test ALL navigation links first** - ensure every menu item loads without errors
- **Test ALL page functionality** - forms, buttons, modals, filters, search, etc.
- **Test ALL workflows end-to-end** - complete user journeys from start to finish
- **Create GitHub issues for ALL problems found** - do not fix immediately
- **Continue testing other features** - complete full testing cycle before fixing
- **Fix ALL issues only after complete testing cycle**

### **3. Quality Assurance (QA) Testing Approach**
**Adopt the mindset of a professional QA tester whose job is to find problems:**

#### **🎯 QA Testing Strategy**
- **Adversarial Testing**: Try to break the system intentionally
- **Boundary Testing**: Test limits (very long inputs, special characters, edge cases)
- **User Experience Testing**: Test as different user types (new users, experienced users, admin users)
- **Error Path Testing**: Test all error conditions and recovery paths
- **Integration Testing**: Test how features work together
- **Regression Testing**: Ensure existing features still work after changes

#### **🔍 Real-World Station Testing**
**MANDATORY: Test with real radio stations every time:**
- **KEXP.org** - Test JavaScript-heavy site with complex player
- **WNYC.org** - Test NPR affiliate with standard streaming
- **WFMU.org** - Test independent station with unique setup
- **wjffradio.org** - Test domain-only URL validation
- **Local college radio** - Test smaller stations with simpler sites
- **Broken/invalid URLs** - Test error handling with non-existent domains
- **Malformed URLs** - Test with invalid formats, special characters

#### **🔥 GitHub Issue Creation Protocol** 
**CRITICAL: Every bug found must become a GitHub issue immediately:**

**When you find ANY problem during testing:**
1. **Stop testing immediately**
2. **Document the exact problem** (URL, steps to reproduce, expected vs actual behavior)
3. **Create a GitHub issue** with detailed reproduction steps
4. **Label the issue appropriately** (bug, enhancement, critical, etc.)
5. **Continue testing other features** - do NOT fix immediately
6. **Only fix issues after completing full testing cycle**

**Example GitHub Issue Format:**
```
Title: Add Station form breaks with KEXP.org - JavaScript streaming discovery fails

Description:
- URL: https://radiograb.svaha.com/add-station.php
- Steps to reproduce: 
  1. Enter "kexp.org" in website URL field
  2. Click "Discover" button
  3. Observe results
- Expected: Stream URL and station info discovered
- Actual: Discovery fails with no streaming URL found
- Impact: Major radio stations cannot be auto-discovered
- Priority: High
```

#### **💥 Destructive Testing Scenarios**
**Try to break things with these test cases:**
- Submit forms with empty required fields
- Submit forms with extremely long text (1000+ characters)
- Submit forms with HTML/JavaScript code in text fields
- Submit forms with special characters (quotes, semicolons, Unicode)
- Try to access protected pages without authentication
- Test with invalid IDs in URLs (/edit-station.php?id=999999)
- Test with SQL injection attempts in form fields
- Test concurrent operations (multiple form submissions)
- Test network interruption scenarios (slow connections)

### **4. Production Environment Testing Requirements**
- **Always test on the live production site** (https://radiograb.svaha.com)
- **Use the deployed version, not just local development**
- **Verify changes work in the actual Docker container environment**
- **Test with real data and real user scenarios**
- **Test both authenticated and unauthenticated user flows**

### **🚨 CRITICAL: File Deployment Issues Protocol**
**When ANY error indicates incorrect file deployment (parse errors, missing functions, etc.):**

1. **IMMEDIATELY stop all testing**
2. **Perform FULL deployment** - `./deploy-from-git.sh` (NOT --quick)
3. **Wait for complete container rebuild** (10+ minutes if needed)
4. **Verify all containers are healthy** before continuing
5. **NEVER use shortcuts when deployment issues detected**
6. **Only continue testing after FULL system restart**

**Examples of deployment-related errors that trigger FULL rebuild:**
- PHP Parse errors (syntax errors in deployed files)
- Function redeclaration errors  
- Missing function/class errors
- File not found errors for files that exist in repo
- Any error suggesting files weren't properly updated

## 🧪 COMPREHENSIVE TESTING CHECKLIST

### **📋 Complete Page Testing Protocol**
**EVERY page must be tested with this exact checklist:**

1. **Page Load Test**
   - ✅ Page loads without 500/404 errors
   - ✅ All CSS and JavaScript resources load
   - ✅ No console errors in browser dev tools
   - ✅ Page renders correctly (no broken layout)

2. **Navigation Test**  
   - ✅ All navigation menu links work
   - ✅ Breadcrumbs function correctly
   - ✅ Back/forward browser buttons work
   - ✅ All page-specific links and buttons work

3. **Form Testing (if applicable)**
   - ✅ Form loads without errors
   - ✅ All form fields accept input
   - ✅ Form validation works with invalid data
   - ✅ Form submission works with valid data
   - ✅ Success/error messages display correctly
   - ✅ CSRF tokens function properly

4. **Interactive Elements**
   - ✅ All buttons clickable and functional
   - ✅ All dropdowns open and close
   - ✅ All modals open and close properly
   - ✅ All filters and search features work
   - ✅ All AJAX calls complete successfully

5. **Authentication & Database Connection Tests**
   - ✅ Database connection works (no "Database error" messages)
   - ✅ Login form loads without database errors
   - ✅ Login form accepts credentials and validates properly
   - ✅ Registration form works (if applicable)
   - ✅ Password reset flow functions (if applicable)
   - ✅ Authenticated access works as intended  
   - ✅ Proper redirects to login when required
   - ✅ Access control enforced correctly
   - ✅ Session management works (login/logout cycle)
   - ✅ Admin access controls function properly

### **📋 Comprehensive Page-by-Page Testing Requirements**
**Test EVERY page in this exact order with FULL QA approach:**

#### **🏠 Public Pages (Test Unauthenticated)**
- [ ] **Dashboard** (/) 
  - Statistics display correctly
  - All navigation links work
  - ON-AIR indicators function
  - Recent recordings load
  - Page responsive on mobile
  
- [ ] **Stations** (/stations.php)
  - Station cards display properly  
  - Filter by status works
  - Test recording buttons function
  - Edit station links work
  - Station health indicators accurate
  - Test with stations in different states
  
- [ ] **Shows** (/shows.php)
  - Show listings load correctly
  - Station filter works (test with specific station)
  - Show active/inactive toggle functions
  - Edit show links work
  - Schedule display accurate
  - Test pagination if many shows
  
- [ ] **Playlists** (/playlists.php)
  - Playlist listings display
  - Create playlist function works
  - Upload audio files works
  - Edit playlist functions
  - Delete playlist with confirmation
  
- [ ] **RSS Feeds** (/feeds.php)
  - All feed tabs load (Universal, Station, Show, Playlist, Custom)
  - Copy URL buttons work
  - Feed URLs actually work when accessed
  - QR codes generate properly
  - Custom feed creation works
  
- [ ] **Login** (/login.php)
  - Form displays without errors
  - Valid credentials work
  - Invalid credentials show error
  - Password field masks input
  - Remember me checkbox functions

#### **➕ Form Pages (COMPREHENSIVE Form Testing)**
- [ ] **Add Station** (/add-station.php)
  - **Test with REAL stations**: KEXP.org, WNYC.org, WFMU.org, wjffradio.org
  - Domain-only URL validation works (wjffradio.org)
  - Auto-discovery button functions
  - Discovery shows results or clear error messages
  - Manual entry works when discovery fails
  - Form validation prevents empty submissions
  - Test with malformed URLs
  - Test with non-existent domains
  - CSRF protection works
  - **CRITICAL**: Test JavaScript discovery with complex sites
- [ ] **Edit Station** (/edit-station.php?id=X)
  - Form pre-populates with existing data
  - Changes save correctly
  - Stream testing works
  - Logo upload/URL functions
  - Delete station with confirmation
  - Test with different station types
  
- [ ] **Add Show** (/add-show.php)
  - Schedule creation works
  - Station selection dropdown populated
  - Natural language schedule parsing ("Mondays at 7 PM")
  - Multiple airing support ("Mon 7PM and Thu 3PM")
  - Show calendar discovery functions
  - Retention settings work
  
- [ ] **Edit Show** (/edit-show.php?id=X)
  - Pre-population works
  - Schedule modification saves
  - Show activation/deactivation works
  - Recording history displays
  - Delete show with confirmation

#### **🔒 Protected Pages (Test with Login)**  
- [ ] **Recordings** (/recordings.php)
  - Audio files list correctly
  - Playback controls work
  - Download links function
  - Search/filter works
  - Pagination works with many recordings
  - Streaming vs download controls work (once implemented)
  
- [ ] **Settings/Admin** (various admin pages)
  - Admin access control enforced
  - Settings changes save properly
  - User management functions
  - System health monitoring

#### **🔧 API Endpoints (Test Via Browser Forms)**
- [ ] **Station Discovery** (/api/discover-station.php)
  - Test through Add Station form with real stations
  - Verify CSRF token handling
  - Test error responses for broken sites
  - Test response time with slow sites
  
- [ ] **Test Recording** (/api/test-recording.php)  
  - Test through station buttons
  - Verify 10-second test recordings work
  - Test with different stream types
  - Test error handling for broken streams
  
- [ ] **Enhanced RSS Feeds** (/api/enhanced-feeds.php)
  - Test all feed types via RSS page
  - Verify XML format correctness
  - Test with browsers and podcast apps
  - Test feed performance with large datasets

## 🏆 Testing Success Criteria

### **📝 Required Testing Documentation**
**For EVERY testing session, you MUST document:**

#### **🎯 Testing Summary Format**
```markdown
## QA Testing Results - [Date]

### 🧪 Testing Approach
- **Testing Philosophy**: Adversarial QA testing to find and break functionality
- **Real Station Testing**: [List stations tested - KEXP, WNYC, etc.]
- **Destructive Testing**: [Edge cases and error scenarios tested]

### 🔍 Pages/Features Tested
- [X] Dashboard - ✅ All functions work
- [X] Add Station - ❌ KEXP discovery fails (GitHub Issue #XX created)
- [X] Shows page - ✅ Filtering works correctly
- [List ALL pages tested with results]

### 🐛 Issues Found (GitHub Issues Created)
1. **Issue #XX**: Add Station fails with KEXP.org
   - Status: GitHub issue created
   - Priority: High
   - Impact: Major radio stations cannot be discovered

2. **Issue #YY**: Shows filter doesn't persist on page reload
   - Status: GitHub issue created  
   - Priority: Medium
   - Impact: User experience degradation

### ✅ Successful Test Cases
- Domain-only URL validation (wjffradio.org) works correctly
- Login/logout cycle functions properly
- Station filtering maintains state correctly
- [List successful tests to confirm no regressions]

### 🚨 Critical Issues Requiring Immediate Attention
- [List any critical bugs that break core functionality]

### 📊 Testing Statistics
- Pages tested: X/Y
- Forms tested: X/Y  
- Real stations tested: X
- Issues found: X
- GitHub issues created: X
```

### **🔥 Key Testing Metrics**
**Track these metrics for every testing session:**
- **Coverage**: Percentage of pages/features tested
- **Issues Found**: Total bugs discovered (victory metric!)
- **Real Station Success Rate**: Percentage of real stations that work with discovery
- **Form Validation Coverage**: Percentage of forms tested with invalid data
- **Error Scenario Coverage**: Percentage of error conditions tested

### **💯 Testing Completion Criteria**
**Testing is only complete when:**
- ✅ ALL pages in the checklist have been tested
- ✅ ALL forms tested with both valid and invalid data
- ✅ ALL real radio stations tested (KEXP, WNYC, WFMU, etc.)
- ✅ ALL issues found have been documented as GitHub issues
- ✅ ALL destructive/edge case scenarios attempted
- ✅ ALL existing functionality verified (regression testing)
- ✅ Testing documentation completed and shared

---

## 💡 Remember: "Breaking the System is Victory!"

**"If it's not tested like an adversarial user trying to break it, it's not tested."**

Every bug found during testing is a victory that prevents user-facing problems. Quality software requires comprehensive, destructive testing. Take the time to truly break things - your users will thank you for it.

### **🎛️ Forms and User Input**
**Test ALL of the following:**
- ✅ Form submission with valid data
- ✅ Form validation with invalid/missing data  
- ✅ Error message display and clarity
- ✅ Field validation (required fields, format validation, length limits)
- ✅ CSRF token functionality
- ✅ Success/failure feedback to users
- ✅ Edge cases (empty fields, special characters, very long input)
- ✅ Browser back/forward button behavior
- ✅ Form persistence/clearing behavior

**Example Test Cases:**
```
Station Form Testing:
1. Submit with all fields filled correctly
2. Submit with missing required fields
3. Submit with invalid URL formats
4. Test auto-discovery functionality
5. Test manual entry workflow
6. Verify error messages are helpful and clear
7. Test form reset/cancel functionality
```

### **🔍 Search and Filtering**
**Test ALL filter combinations:**
- ✅ Each filter individually
- ✅ Multiple filters combined
- ✅ Filter reset/clear functionality
- ✅ Filter persistence across page reloads
- ✅ Empty result states
- ✅ Search with various query types (partial matches, special characters)
- ✅ Sorting options and order changes
- ✅ Pagination if applicable

**Critical Test Case:**
```
Shows Page Filtering (station_id=3 example):
1. Go to shows page
2. Select "WERU" from station dropdown
3. Click Filter button
4. Verify ONLY WERU shows appear in results
5. Test other filter combinations
6. Verify filter persistence
```

### **🎨 User Interface Changes**
**Verify ALL interactions:**
- ✅ Hover effects and animations
- ✅ Button click responses and states
- ✅ Modal open/close functionality
- ✅ Responsive design on different screen sizes
- ✅ Loading states and progress indicators
- ✅ Error state displays
- ✅ Accessibility (tab navigation, screen reader compatibility)
- ✅ Cross-browser compatibility (Chrome, Firefox, Safari)

### **📡 API Endpoints and AJAX**
**Test ALL scenarios:**
- ✅ Successful API responses
- ✅ Error responses and handling
- ✅ Network timeout scenarios
- ✅ Invalid input handling
- ✅ Authentication/authorization
- ✅ Rate limiting if applicable
- ✅ Loading states in UI during API calls

### **🔐 Authentication and Security**
**CRITICAL: Authentication Must Be Tested First - Before All Other Features**

**Database Connection Testing:**
- ✅ Login page loads without "Database error" messages
- ✅ Registration page loads without database errors
- ✅ Password reset page functions without database errors
- ✅ All database-dependent pages load successfully

**Login System Testing:**
- ✅ Login form displays and accepts input
- ✅ Valid credentials authenticate successfully
- ✅ Invalid credentials show appropriate error message
- ✅ User is redirected to appropriate page after login
- ✅ Session persists across page refreshes and navigation
- ✅ Logout functionality clears session properly

**Registration Testing (if enabled):**
- ✅ Registration form validates input properly
- ✅ Password requirements are enforced
- ✅ User creation succeeds with valid data
- ✅ Email verification flow works (if enabled)
- ✅ Duplicate email/username handling works

**Access Control Testing:**
- ✅ Protected pages redirect to login when unauthenticated
- ✅ Authenticated users can access appropriate pages
- ✅ Admin-only pages restrict non-admin users
- ✅ CSRF protection works on forms
- ✅ Session timeout behavior is appropriate

**User Experience Testing:**
- ✅ Login/logout process is intuitive
- ✅ Error messages are clear and helpful
- ✅ Password reset flow is user-friendly
- ✅ User profile/settings pages function correctly

## 🧪 Comprehensive Testing Workflows

### **Before Starting Development**
1. **Understand the Current State**
   - Test existing functionality before making changes
   - Document current behavior
   - Identify potential areas of impact

### **During Development** 
1. **Test Each Component**
   - Implement one feature/fix at a time
   - Test immediately after each change
   - Verify both new and existing functionality
   - Fix issues before proceeding

### **After Implementation**
1. **Complete User Journey Testing**
   - Test the entire user workflow end-to-end
   - Include all relevant user personas and scenarios
   - Test error conditions and edge cases
   - Verify integration with existing features

### **Before Deployment**
1. **Final Verification**
   - Test all changes on production environment
   - Verify no regressions in existing functionality
   - Confirm all requirements are met
   - Document any known limitations or issues

## 📝 Testing Documentation Requirements

### **For Each Feature/Fix**
Document the following:

```markdown
## Testing Performed

### ✅ Test Cases Executed
- [List specific tests performed]
- [Include both positive and negative test cases]
- [Note any edge cases tested]

### 🐛 Issues Found and Fixed
- [Document any bugs discovered during testing]
- [Explain how each issue was resolved]

### 🚀 Production Verification
- [Confirm functionality works on live site]
- [Include specific URLs tested]
- [Note any browser-specific considerations]
```

## 🚨 Common Testing Failures to Avoid

### **❌ Don't Do This:**
- Assume form validation works without testing invalid inputs
- Test only the "happy path" without error conditions  
- Make changes without verifying on production site
- Test components in isolation without integration testing
- Skip testing of existing functionality after changes
- Rely on code review alone without functional testing

### **✅ Do This Instead:**
- Test both valid and invalid inputs thoroughly
- Include error conditions and edge cases in testing
- Always verify functionality on the live production site
- Test the complete user workflow from start to finish
- Verify existing features still work after changes
- Combine code review with comprehensive functional testing

## 🎯 Testing Prompt Templates

### **For Feature Development:**
```
Implement [feature description]. 

TESTING REQUIREMENTS:
- Test using the live site at https://radiograb.svaha.com
- Verify complete user workflows end-to-end
- Test error conditions and edge cases
- Confirm existing functionality still works
- Report testing results and any issues found/fixed

Do not proceed to the next task until testing is complete and successful.
```

### **For Bug Fixes:**
```
Fix [bug description].

TESTING REQUIREMENTS:
- Reproduce the exact issue described
- Implement and test the fix
- Verify the fix resolves the original problem
- Test related functionality to ensure no regressions
- Use the live production site for all testing

Confirm the bug is resolved before marking as complete.
```

### **For UI/UX Changes:**
```
Implement [UI changes].

TESTING REQUIREMENTS:
- Test all interactive elements using actual browser
- Verify responsive design on different screen sizes
- Test hover effects, animations, and transitions
- Confirm accessibility and usability
- Test with real user data and scenarios

Ensure the user experience meets requirements before proceeding.
```

## 🔧 Testing Tools and Environment

### **Required Testing Environment:**
- **Live Production Site:** https://radiograb.svaha.com
- **Browser Testing:** Chrome, Firefox, Safari (minimum)
- **Device Testing:** Desktop and mobile viewports
- **Network Conditions:** Test with normal and slow connections

### **Testing Data:**
- Use real production data when possible
- Test with various data states (empty, minimal, extensive)
- Include edge cases in test data (special characters, long text, etc.)

### **Documentation:**
- Keep testing notes and results
- Document any workarounds or known issues
- Update this testing guide based on lessons learned

## 📊 Quality Gates

### **No deployment without:**
- ✅ All functional requirements tested and working
- ✅ Error conditions handled gracefully
- ✅ User experience verified on live site
- ✅ No regressions in existing functionality
- ✅ Cross-browser compatibility confirmed
- ✅ Mobile responsiveness verified (if applicable)

### **Definition of Done:**
A feature/fix is only complete when:
1. Code is implemented correctly
2. All testing requirements are met
3. Production deployment is successful
4. End-to-end user workflows are verified
5. No critical issues remain unresolved

---

## 💡 Remember

**"If it's not tested in production with real user workflows, it's not done."**

Quality software requires comprehensive testing. Taking time to test thoroughly prevents user-facing bugs and maintains system reliability. Every bug caught in testing is a bug that doesn't impact users.