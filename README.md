# Electronic Voting System - Setup Instructions

## Overview
This is a secure, web-based electronic voting system built with PHP and MySQL, following industry best practices for security, scalability, and user experience.

## Features
- ✅ Secure user authentication with password hashing
- ✅ Role-based access control (Admin/Voter)
- ✅ Session management with timeout protection
- ✅ CSRF protection on all forms
- ✅ SQL injection prevention with prepared statements
- ✅ Input validation and sanitization
- ✅ Anonymous voting with cryptographic hashing
- ✅ Real-time election results
- ✅ Comprehensive audit logging
- ✅ Rate limiting on login attempts
- ✅ Responsive web design
- ✅ Vote integrity verification

## Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- Modern web browser (Chrome, Firefox, Safari, Edge)

## Installation Steps

### 1. Server Setup
1. Install XAMPP, WAMP, or similar local development environment
2. Start Apache and MySQL services
3. Ensure PHP extensions are enabled: PDO, PDO_MySQL, session

### 2. Database Setup
1. Open phpMyAdmin or MySQL command line
2. Create a new database named `voting_system`
3. Import the SQL file: `database/voting_system.sql`
4. Verify tables are created successfully

### 3. Configuration
1. Copy all files to your web server directory (e.g., `htdocs/Finals/`)
2. Update database configuration in `config/database.php`:
   ```php
   private $host = 'localhost';
   private $dbname = 'voting_system';
   private $username = 'root';
   private $password = '';
   ```

### 4. Security Configuration
1. In production, set `display_errors = 0` in `includes/app.php`
2. Generate new secret keys for password hashing
3. Configure HTTPS for production deployment
4. Set appropriate file permissions (644 for files, 755 for directories)

### 5. First Run
1. Access the application: `http://localhost/Finals/`
2. Register a new user account or use the default admin:
   - Username: `admin`
   - Password: `admin123` (change immediately)

## User Roles

### Admin Users
- Create and manage elections
- Add/edit/delete candidates
- View comprehensive reports
- Monitor system security
- Access audit logs
- Manage user accounts

### Voter Users
- View active elections
- Cast votes securely
- View election results
- Update profile information

## Security Features

### Authentication & Authorization
- Secure password hashing using PHP's `password_hash()`
- Role-based access control
- Session management with automatic timeout
- Rate limiting on login attempts

### Input Validation
- CSRF token validation on all forms
- SQL injection prevention with prepared statements
- XSS protection through input sanitization
- Data type validation and length checks

### Vote Integrity
- Anonymous voting using cryptographic hashing
- One vote per user per election enforcement
- Vote audit trail without compromising anonymity
- Real-time integrity verification

### Session Security
- Secure session configuration
- Automatic session regeneration
- Session timeout warnings
- IP address tracking

## File Structure
```
/Finals/
├── admin/                  # Admin-only pages
│   ├── dashboard.php      # Admin dashboard
│   └── ...
├── api/                   # AJAX endpoints
│   ├── cast_vote.php      # Vote submission
│   ├── session_check.php  # Session validation
│   └── ...
├── assets/                # Static assets
│   ├── css/
│   │   └── style.css      # Main stylesheet
│   └── js/
│       └── app.js         # JavaScript functionality
├── classes/               # Core application classes
│   ├── Auth.php          # Authentication
│   ├── Election.php      # Election management
│   ├── Candidate.php     # Candidate management
│   └── Vote.php          # Voting logic
├── config/               # Configuration files
│   └── database.php      # Database connection
├── database/             # Database schema
│   └── voting_system.sql # Database structure
├── includes/             # Include files
│   ├── app.php           # Application bootstrap
│   ├── header.php        # Page header
│   ├── footer.php        # Page footer
│   ├── security.php      # Security utilities
│   └── session.php       # Session management
├── login.php             # User login
├── register.php          # User registration
├── dashboard.php         # User dashboard
├── vote.php              # Voting interface
├── results.php           # Election results
└── index.php             # Application entry point
```

## Testing Guidelines

### Unit Testing
Test individual components:
- User authentication functions
- Vote casting and validation
- Election management operations
- Security functions (CSRF, validation, etc.)

### Integration Testing
Test complete workflows:
1. User registration → Login → Vote → View Results
2. Admin creates election → Adds candidates → Monitors voting
3. Session timeout and security features

### Security Testing
- SQL injection attempts
- XSS attack vectors
- CSRF token manipulation
- Session hijacking attempts
- Rate limiting verification

## Usage Instructions

### For Administrators
1. **Creating Elections:**
   - Log in as admin
   - Go to Admin Dashboard
   - Click "Create Election"
   - Fill in election details (title, description, dates)
   - Add candidates to the election

2. **Managing Elections:**
   - View all elections from the admin dashboard
   - Edit election details before voting starts
   - Monitor real-time voting progress
   - Generate reports after completion

### For Voters
1. **Registration:**
   - Visit the registration page
   - Provide required information
   - Verify email address (if implemented)

2. **Voting:**
   - Log in to your account
   - View active elections
   - Select your preferred candidate
   - Confirm your vote (irreversible)

3. **Results:**
   - View real-time results during active elections
   - Access final results after election completion

## Maintenance

### Regular Tasks
- Monitor audit logs for suspicious activity
- Clean up expired sessions
- Backup database regularly
- Update PHP and MySQL versions
- Review and rotate security tokens

### Performance Optimization
- Enable database indexing
- Implement caching for frequently accessed data
- Optimize images and static assets
- Monitor server resources during peak voting

## Troubleshooting

### Common Issues
1. **Database Connection Errors:**
   - Verify MySQL service is running
   - Check database credentials in `config/database.php`
   - Ensure database exists and tables are created

2. **Session Issues:**
   - Check PHP session configuration
   - Verify file permissions on session directory
   - Clear browser cookies and cache

3. **Voting Errors:**
   - Ensure election is active
   - Check user hasn't already voted
   - Verify candidate exists in election

### Error Logs
- Check PHP error logs
- Review application audit logs
- Monitor web server access logs

## Production Deployment

### Security Checklist
- [ ] Enable HTTPS
- [ ] Disable debug mode
- [ ] Set secure session cookies
- [ ] Configure proper file permissions
- [ ] Enable database backup automation
- [ ] Set up monitoring and alerting
- [ ] Configure rate limiting at server level
- [ ] Enable CORS protection
- [ ] Set security headers (CSP, HSTS, etc.)

### Performance Checklist
- [ ] Enable compression (gzip)
- [ ] Configure caching headers
- [ ] Optimize database queries
- [ ] Set up CDN for static assets
- [ ] Configure database connection pooling
- [ ] Enable opcache for PHP

## Support & Documentation
For additional support or to report security issues, please contact the development team.

## License
This Electronic Voting System is built for educational and demonstration purposes. Ensure compliance with local election laws and regulations before deployment in production environments.
