#!/bin/bash
#
# RadioGrab Git-Based Deployment Script
# Streamlines deployment by pulling from GitHub and rebuilding containers
#

set -e

# Parse command line arguments
FORCE_REBUILD=false
SMART_MODE=true

if [[ "$1" == "--force" ]] || [[ "$1" == "--full" ]]; then
    FORCE_REBUILD=true
    SMART_MODE=false
    echo "🔨 RadioGrab Force Full Rebuild"
    echo "==============================="
elif [[ "$1" == "--quick" ]] || [[ "$1" == "-q" ]]; then
    SMART_MODE=false
    echo "🏃 RadioGrab Quick Deployment (Documentation/Config Only)"
    echo "========================================================"
else
    echo "🧠 RadioGrab Smart Deployment (Default)"
    echo "======================================"
    echo "Analyzes changes and restarts only what's needed"
fi

# Change to radiograb directory
cd /opt/radiograb

# Check if we're in a git repository
if [ ! -d ".git" ]; then
    echo "❌ Error: Not a git repository!"
    echo "Run setup: git init && git remote add origin https://github.com/mattbaya/radiograb.git"
    exit 1
fi

# Show current status
echo "📍 Current status:"
git status --porcelain
echo

# Stash any local changes (like .env file)
echo "💾 Stashing local changes..."
git stash push -m "Auto-stash before deployment $(date)" || true

# CRITICAL: Force full synchronization with remote repository
echo "⬇️  Forcing complete sync with GitHub repository..."
git fetch --all --prune
git reset --hard origin/main
echo "   ✅ Repository completely synchronized with GitHub"

# Show what changed
echo "📝 Recent commits:"
git log --oneline -5
echo

# Deployment strategy based on mode and changes
if [[ "$SMART_MODE" == "true" ]]; then
    echo "🧠 Analyzing changes for smart deployment..."
    
    # Get changed files
    CHANGED_FILES=$(git diff --name-only HEAD~1 HEAD 2>/dev/null || echo "")
    if [[ -z "$CHANGED_FILES" ]]; then
        CHANGED_FILES=$(git diff --name-only HEAD origin/main 2>/dev/null || echo "")
    fi
    
    # Categorize changes
    DOCKER_CHANGES=$(echo "$CHANGED_FILES" | grep -E '^(docker/|Dockerfile|docker-compose\.yml)' || true)
    CONFIG_CHANGES=$(echo "$CHANGED_FILES" | grep -E '\.(conf|ini|sh)$' || true)
    PHP_CHANGES=$(echo "$CHANGED_FILES" | grep -E '\.php$' || true)
    PYTHON_CHANGES=$(echo "$CHANGED_FILES" | grep -E '\.py$' || true)
    JS_CSS_CHANGES=$(echo "$CHANGED_FILES" | grep -E '\.(js|css)$' || true)
    DOCS_CHANGES=$(echo "$CHANGED_FILES" | grep -E '\.(md|txt)$' || true)
    
    # Smart deployment logic
    if [[ -n "$DOCKER_CHANGES" ]]; then
        echo "   🔨 Docker changes detected - full rebuild required"
        docker compose down
        docker compose up -d --build
        RESTART_TYPE="full_rebuild"
    elif [[ -n "$CONFIG_CHANGES" ]]; then
        echo "   🔄 Config changes detected - restarting all containers"
        docker compose restart
        RESTART_TYPE="restart_all"
    else
        echo "   📝 Code-only changes - smart restart"
        RESTART_TYPE="smart"
        
        if [[ -n "$PHP_CHANGES" ]] || [[ -n "$JS_CSS_CHANGES" ]]; then
            echo "      Restarting web container (PHP/JS/CSS changes)"
            docker compose restart radiograb-web-1
        fi
        
        if [[ -n "$PYTHON_CHANGES" ]]; then
            echo "      Restarting backend containers (Python changes)"
            docker compose restart radiograb-recorder-1 radiograb-rss-updater-1 radiograb-housekeeping-1
        fi
        
        if [[ -n "$DOCS_CHANGES" ]] && [[ -z "$PHP_CHANGES" ]] && [[ -z "$PYTHON_CHANGES" ]] && [[ -z "$JS_CSS_CHANGES" ]]; then
            echo "      Documentation-only changes - no restart needed"
            RESTART_TYPE="none"
        fi
    fi
elif [[ "$FORCE_REBUILD" == "true" ]]; then
    echo "🔨 Force rebuild: Rebuilding all Docker containers..."
    docker compose down
    docker compose up -d --build
    RESTART_TYPE="full_rebuild"
else
    # Quick mode - check for code changes
    CHANGED_FILES=$(git diff --name-only HEAD~1 HEAD)
    CODE_CHANGES=$(echo "$CHANGED_FILES" | grep -E '\.(php|py|js|css|html)$' || true)
    
    if [[ -n "$CODE_CHANGES" ]]; then
        echo "📝 Quick mode: Code changes detected, performing full rebuild..."
        docker compose down
        docker compose up -d --build
        RESTART_TYPE="full_rebuild"
    else
        echo "📝 Quick mode: Only config/docs changed, restarting containers..."
        docker compose restart
        RESTART_TYPE="restart_all"
    fi
fi

# Wait for services based on restart type
if [[ "$RESTART_TYPE" == "full_rebuild" ]]; then
    echo "⏳ Waiting for full rebuild to complete..."
    sleep 15
    
    echo "⏳ Waiting for database to be ready..."
    for i in {1..30}; do
        if docker exec radiograb-mysql-1 mysql -u radiograb -pradiograb_pass_2024 -e "SELECT 1;" radiograb > /dev/null 2>&1; then
            echo "   ✅ Database is ready"
            break
        fi
        echo "   ... waiting for database (attempt $i/30)"
        sleep 5
    done
elif [[ "$RESTART_TYPE" == "restart_all" ]] || [[ "$RESTART_TYPE" == "smart" ]]; then
    echo "⏳ Waiting for containers to restart..."
    sleep 5
    
    # Quick database check for web/backend restarts
    if docker exec radiograb-mysql-1 mysql -u radiograb -pradiograb_pass_2024 -e "SELECT 1;" radiograb > /dev/null 2>&1; then
        echo "   ✅ Database is ready"
    else
        echo "   ⚠️  Database may still be starting..."
    fi
else
    echo "ℹ️  No restart needed - containers running normally"
fi

# Check container status
echo "🩺 Container health check:"
docker compose ps

# Check for active recordings
echo "🎧 Checking for active recordings..."
RECORDING_STATUS=$(curl -s https://radiograb.svaha.com/api/recording-status.php)
RECORDING_COUNT=$(echo "$RECORDING_STATUS" | jq -r '.count')

if [ "$RECORDING_COUNT" -gt 0 ]; then
    echo "⚠️  WARNING: There are $RECORDING_COUNT active recordings!"
    echo "Details:"
    echo "$RECORDING_STATUS" | jq '.current_recordings[] | {show_name, station_name, start_time, end_time, progress_percent}'

    read -p "Continuing deployment will interrupt active recordings. Do you want to proceed? (yes/no): " CONFIRM_DEPLOY
    if [[ ! "$CONFIRM_DEPLOY" =~ ^[Yy][Ee][Ss]$ ]]; then
        echo "Deployment aborted by user."
        exit 1
    fi
    echo "Proceeding with deployment despite active recordings."
else
    echo "✅ No active recordings found."
fi

# Sync version from VERSION file to database
echo "🔄 Synchronizing version..."
if [ -f "scripts/sync-version.sh" ]; then
    if bash scripts/sync-version.sh; then
        echo "   ✅ Version synchronized successfully"
    else
        echo "   ⚠️  Version sync failed (continuing deployment)"
    fi
else
    echo "   ⚠️  Version sync script not found"
fi

# Test basic functionality:
echo "🧪 Basic functionality test:"
if curl -s -f https://radiograb.svaha.com/ > /dev/null; then
    echo "   ✅ Website is accessible"
else
    echo "   ❌ Website not accessible"
fi

if curl -s -f https://radiograb.svaha.com/api/get-csrf-token.php | grep -q csrf_token; then
    echo "   ✅ CSRF token API working"
else
    echo "   ❌ CSRF token API not working"
fi

echo
echo "✅ Deployment complete!"
echo "📊 Deployment summary: $RESTART_TYPE"

case "$RESTART_TYPE" in
    "full_rebuild")
        echo "   🔨 Full container rebuild performed"
        ;;
    "restart_all")
        echo "   🔄 All containers restarted"
        ;;
    "smart")
        echo "   🧠 Smart restart - only affected containers"
        ;;
    "none")
        echo "   📝 No restart needed (docs only)"
        ;;
esac

echo
echo "🚀 Available deployment modes:"
echo "   ./deploy-from-git.sh           # Smart deployment (default)"
echo "   ./deploy-from-git.sh --force   # Force full rebuild"
echo "   ./deploy-from-git.sh --quick   # Quick restart mode"
echo
echo "🌐 Site: https://radiograb.svaha.com"
echo "📊 Check containers: docker compose ps"
echo "📋 View logs: docker logs radiograb-web-1"
echo