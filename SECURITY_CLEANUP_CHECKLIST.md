# Security Cleanup Checklist for Public Repository

## 🚨 REQUIRED CHANGES BEFORE MAKING PUBLIC

### 1. Replace Database Passwords
**File: `docker-compose.yml`**
```yaml
# CHANGE FROM:
MYSQL_ROOT_PASSWORD: radiograb_root_2024
MYSQL_PASSWORD: radiograb_pass_2024
DB_PASSWORD=radiograb_pass_2024

# CHANGE TO:
MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
MYSQL_PASSWORD: ${MYSQL_PASSWORD}  
DB_PASSWORD=${DB_PASSWORD}
```

### 2. Update Documentation
**Replace in ALL .md files:**
- `167.71.84.143` → `YOUR_SERVER_IP`
- `radiograb.svaha.com` → `your-domain.com`
- `admin@svaha.com` → `admin@your-domain.com`
- `radiograb@167.71.84.143` → `your-user@YOUR_SERVER_IP`

### 3. Update Environment Files
**File: `.env`**
```bash
# CHANGE FROM:
SSL_DOMAIN=radiograb.svaha.com
SSL_EMAIL=admin@svaha.com

# CHANGE TO:
SSL_DOMAIN=your-domain.com
SSL_EMAIL=admin@your-domain.com
```

### 4. Update Scripts
**Files to update:**
- `setup-ssl.sh`
- `setup-container-ssl.sh` 
- `check-domain.sh`
- `backup-ssl.sh`
- `deploy.sh`
- `quick-deploy.sh`

**Replace:**
- `167.71.84.143` → `YOUR_SERVER_IP`
- `radiograb@167.71.84.143` → `your-user@YOUR_SERVER_IP`

### 5. Add .env to .gitignore
Create/update `.gitignore`:
```
.env
*.log
recordings/
temp/
backups/
```

## ✅ AFTER CLEANUP COMMANDS

```bash
# 1. Make database passwords environment-based
# 2. Update all documentation 
# 3. Remove sensitive .env from git
git rm --cached .env
git add .gitignore

# 4. Commit cleanup
git add .
git commit -m "Security cleanup: Remove sensitive data for public repository"

# 5. Make repository public
```

## 🎯 BENEFITS AFTER CLEANUP

- Clean, professional open-source project
- Easy deployment with environment variables
- No hardcoded credentials or server details
- Community can contribute and deploy anywhere