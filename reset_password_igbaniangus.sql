-- Reset password for igbaniangus@gmail.com to: password123
-- Run this SQL in your MySQL database

UPDATE users 
SET password_hash = '$2y$12$4J/F5PQlK4HeaRDMi751KOy8oPrFSd9njhRbP6S.WUAM4dXYbDeAG'
WHERE email = 'igbaniangus@gmail.com';

-- Verify the update
SELECT user_id, email, first_name, last_name, is_verified, is_active 
FROM users 
WHERE email = 'igbaniangus@gmail.com';