# cPanel Deployment Guide
## Bright Light Ministry Int'l Partners Portal

---

## 1. Upload Files to cPanel

1. Log in to your cPanel account
2. Navigate to **File Manager**
3. Go to your `public_html` directory (or subdirectory like `public_html/portal`)
4. Upload the entire contents of this zip file
5. **Important:** Do NOT upload `.gitignore` or `cpanel-deploy/` folder to production

---

## 2. Secure the .env File (CRITICAL)

### Step 2a: Create the .env file
1. In cPanel File Manager, navigate to your application root (e.g., `public_html`)
2. Create a new file named `.env`
3. Copy the contents of `.env.example` into `.env`
4. Update the values with your actual credentials:

```
DB_HOST=localhost
DB_PORT=3306
DB_NAME=your_database_name
DB_USER=your_database_user
DB_PASSWORD=your_strong_database_password

PAYSTACK_SECRET_KEY=sk_live_YOUR_LIVE_SECRET_KEY
PAYSTACK_PUBLIC_KEY=pk_live_YOUR_LIVE_PUBLIC_KEY
PAYSTACK_WEBHOOK_SECRET=whsec_YOUR_WEBHOOK_SECRET
PAYSTACK_WEBHOOK_URL=https://yourdomain.com/paystack/webhook
PAYSTACK_ENVIRONMENT=live

RESEND_API_KEY=re_YOUR_API_KEY
RESEND_FROM_EMAIL=noreply@yourdomain.com
RESEND_FROM_NAME=Bright Light Ministry Int'l

CURRENCY_API_KEY=YOUR_EXCHANGERATE_API_KEY

RECAPTCHA_SECRET_KEY=your_recaptcha_secret_key
RECAPTCHA_SITE_KEY=your_recaptcha_site_key
```

### Step 2b: Protect the .env file
1. In cPanel File Manager, right-click on `.env`
2. Select **Permissions** or **Change Permissions**
3. Set permissions to `600` (owner read/write only)
4. Alternatively, add this to your `.htaccess` file:

```apache
<Files ".env">
    Order allow,deny
    Deny from all
</Files>
```

---

## 3. Create MySQL Database

1. In cPanel, go to **MySQL Databases**
2. Create a new database (e.g., `username_church`)
3. Create a new database user with a strong password
4. Add the user to the database with **ALL PRIVILEGES**
5. Update your `.env` file with the database name, user, and password

---

## 4. Import Database Schema

1. In cPanel, go to **phpMyAdmin**
2. Select your database from the left sidebar
3. Click **Import** tab
4. Choose `database/schema.sql` from your uploaded files
5. Click **Go** to import

### Run Migrations (if needed)
After importing the main schema, run these migration files in order:
1. `database/migrations/add_email_verification_tables.sql`
2. `database/migrations/add_email_verification_system.sql`
3. `database/migrations/add_paystack_fields.sql`
4. `database/migrations/add_project_milestones_table.sql`
5. `database/migrations/add_project_support_category.sql`
6. `database/migrations/add_public_portal_settings.sql`
7. `database/migrations/add_support_ticket_messages.sql`
8. `database/migrations/fix_payment_subscriptions.sql`
9. `database/migrations/fix_transactions_user_id.sql`

---

## 5. Create Admin User

1. In phpMyAdmin, run the SQL from `create_admin_igbaniangus.sql`
2. Or use the `create_admin.php` script by visiting:
   `https://yourdomain.com/create_admin.php`
3. **Delete `create_admin.php` after use** for security

---

## 6. Configure Web Server

### If using Apache (most cPanel setups):
Create or edit `.htaccess` in your document root:

```apache
RewriteEngine On

# Block access to .env file
<Files ".env">
    Order allow,deny
    Deny from all
</Files>

# Block access to sensitive directories
<IfModule mod_rewrite.c>
    RewriteRule ^\.env - [F,L]
    RewriteRule ^database/ - [F,L]
    RewriteRule ^src/cache/ - [F,L]
</IfModule>

# Route to public directory
RewriteCond %{REQUEST_URI} !^/public/
RewriteRule ^(.*)$ /public/$1 [L]
```

### Set Document Root to public/ folder:
1. In cPanel, go to **Domains**
2. Edit your domain
3. Set document root to `/public_html/public`

---

## 7. Set Up Paystack Webhook

1. Log in to your Paystack Dashboard
2. Go to **Settings > API Keys & Webhooks**
3. Add webhook URL: `https://yourdomain.com/paystack/webhook`
4. Enable these events:
   - `charge.success`
   - `charge.failed`
   - `subscription.success`
   - `subscription.active`
   - `subscription.failure`
   - `subscription.expired`
5. Copy the webhook secret to your `.env` file

---

## 8. Set Up Email (Resend)

1. Sign up at [resend.com](https://resend.com)
2. Create an API key
3. Verify your domain for sending emails
4. Add the API key to your `.env` file

---

## 9. Security Checklist

- [ ] `.env` file permissions set to 600
- [ ] `.htaccess` blocks access to `.env`
- [ ] `create_admin.php` deleted after use
- [ ] Database user has only necessary privileges
- [ ] Paystack set to LIVE mode with live keys
- [ ] Error reporting disabled in production (check `admin/includes/config.php` and `src/bootstrap.php`)
- [ ] HTTPS enabled on your domain
- [ ] reCAPTCHA keys configured
- [ ] Regular database backups scheduled

---

## 10. Test Your Deployment

1. Visit `https://yourdomain.com` - Public portal should load
2. Visit `https://yourdomain.com/admin/` - Admin login should appear
3. Test a small donation with Paystack test mode first
4. Verify email notifications are working
5. Check admin dashboard loads correctly

---

## Troubleshooting

### "Database connection failed"
- Verify DB_HOST, DB_NAME, DB_USER, DB_PASSWORD in `.env`
- Ensure database user has correct privileges

### "500 Internal Server Error"
- Check error logs in cPanel > Errors
- Ensure `display_errors` is set to 0 in production
- Verify file permissions (folders: 755, files: 644)

### "Paystack webhook not working"
- Verify webhook URL is accessible (not blocked by firewall)
- Check Paystack logs in dashboard
- Ensure `PAYSTACK_WEBHOOK_SECRET` is correct

### "Emails not sending"
- Verify RESEND_API_KEY is correct
- Check domain is verified in Resend dashboard
- Check Resend API logs