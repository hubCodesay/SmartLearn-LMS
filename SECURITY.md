# Security Policy

## Supported Versions

We release patches for security vulnerabilities for the following versions:

| Version | Supported          |
| ------- | ------------------ |
| 1.0.x   | :white_check_mark: |
| < 1.0   | :x:                |

## Reporting a Vulnerability

We take the security of SmartLearn LMS seriously. If you discover a security vulnerability, please follow these steps:

### Private Disclosure

**Please do NOT create a public GitHub issue for security vulnerabilities.**

Instead, please email us at: **security@stabion.studio**

### What to Include

Please include the following information in your report:
- Type of vulnerability
- Full paths of affected source files
- Location of the affected code (tag/branch/commit)
- Step-by-step instructions to reproduce the issue
- Proof-of-concept or exploit code (if possible)
- Impact of the issue (what can an attacker do)
- Suggested fix (if you have one)

### Response Timeline

- **Initial Response**: Within 48 hours
- **Status Update**: Within 7 days
- **Fix Timeline**: Depends on severity
  - Critical: Within 7 days
  - High: Within 14 days
  - Medium: Within 30 days
  - Low: Next release

### What to Expect

1. **Acknowledgment**: We'll acknowledge receipt of your report
2. **Investigation**: We'll investigate and confirm the vulnerability
3. **Fix**: We'll develop a fix
4. **Release**: We'll release a security update
5. **Credit**: We'll publicly credit you (if you wish)

## Security Best Practices

When using SmartLearn LMS, we recommend:

### WordPress Security
- Keep WordPress core updated
- Keep all plugins and themes updated
- Use strong passwords
- Enable two-factor authentication
- Use security plugins (e.g., Wordfence)
- Regular backups
- Use HTTPS/SSL

### Plugin Security
- Download only from official sources
- Verify plugin authenticity
- Keep SmartLearn LMS updated
- Review user permissions
- Monitor access logs
- Use security headers

### WooCommerce Security
- Keep WooCommerce updated
- Use secure payment gateways
- Enable SSL for checkout
- Regular security audits
- Monitor for suspicious orders

### Server Security
- Use latest PHP version (7.4+)
- Enable security extensions
- Configure firewall
- Disable directory listing
- Set proper file permissions
- Regular security scans

## Known Security Features

SmartLearn LMS includes:

### Input Validation
- All user inputs are sanitized
- Nonce verification for forms
- Capability checks for admin actions
- SQL injection prevention
- XSS prevention

### Access Control
- Role-based permissions
- Purchase verification
- User authentication checks
- Admin-only settings
- Secure AJAX handlers

### Data Protection
- No sensitive data in frontend
- Secure meta data handling
- Protected API endpoints
- Encrypted user data (via WordPress)

### Code Security
- ABSPATH checks in all files
- No direct file access
- Proper escaping of output
- Prepared SQL statements
- Security headers

## Vulnerability Disclosure Policy

We believe in responsible disclosure:
1. Report vulnerability privately
2. Give us time to fix (90 days)
3. We'll release fix and credit you
4. Then you can publicly disclose

## Bug Bounty

Currently, we don't have a formal bug bounty program, but we:
- Publicly credit researchers
- Feature you in release notes
- Provide early access to fixes
- Consider donations/gifts for critical issues

## Security Updates

Security updates are released as:
- **Critical**: Immediate patch release
- **High**: Priority release (1-2 weeks)
- **Medium**: Next minor version
- **Low**: Next major version

Updates are announced via:
- WordPress.org plugin page
- GitHub releases
- Email notification
- Blog post on stabion.studio

## Contact

For security issues: **security@stabion.studio**

For other issues: **support@stabion.studio**

Website: [stabion.studio](https://stabion.studio)

---

Thank you for helping keep SmartLearn LMS secure! 🔒
