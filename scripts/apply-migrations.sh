#!/bin/bash

echo "Applying database migrations..."

# Wait for MySQL to be ready
echo "Waiting for MySQL container to be healthy..."
while ! docker inspect --format='{{.State.Health.Status}}' radiograb-mysql-1 | grep -q "healthy"; do
    echo -n "."
    sleep 5
done
echo "MySQL is healthy."

# Apply migrations
MIGRATIONS_DIR="/opt/radiograb/database/migrations"
for MIGRATION_FILE in $(ls $MIGRATIONS_DIR/*.sql | sort); do
    echo "Applying migration: $MIGRATION_FILE"
    docker exec radiograb-mysql-1 mysql -u radiograb -pradiograb_pass_2024 radiograb < "$MIGRATION_FILE"
_done

echo "All migrations applied."
