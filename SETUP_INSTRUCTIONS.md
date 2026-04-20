# Admin Dashboard Setup Instructions

## Step 1: Run Database Schema

### Option A: Using MySQL Command Line

```bash
# Navigate to the database directory
cd database

# Run the base schema
mysql -u root -p church_partnership < schema.sql

# Run the admin schema extensions
mysql -u root -p church_partnership < admin_schema.sql
```

### Option B: Using Docker (if using the existing docker-compose)

```bash
# From the project root directory
docker-compose exec mysql mysql -u church_user -pchurch_password123 church_partnership < /app/database/schema.sql

docker-compose exec mysql mysql -u church_user -pchurch_password123 church_partnership < /app/database/admin_schema.sql
```

### Option C: Using phpMyAdmin or MySQL Workbench

1. Open phpMyAdmin or MySQL Workbench
2. Select the `church_partnership` database
3. Go to the SQL tab
4. Copy and paste the contents of `database/schema.sql` and execute
5. Copy and paste the contents of `database/admin_schema.sql` and execute

## Step 2: Create an Admin User

### Method 1: Using SQL (Recommended)

```sql
-- Step 1: Create the user account
INSERT INTO users (email, password_hash, first_name, last_name, phone, is_active, is_verified) 
VALUES (
    'admin@gracecathedral.org', 
    '$2y$10$9rTE8v8qJZvN5KxJ5KxJ5KxJ5KxJ5KxJ5KxJ5KxJ5KxJ5KxJ5KxJ5', 
    'Admin', 
    'User', 
    '555-0000', 
    TRUE, 
    TRUE
);

-- Step 2: Get the user_id (replace 2 with the actual ID from the query)
SELECT user_id FROM users WHERE email = 'admin@gracecathedral.org';

-- Step 3: Create the admin record (replace 2 with the actual user_id)
INSERT INTO admin_users (user_id, jwt_secret) 
VALUES (2, 'admin-jwt-secret-key-change-in-production-' || MD5(RAND()));

-- Step 4: Assign admin role (replace 2 with the actual user_id)
INSERT INTO user_role_assignments (user_id, role_id) 
VALUES (2, 1)
ON DUPLICATE KEY UPDATE role_id = 1;
```

### Method 2: Using PHP Script

Create a file called `create_admin.php` in the project root:

```php
<?php
/**
 * Create Admin User Script
 * Run this once to create the initial admin account
 */

require_once 'src/config/database.php';

$email = 'admin@gracecathedral.org';
$password = 'admin123'; // Change this after first login
$firstName = 'Admin';
$lastName = 'User';
$phone = '555-0000';

try {
    $db = Database::getInstance();
    
    // Check if user already exists
    $existing = $db->fetchOne(
        "SELECT user_id FROM users WHERE email = :email",
        ['email' => $email]
    );
    
    if ($existing) {
        echo "User already exists with ID: " . $existing['user_id'] . "\n";
        $userId = $existing['user_id'];
    } else {
        // Hash the password
        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        
        // Insert user
        $db->execute(
            "INSERT INTO users (email, password_hash, first_name, last_name, phone, is_active, is_verified) 
             VALUES (:email, :password_hash, :first_name, :last_name, :phone, TRUE, TRUE)",
            [
                'email' => $email,
                'password_hash' => $passwordHash,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone
            ]
        );
        
        $userId = $db->lastInsertId();
        echo "User created with ID: " . $userId . "\n";
    }
    
    // Check if admin record exists
    $adminExists = $db->fetchOne(
        "SELECT admin_id FROM admin_users WHERE user_id = :user_id",
        ['user_id' => $userId]
    );
    
    if (!$adminExists) {
        // Generate JWT secret
        $jwtSecret = 'admin-jwt-secret-key-change-in-production-' . md5(uniqid());
        
        // Create admin record
        $db->execute(
            "INSERT INTO admin_users (user_id, jwt_secret) VALUES (:user_id, :jwt_secret)",
            ['user_id' => $userId, 'jwt_secret' => $jwtSecret]
        );
        echo "Admin record created\n";
    } else {
        echo "Admin record already exists\n";
    }
    
    // Assign admin role
    $adminRole = $db->fetchOne("SELECT role_id FROM user_roles WHERE role_name = 'admin'");
    if ($adminRole) {
        $db->execute(
            "INSERT INTO user_role_assignments (user_id, role_id) VALUES (:user_id, :role_id)
             ON DUPLICATE KEY UPDATE role_id = :role_id",
            ['user_id' => $userId, 'role_id' => $adminRole['role_id']]
        );
        echo "Admin role assigned\n";
    }
    
    echo "\n========================================\n";
    echo "Admin account created successfully!\n";
    echo "Email: $email\n";
    echo "Password: $password\n";
    echo "========================================\n";
    echo "\nIMPORTANT: Change the password after first login!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
```

Run the script:
```bash
php create_admin.php
```

## Step 3: Access the Admin Dashboard

### Option A: Using Docker

```bash
# Start the Docker containers
docker-compose up -d

# The admin dashboard will be available at:
# http://localhost/admin/
```

### Option B: Using Local Web Server

1. Make sure your web server is running (Apache/Nginx)
2. Navigate to: `http://localhost/admin/` or `http://your-domain.com/admin/`

### Option C: Using PHP Built-in Server

```bash
# From the project root directory
php -S localhost:8000 -t src/

# Then in another terminal, run the admin files
# You may need to configure the admin directory separately
```

## Step 4: Login

1. Navigate to `http://localhost/admin/login.php`
2. Enter the admin credentials:
   - Email: `admin@gracecathedral.org`
   - Password: `admin123` (or the password you set)
3. Click "Sign In to Admin Dashboard"

## Troubleshooting

### Database Connection Issues

If you get database connection errors, check your database credentials in:
- `src/config/database.php`
- `admin/includes/config.php`

### Table Not Found Errors

Make sure you ran both schema files:
```bash
mysql -u root -p church_partnership < database/schema.sql
mysql -u root -p church_partnership < database/admin_schema.sql
```

### Permission Denied Errors

Ensure the web server has proper permissions to access the admin directory.

## Next Steps

1. Change the default admin password after first login
2. Configure your Paystack API keys for payment processing
3. Customize the dashboard to match your branding
4. Set up email notifications for important events