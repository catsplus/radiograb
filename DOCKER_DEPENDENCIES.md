# RadioGrab Docker Setup Dependencies

## Host Server Requirements for User Account Installation

### **System Requirements**
- **OS**: Linux (Ubuntu 20.04+, Debian 10+, CentOS 8+, or similar)
- **RAM**: Minimum 2GB (optimized configuration), 4GB+ recommended
- **CPU**: 1+ cores (2+ recommended)  
- **Storage**: 20GB+ available space (recordings can grow quickly)
- **Network**: Public IP with ports 80/443 accessible

### **Required Software Dependencies**

#### **1. Docker Engine**
```bash
# Install Docker (user must be in docker group)
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo usermod -aG docker $USER
```

#### **2. Docker Compose**
```bash
# Install Docker Compose v2+
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose
```

#### **3. Git**
```bash
# For pulling code updates
sudo apt-get update && sudo apt-get install -y git
```

#### **4. Essential System Tools**
```bash
# Required for various operations
sudo apt-get install -y curl wget jq unzip
```

#### **5. SSL Certificate Tools (Optional)**
```bash
# If using Let's Encrypt SSL (recommended for production)
sudo apt-get install -y certbot
```

### **User Account Permissions Required**

#### **Docker Access**
- User must be in `docker` group
- Ability to run `docker` and `docker-compose` commands
- Access to Docker socket (`/var/run/docker.sock`)

#### **File System Access**
- **Application Directory**: `/home/username/radiograb/` (or similar)
- **Data Volumes**: Docker manages these automatically
- **Port Binding**: Ability to bind to ports 80, 443, 3306

#### **Network Access**
- **Outbound**: Internet access for:
  - Docker image pulls
  - Radio stream connections
  - RSS feed generation
  - Station discovery APIs
- **Inbound**: Ports 80 (HTTP) and 443 (HTTPS) accessible from internet

### **No Root Access Required For**
- Starting/stopping containers
- Application deployment
- Database management
- SSL certificate renewal (if using Docker-based Let's Encrypt)
- Log management
- Backup operations

### **Directory Structure**
```
/home/username/radiograb/
├── docker-compose.yml          # Container orchestration
├── Dockerfile                  # Container build instructions
├── backend/                    # Python services
├── frontend/                   # PHP web interface
├── docker/                     # Configuration files
│   ├── mysql-low-memory.cnf    # MySQL optimization
│   ├── php-custom.ini          # PHP configuration
│   ├── php-fpm-low-memory.conf # PHP-FPM process management
│   └── nginx.conf              # Web server config
└── database/                   # SQL migrations
```

### **Docker Volumes (Automatic Management)**
- `mysql_data`: Database storage
- `recordings`: Audio file storage  
- `feeds`: RSS feed cache
- `logs`: Application logs
- `temp`: Temporary files
- `letsencrypt`: SSL certificates
- `letsencrypt_lib`: SSL certificate library

### **Environment Variables Needed**
```bash
# Required in .env file or shell environment
MYSQL_ROOT_PASSWORD=secure_root_password
MYSQL_PASSWORD=secure_db_password  
DB_PASSWORD=secure_db_password
SSL_DOMAIN=your-domain.com
SSL_EMAIL=admin@your-domain.com
```

### **Port Requirements**
- **80**: HTTP (redirects to HTTPS)
- **443**: HTTPS (main web interface)
- **3306**: MySQL (for external access if needed)

### **Installation Commands (User Account)**
```bash
# Clone repository
git clone https://github.com/mattbaya/radiograb.git
cd radiograb

# Create environment file
cp .env.example .env
# Edit .env with your values

# Start services
docker-compose up -d

# Check status
docker-compose ps

# View logs
docker-compose logs -f
```

### **Maintenance Commands**
```bash
# Update from Git
git pull origin main
docker-compose down
docker-compose up -d --build

# Backup database
docker exec radiograb-mysql-1 mysqldump -u root -p radiograb > backup.sql

# View container resources
docker stats

# Restart specific service
docker-compose restart web
```

### **Resource Monitoring**
```bash
# Monitor container memory/CPU usage
docker stats --no-stream

# Check disk usage
du -sh /home/username/radiograb/
docker system df

# View container logs
docker-compose logs --tail=100 web
```

### **Security Considerations**
- User should have strong password/SSH key authentication
- Docker daemon security (user in docker group has effective root)
- SSL certificates should auto-renew
- Regular security updates for host OS
- Network firewall rules (only ports 80/443 open)

### **Migration Process**
1. Install dependencies on new server
2. Create user account with docker group membership
3. Clone repository and configure environment
4. Transfer data volumes if needed:
   ```bash
   # Export data from old server
   docker run --rm -v radiograb_mysql_data:/data -v $(pwd):/backup ubuntu tar czf /backup/mysql_data.tar.gz -C /data .
   docker run --rm -v radiograb_recordings:/data -v $(pwd):/backup ubuntu tar czf /backup/recordings.tar.gz -C /data .
   
   # Import data on new server
   docker run --rm -v radiograb_mysql_data:/data -v $(pwd):/backup ubuntu tar xzf /backup/mysql_data.tar.gz -C /data
   docker run --rm -v radiograb_recordings:/data -v $(pwd):/backup ubuntu tar xzf /backup/recordings.tar.gz -C /data
   ```
5. Start services and verify functionality

### **Troubleshooting**
- **Memory Issues**: Use optimized configuration provided
- **Permission Errors**: Ensure user is in docker group and logged out/in
- **Port Conflicts**: Check no other services using ports 80/443
- **SSL Issues**: Verify domain DNS points to server IP
- **Database Issues**: Check MySQL memory configuration and container limits