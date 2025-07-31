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
if [ ! -d "$MIGRATIONS_DIR" ]; then
    echo "Migrations directory not found: $MIGRATIONS_DIR"
    exit 1
fi

# Check if there are any .sql files
if ! ls "$MIGRATIONS_DIR"/*.sql > /dev/null 2>&1; then
    echo "No migration files found in $MIGRATIONS_DIR"
    exit 0
fi

# Apply migrations in order
for MIGRATION_FILE in $(ls "$MIGRATIONS_DIR"/*.sql | sort); do
    echo "Applying migration: $(basename "$MIGRATION_FILE")"
    # Run migration and capture both stdout and stderr
    if docker exec radiograb-mysql-1 mysql -u radiograb -pradiograb_pass_2024 radiograb < "$MIGRATION_FILE" 2>&1; then
        echo "   ✅ Migration applied successfully"
    else
        echo "   ❌ Migration failed: $?"
        # Don't exit on failure - some migrations might already be applied
    fi
done

echo "All migrations processed."
