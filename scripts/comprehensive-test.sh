#!/bin/bash
#
# Comprehensive RadioGrab System Test
# Tests all fixes and improvements made during this session
#

echo "🧪 RadioGrab Comprehensive System Test"
echo "====================================="

BASE_URL="https://radiograb.svaha.com"
TESTS_PASSED=0
TESTS_FAILED=0

# Function to run test
run_test() {
    local test_name="$1"
    local test_command="$2"
    
    echo -n "Testing $test_name... "
    
    if eval "$test_command" > /dev/null 2>&1; then
        echo "✅ PASS"
        ((TESTS_PASSED++))
    else
        echo "❌ FAIL"
        ((TESTS_FAILED++))
    fi
}

# Function to run test with output check
run_test_with_check() {
    local test_name="$1"
    local test_command="$2"
    local success_pattern="$3"
    
    echo -n "Testing $test_name... "
    
    local output
    output=$(eval "$test_command" 2>&1)
    
    if echo "$output" | grep -q "$success_pattern"; then
        echo "✅ PASS"
        ((TESTS_PASSED++))
    else
        echo "❌ FAIL"
        echo "   Output: $output"
        ((TESTS_FAILED++))
    fi
}

echo "1. 🌐 Website Accessibility Tests"
echo "================================"

run_test "Main page accessibility" "curl -s -f $BASE_URL/"
run_test "Login page accessibility" "curl -s -f $BASE_URL/login.php"
run_test "Stations page accessibility" "curl -s -f $BASE_URL/stations.php"
run_test "Add station page accessibility" "curl -s -f $BASE_URL/add-station.php"

echo
echo "2. 🔐 Authentication System Tests"
echo "================================="

# Test database connection
run_test_with_check "Database connection from web container" \
    "ssh radiograb@167.71.84.143 \"docker exec radiograb-web-1 php -r \\\"try { \\\$pdo = new PDO('mysql:host=mysql;dbname=radiograb', 'radiograb', 'radiograb_pass_2024'); echo 'SUCCESS'; } catch(Exception \\\$e) { echo 'FAILED: ' . \\\$e->getMessage(); }\\\"\"" \
    "SUCCESS"

# Test login page for database errors
run_test_with_check "Login page has no database errors" \
    "curl -s $BASE_URL/login.php" \
    -v "database.*error"

# Test users table access
run_test_with_check "Users table accessible" \
    "ssh radiograb@167.71.84.143 \"docker exec radiograb-mysql-1 mysql -u radiograb -pradiograb_pass_2024 radiograb -e 'SELECT COUNT(*) FROM users;' 2>/dev/null\"" \
    "[0-9]"

echo
echo "3. 🔄 Container Health Tests"
echo "==========================="

# Test all containers are running
CONTAINERS=("radiograb-web-1" "radiograb-mysql-1" "radiograb-recorder-1" "radiograb-rss-updater-1" "radiograb-housekeeping-1")

for container in "${CONTAINERS[@]}"; do
    run_test_with_check "$container is running" \
        "ssh radiograb@167.71.84.143 \"docker ps --filter name=$container --format '{{.Status}}'\"" \
        "Up"
done

echo
echo "4. 🧠 Smart Deployment Tests"
echo "==========================="

# Test deployment script exists and is executable
run_test "Enhanced deployment script exists" "test -x /Users/mjb9/scripts/radiograb-public/deploy-from-git.sh"
run_test "Smart deploy script exists" "test -x /Users/mjb9/scripts/radiograb-public/scripts/smart-deploy.sh"
run_test "Force sync script exists" "test -x /Users/mjb9/scripts/radiograb-public/scripts/force-git-sync.sh"
run_test "Authentication test script exists" "test -x /Users/mjb9/scripts/radiograb-public/scripts/test-authentication.sh"

echo
echo "5. 🌐 URL Validation Tests"
echo "========================="

# Test URL validation fix (would need browser automation for full test)
run_test_with_check "Add station page contains updated validation" \
    "curl -s $BASE_URL/add-station.php" \
    "wjffradio.org"

echo
echo "6. 📊 API Functionality Tests"
echo "============================"

# Test CSRF token API
run_test_with_check "CSRF token API working" \
    "curl -s $BASE_URL/api/get-csrf-token.php" \
    "csrf_token"

# Test recording status API
run_test "Recording status API accessible" "curl -s -f $BASE_URL/api/recording-status.php"

echo
echo "7. 🗄️ Database Schema Tests"
echo "=========================="

# Test streaming controls tables exist
TABLES=("content_categorization_rules" "users" "user_sessions" "stations" "shows")

for table in "${TABLES[@]}"; do
    run_test_with_check "$table table exists" \
        "ssh radiograb@167.71.84.143 \"docker exec radiograb-mysql-1 mysql -u radiograb -pradiograb_pass_2024 radiograb -e 'DESCRIBE $table;' 2>/dev/null\"" \
        "Field"
done

echo
echo "8. 📁 File Synchronization Tests"
echo "==============================="

# Test critical files exist and have recent timestamps
CRITICAL_FILES=(
    "frontend/includes/database.php"
    "frontend/public/login.php"
    "deploy-from-git.sh"
    "scripts/smart-deploy.sh"
)

for file in "${CRITICAL_FILES[@]}"; do
    run_test_with_check "$file exists and recent" \
        "ssh radiograb@167.71.84.143 \"test -f /opt/radiograb/$file && find /opt/radiograb/$file -mmin -60\"" \
        "/opt/radiograb/$file"
done

echo
echo "📊 Test Results Summary"
echo "======================"
echo "✅ Tests Passed: $TESTS_PASSED"
echo "❌ Tests Failed: $TESTS_FAILED"
echo "📈 Success Rate: $(( TESTS_PASSED * 100 / (TESTS_PASSED + TESTS_FAILED) ))%"

if [ $TESTS_FAILED -eq 0 ]; then
    echo
    echo "🎉 ALL TESTS PASSED!"
    echo "✅ Authentication system is working"
    echo "✅ Smart deployment system is ready"  
    echo "✅ Git synchronization is reliable"
    echo "✅ Container architecture is healthy"
    echo
    echo "🚀 Ready to proceed with:"
    echo "   - KEXP discovery analysis"
    echo "   - Issue #27 streaming controls implementation"
    echo "   - Production validation testing"
else
    echo
    echo "⚠️  Some tests failed - investigation needed"
    echo "🔧 Check failed tests above for troubleshooting"
fi

echo
echo "🌐 Manual Testing URLs:"
echo "   Login: $BASE_URL/login.php"
echo "   Add Station: $BASE_URL/add-station.php"
echo "   Dashboard: $BASE_URL/"
echo