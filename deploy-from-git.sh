#!/bin/bash
#
# RadioGrab Git-Based Deployment Script
# Streamlines deployment by pulling from GitHub and rebuilding containers
#

set -e

# Parse command line arguments
QUICK_MODE=false
if [[ "$1" == "--quick" ]] || [[ "$1" == "-q" ]]; then
    QUICK_MODE=true
    echo "🏃 RadioGrab Quick Deployment (Documentation/Config Only)"
    echo "========================================================"
else
    echo "🚀 RadioGrab Full Deployment from Git"
    echo "================================="
fi

# Change to radiograb directory
cd /opt/radiograb

# Check if we're in a git repository
if [ ! -d ".git" ]; then
    echo "❌ Error: Not a git repository!"
    echo "Run setup: git init && git remote add origin https://github.com/mattbaya/misc.git"
    exit 1
fi

# Show current status
echo "📍 Current status:"
git status --porcelain
echo

# Stash any local changes (like .env file)
echo "💾 Stashing local changes..."
git stash push -m "Auto-stash before deployment $(date)" || true

# Pull latest changes
echo "⬇️  Pulling latest changes from GitHub..."
if git pull origin main --rebase; then
    echo "   ✅ Successfully pulled latest changes from GitHub"
else
    echo "   ⚠️  Failed to pull from GitHub, using local repository"
fi

# Show what changed
echo "📝 Recent commits:"
git log --oneline -5
echo

# Rebuild containers with new code
if [[ "$QUICK_MODE" == "true" ]]; then
    echo "📝 Quick mode: Restarting containers without rebuild..."
    docker compose restart
else
    echo "🔄 Full rebuild: Rebuilding Docker containers..."
    docker compose down
    docker compose up -d --build
fi

# Wait for containers to be healthy
echo "⏳ Waiting for containers to start..."
sleep 10

# Check container status
echo "🩺 Container health check:"
docker compose ps

# Test basic functionality
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
if [[ "$QUICK_MODE" == "true" ]]; then
    echo "✅ Quick deployment complete!"
    echo "📝 For code changes, use: ./deploy-from-git.sh (full rebuild)"
else
    echo "✅ Full deployment complete!"
    echo "📝 For docs/config changes, use: ./deploy-from-git.sh --quick"
fi
echo "🌐 Site: https://radiograb.svaha.com"
echo "📊 Check containers: docker compose ps"
echo "📋 View logs: docker logs radiograb-web-1"
echo