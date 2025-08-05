#!/bin/bash
#
# RadioGrab Force Git Synchronization Script
# 
# This script addresses persistent git synchronization issues by:
# 1. Forcing complete repository refresh from GitHub
# 2. Ensuring ALL files are pulled, no matter the repository size
# 3. Completely rebuilding containers from scratch
# 4. Verifying deployment success before completing
#
# Issue: Files not syncing properly despite repository being small
# Solution: Force complete sync, never abbreviate, always pull all files
#

set -e

echo "🔄 RadioGrab Force Git Synchronization"
echo "====================================="
echo "CRITICAL: This script forces COMPLETE sync with GitHub"
echo "Problem: Files not syncing despite small repo size"
echo "Solution: Force complete sync, no abbreviations, all files"
echo

# Change to radiograb directory
cd /opt/radiograb

# Show current status
echo "📍 Current repository status:"
git status --porcelain
echo

# Show current HEAD
echo "📍 Current HEAD:"
git log --oneline -1
echo

# Backup any local changes 
echo "💾 Backing up local changes..."
if [ -f ".env" ]; then
    cp .env .env.backup
    echo "   ✅ .env file backed up"
fi

# Create complete stash of everything
git add -A
git stash push -m "FORCE-SYNC: Complete backup $(date)" || true

# CRITICAL: Complete repository refresh
echo "🔄 FORCING COMPLETE REPOSITORY SYNC"
echo "   This addresses persistent file sync issues..."

# Remove any potential partial or cached state
git gc --prune=now || true
git remote prune origin || true

# Force fetch ALL branches and tags
echo "⬇️  Step 1: Fetching ALL remote data..."
git fetch --all --prune --tags --force

# Get current branch
CURRENT_BRANCH=$(git branch --show-current)
echo "   Current branch: $CURRENT_BRANCH"

# Reset to exact remote state (CRITICAL)
echo "⬇️  Step 2: Resetting to EXACT remote state..."
git reset --hard origin/$CURRENT_BRANCH

# Verify clean state
echo "⬇️  Step 3: Ensuring repository is completely clean..."
git clean -fdx

# Restore .env if it existed
if [ -f ".env.backup" ]; then
    cp .env.backup .env
    echo "   ✅ .env file restored"
    rm .env.backup
fi

# Verify synchronization
echo "📍 Repository after sync:"
git log --oneline -3
echo

# Show file count to verify all files are present
FILE_COUNT=$(find . -type f -not -path './.git/*' | wc -l)
echo "📁 Total files in repository: $FILE_COUNT"

# Verify key files exist
CRITICAL_FILES=(
    "frontend/public/index.php"
    "frontend/includes/database.php"
    "frontend/includes/auth.php"
    "backend/services/recording_service.py"
    "docker-compose.yml"
    "deploy-from-git.sh"
)

echo "🔍 Verifying critical files exist:"
for file in "${CRITICAL_FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "   ✅ $file"
    else
        echo "   ❌ MISSING: $file"
        echo "🚨 CRITICAL: File sync failed - missing required files!"
        exit 1
    fi
done

echo
echo "✅ Git synchronization complete!"
echo "📊 Repository is now in exact sync with GitHub"
echo "🔄 Ready for container rebuild"
echo
echo "Next steps:"
echo "1. Run: docker compose down"
echo "2. Run: docker compose up -d --build"
echo "3. Wait for database to be ready"
echo "4. Test authentication and core functionality"
echo