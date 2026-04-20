# Admin Dashboard - Church Financial Partnership System

A comprehensive admin dashboard for managing the Church Financial Partnership System with role-based authentication, comprehensive CRUD operations, and transaction tracking.

## Features

### Authentication & Security
- **JWT-based authentication** for admin users
- **Role-Based Access Control (RBAC)** with permission tiers (super_admin, project_admin, finance_admin)
- **CSRF protection** on all state-changing operations
- **Rate limiting** for authentication attempts (5 failed attempts = 15 min lockout)
- **Audit logging** of all admin actions
- **Password hashing** using bcrypt

### Modules

#### 1. Dashboard Overview
- Real-time metrics cards (total users, active projects, monthly revenue, pending transactions)
- Recent transactions feed
- Active projects with progress indicators
- Payment plans summary
- Recent users list

#### 2. Projects Management
- Full CRUD operations for projects
- Tag management system (create, assign, remove tags)
- Project metadata tracking (creation date, last modified, status)
- Progress visualization with funding bars
- Category-based filtering

#### 3. User Management
- View all user profiles with complete account details
- Total contribution amount per user (aggregated from transactions)
- Projects participation tracking
- Account management actions:
  - Pause/activate account
  - Delete account (with confirmation)
  - Edit profile/biodata
  - Reset password (secure token-based flow)
- Accidental deletion prevention

#### 4. Partners Module
- View users with active recurring payment plans
- Subscription status tracking
- Next billing date and plan expiration dates
- Top contributors display
- Upcoming pledge endings alerts

#### 5. Payment Plans
- View all payment plans (one-time, weekly, monthly, annual)
- Create new subscription plans
- Update existing plan details
- Pause/suspend plans (preserves existing subscriber data)
- Paystack plan code integration field

#### 6. Transaction Management
- Display all app-wide transactions
- Transaction status tracking (successful, failed, pending)
- Transaction details: user, amount, date, payment method, project
- Filter/search capabilities:
  - By status
  - By date range
  - By project
  - By search (email, reference)
- Pagination support
- CSV export

#### 7. Admin Members
- Grant/revoke admin access to existing users
- Prevent all admins from being removed (at least one must remain)
- Prevent self-revocation
- Audit trail of admin changes

#### 8. Audit Logs
- Comprehensive logging of all admin actions
- Filter by action type, date range, admin user
- View IP addresses and user agents
- CSV export

#### 9. Data Export
- Export transactions to CSV
- Export users to CSV
- Export partners to CSV
- Export projects to CSV
- Export payment plans to CSV
- Export audit logs to CSV

## Installation

### Prerequisites
- PHP 8.0+
- MySQL 5.7+ or MariaDB 10.3+
- Web server (Apache/Nginx)

### Database Setup

1. Run the base schema:
```bash
mysql -u root -p church_partnership < database/schema.sql
```

2. Run the admin schema extensions:
```bash
mysql -u root -p church_partnership < database/admin_schema.sql
```

### Configuration

1. Copy the `.env.example` to `.env` and configure:
```
DB_HOST=localhost
DB_PORT=3306
DB_NAME=church_partnership
DB_USER=church_user
DB_PASSWORD=your_password
JWT_SECRET=your-secure-jwt-secret-key
```

2. Set up web server to point to the `admin/` directory or configure URL rewriting.

### Default Admin Account

After running the schema, create an admin user:

1. Create a user account with admin privileges:
```sql
-- Create user
INSERT INTO users (email, password_hash, first_name, last_name, is_active, is_verified) 
VALUES ('admin@gracecathedral.org', '$2y$10$your-bcrypt-hash', 'Admin', 'User', TRUE, TRUE);

-- Create admin record
INSERT INTO admin_users (user_id, jwt_secret) 
VALUES (LAST_INSERT_ID(), 'generate-random-secret-here');

-- Assign admin role
INSERT INTO user_role_assignments (user_id, role_id) 
VALUES (LAST_INSERT_ID(), 1);
```

2. Use a password generator to create the bcrypt hash:
```php
echo password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12]);
```

## File Structure

```
admin/
├── index.php              # Dashboard overview
├── login.php              # Admin login page
├── logout.php             # Logout handler
├── export.php             # Data export endpoint
├── projects.php           # Projects management
├── users.php              # User management
├── transactions.php       # Transaction management
├── plans.php              # Payment plans management
├── partners.php           # Active partners view
├── admin-members.php      # Admin member management
├── audit-logs.php         # Audit logs viewer
├── README.md              # This file
├── includes/
│   ├── config.php         # Configuration and helper functions
│   ├── database.php       # Database connection class
│   └── Auth.php           # Authentication class
└── models/                # (Optional) Additional models
```

## Security Features

1. **Row-level security**: Database constraints ensure only admin users can access admin routes
2. **Server-side validation**: All inputs validated server-side, not just client-side
3. **Prepared statements**: All SQL queries use prepared statements to prevent injection
4. **XSS prevention**: All output escaped using `e()` function
5. **Session management**: Secure session handling with token validation
6. **Audit trail**: All sensitive actions logged with before/after snapshots

## API Endpoints

The admin dashboard uses server-rendered pages. For API integration, you can extend the `includes/` folder with API endpoints.

## Customization

### Adding New Modules

1. Create a new PHP file in the `admin/` directory
2. Include `require_once __DIR__ . '/includes/config.php';` at the top
3. Call `requireAdmin()` to enforce authentication
4. Add navigation link in the sidebar of existing pages

### Styling

The dashboard uses CSS variables for consistent theming:
- `--gold`: Primary accent color (#D4AF37)
- `--dark`: Primary dark color (#1a1a2e)
- `--cream`: Background color (#FDFBF7)

## Support

For issues or questions, please contact the system administrator.