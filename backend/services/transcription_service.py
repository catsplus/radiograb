#!/usr/bin/env python3
"""
RadioGrab Transcription Service
Supports multiple transcription providers with unified interface
Issue #25 - Comprehensive transcription system
"""

import sys
import os
import requests
import time
import logging
import json
from datetime import datetime
from typing import Dict, List, Optional, Tuple
from pathlib import Path

# Add project root to path
sys.path.insert(0, '/opt/radiograb')

from backend.config.database import SessionLocal
from backend.models.station import Recording, Show

# Set up logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

class TranscriptionService:
    """Unified transcription service supporting multiple providers"""
    
    # Provider configurations
    PROVIDERS = {
        'openai_whisper': {
            'name': 'OpenAI Whisper API',
            'cost_per_minute': 0.006,
            'api_format': 'openai',
            'endpoint': 'https://api.openai.com/v1/audio/transcriptions',
            'models': ['whisper-1'],
            'max_file_size': 25 * 1024 * 1024,  # 25MB
            'supported_formats': ['mp3', 'mp4', 'mpeg', 'mpga', 'm4a', 'wav', 'webm']
        },
        'deepinfra_whisper': {
            'name': 'DeepInfra Whisper',
            'cost_per_minute': 0.0006,
            'api_format': 'openai_compatible',
            'endpoint': 'https://api.deepinfra.com/v1/inference/openai/whisper-large-v3',
            'models': ['whisper-large-v3'],
            'max_file_size': 100 * 1024 * 1024,  # 100MB
            'supported_formats': ['mp3', 'wav', 'flac', 'm4a']
        },
        'borgcloud': {
            'name': 'BorgCloud',
            'cost_per_minute': 0.001,
            'api_format': 'custom',
            'endpoint': 'https://borgcloud.org/api/v1/audio/transcriptions',
            'models': ['whisper-base', 'whisper-small', 'whisper-medium'],
            'max_file_size': 50 * 1024 * 1024,  # 50MB
            'supported_formats': ['mp3', 'wav', 'flac']
        },
        'assemblyai': {
            'name': 'AssemblyAI',
            'cost_per_minute': 0.0037,
            'api_format': 'custom',
            'endpoint': 'https://api.assemblyai.com/v2/transcript',
            'models': ['best', 'nano'],
            'max_file_size': 500 * 1024 * 1024,  # 500MB
            'supported_formats': ['mp3', 'wav', 'flac', 'm4a', 'mp4']
        },
        'groq_whisper': {
            'name': 'Groq Whisper',
            'cost_per_minute': 0.00111,
            'api_format': 'openai_compatible',
            'endpoint': 'https://api.groq.com/openai/v1/audio/transcriptions',
            'models': ['whisper-large-v3'],
            'max_file_size': 25 * 1024 * 1024,  # 25MB
            'supported_formats': ['mp3', 'mp4', 'mpeg', 'mpga', 'm4a', 'wav', 'webm']
        },
        'replicate': {
            'name': 'Replicate',
            'cost_per_minute': 0.0023,
            'api_format': 'custom',
            'endpoint': 'https://api.replicate.com/v1/predictions',
            'models': ['openai-whisper'],
            'max_file_size': 100 * 1024 * 1024,  # 100MB
            'supported_formats': ['mp3', 'wav', 'flac', 'm4a']
        },
        'huggingface': {
            'name': 'Hugging Face',
            'cost_per_minute': 0.001,
            'api_format': 'custom',
            'endpoint': 'https://api-inference.huggingface.co/models/openai/whisper-large-v3',
            'models': ['whisper-large-v3'],
            'max_file_size': 30 * 1024 * 1024,  # 30MB
            'supported_formats': ['mp3', 'wav', 'flac']
        }
    }
    
    def __init__(self):
        self.transcriptions_dir = Path('/var/radiograb/transcriptions')
        self.transcriptions_dir.mkdir(exist_ok=True)
        
    def get_user_transcription_config(self, user_id: int, provider: str = None) -> Optional[Dict]:
        """Get user's transcription configuration"""
        try:
            db = SessionLocal()
            try:
                from backend.models.api_keys import UserApiKey
                
                query = db.query(UserApiKey).filter(
                    UserApiKey.user_id == user_id,
                    UserApiKey.service_type == 'transcription',
                    UserApiKey.is_active == True
                )
                
                if provider:
                    query = query.filter(UserApiKey.service_configuration.like(f'%"service_provider":"{provider}"%'))
                
                config = query.first()
                
                if config:
                    # Parse credentials (stored as JSON)
                    try:
                        credentials = json.loads(config.encrypted_credentials)
                        service_config = json.loads(config.service_configuration) if config.service_configuration else {}
                        
                        return {
                            'api_key_id': config.id,
                            'provider': service_config.get('service_provider', provider),
                            'credentials': credentials,
                            'config': service_config
                        }
                    except json.JSONDecodeError as e:
                        logger.error(f"Invalid JSON in credentials for user {user_id}, provider {provider}: {e}")
                        return None
                    
                return None
                
            finally:
                db.close()
                
        except Exception as e:
            logger.error(f"Error getting transcription config: {e}")
            return None
    
    def transcribe_recording(self, recording_id: int, user_id: int, provider: str = None) -> Dict:
        """Transcribe a recording using specified or default provider"""
        result = {
            'success': False,
            'transcript': None,
            'provider_used': None,
            'cost_estimate': 0,
            'duration_minutes': 0,
            'error': None
        }
        
        try:
            db = SessionLocal()
            try:
                recording = db.query(Recording).filter(Recording.id == recording_id).first()
                if not recording:
                    result['error'] = f"Recording {recording_id} not found"
                    return result
                
                # Get transcription config
                config = self.get_user_transcription_config(user_id, provider)
                if not config:
                    result['error'] = "No transcription service configured"
                    return result
                
                provider_key = config['provider']
                if provider_key not in self.PROVIDERS:
                    result['error'] = f"Provider {provider_key} not supported"
                    return result
                
                # Get audio file path
                audio_file = Path('/var/radiograb/recordings') / recording.filename
                if not audio_file.exists():
                    result['error'] = f"Audio file not found: {recording.filename}"
                    return result
                
                # Validate file size
                file_size = audio_file.stat().st_size
                max_size = self.PROVIDERS[provider_key]['max_file_size']
                if file_size > max_size:
                    result['error'] = f"File too large: {file_size} bytes (max: {max_size})"
                    return result
                
                # Calculate duration and cost estimate
                duration_seconds = recording.duration_seconds or 3600
                duration_minutes = duration_seconds / 60
                cost_per_minute = self.PROVIDERS[provider_key]['cost_per_minute']
                cost_estimate = duration_minutes * cost_per_minute
                
                result['duration_minutes'] = duration_minutes
                result['cost_estimate'] = cost_estimate
                result['provider_used'] = provider_key
                
                # Perform transcription based on provider
                transcript = self._transcribe_with_provider(
                    audio_file, provider_key, config['credentials'], config['config']
                )
                
                if transcript:
                    result['success'] = True
                    result['transcript'] = transcript
                    
                    # Save transcript to file
                    transcript_file = self.transcriptions_dir / f"{recording_id}_{provider_key}.txt"
                    with open(transcript_file, 'w', encoding='utf-8') as f:
                        f.write(transcript)
                    
                    # Update recording with transcript info
                    recording.transcript_file = str(transcript_file)
                    recording.transcript_provider = provider_key
                    recording.transcript_generated_at = datetime.now()
                    db.commit()
                    
                    # Log usage
                    self._log_transcription_usage(
                        user_id, config['api_key_id'], provider_key,
                        duration_minutes, cost_estimate, True
                    )
                    
                    logger.info(f"Transcription completed for recording {recording_id} using {provider_key}")
                else:
                    result['error'] = "Transcription failed - no transcript returned"
                    self._log_transcription_usage(
                        user_id, config['api_key_id'], provider_key,
                        duration_minutes, cost_estimate, False
                    )
                
            finally:
                db.close()
                
        except Exception as e:
            result['error'] = f"Transcription error: {str(e)}"
            logger.error(f"Error transcribing recording {recording_id}: {e}", exc_info=True)
        
        return result
    
    def _transcribe_with_provider(self, audio_file: Path, provider: str, credentials: Dict, config: Dict) -> Optional[str]:
        """Transcribe audio file with specific provider"""
        
        if provider == 'openai_whisper':
            return self._transcribe_openai(audio_file, credentials, config)
        elif provider == 'deepinfra_whisper':
            return self._transcribe_deepinfra(audio_file, credentials, config)
        elif provider == 'borgcloud':
            return self._transcribe_borgcloud(audio_file, credentials, config)
        elif provider == 'assemblyai':
            return self._transcribe_assemblyai(audio_file, credentials, config)
        elif provider == 'groq_whisper':
            return self._transcribe_groq(audio_file, credentials, config)
        elif provider == 'replicate':
            return self._transcribe_replicate(audio_file, credentials, config)
        elif provider == 'huggingface':
            return self._transcribe_huggingface(audio_file, credentials, config)
        else:
            raise ValueError(f"Provider {provider} not implemented")
    
    def _transcribe_openai(self, audio_file: Path, credentials: Dict, config: Dict) -> Optional[str]:
        """Transcribe using OpenAI Whisper API"""
        try:
            headers = {
                'Authorization': f"Bearer {credentials['api_key']}"
            }
            
            with open(audio_file, 'rb') as f:
                files = {
                    'file': (audio_file.name, f, 'audio/mpeg'),
                    'model': (None, config.get('model_name', 'whisper-1')),
                    'language': (None, config.get('language_code', 'en')),
                    'response_format': (None, 'text')
                }
                
                response = requests.post(
                    self.PROVIDERS['openai_whisper']['endpoint'],
                    headers=headers,
                    files=files,
                    timeout=300
                )
            
            if response.status_code == 200:
                return response.text.strip()
            else:
                logger.error(f"OpenAI transcription failed: {response.status_code} {response.text}")
                return None
                
        except Exception as e:
            logger.error(f"OpenAI transcription error: {e}")
            return None
    
    def _transcribe_deepinfra(self, audio_file: Path, credentials: Dict, config: Dict) -> Optional[str]:
        """Transcribe using DeepInfra Whisper"""
        try:
            headers = {
                'Authorization': f"Bearer {credentials['api_key']}"
            }
            
            with open(audio_file, 'rb') as f:
                files = {
                    'audio': (audio_file.name, f, 'audio/mpeg')
                }
                data = {
                    'model': 'openai/whisper-large-v3',
                    'language': config.get('language_code', 'en'),
                    'response_format': 'text'
                }
                
                response = requests.post(
                    self.PROVIDERS['deepinfra_whisper']['endpoint'],
                    headers=headers,
                    files=files,
                    data=data,
                    timeout=300
                )
            
            if response.status_code == 200:
                # DeepInfra returns JSON with text field
                result = response.json()
                return result.get('text', '').strip()
            else:
                logger.error(f"DeepInfra transcription failed: {response.status_code} {response.text}")
                return None
                
        except Exception as e:
            logger.error(f"DeepInfra transcription error: {e}")
            return None
    
    def _transcribe_borgcloud(self, audio_file: Path, credentials: Dict, config: Dict) -> Optional[str]:
        """Transcribe using BorgCloud"""
        # Placeholder for BorgCloud implementation
        # You'll need to implement based on their specific API format
        logger.warning("BorgCloud transcription not yet implemented")
        return None
    
    def _transcribe_assemblyai(self, audio_file: Path, credentials: Dict, config: Dict) -> Optional[str]:
        """Transcribe using AssemblyAI"""
        # Placeholder for AssemblyAI implementation
        logger.warning("AssemblyAI transcription not yet implemented")
        return None
    
    def _transcribe_groq(self, audio_file: Path, credentials: Dict, config: Dict) -> Optional[str]:
        """Transcribe using Groq Whisper"""
        # Similar to OpenAI but with Groq endpoint
        logger.warning("Groq transcription not yet implemented")
        return None
    
    def _transcribe_replicate(self, audio_file: Path, credentials: Dict, config: Dict) -> Optional[str]:
        """Transcribe using Replicate"""
        # Placeholder for Replicate implementation
        logger.warning("Replicate transcription not yet implemented")
        return None
    
    def _transcribe_huggingface(self, audio_file: Path, credentials: Dict, config: Dict) -> Optional[str]:
        """Transcribe using Hugging Face"""
        # Placeholder for Hugging Face implementation
        logger.warning("Hugging Face transcription not yet implemented")
        return None
    
    def _log_transcription_usage(self, user_id: int, api_key_id: int, provider: str, 
                                duration_minutes: float, cost_estimate: float, success: bool):
        """Log transcription usage for tracking and billing"""
        try:
            db = SessionLocal()
            try:
                from backend.models.api_keys import UserApiUsageLog
                
                usage_log = UserApiUsageLog(
                    user_id=user_id,
                    api_key_id=api_key_id,
                    service_type='transcription',
                    operation_type=f'transcribe_{provider}',
                    request_count=1,
                    total_tokens=int(duration_minutes * 60),  # Store as seconds
                    total_cost=cost_estimate,
                    success=success,
                    response_time_ms=0,  # Could add timing if needed
                    created_at=datetime.now()
                )
                
                db.add(usage_log)
                db.commit()
                
            finally:
                db.close()
                
        except Exception as e:
            logger.error(f"Error logging transcription usage: {e}")
    
    def get_provider_info(self) -> Dict:
        """Get information about all supported providers"""
        return self.PROVIDERS
    
    def validate_audio_file(self, file_path: Path, provider: str) -> Tuple[bool, str]:
        """Validate audio file for specific provider"""
        if not file_path.exists():
            return False, "File does not exist"
        
        if provider not in self.PROVIDERS:
            return False, f"Provider {provider} not supported"
        
        # Check file size
        file_size = file_path.stat().st_size
        max_size = self.PROVIDERS[provider]['max_file_size']
        if file_size > max_size:
            return False, f"File too large: {file_size} bytes (max: {max_size})"
        
        # Check file format
        file_ext = file_path.suffix.lower().lstrip('.')
        supported_formats = self.PROVIDERS[provider]['supported_formats']
        if file_ext not in supported_formats:
            return False, f"Unsupported format: {file_ext} (supported: {', '.join(supported_formats)})"
        
        return True, "File is valid"

def main():
    """Command line interface for transcription service"""
    import argparse
    
    parser = argparse.ArgumentParser(description='RadioGrab Transcription Service')
    parser.add_argument('--recording-id', type=int, help='Recording ID to transcribe')
    parser.add_argument('--user-id', type=int, help='User ID for configuration')
    parser.add_argument('--provider', help='Specific provider to use')
    parser.add_argument('--list-providers', action='store_true', help='List all supported providers')
    parser.add_argument('--test-config', action='store_true', help='Test transcription configuration')
    
    args = parser.parse_args()
    
    service = TranscriptionService()
    
    if args.list_providers:
        print("=== Supported Transcription Providers ===")
        for key, info in service.PROVIDERS.items():
            print(f"{key}: {info['name']}")
            print(f"  Cost: ${info['cost_per_minute']:.4f}/minute")
            print(f"  Max file size: {info['max_file_size'] / (1024*1024):.0f}MB")
            print(f"  Formats: {', '.join(info['supported_formats'])}")
            print()
        return
    
    if args.recording_id and args.user_id:
        print(f"=== Transcribing Recording {args.recording_id} ===")
        result = service.transcribe_recording(args.recording_id, args.user_id, args.provider)
        
        if result['success']:
            print(f"✅ Transcription completed using {result['provider_used']}")
            print(f"Duration: {result['duration_minutes']:.2f} minutes")
            print(f"Estimated cost: ${result['cost_estimate']:.4f}")
            print(f"Transcript length: {len(result['transcript'])} characters")
        else:
            print(f"❌ Transcription failed: {result['error']}")
    else:
        parser.print_help()

if __name__ == '__main__':
    main()