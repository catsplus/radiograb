# RadioGrab 📻

[![Docker](https://img.shields.io/badge/docker-supported-blue)](https://www.docker.com/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![Python](https://img.shields.io/badge/python-3.8+-blue)](https://www.python.org/)
[![PHP](https://img.shields.io/badge/php-8.1+-blue)](https://www.php.net/)

**TiVo for Radio Stations** - Automatically record radio shows based on schedules and generate podcast feeds

RadioGrab is a comprehensive radio show recording and podcast generation system that turns any radio station's programming into a personal podcast archive. It automatically schedules and records shows at specified times, discovers streaming URLs, and generates RSS feeds - all with a beautiful web interface.

## 📅 Current Version: v2.6.0 (July 28, 2025)
**Latest Features**: Complete automatic recording system with APScheduler integration, schedule management interface, real-time scheduler synchronization, and web-based monitoring. Shows are now automatically recorded at scheduled times. See [CHANGELOG.md](CHANGELOG.md) for full details.

## ✨ Features

### 🎯 **Core Functionality**
- **Automatic Show Recording**: APScheduler-based system that automatically records shows at scheduled times
- **Enhanced Recording Service v2.5**: Database-driven recording with proven architecture and duplicate prevention
- **Schedule Management**: Web interface for adding/editing show schedules with automatic scheduler integration
- **Smart Discovery**: Extract streaming URLs and schedules from station websites with User-Agent support
- **Podcast Generation**: Create RSS feeds for individual shows or all recordings
- **Test Recording**: 30-second test recordings that now appear properly in web interface
- **On-Demand Recording**: Manual 1-hour recordings with quality validation

### 🔧 **Technical Features**
- **Enhanced Recording Architecture**: Complete rewrite with database-driven design and duplicate prevention
- **User-Agent Support**: Saved User-Agent per station for HTTP 403 handling and stream compatibility
- **Call Sign File Naming**: Human-readable 4-letter call signs (WEHC, WERU, WTBR, WYSO) instead of numeric IDs
- **Multi-Tool Recording**: Automatic tool selection (streamripper/ffmpeg/wget) with quality validation (2KB/sec minimum)
- **JavaScript-Aware Parsing**: Selenium WebDriver handles dynamic calendar pages
- **Docker Containerized**: Complete Docker setup with 5 specialized containers
- **SSL/HTTPS Ready**: Automatic Let's Encrypt certificate management
- **Timezone Synchronized**: All containers use Eastern Time for consistent timestamps
- **Test Interface Fixed**: Test recordings now appear properly in web interface without duplicates
- **Responsive Web UI**: Modern Bootstrap interface with real-time updates

### 📊 **Smart Automation**
- **APScheduler Integration**: Cron-based automatic recording of shows at scheduled times
- **Schedule Synchronization**: Real-time scheduler updates when shows are added/modified
- **Enhanced Recording Service**: Database-driven recording with 30-minute duplicate prevention window
- **Quality Validation**: File size and format verification (2KB/sec minimum for recordings)
- **User-Agent Persistence**: Automatically saves working User-Agents for stations with HTTP 403 issues
- **Automatic Housekeeping**: Cleans up empty recordings every 6 hours with retention policies
- **Stream Testing**: Validates streams before recording attempts with comprehensive error handling
- **Schedule Caching**: Remembers successful parsing methods per station
- **RSS Updates**: Refreshes podcast feeds every 15 minutes

## 🚀 Quick Start

### Prerequisites
- Docker and Docker Compose
- A server with internet access
- Domain name (for SSL/HTTPS) *optional*

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/radiograb.git
   cd radiograb
   ```

2. **Configure environment**
   ```bash
   cp .env.example .env
   # Edit .env with your settings:
   # - Database passwords
   # - Domain name (for SSL)
   # - Email address (for SSL)
   ```

3. **Start the application**
   ```bash
   docker-compose up -d
   ```

4. **Access the web interface**
   ```
   http://localhost        (development)
   https://your-domain.com (production with SSL)
   ```

## 📖 Documentation

### 📋 **Quick Setup Guides**
- [Deployment Guide](docs/DEPLOYMENT.md) - Production deployment and installation
- [SSL Setup](docs/SSL_PRESERVATION_GUIDE.md) - HTTPS configuration

### 🔧 **Technical Documentation**
- [System Architecture](docs/SYSTEM_ARCHITECTURE.md) - How RadioGrab works
- [Container Setup](docs/CONTAINER_SETUP.md) - Docker configuration
- [Recording Tools Guide](docs/RECORDING_TOOLS_GUIDE.md) - Multi-tool recording system
- [Project Overview](docs/PROJECT_OVERVIEW.md) - Complete project overview

### 🛠️ **Advanced Topics**
- [Stream Testing](docs/STREAM_TESTING_INTEGRATION.md) - Automatic stream validation
- [Schedule Discovery](docs/STREAM_URL_DISCOVERY.md) - Website parsing system
- [Troubleshooting](docs/TROUBLESHOOTING.md) - Common issues and solutions

## 🏗️ Architecture

RadioGrab uses a 5-container Docker architecture:

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Web Server    │    │    Recorder     │    │   RSS Updater   │
│ (nginx + PHP)   │    │ (Python daemon) │    │ (15min cron)    │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         └───────────────────────┼───────────────────────┘
                                 │
         ┌─────────────────┬─────┴─────┐
         │   Housekeeping  │   MySQL   │
         │   (6hr cron)    │ Database  │
         └─────────────────┴───────────┘
```

### Key Components
- **Web Interface**: PHP/JavaScript frontend with Bootstrap UI and test recording fixes
- **Enhanced Recording Engine**: Python services with database-driven architecture, User-Agent support, and duplicate prevention
- **Schedule Parser**: JavaScript-aware calendar extraction with stream discovery
- **Stream Validator**: Tests streams with comprehensive error handling and User-Agent persistence
- **RSS Generator**: Creates podcast feeds from recordings with proper call letters naming

## 🎛️ Usage

### Adding a Radio Station
1. Go to **Stations** → **Add Station**
2. Enter the station's website URL
3. Click **Discover** to automatically extract:
   - Station name and logo
   - Streaming URL
   - Schedule calendar URL
4. Save the station

### Setting Up Show Recording
1. Go to **Shows** → **Add Show**
2. Select your station
3. Configure:
   - Show name and description
   - Schedule pattern (e.g., "Monday 9:00 AM")
   - Recording duration
   - Retention period

### Testing Streams
- Use **Test Recording** buttons to verify streams work
- 10-second test recordings help debug issues
- All test recordings are saved and playable

### Accessing Recordings
- **Web Interface**: Listen and download via the Recordings page
- **RSS Feeds**: Subscribe to podcast feeds for individual shows
- **Master Feed**: Combined feed of all recordings
- **Direct Files**: Access recordings via `/recordings/` URL

## 📡 Recording Compatibility

RadioGrab handles virtually any stream type through intelligent tool selection:

| Stream Type | Tool Used | Compatibility |
|-------------|-----------|---------------|
| Direct MP3/AAC | streamripper | ✅ Excellent |
| HLS/DASH Streams | ffmpeg | ✅ Excellent |
| Authenticated Streams | ffmpeg | ✅ Good |
| Redirect URLs | wget | ✅ Good |
| HTTPS/SSL Streams | ffmpeg | ✅ Excellent |

## 🔐 Security Features

- **CSRF Protection**: All forms protected against cross-site attacks
- **Session Management**: Secure PHP session handling
- **SSL/TLS Ready**: Automatic Let's Encrypt certificate management
- **Container Isolation**: Docker security boundaries
- **Environment Variables**: Sensitive data in environment files

## 🛠️ Development

### Local Development Setup
```bash
# Clone repository
git clone https://github.com/yourusername/radiograb.git
cd radiograb

# Create local environment
cp .env.example .env
# Edit .env with development settings

# Start development containers
docker-compose up -d

# View logs
docker-compose logs -f
```

### Project Structure
```
radiograb/
├── frontend/           # Web interface (PHP/JavaScript)
│   ├── public/        # Web root
│   ├── includes/      # PHP shared code
│   └── assets/        # CSS/JS/images
├── backend/           # Python services
│   ├── services/      # Recording/parsing services
│   ├── models/        # Database models
│   └── utils/         # Utility functions
├── database/          # SQL schema and migrations
├── docker/            # Docker configuration files
└── docs/             # Documentation
```

### Contributing
1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📝 Configuration

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `MYSQL_ROOT_PASSWORD` | MySQL root password | `your_root_password` |
| `MYSQL_PASSWORD` | MySQL user password | `your_db_password` |
| `DB_PASSWORD` | Application database password | `your_db_password` |
| `SSL_DOMAIN` | Domain for SSL certificate | `your-domain.com` |
| `SSL_EMAIL` | Email for Let's Encrypt | `admin@your-domain.com` |

### Database Configuration
RadioGrab uses MySQL 8.0 with the following structure:
- **stations**: Radio station information
- **shows**: Show definitions and schedules  
- **recordings**: Individual recording entries
- **feeds**: RSS feed metadata

## 🆘 Troubleshooting

### Common Issues

**"Network error occurred during test recording"**
- Check if the stream URL is accessible
- Verify Docker containers are running: `docker-compose ps`
- Check logs: `docker-compose logs recorder`

**"Recording file not found"**
- Ensure recordings directory has proper permissions
- Check if recording completed successfully
- Verify nginx configuration for `/recordings/` path

**CSRF Token Issues**
- Clear browser cache and cookies
- Check PHP session configuration
- Verify CSRF debug endpoint: `/api/debug-csrf.php`

For more troubleshooting help, see [TROUBLESHOOTING.md](TROUBLESHOOTING.md).

## 📊 System Requirements

### Minimum Requirements
- **CPU**: 1 vCPU (2+ recommended for multiple concurrent recordings)
- **RAM**: 1GB (2GB+ recommended)
- **Storage**: 10GB+ (depends on retention settings)
- **Network**: Stable internet connection

### Recommended Production Setup
- **CPU**: 2+ vCPUs
- **RAM**: 4GB
- **Storage**: 100GB+ SSD
- **Network**: High-bandwidth connection for multiple streams

## 🔄 Changelog

See [CHANGELOG.md](CHANGELOG.md) for detailed version history.

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- **Streamripper**: For reliable MP3 stream recording
- **FFmpeg**: For handling complex streaming protocols
- **Bootstrap**: For the responsive web interface
- **Docker**: For containerization and easy deployment
- **Let's Encrypt**: For free SSL certificates

## 📞 Support

- **Documentation**: Check the `/docs` directory
- **Issues**: Open an issue on GitHub
- **Discussions**: Use GitHub Discussions for questions

---

**Made with ❤️ for radio enthusiasts and podcast lovers**