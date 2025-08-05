"""
Manages the local storage of station logos.

This service downloads station logos from their websites or Facebook pages,
resizes and optimizes them, and stores them locally. This ensures consistent
performance and avoids hotlinking to external images.

Key Variables:
- `station_id`: The database ID of the station.
- `logo_url`: The URL of the logo to be downloaded.

Inter-script Communication:
- This script is used by `station_manager.py` and other services to manage logos.
- It uses `facebook_logo_extractor.py` to find logos on Facebook.
- It interacts with the `Station` model from `backend/models/station.py`.
"""

"""
import os
import requests
import hashlib
from pathlib import Path
from PIL import Image
import io
import logging
from typing import Optional, Dict, Tuple
from urllib.parse import urlparse, urljoin
import time

# Setup logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

class LogoStorageService:
    """Service for downloading and storing station logos locally"""
    
    def __init__(self, storage_dir: str = "/var/radiograb/logos"):
        self.storage_dir = Path(storage_dir)
        self.storage_dir.mkdir(parents=True, exist_ok=True)
        
        # Supported image formats
        self.supported_formats = {'.jpg', '.jpeg', '.png', '.gif', '.webp', '.svg'}
        
        # Maximum file size (2MB)
        self.max_file_size = 2 * 1024 * 1024
        
        # Request headers to appear as a browser
        self.headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Accept': 'image/webp,image/apng,image/*,*/*;q=0.8',
            'Accept-Language': 'en-US,en;q=0.9',
            'Accept-Encoding': 'gzip, deflate, br',
            'Connection': 'keep-alive',
            'Upgrade-Insecure-Requests': '1',
        }
    
    def download_and_store_logo(self, logo_url: str, station_id: int, source: str = 'website') -> Optional[Dict]:
        """
        Download a logo from URL and store it locally
        
        Args:
            logo_url: URL of the logo to download
            station_id: Station ID for filename
            source: Source of the logo (website, facebook, favicon)
            
        Returns:
            Dict with local_path, filename, and metadata or None if failed
        """
        try:
            # Validate URL
            if not logo_url or not logo_url.startswith(('http://', 'https://')):
                logger.warning(f"Invalid logo URL: {logo_url}")
                return None
            
            # Download the image
            logger.info(f"Downloading logo from: {logo_url}")
            response = requests.get(logo_url, headers=self.headers, timeout=30, stream=True)
            response.raise_for_status()
            
            # Check content type
            content_type = response.headers.get('content-type', '').lower()
            if not content_type.startswith('image/'):
                logger.warning(f"URL does not return an image: {content_type}")
                return None
            
            # Check file size
            content_length = response.headers.get('content-length')
            if content_length and int(content_length) > self.max_file_size:
                logger.warning(f"Logo too large: {content_length} bytes")
                return None
            
            # Read the content
            content = b''
            for chunk in response.iter_content(chunk_size=8192):
                content += chunk
                if len(content) > self.max_file_size:
                    logger.warning(f"Logo download exceeded size limit")
                    return None
            
            if not content:
                logger.warning("Empty logo content")
                return None
            
            # Determine file extension from content type or URL
            extension = self._get_file_extension(content_type, logo_url)
            if not extension:
                logger.warning(f"Unsupported image format: {content_type}")
                return None
            
            # Generate filename
            url_hash = hashlib.md5(logo_url.encode()).hexdigest()[:8]
            filename = f"station_{station_id}_{source}_{url_hash}{extension}"
            local_path = self.storage_dir / filename
            
            # Validate and optimize image if possible
            if extension in {'.jpg', '.jpeg', '.png', '.gif', '.webp'}:
                content = self._optimize_image(content, extension)
                if not content:
                    logger.warning("Failed to optimize image")
                    return None
            
            # Save the file
            with open(local_path, 'wb') as f:
                f.write(content)
            
            # Get image dimensions if possible
            dimensions = self._get_image_dimensions(local_path)
            
            logger.info(f"Logo saved successfully: {filename}")
            
            return {
                'local_path': str(local_path),
                'filename': filename,
                'url_path': f'/logos/{filename}',  # Web-accessible path
                'file_size': len(content),
                'dimensions': dimensions,
                'source': source,
                'original_url': logo_url
            }
            
        except requests.RequestException as e:
            logger.error(f"Failed to download logo from {logo_url}: {e}")
            return None
        except Exception as e:
            logger.error(f"Error processing logo {logo_url}: {e}")
            return None
    
    def _get_file_extension(self, content_type: str, url: str) -> Optional[str]:
        """Determine file extension from content type or URL"""
        # Map content types to extensions
        type_map = {
            'image/jpeg': '.jpg',
            'image/jpg': '.jpg',
            'image/png': '.png',
            'image/gif': '.gif',
            'image/webp': '.webp',
            'image/svg+xml': '.svg'
        }
        
        # First try content type
        if content_type in type_map:
            return type_map[content_type]
        
        # Then try URL extension
        parsed_url = urlparse(url)
        path_ext = Path(parsed_url.path).suffix.lower()
        if path_ext in self.supported_formats:
            return path_ext
        
        # Default to jpg for generic image types
        if content_type.startswith('image/'):
            return '.jpg'
        
        return None
    
    def _optimize_image(self, content: bytes, extension: str) -> Optional[bytes]:
        """Optimize image size and quality"""
        try:
            # Skip SVG files
            if extension == '.svg':
                return content
            
            # Open image with PIL
            image = Image.open(io.BytesIO(content))
            
            # Convert to RGB if needed (for JPEG)
            if extension in {'.jpg', '.jpeg'} and image.mode in ['RGBA', 'P']:
                # Create white background for transparency
                background = Image.new('RGB', image.size, (255, 255, 255))
                if image.mode == 'P':
                    image = image.convert('RGBA')
                background.paste(image, mask=image.split()[-1] if image.mode == 'RGBA' else None)
                image = background
            
            # Resize if too large (max 400x400)
            max_size = 400
            if image.width > max_size or image.height > max_size:
                image.thumbnail((max_size, max_size), Image.Resampling.LANCZOS)
            
            # Save optimized image
            output = io.BytesIO()
            if extension in {'.jpg', '.jpeg'}:
                image.save(output, format='JPEG', quality=85, optimize=True)
            elif extension == '.png':
                image.save(output, format='PNG', optimize=True)
            elif extension == '.webp':
                image.save(output, format='WEBP', quality=80, optimize=True)
            else:
                # Keep original for other formats
                return content
            
            optimized_content = output.getvalue()
            
            # Only use optimized version if it's smaller or not much larger
            if len(optimized_content) <= len(content) * 1.1:
                return optimized_content
            else:
                return content
                
        except Exception as e:
            logger.warning(f"Failed to optimize image: {e}")
            return content
    
    def _get_image_dimensions(self, file_path: Path) -> Optional[Tuple[int, int]]:
        """Get image dimensions"""
        try:
            if file_path.suffix.lower() == '.svg':
                return None  # SVG dimensions are complex
            
            with Image.open(file_path) as img:
                return img.size
        except Exception:
            return None
    
    def cleanup_old_logos(self, days_old: int = 30) -> int:
        """Remove old logo files that haven't been used recently"""
        try:
            cutoff_time = time.time() - (days_old * 24 * 60 * 60)
            removed_count = 0
            
            for file_path in self.storage_dir.glob("station_*"):
                if file_path.is_file() and file_path.stat().st_mtime < cutoff_time:
                    try:
                        file_path.unlink()
                        removed_count += 1
                        logger.info(f"Removed old logo: {file_path.name}")
                    except Exception as e:
                        logger.warning(f"Failed to remove {file_path}: {e}")
            
            return removed_count
            
        except Exception as e:
            logger.error(f"Error during logo cleanup: {e}")
            return 0
    
    def get_logo_info(self, local_path: str) -> Optional[Dict]:
        """Get information about a stored logo"""
        try:
            path = Path(local_path)
            if not path.exists():
                return None
            
            stat = path.stat()
            dimensions = self._get_image_dimensions(path)
            
            return {
                'filename': path.name,
                'url_path': f'/logos/{path.name}',
                'file_size': stat.st_size,
                'dimensions': dimensions,
                'modified_at': stat.st_mtime
            }
            
        except Exception as e:
            logger.error(f"Error getting logo info for {local_path}: {e}")
            return None

# Test function
if __name__ == "__main__":
    service = LogoStorageService()
    
    # Test with WEHC Facebook logo
    test_url = "https://scontent-bos5-1.xx.fbcdn.net/v/t39.30808-1/358694351_741622007969636_6558597262399378784_n.png?stp=c0.0.241.241a_dst-png_s241x240&_nc_cat=104&ccb=1-7&_nc_sid=2d3e12&_nc_ohc=FwIw0jSLn-kQ7kNvwEag9KD&_nc_oc=AdlsQo0vZ5BGwBULFQFTs7f3qYqy_w0pNd40BJFDTanzU-kGP5ygOQEmMNIuhXb4AvA&_nc_zt=24&_nc_ht=scontent-bos5-1.xx&_nc_gid=vjoPuWhJR37369TKuCqfkQ&oh=00_AfRVOxIvJPwjIcvsvNbZERgFr_Bjd0oU6IQEHDrOxeKf3w&oe=688EA6CF"
    
    result = service.download_and_store_logo(test_url, 1, 'facebook')
    if result:
        print(f"Successfully downloaded and stored logo: {result}")
    else:
        print("Failed to download logo")