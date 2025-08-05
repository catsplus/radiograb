#!/bin/bash
#
# Smart RadioGrab Deployment Script
# Only rebuilds/restarts what's actually needed based on changed files
#

set -e

echo "🧠 Smart RadioGrab Deployment"
echo "============================"

cd /opt/radiograb

# Get list of changed files since last deployment
echo "📍 Analyzing changes since last deployment..."
git fetch --all --prune

# Get the changes
CHANGED_FILES=$(git diff --name-only HEAD HEAD~1 2>/dev/null || git diff --name-only HEAD origin/main 2>/dev/null || echo "")

if [ -z "$CHANGED_FILES" ]; then
    echo "   ℹ️  No changes detected, checking with remote..."
    CHANGED_FILES=$(git diff --name-only HEAD origin/main)
fi

echo "📝 Changed files:"
echo "$CHANGED_FILES" | sed 's/^/   /'
echo

# Categorize changes
DOCKER_CHANGES=$(echo "$CHANGED_FILES" | grep -E '^(docker/|Dockerfile|docker-compose\.yml)' || true)
PYTHON_CHANGES=$(echo "$CHANGED_FILES" | grep -E '\.py$' || true)
PHP_CHANGES=$(echo "$CHANGED_FILES" | grep -E '\.php$' || true)
JS_CSS_CHANGES=$(echo "$CHANGED_FILES" | grep -E '\.(js|css)$' || true)
CONFIG_CHANGES=$(echo "$CHANGED_FILES" | grep -E '\.(conf|ini|sh)$' || true)
DOCS_CHANGES=$(echo "$CHANGED_FILES" | grep -E '\.(md|txt)$' || true)

# Determine deployment strategy
NEEDS_REBUILD=false
NEEDS_WEB_RESTART=false
NEEDS_RECORDER_RESTART=false
NEEDS_RSS_RESTART=false

if [ -n "$DOCKER_CHANGES" ]; then
    echo "🔄 Docker configuration changes detected - FULL REBUILD required"
    NEEDS_REBUILD=true
elif [ -n "$CONFIG_CHANGES" ]; then
    echo "🔄 Configuration changes detected - Container restart required"
    NEEDS_WEB_RESTART=true
    NEEDS_RECORDER_RESTART=true
    NEEDS_RSS_RESTART=true
else
    echo "📝 Code-only changes detected - Smart restart approach"
    
    if [ -n "$PHP_CHANGES" ] || [ -n "$JS_CSS_CHANGES" ]; then
        echo "   - PHP/JS/CSS changes: Web container restart"
        NEEDS_WEB_RESTART=true
    fi
    
    if [ -n "$PYTHON_CHANGES" ]; then
        echo "   - Python changes: Backend service restarts"
        NEEDS_RECORDER_RESTART=true
        NEEDS_RSS_RESTART=true
    fi
    
    if [ -n "$DOCS_CHANGES" ] && [ -z "$PHP_CHANGES" ] && [ -z "$PYTHON_CHANGES" ] && [ -z "$JS_CSS_CHANGES" ]; then
        echo "   - Documentation-only changes: No restart needed"
    fi
fi

# Perform COMPLETE git sync (always pull all files for safety)
echo "⬇️  Syncing ALL files with GitHub..."
echo "   (Smart deployment = smart restarts, but ALWAYS pulls all files)"
git stash push -m "Auto-stash before smart deploy $(date)" || true
git fetch --all --prune
git reset --hard origin/main
echo "   ✅ ALL files synchronized with GitHub"

# Execute deployment strategy
if [ "$NEEDS_REBUILD" = true ]; then
    echo "🔨 Performing full rebuild..."
    docker compose down
    docker compose up -d --build
    WAIT_FOR_DB=true
else
    echo "🔄 Performing smart restart..."
    
    if [ "$NEEDS_WEB_RESTART" = true ]; then
        echo "   Restarting web container..."
        docker compose restart radiograb-web-1
    fi
    
    if [ "$NEEDS_RECORDER_RESTART" = true ]; then
        echo "   Restarting recorder container..."
        docker compose restart radiograb-recorder-1
    fi
    
    if [ "$NEEDS_RSS_RESTART" = true ]; then
        echo "   Restarting RSS updater container..."
        docker compose restart radiograb-rss-updater-1
    fi
    
    # Only wait for DB if we restarted containers that need it
    if [ "$NEEDS_WEB_RESTART" = true ] || [ "$NEEDS_RECORDER_RESTART" = true ]; then
        WAIT_FOR_DB=true
    else
        WAIT_FOR_DB=false
    fi
fi

# Wait for services if needed
if [ "$WAIT_FOR_DB" = true ]; then
    echo "⏳ Waiting for database to be ready..."
    for i in {1..30}; do
        if docker exec radiograb-mysql-1 mysql -u radiograb -pradiograb_pass_2024 -e "SELECT 1;" radiograb > /dev/null 2>&1; then
            echo "   ✅ Database ready"
            break
        fi
        echo "   ... waiting for database (attempt $i/30)"
        sleep 2
    done
fi

# Test basic functionality
echo "🧪 Testing basic functionality..."
if curl -s -f https://radiograb.svaha.com/ > /dev/null; then
    echo "   ✅ Website accessible"
else
    echo "   ❌ Website not accessible"
fi

if curl -s -f https://radiograb.svaha.com/login.php | grep -v -i "database.*error" > /dev/null; then
    echo "   ✅ Login page working"
else
    echo "   ⚠️  Login page may have issues"
fi

echo
echo "✅ Smart deployment complete!"
echo "📊 Deployment summary:"
if [ "$NEEDS_REBUILD" = true ]; then
    echo "   Strategy: Full rebuild (Docker/config changes)"
else
    echo "   Strategy: Smart restart (code-only changes)"
    [ "$NEEDS_WEB_RESTART" = true ] && echo "   - Web container restarted"
    [ "$NEEDS_RECORDER_RESTART" = true ] && echo "   - Recorder container restarted" 
    [ "$NEEDS_RSS_RESTART" = true ] && echo "   - RSS container restarted"
    [ "$NEEDS_WEB_RESTART" = false ] && [ "$NEEDS_RECORDER_RESTART" = false ] && [ "$NEEDS_RSS_RESTART" = false ] && echo "   - No restarts needed"
fi
echo "🌐 Site: https://radiograb.svaha.com"