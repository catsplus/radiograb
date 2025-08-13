#!/bin/bash
#
# RadioGrab Efficient File-Only Deployment Script
# 
# PRINCIPLE: Update files in running containers, avoid rebuilds
# EFFICIENCY: Only restart services that need it (nginx, php-fpm, supervisor)
# SPEED: 5-15 seconds vs 5-10 minutes for most updates
#

set -e

# Parse command line arguments
FORCE_REBUILD=false
FILE_ONLY_MODE=true

if [[ "$1" == "--force" ]] || [[ "$1" == "--full" ]]; then
    FORCE_REBUILD=true
    FILE_ONLY_MODE=false
    echo "🔨 RadioGrab Force Full Rebuild"
    echo "==============================="
elif [[ "$1" == "--rebuild" ]]; then
    FORCE_REBUILD=true
    FILE_ONLY_MODE=false
    echo "🔨 RadioGrab Container Rebuild"
    echo "============================="
else
    echo "⚡ RadioGrab Efficient File-Only Deployment"
    echo "==========================================="
    echo "Updates files in running containers - 5-15 seconds vs 5-10 minutes"
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

# CRITICAL: Always force COMPLETE synchronization with remote repository
echo "⬇️  Pulling latest changes from GitHub..."
git fetch --all --prune
BEFORE_COMMIT=$(git rev-parse HEAD)
git reset --hard origin/main
AFTER_COMMIT=$(git rev-parse HEAD)
echo "   ✅ Repository synchronized with GitHub"

# Show what changed
echo "📝 Recent commits:"
git log --oneline -5
echo

# Check if containers are running
if ! docker compose ps | grep -q "Up"; then
    echo "🚀 Containers not running - starting them..."
    docker compose up -d
    sleep 10
    echo "   ✅ Containers started"
fi

# Get changed files
CHANGED_FILES=""
if [[ "$BEFORE_COMMIT" != "$AFTER_COMMIT" ]]; then
    CHANGED_FILES=$(git diff --name-only $BEFORE_COMMIT $AFTER_COMMIT 2>/dev/null || echo "")
fi

if [[ -z "$CHANGED_FILES" ]]; then
    echo "📝 No changes detected - deployment complete"
    exit 0
fi

echo "📁 Changed files:"
echo "$CHANGED_FILES"
echo

# Categorize changes
DOCKER_CHANGES=$(echo "$CHANGED_FILES" | grep -E '^(docker/|Dockerfile|docker-compose\.yml|requirements\.txt)' || true)
CONFIG_CHANGES=$(echo "$CHANGED_FILES" | grep -E '^docker/.*\.(conf|ini)$' || true)
PHP_CHANGES=$(echo "$CHANGED_FILES" | grep -E '\.php$' || true)
PYTHON_CHANGES=$(echo "$CHANGED_FILES" | grep -E '\.py$' || true)
JS_CSS_CHANGES=$(echo "$CHANGED_FILES" | grep -E '\.(js|css|html)$' || true)
DOCS_CHANGES=$(echo "$CHANGED_FILES" | grep -E '\.(md|txt)$' || true)

# Deployment strategy
if [[ "$FORCE_REBUILD" == "true" ]]; then
    echo "🔨 Force rebuild: Rebuilding all Docker containers..."
    docker compose down
    docker compose up -d --build
    DEPLOYMENT_TYPE="full_rebuild"
elif [[ -n "$DOCKER_CHANGES" ]]; then
    echo "🔨 Docker/requirements changes detected - rebuild required"
    echo "   Changed: $DOCKER_CHANGES"
    docker compose down
    docker compose up -d --build
    DEPLOYMENT_TYPE="full_rebuild"
elif [[ "$FILE_ONLY_MODE" == "true" ]]; then
    echo "⚡ File-only deployment - updating running containers..."
    DEPLOYMENT_TYPE="file_only"
    
    # Copy changed files to containers
    if [[ -n "$PHP_CHANGES" ]] || [[ -n "$JS_CSS_CHANGES" ]]; then
        echo "   📄 Updating web files (PHP/JS/CSS)..."
        docker cp frontend/. radiograb-web-1:/opt/radiograb/frontend/
        echo "   🔄 Reloading PHP-FPM..."
        docker exec radiograb-web-1 kill -USR2 $(docker exec radiograb-web-1 pgrep -f "php-fpm: master")
        echo "   🔄 Reloading Nginx..."
        docker exec radiograb-web-1 nginx -s reload
    fi
    
    if [[ -n "$PYTHON_CHANGES" ]]; then
        echo "   🐍 Updating Python files..."
        # Copy to all Python containers
        docker cp backend/. radiograb-recorder-1:/opt/radiograb/backend/
        docker cp backend/. radiograb-rss-updater-1:/opt/radiograb/backend/
        docker cp backend/. radiograb-housekeeping-1:/opt/radiograb/backend/
        
        echo "   🔄 Restarting Python services..."
        # Restart supervisor processes instead of containers
        docker exec radiograb-recorder-1 supervisorctl restart radiograb-recorder || true
        docker exec radiograb-recorder-1 supervisorctl restart radiograb-station-auto-test || true
        docker exec radiograb-rss-updater-1 supervisorctl restart radiograb-rss || true
    fi
    
    if [[ -n "$CONFIG_CHANGES" ]]; then
        echo "   ⚙️  Updating config files..."
        # Copy specific config files that changed
        for file in $CONFIG_CHANGES; do
            if [[ "$file" == docker/nginx.conf ]]; then
                docker cp docker/nginx.conf radiograb-web-1:/etc/nginx/sites-available/default
                docker exec radiograb-web-1 nginx -s reload
            elif [[ "$file" == docker/php-custom.ini ]]; then
                docker cp docker/php-custom.ini radiograb-web-1:/etc/php/8.1/fpm/conf.d/99-radiograb.ini
                docker cp docker/php-custom.ini radiograb-web-1:/etc/php/8.1/cli/conf.d/99-radiograb.ini
                docker exec radiograb-web-1 kill -USR2 $(docker exec radiograb-web-1 pgrep -f "php-fpm: master")
            elif [[ "$file" == docker/supervisord.conf ]]; then
                docker cp docker/supervisord.conf radiograb-recorder-1:/etc/supervisor/conf.d/supervisord.conf
                docker cp docker/supervisord.conf radiograb-rss-updater-1:/etc/supervisor/conf.d/supervisord.conf
                docker cp docker/supervisord.conf radiograb-housekeeping-1:/etc/supervisor/conf.d/supervisord.conf
                docker exec radiograb-recorder-1 supervisorctl reread && docker exec radiograb-recorder-1 supervisorctl update
                docker exec radiograb-rss-updater-1 supervisorctl reread && docker exec radiograb-rss-updater-1 supervisorctl update
                docker exec radiograb-housekeeping-1 supervisorctl reread && docker exec radiograb-housekeeping-1 supervisorctl update
            fi
        done
    fi
    
    if [[ -n "$DOCS_CHANGES" ]] && [[ -z "$PHP_CHANGES" ]] && [[ -z "$PYTHON_CHANGES" ]] && [[ -z "$JS_CSS_CHANGES" ]] && [[ -z "$CONFIG_CHANGES" ]]; then
        echo "   📚 Documentation-only changes - no restart needed"
        DEPLOYMENT_TYPE="docs_only"
    fi
else
    echo "🔄 Legacy mode: Restarting all containers..."
    docker compose restart
    DEPLOYMENT_TYPE="restart_all"
fi

# Wait and verify based on deployment type
if [[ "$DEPLOYMENT_TYPE" == "full_rebuild" ]]; then
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
elif [[ "$DEPLOYMENT_TYPE" == "file_only" ]]; then
    echo "⏳ Waiting for services to reload..."
    sleep 2
elif [[ "$DEPLOYMENT_TYPE" != "docs_only" ]]; then
    echo "⏳ Waiting for services to restart..."
    sleep 5
fi

# Health check
echo "🔍 Health check..."
for i in {1..10}; do
    if curl -s -o /dev/null -w "%{http_code}" http://localhost/ | grep -q "200"; then
        echo "   ✅ Website is responding"
        break
    fi
    if [[ $i -eq 10 ]]; then
        echo "   ⚠️  Website not responding - check logs"
        docker compose logs --tail 10 radiograb-web-1
    else
        echo "   ... checking website (attempt $i/10)"
        sleep 2
    fi
done

# Show final status
echo
echo "🎉 Deployment complete!"
echo "   Type: $DEPLOYMENT_TYPE"
if [[ "$DEPLOYMENT_TYPE" == "file_only" ]]; then
    echo "   ⚡ File-only deployment completed in seconds"
elif [[ "$DEPLOYMENT_TYPE" == "full_rebuild" ]]; then
    echo "   🔨 Full rebuild completed (required for Docker/dependency changes)"
fi

echo "📊 Container status:"
docker compose ps

echo
echo "🌐 Website: https://radiograb.svaha.com/"
echo "📝 Check logs: docker compose logs --tail 20 [service-name]"