# Security Configuration Guide

## Overview

This project has been updated to remove hardcoded secrets and webhooks. All sensitive configuration should now be managed through environment variables or secure configuration files.

## What Changed

### Removed Hardcoded Secrets
- ✅ Database credentials removed from `db.php`
- ✅ Bitrix24 webhook URLs and tokens removed from `api/bitrix/config.php`
- ✅ Diagnostic keys removed from `api/webhook_ping.php`
- ✅ Placeholder webhook URLs replaced in `api/bitrix_out.php`
- ✅ Portal URLs made configurable in `warehouse_orders.php`, `sell.php`, `report_day.php`

### Added Security Features
- ✅ Environment variable support for all sensitive data
- ✅ Webhook authentication validation in `api/webhook.php`
- ✅ Example `.env.example` file for configuration template
- ✅ Updated `.gitignore` to prevent committing secrets
- ✅ Bitrix24 portal domain now configurable

## Setup Instructions

### 1. Create Environment Configuration

Copy `.env.example` to `.env`:

```bash
cp .env.example .env
```

### 2. Configure Database

Edit `.env` and set your database credentials:

```env
DB_HOST=localhost
DB_NAME=your_database
DB_USER=your_user
DB_PASS=your_secure_password
```

### 3. Configure Bitrix24 Integration

Get your webhook URLs from your Bitrix24 portal and add them to `.env`:

```env
BITRIX_WEBHOOK=https://your-portal.bitrix24.com/rest/1/your_token_here/
BITRIX_CATALOG_WEBHOOK=https://your-portal.bitrix24.com/rest/1/catalog_token_here/
BITRIX_PORTAL_DOMAIN=your-portal.bitrix24.com
BITRIX_WEBHOOK_SECRET=generate_a_secure_random_token_here
```

### 4. Configure Stock Receipt API

If using the stock receipt API:

```env
STOCK_RECEIPT_API_SECRET=generate_a_secure_random_token_here
```

## Webhook Security

### Validating Incoming Webhooks

The `api/webhook.php` now validates incoming webhooks using a secret token:

1. **Generate a secret token** - Create a secure random string (recommended: 32+ characters)
2. **Set `BITRIX_WEBHOOK_SECRET`** in your `.env` file
3. **Configure Bitrix24** - When setting up outgoing webhooks, include the header:
   ```
   X-Webhook-Secret: your_secret_token
   ```

If the secret doesn't match, the webhook will be rejected with HTTP 401.

### Disabling Webhook Validation (Development Only)

To disable webhook validation during development, leave `BITRIX_WEBHOOK_SECRET` empty in `.env`:

```env
BITRIX_WEBHOOK_SECRET=
```

⚠️ **WARNING**: Only use this for development. Always enable validation in production.

## Best Practices

### For Development

1. Create a `.env.local` file for your local machine:
   ```bash
   cp .env.example .env.local
   ```

2. Update with your local development values

3. Never commit `.env` or `.env.local` files

### For Production Deployment

1. Use your hosting provider's environment variable management:
   - VPS/Dedicated: Set in `.bashrc`, systemd service, or Docker environment
   - Managed hosting (Beget, etc.): Use control panel settings
   - Docker: Use `.env` in production only with strict file permissions

2. Set file permissions:
   ```bash
   chmod 600 .env  # Only owner can read/write
   ```

3. Rotate secrets regularly:
   - Change `BITRIX_WEBHOOK_SECRET` periodically
   - Update Bitrix24 webhook tokens
   - Rotate database passwords

### Secret Generation

Generate secure random tokens using:

```bash
# Linux/Mac
openssl rand -hex 32

# Online (development only)
# Use: https://www.random.org/ (development only)
```

## Troubleshooting

### "Unauthorized" webhook responses

- Verify `BITRIX_WEBHOOK_SECRET` is set in `.env`
- Check that Bitrix24 is sending the `X-Webhook-Secret` header
- Ensure the header value matches your secret

### "Unauthorized" database connection

- Verify `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` in `.env`
- Check database server is running
- Verify credentials with your hosting provider

### Bitrix24 webhook URLs not working

- Verify `BITRIX_WEBHOOK` and `BITRIX_CATALOG_WEBHOOK` URLs are correct
- Test URLs manually from your server: `curl https://your-portal.bitrix24.com/rest/...`
- Check Bitrix24 API logs for errors

## Environment Variables Reference

| Variable | Required | Example | Notes |
|----------|----------|---------|-------|
| `DB_HOST` | Yes | `localhost` | Database server host |
| `DB_NAME` | Yes | `warehouse_db` | Database name |
| `DB_USER` | Yes | `user_name` | Database user |
| `DB_PASS` | Yes | `secure_pass` | Database password |
| `BITRIX_WEBHOOK` | Yes | `https://portal.bitrix24.com/rest/1/token/` | Main webhook URL |
| `BITRIX_CATALOG_WEBHOOK` | Yes | `https://portal.bitrix24.com/rest/1/token/` | Catalog webhook URL |
| `BITRIX_PORTAL_DOMAIN` | Yes | `portal.bitrix24.com` | Portal domain for links |
| `BITRIX_WEBHOOK_SECRET` | No* | `random_token_32_chars` | Webhook authentication secret |
| `STOCK_RECEIPT_API_SECRET` | No | `random_token_32_chars` | Stock receipt API secret |

*BITRIX_WEBHOOK_SECRET is optional but recommended for production

## Additional Security Measures

1. **Use HTTPS** - Always use HTTPS for webhook URLs
2. **Database Access** - Limit database user permissions to specific tables
3. **File Permissions** - Protect `.env` files with restrictive permissions
4. **Access Logging** - Monitor `webhook_log` table for suspicious activity
5. **Regular Backups** - Keep encrypted backups of configuration
6. **Code Review** - Review all changes before deploying to production
7. **Secret Rotation** - Rotate secrets on a regular schedule

## Support

For security issues or questions, please contact your system administrator or the development team.
