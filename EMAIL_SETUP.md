# RadioGrab Email Configuration Guide

## Overview

RadioGrab uses `msmtp` for sending outgoing emails (password resets, email verification, etc.). This document explains how to configure email functionality.

## Email Configuration

### Environment Variables

Add these variables to your `.env` file or docker-compose environment:

```bash
# SMTP Configuration
SMTP_HOST=smtp.gmail.com           # Your SMTP server
SMTP_PORT=587                      # SMTP port (587 for TLS, 465 for SSL)
SMTP_FROM=noreply@yourdomain.com   # From address for emails
SMTP_USERNAME=your-email@gmail.com # SMTP authentication username
SMTP_PASSWORD=your-app-password    # SMTP authentication password
```

### Common SMTP Providers

#### Gmail
```bash
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-app-password  # Use App Password, not regular password
```

#### SendGrid
```bash
SMTP_HOST=smtp.sendgrid.net
SMTP_PORT=587
SMTP_USERNAME=apikey
SMTP_PASSWORD=your-sendgrid-api-key
```

#### Amazon SES
```bash
SMTP_HOST=email-smtp.us-east-1.amazonaws.com
SMTP_PORT=587
SMTP_USERNAME=your-ses-smtp-username
SMTP_PASSWORD=your-ses-smtp-password
```

#### Mailgun
```bash
SMTP_HOST=smtp.mailgun.org
SMTP_PORT=587
SMTP_USERNAME=your-mailgun-smtp-username
SMTP_PASSWORD=your-mailgun-smtp-password
```

## Testing Email Configuration

### Admin Panel Test

1. Log in as admin
2. Go to `/admin/test-email.php`
3. Enter your email address
4. Click "Send Test Email"

### Command Line Test

SSH into the container and test directly:

```bash
# Access the container
docker exec -it radiograb-web-1 bash

# Send a test email
echo "Test email body" | msmtp your-email@example.com

# Check msmtp logs
tail -f /var/log/msmtp.log
```

### PHP Mail Test

Create a simple PHP script to test:

```php
<?php
$to = "test@example.com";
$subject = "Test Email";
$message = "This is a test email";
$headers = "From: noreply@yourdomain.com";

if (mail($to, $subject, $message, $headers)) {
    echo "Email sent successfully!";
} else {
    echo "Email failed to send.";
}
?>
```

## Troubleshooting

### Email Not Sending

1. **Check environment variables**: Ensure all SMTP_* variables are set correctly
2. **Check logs**: Look at `/var/log/msmtp.log` for detailed error messages
3. **Verify credentials**: Test your SMTP credentials with a mail client
4. **Check firewall**: Ensure the container can access the SMTP server port
5. **Review sendmail path**: Should be `/usr/bin/msmtp -t` in PHP config

### Common Error Messages

#### "Connection refused"
- SMTP server or port is incorrect
- Firewall blocking the connection

#### "Authentication failed"
- Wrong username/password
- For Gmail: need App Password, not regular password

#### "Relay access denied"
- SMTP server requires authentication
- Username/password not provided or incorrect

### Log Files

- **msmtp logs**: `/var/log/msmtp.log`
- **PHP logs**: Check error_log() output in PHP logs
- **Container logs**: `docker logs radiograb-web-1`

## Security Considerations

1. **Use App Passwords**: For Gmail and other providers, use app-specific passwords
2. **Secure credentials**: Keep SMTP credentials in environment variables, not code
3. **TLS/SSL**: Always use encrypted connections (port 587 with TLS or 465 with SSL)
4. **From address**: Use a legitimate from address that matches your domain

## Email Templates

RadioGrab includes HTML email templates for:

- **Password Reset**: Professional-looking reset emails with buttons
- **Email Verification**: Welcome emails for new user registration

Both templates are responsive and include proper fallbacks for text-only email clients.

## Deployment

After configuring environment variables:

1. Rebuild and restart containers:
   ```bash
   docker-compose down
   docker-compose up -d --build
   ```

2. Test email functionality using the admin panel

3. Monitor logs during initial testing:
   ```bash
   docker logs -f radiograb-web-1
   ```

## Production Recommendations

1. **Use a dedicated SMTP service** (SendGrid, Mailgun, Amazon SES)
2. **Set up SPF/DKIM records** for your domain
3. **Monitor email delivery rates**
4. **Set up proper bounce handling**
5. **Use a dedicated subdomain** for email (e.g., mail.yourdomain.com)

---

*This configuration ensures reliable email delivery for RadioGrab's password resets and user verification emails.*