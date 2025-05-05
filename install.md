# Installation Guide

## Prerequisites
- PHP 7.4 or later
- MySQL 5.7 or later
- Apache or Nginx web server
- PHP extensions: PDO, cURL, mbstring

## Installation Steps
1. Clone or download the repository to your server.
2. Ensure the `setup.sql` file is in the root directory.
3. Set the correct permissions for the project folder:
   ```bash
   chmod -R 755 /path/to/project
   ```
4. Navigate to the project folder in your browser:
   ```
   http://your-domain.com/install.php
   ```
5. Fill in the database credentials and click "Install".
6. After installation, delete `install.php` for security purposes.

## Troubleshooting
- **Database Connection Error**: Ensure the database host, username, and password are correct.
- **File Permission Issues**: Ensure the web server has write access to the project directory.