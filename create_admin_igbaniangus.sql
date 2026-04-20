-- Create admin user: igbaniangus@gmail.com
-- Password: password123 (bcrypt hashed)
-- Run this SQL in your MySQL database

-- First, create the user (skip if already exists)
INSERT INTO users (email, password_hash, first_name, last_name, is_verified, is_active)
VALUES (
    'igbaniangus@gmail.com',
    '$2y$12$tQ0m8GJEK7N2KFVD1LH3VOgD9MisVq9jK9pVq7/0skhCFdVnsIbF.',
    'Igbanian',
    'Gus',
    TRUE,
    TRUE
) ON DUPLICATE KEY UPDATE
    first_name = VALUES(first_name),
    last_name = VALUES(last_name),
    is_verified = TRUE,
    is_active = TRUE;

-- Get the user_id for the next steps
SET @user_id = (SELECT user_id FROM users WHERE email = 'igbaniangus@gmail.com' LIMIT 1);

-- Create admin entry (skip if already exists)
INSERT INTO admin_users (user_id, jwt_secret)
SELECT @user_id, HEX(RANDOM_BYTES(32))
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM admin_users WHERE user_id = @user_id
);

-- Assign admin role (skip if already assigned)
INSERT INTO user_role_assignments (user_id, role_id)
SELECT @user_id, (SELECT role_id FROM user_roles WHERE role_name = 'admin' LIMIT 1)
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM user_role_assignments 
    WHERE user_id = @user_id 
    AND role_id = (SELECT role_id FROM user_roles WHERE role_name = 'admin' LIMIT 1)
);

-- Verify the setup
SELECT 
    u.user_id,
    u.email,
    u.first_name,
    u.last_name,
    u.is_verified,
    u.is_active,
    au.admin_id,
    ur.role_name
FROM users u
LEFT JOIN admin_users au ON u.user_id = au.user_id
LEFT JOIN user_role_assignments ura ON u.user_id = ura.user_id
LEFT JOIN user_roles ur ON ura.role_id = ur.role_id
WHERE u.email = 'igbaniangus@gmail.com';