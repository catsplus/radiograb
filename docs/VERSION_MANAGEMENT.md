# RadioGrab Version Management System

## Overview

RadioGrab uses a comprehensive version management system that automatically synchronizes version numbers between the codebase, database, and website display.

## Architecture

### Components

1. **VERSION File** (`/VERSION`) - Master version source
2. **Database** (`system_info` table) - Runtime version storage  
3. **Sync Script** (`scripts/sync-version.sh`) - Synchronization automation
4. **Display Functions** (`frontend/includes/version.php`) - Website display
5. **Deployment Integration** (`deploy-from-git.sh`) - Automatic sync on deploy

### Version Flow

```
VERSION file (source of truth)
     ↓
Deployment Script
     ↓  
sync-version.sh
     ↓
Database (system_info table)
     ↓
Website Footer Display
```

## Usage

### Automatic Synchronization

Version sync happens automatically during deployment:

```bash
# Version sync is integrated into deployment
./deploy-from-git.sh         # Full deployment with version sync
./deploy-from-git.sh --quick # Quick deployment with version sync
```

### Manual Version Sync

To manually sync version from VERSION file to database:

```bash
cd /opt/radiograb
bash scripts/sync-version.sh
```

### Updating Version

1. **Update VERSION file** with new version and description:
   ```
   2025-08-01 10:30:00 - v3.10.0 - NEW FEATURE - Description of changes...
   ```

2. **Deploy to sync automatically**:
   ```bash
   git add VERSION
   git commit -m "Bump version to v3.10.0"
   git push origin main
   ./deploy-from-git.sh --quick
   ```

3. **Verify on website** - Footer will show new version

### Version Format

The VERSION file follows this format:
```
YYYY-MM-DD HH:MM:SS - vX.Y.Z - TITLE - Description of changes and features...
```

Example:
```
2025-08-01 10:30:00 - v3.10.0 - FEATURE ENHANCEMENT - Added new recording scheduler with improved error handling and automatic stream discovery.
```

## Manual Version Management

### Using Python Version Manager

For advanced version management:

```bash
# Get current version
docker exec radiograb-recorder-1 bash -c "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python backend/services/version_manager.py --get"

# Set specific version
docker exec radiograb-recorder-1 bash -c "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python backend/services/version_manager.py --set v3.10.0 --description 'Manual version update'"

# Auto-increment version
docker exec radiograb-recorder-1 bash -c "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python backend/services/version_manager.py --auto-increment minor"

# View version history
docker exec radiograb-recorder-1 bash -c "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python backend/services/version_manager.py --history"
```

## Database Schema

The version is stored in the `system_info` table:

```sql
CREATE TABLE system_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(100) NOT NULL UNIQUE,
    version VARCHAR(20),
    description TEXT,
    value_text TEXT,
    value_json JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Version entry
INSERT INTO system_info (key_name, version, description) 
VALUES ('current_version', 'v3.9.0', 'Feature description...');
```

## Troubleshooting

### Version Not Updating

1. **Check VERSION file format**:
   ```bash
   cat VERSION | grep -o 'v[0-9]\+\.[0-9]\+\.[0-9]\+'
   ```

2. **Run manual sync**:
   ```bash
   cd /opt/radiograb && bash scripts/sync-version.sh
   ```

3. **Check database**:
   ```bash
   docker exec radiograb-mysql-1 mysql -u radiograb -pradiograb_pass_2024 radiograb -e "SELECT * FROM system_info WHERE key_name = 'current_version';"
   ```

### Sync Script Fails

- Ensure Docker containers are running
- Check MySQL credentials in sync script
- Verify VERSION file exists and has correct format

### Website Shows Wrong Version

- Clear browser cache
- Check database value matches VERSION file
- Run deployment to trigger sync

## Implementation Details

### Fallback System

The version display system has multiple fallbacks:

1. **Database** (`system_info` table) - Primary source
2. **VERSION file** - Fallback if database fails
3. **Hardcoded** (`v2.13.0`) - Final fallback

### Error Handling

- Sync script continues deployment even if version sync fails
- Database errors fall back to VERSION file or hardcoded version
- Logging captures all version-related errors

### Performance

- Version is cached in PHP session to avoid repeated database queries
- Sync only runs during deployment, not on every page load
- Minimal performance impact on website rendering

## Best Practices

1. **Always update VERSION file first** before deploying
2. **Use semantic versioning** (MAJOR.MINOR.PATCH)
3. **Include meaningful descriptions** in VERSION file
4. **Test version display** after deployment
5. **Keep version history** in git commits

## Integration with CI/CD

For automated deployments, the version sync can be integrated into CI/CD pipelines:

```yaml
# Example GitHub Actions step
- name: Deploy with Version Sync
  run: |
    ssh user@server "cd /opt/radiograb && ./deploy-from-git.sh --quick"
```

The version will automatically sync during the deployment process.