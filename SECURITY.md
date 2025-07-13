# Security Configuration for Electronic Voting System

## Session Security
- Session timeout: 30 minutes
- Session regeneration: Every 5 minutes
- Secure cookies enabled
- HTTPOnly cookies enabled
- SameSite: Strict

## CSRF Protection
- CSRF tokens generated for all forms
- Tokens verified on sensitive operations
- Token regeneration on successful operations

## Rate Limiting
- Login attempts: 5 per IP per 15 minutes
- Vote attempts: Tracked and logged

## Password Security
- Minimum length: 8 characters
- Required: Uppercase, lowercase, number, special character
- Hashing: PHP password_hash() with default algorithm

## Input Validation
- All user input sanitized with htmlspecialchars()
- SQL injection prevention via prepared statements
- Integer validation for all numeric inputs
- Username validation: alphanumeric and underscore only

## Security Headers
- X-Content-Type-Options: nosniff
- X-Frame-Options: DENY
- X-XSS-Protection: 1; mode=block
- Strict-Transport-Security: enabled
- Content-Security-Policy: restrictive policy

## Logging
- All security events logged to /logs/security.log
- Login attempts (success/failure)
- Vote casting events
- Admin operations
- Unauthorized access attempts

## Database Security
- All queries use prepared statements
- Database connection uses PDO with secure options
- Foreign key constraints enforced
- Indexes for performance and security

## Vote Integrity
- One vote per user per election (database constraint)
- Vote casting within election timeframe only
- Transaction-based vote recording
- Audit trail with timestamps

## Access Control
- Role-based access (admin/voter)
- Admin functions restricted to admin role
- Session validation on every request
- Automatic logout on session timeout

## Error Handling
- User-friendly error messages
- Detailed logging for debugging
- No sensitive information in error responses
- Graceful handling of database errors
