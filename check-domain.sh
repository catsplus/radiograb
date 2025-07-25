#!/bin/bash
# Check if domain is properly configured for SSL setup

if [ -z "$1" ]; then
    echo "Usage: $0 <domain_name>"
    echo "Example: $0 radiograb.yourdomain.com"
    exit 1
fi

DOMAIN="$1"
TARGET_IP="167.71.84.143"

echo "🔍 Checking domain configuration for $DOMAIN"
echo "=============================================="
echo ""

# Check DNS resolution
echo "📍 DNS Resolution:"
RESOLVED_IP=$(dig +short "$DOMAIN" 2>/dev/null | tail -n1)
if [ -z "$RESOLVED_IP" ]; then
    echo "   ❌ Domain does not resolve"
    echo "   📋 To fix: Add an A record pointing $DOMAIN to $TARGET_IP"
    exit 1
elif [ "$RESOLVED_IP" = "$TARGET_IP" ]; then
    echo "   ✅ $DOMAIN → $RESOLVED_IP (correct)"
else
    echo "   ⚠️  $DOMAIN → $RESOLVED_IP (should be $TARGET_IP)"
    echo "   📋 To fix: Update A record to point to $TARGET_IP"
fi

echo ""

# Check HTTP connectivity
echo "🌐 HTTP Connectivity:"
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" -m 10 "http://$DOMAIN" 2>/dev/null || echo "000")
if [ "$HTTP_STATUS" = "200" ] || [ "$HTTP_STATUS" = "302" ] || [ "$HTTP_STATUS" = "301" ]; then
    echo "   ✅ HTTP connection successful (status: $HTTP_STATUS)"
else
    echo "   ❌ HTTP connection failed (status: $HTTP_STATUS)"
    echo "   📋 This might be normal if nginx isn't configured yet"
fi

echo ""

# Check HTTPS (if already configured)
echo "🔒 HTTPS Status:"
HTTPS_STATUS=$(curl -s -o /dev/null -w "%{http_code}" -m 10 -k "https://$DOMAIN" 2>/dev/null || echo "000")
if [ "$HTTPS_STATUS" = "200" ] || [ "$HTTPS_STATUS" = "302" ] || [ "$HTTPS_STATUS" = "301" ]; then
    echo "   ✅ HTTPS already configured (status: $HTTPS_STATUS)"
    
    # Check certificate details
    CERT_INFO=$(echo | timeout 10 openssl s_client -servername "$DOMAIN" -connect "$DOMAIN:443" 2>/dev/null | openssl x509 -noout -subject -dates 2>/dev/null)
    if [ -n "$CERT_INFO" ]; then
        echo "   📋 Certificate details:"
        echo "$CERT_INFO" | sed 's/^/      /'
    fi
else
    echo "   ❌ HTTPS not configured (status: $HTTPS_STATUS)"
    echo "   📋 This is expected before running SSL setup"
fi

echo ""

# Summary
if [ "$RESOLVED_IP" = "$TARGET_IP" ] && ([ "$HTTP_STATUS" = "200" ] || [ "$HTTP_STATUS" = "302" ] || [ "$HTTP_STATUS" = "301" ]); then
    echo "🎉 Domain is ready for SSL setup!"
    echo "   Run: ./setup-container-ssl.sh $DOMAIN"
elif [ "$RESOLVED_IP" = "$TARGET_IP" ]; then
    echo "⚠️  Domain DNS is correct but HTTP isn't responding"
    echo "   This might be normal - you can try SSL setup anyway"
    echo "   Run: ./setup-container-ssl.sh $DOMAIN"
else
    echo "❌ Domain is not ready for SSL setup"
    echo "   Please fix DNS configuration first"
fi