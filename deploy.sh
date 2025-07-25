#!/bin/bash
# RadioGrab Deployment Script
# Automatically deploys modified files to server and restarts necessary containers

set -e  # Exit on any error

SERVER="radiograb@167.71.84.143"
SERVER_TMP="/tmp"
BASE_DIR="/opt/radiograb"

echo "🚀 Starting RadioGrab deployment..."

# Function to copy file to server and then to container
deploy_file() {
    local_file=$1
    container=$2
    container_path=$3
    
    if [ -f "$local_file" ]; then
        echo "  📄 Deploying $local_file"
        # Copy to server temp
        scp "$local_file" "$SERVER:$SERVER_TMP/"
        # Copy to container
        filename=$(basename "$local_file")
        ssh "$SERVER" "docker cp $SERVER_TMP/$filename $container:$container_path"
        # Clean up temp file
        ssh "$SERVER" "rm -f $SERVER_TMP/$filename"
    else
        echo "  ⚠️  Warning: $local_file not found, skipping"
    fi
}

# Deploy backend services
echo "📦 Deploying backend services..."
deploy_file "backend/services/recording_service.py" "radiograb-recorder-1" "$BASE_DIR/backend/services/recording_service.py"
deploy_file "backend/services/recording_service.py" "radiograb-web-1" "$BASE_DIR/backend/services/recording_service.py"
deploy_file "backend/services/test_recording_service.py" "radiograb-web-1" "$BASE_DIR/backend/services/test_recording_service.py"
deploy_file "backend/services/housekeeping_service.py" "radiograb-web-1" "$BASE_DIR/backend/services/housekeeping_service.py"
deploy_file "backend/services/housekeeping_service.py" "radiograb-housekeeping-1" "$BASE_DIR/backend/services/housekeeping_service.py"
deploy_file "backend/services/show_manager.py" "radiograb-web-1" "$BASE_DIR/backend/services/show_manager.py"
deploy_file "backend/services/calendar_parser.py" "radiograb-web-1" "$BASE_DIR/backend/services/calendar_parser.py"
deploy_file "backend/services/js_calendar_parser.py" "radiograb-web-1" "$BASE_DIR/backend/services/js_calendar_parser.py"
deploy_file "backend/services/station_discovery.py" "radiograb-web-1" "$BASE_DIR/backend/services/station_discovery.py"

# Deploy frontend files
echo "🌐 Deploying frontend files..."
deploy_file "frontend/public/stations.php" "radiograb-web-1" "$BASE_DIR/frontend/public/stations.php"
deploy_file "frontend/public/shows.php" "radiograb-web-1" "$BASE_DIR/frontend/public/shows.php"
deploy_file "frontend/public/api/test-recording.php" "radiograb-web-1" "$BASE_DIR/frontend/public/api/test-recording.php"
deploy_file "frontend/public/api/import-schedule.php" "radiograb-web-1" "$BASE_DIR/frontend/public/api/import-schedule.php"
deploy_file "frontend/public/api/discover-station.php" "radiograb-web-1" "$BASE_DIR/frontend/public/api/discover-station.php"

# Deploy configuration files
echo "⚙️  Deploying configuration files..."
if [ -f "docker-compose.yml" ]; then
    echo "  📄 Deploying docker-compose.yml"
    scp "docker-compose.yml" "$SERVER:/home/radiograb/radiograb/"
fi

if [ -f "requirements.txt" ]; then
    echo "  📄 Deploying requirements.txt"
    deploy_file "requirements.txt" "radiograb-web-1" "$BASE_DIR/requirements.txt"
fi

# Apply any pending database migrations
echo "🗃️  Checking for database migrations..."
if ls database/migrations/*.sql >/dev/null 2>&1; then
    for migration in database/migrations/*.sql; do
        if [ -f "$migration" ]; then
            migration_name=$(basename "$migration")
            echo "  📄 Applying migration: $migration_name"
            scp "$migration" "$SERVER:$SERVER_TMP/"
            ssh "$SERVER" "docker exec radiograb-mysql-1 mysql -u radiograb -pradiograb_pass_2024 radiograb < $SERVER_TMP/$migration_name" || echo "  ⚠️  Migration may have already been applied"
            ssh "$SERVER" "rm -f $SERVER_TMP/$migration_name"
        fi
    done
else
    echo "  ℹ️  No migrations to apply"
fi

# Restart containers if needed
echo "🔄 Restarting containers..."
ssh "$SERVER" "cd /home/radiograb/radiograb && docker compose restart radiograb-recorder-1"
echo "  ✅ Restarted recorder container"

# Check if housekeeping container exists and restart it
if ssh "$SERVER" "docker ps -a --format '{{.Names}}' | grep -q radiograb-housekeeping-1"; then
    ssh "$SERVER" "cd /home/radiograb/radiograb && docker compose restart radiograb-housekeeping-1"
    echo "  ✅ Restarted housekeeping container"
else
    echo "  ℹ️  Housekeeping container not running, starting services..."
    ssh "$SERVER" "cd /home/radiograb/radiograb && docker compose up -d"
fi

# Verify deployment
echo "🔍 Verifying deployment..."
echo "  Checking web container health..."
if ssh "$SERVER" "docker exec radiograb-web-1 test -f $BASE_DIR/frontend/public/stations.php"; then
    echo "  ✅ Web files deployed successfully"
else
    echo "  ❌ Web files deployment failed"
    exit 1
fi

echo "  Checking recorder container health..."
if ssh "$SERVER" "docker exec radiograb-recorder-1 test -f $BASE_DIR/backend/services/recording_service.py"; then
    echo "  ✅ Recorder files deployed successfully"
else
    echo "  ❌ Recorder files deployment failed"
    exit 1
fi

echo ""
echo "🎉 Deployment completed successfully!"
echo "   📍 Server: $SERVER"
echo "   🌐 Web interface: http://167.71.84.143"
echo "   📊 Container status: ssh $SERVER 'docker compose ps'"
echo ""