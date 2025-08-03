#!/bin/bash
# Generate secure encryption key for API key storage
# Issues #13, #25, #26 - API Keys Management

echo "🔐 Generating secure API encryption key..."

# Generate 32-byte (256-bit) random key and base64 encode it
API_KEY=$(openssl rand -base64 32)

echo "Generated API encryption key:"
echo "API_ENCRYPTION_KEY=$API_KEY"
echo ""
echo "⚠️  IMPORTANT: Save this key securely!"
echo "   - Add it to your .env file"
echo "   - Never commit it to version control"
echo "   - If lost, all existing API keys will be unrecoverable"
echo ""
echo "📝 To use this key:"
echo "   1. Add to your .env file: API_ENCRYPTION_KEY=$API_KEY"
echo "   2. Restart all Docker containers"
echo "   3. Configure your API keys in the web interface"