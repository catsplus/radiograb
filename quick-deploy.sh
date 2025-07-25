#!/bin/bash
# Quick deployment script for individual files
# Usage: ./quick-deploy.sh [file1] [file2] ...

set -e

SERVER="radiograb@167.71.84.143"
BASE_DIR="/opt/radiograb"

if [ $# -eq 0 ]; then
    echo "Usage: $0 <file1> [file2] ..."
    echo "Example: $0 frontend/public/stations.php backend/services/recording_service.py"
    exit 1
fi

echo "🚀 Quick deploying files to server..."

for file in "$@"; do
    if [ -f "$file" ]; then
        echo "  📄 Deploying $file"
        
        # Determine target container based on file path
        if [[ "$file" == frontend/* ]]; then
            container="radiograb-web-1"
        elif [[ "$file" == backend/services/recording_service.py ]]; then
            # Recording service goes to both containers
            scp "$file" "$SERVER:/tmp/"
            filename=$(basename "$file")
            ssh "$SERVER" "docker cp /tmp/$filename radiograb-web-1:$BASE_DIR/$file"
            ssh "$SERVER" "docker cp /tmp/$filename radiograb-recorder-1:$BASE_DIR/$file"
            ssh "$SERVER" "rm -f /tmp/$filename"
            echo "    ✅ Deployed to both web and recorder containers"
            continue
        elif [[ "$file" == backend/services/housekeeping_service.py ]]; then
            # Housekeeping service goes to both containers
            scp "$file" "$SERVER:/tmp/"
            filename=$(basename "$file")
            ssh "$SERVER" "docker cp /tmp/$filename radiograb-web-1:$BASE_DIR/$file"
            ssh "$SERVER" "docker cp /tmp/$filename radiograb-housekeeping-1:$BASE_DIR/$file"
            ssh "$SERVER" "rm -f /tmp/$filename"
            echo "    ✅ Deployed to web and housekeeping containers"
            continue
        elif [[ "$file" == backend/* ]]; then
            container="radiograb-web-1"
        else
            echo "    ⚠️  Unknown file type: $file, deploying to web container"
            container="radiograb-web-1"
        fi
        
        # Deploy to single container
        scp "$file" "$SERVER:/tmp/"
        filename=$(basename "$file")
        ssh "$SERVER" "docker cp /tmp/$filename $container:$BASE_DIR/$file"
        ssh "$SERVER" "rm -f /tmp/$filename"
        echo "    ✅ Deployed to $container"
        
    else
        echo "  ❌ File not found: $file"
    fi
done

echo "🎉 Quick deployment completed!"