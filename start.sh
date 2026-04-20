#!/bin/bash

# Church Partnership Admin Dashboard - Docker Startup Script

echo "=========================================="
echo "Starting Church Partnership Admin Dashboard"
echo "=========================================="

# Start Docker containers
echo "Starting Docker containers..."
docker-compose up -d

# Wait for MySQL to be ready
echo "Waiting for MySQL to be ready..."
sleep 10

# Check if MySQL is ready
docker-compose exec -T mysql mysqladmin ping -h localhost -u church_user -pchurch_password123 --silent

if [ $? -eq 0 ]; then
    echo "MySQL is ready!"
    
    # Run the admin creation script
    echo "Creating admin user..."
    docker-compose exec -T php php /var/www/html/create_admin.php
else
    echo "MySQL is not ready. Please wait a moment and try again."
    exit 1
fi

echo ""
echo "=========================================="
echo "Setup Complete!"
echo "=========================================="
echo ""
echo "Access the application:"
echo "  - User Portal: http://localhost/"
echo "  - Admin Dashboard: http://localhost/admin/"
echo "  - Admin Login: http://localhost/admin/login.php"
echo ""
echo "Admin Credentials:"
echo "  - Email: admin@gracecathedral.org"
echo "  - Password: admin123"
echo ""
echo "IMPORTANT: Change the password after first login!"
echo "=========================================="