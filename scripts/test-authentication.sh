#!/bin/bash
#
# RadioGrab Authentication Testing Script
# Tests authentication system after deployment
#

echo "🔐 RadioGrab Authentication Testing"
echo "=================================="

BASE_URL="https://radiograb.svaha.com"

echo "1. Testing login page accessibility..."
LOGIN_RESPONSE=$(curl -s -w "%{http_code}" "$BASE_URL/login.php")
HTTP_CODE="${LOGIN_RESPONSE: -3}"

if [ "$HTTP_CODE" = "200" ]; then
    echo "   ✅ Login page accessible (HTTP $HTTP_CODE)"
else
    echo "   ❌ Login page not accessible (HTTP $HTTP_CODE)"
    exit 1
fi

echo "2. Checking for database errors..."
if echo "$LOGIN_RESPONSE" | grep -qi "database.*error"; then
    echo "   ❌ Database error found on login page!"
    echo "$LOGIN_RESPONSE" | grep -i "database.*error"
    exit 1
else
    echo "   ✅ No database errors on login page"
fi

echo "3. Testing login form presence..."
if echo "$LOGIN_RESPONSE" | grep -q "email_or_username"; then
    echo "   ✅ Login form found"
else
    echo "   ❌ Login form not found"
    exit 1
fi

echo "4. Testing database connection via PHP..."
DB_TEST=$(ssh radiograb@167.71.84.143 "docker exec radiograb-web-1 php -r \"
try { 
    \\\$pdo = new PDO('mysql:host=mysql;dbname=radiograb', 'radiograb', 'radiograb_pass_2024'); 
    echo 'SUCCESS'; 
} catch(Exception \\\$e) { 
    echo 'FAILED: ' . \\\$e->getMessage(); 
}\"")

if [[ "$DB_TEST" == "SUCCESS" ]]; then
    echo "   ✅ Database connection working"
else
    echo "   ❌ Database connection failed: $DB_TEST"
    exit 1
fi

echo "5. Testing users table access..."
USERS_TEST=$(ssh radiograb@167.71.84.143 "docker exec radiograb-mysql-1 mysql -u radiograb -pradiograb_pass_2024 radiograb -e 'SELECT COUNT(*) as user_count FROM users;' 2>/dev/null | tail -1")

if [[ "$USERS_TEST" =~ ^[0-9]+$ ]]; then
    echo "   ✅ Users table accessible ($USERS_TEST users)"
else
    echo "   ❌ Users table not accessible"
    exit 1
fi

echo "6. Testing session table access..."
SESSIONS_TEST=$(ssh radiograb@167.71.84.143 "docker exec radiograb-mysql-1 mysql -u radiograb -pradiograb_pass_2024 radiograb -e 'SELECT COUNT(*) as session_count FROM user_sessions;' 2>/dev/null | tail -1")

if [[ "$SESSIONS_TEST" =~ ^[0-9]+$ ]]; then
    echo "   ✅ User sessions table accessible ($SESSIONS_TEST sessions)"
else
    echo "   ❌ User sessions table not accessible"
    exit 1
fi

echo
echo "✅ Authentication system tests passed!"
echo "🔐 Login system should be working properly"
echo "🌐 Test manually at: $BASE_URL/login.php"
echo

# Optional: Test a real login if test credentials exist
echo "7. Manual testing instructions:"
echo "   - Visit $BASE_URL/login.php"
echo "   - Verify no 'Database error' messages appear"
echo "   - Try logging in with valid credentials"
echo "   - Check that login/logout cycle works"
echo