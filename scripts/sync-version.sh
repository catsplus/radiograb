#!/bin/bash
##
# RadioGrab Version Synchronization Script
# Syncs version between VERSION file and database
##

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}📝 RadioGrab Version Synchronization${NC}"
echo "=================================="

# Check if VERSION file exists
if [ ! -f "/opt/radiograb/VERSION" ]; then
    echo -e "${RED}❌ VERSION file not found${NC}"
    exit 1
fi

# Extract version from VERSION file
VERSION_CONTENT=$(cat /opt/radiograb/VERSION)
VERSION=$(echo "$VERSION_CONTENT" | grep -oE 'v[0-9]+\.[0-9]+\.[0-9]+' | head -1)

if [ -z "$VERSION" ]; then
    echo -e "${RED}❌ Could not extract version from VERSION file${NC}"
    echo "Content: $VERSION_CONTENT"
    exit 1
fi

# Extract description from VERSION file (everything after the version)
DESCRIPTION=$(echo "$VERSION_CONTENT" | sed "s/.*$VERSION - //")

echo -e "${BLUE}📖 Extracted from VERSION file:${NC}"
echo "   Version: $VERSION"
echo "   Description: ${DESCRIPTION:0:100}..."

# Update database version using direct MySQL command
echo -e "${YELLOW}🔄 Updating database version...${NC}"

# Escape quotes in description for SQL
ESCAPED_DESCRIPTION=$(echo "$DESCRIPTION" | sed "s/'/''/g")

# Update database directly using Docker exec
SQL_COMMAND="INSERT INTO system_info (key_name, version, description, created_at, updated_at) VALUES ('current_version', '$VERSION', '$ESCAPED_DESCRIPTION', NOW(), NOW()) ON DUPLICATE KEY UPDATE version = '$VERSION', description = '$ESCAPED_DESCRIPTION', updated_at = NOW();"

if docker exec radiograb-mysql-1 mysql -u radiograb -pradiograb_pass_2024 radiograb -e "$SQL_COMMAND" 2>/dev/null; then
    echo -e "${GREEN}✅ Version synchronized successfully${NC}"
    echo "   Database version: $VERSION"
    echo -e "${BLUE}🌐 Version will be displayed on website footer${NC}"
else
    echo -e "${RED}❌ Failed to update database version${NC}"
    exit 1
fi

# Verify the version was set correctly
echo -e "${YELLOW}🔍 Verifying version...${NC}"
DB_VERSION=$(docker exec radiograb-mysql-1 mysql -u radiograb -pradiograb_pass_2024 radiograb -se "SELECT version FROM system_info WHERE key_name = 'current_version';" 2>/dev/null)

if [[ "$DB_VERSION" == "$VERSION" ]]; then
    echo -e "${GREEN}✅ Version verification successful${NC}"
else
    echo -e "${RED}❌ Version verification failed${NC}"
    echo "   Expected: $VERSION"
    echo "   Got: $DB_VERSION"
    exit 1
fi

echo -e "${GREEN}🎉 Version synchronization complete!${NC}"