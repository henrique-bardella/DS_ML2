# Ticket Registration System

A comprehensive web-based ticket registration system with user roles, conditional workflows, and timeline tracking. Built with HTML, CSS, JavaScript (Bootstrap), PHP 7.1, and SQL Server.

## Features

### Core Functionality
- **Triage System**: Three categories (PADE, META, ENCARTEIRAMENTO POR EXCEÇÃO)
- **Dynamic Forms**: Category-specific forms with relevant fields
- **Timeline Tracking**: Complete audit trail of all ticket interactions
- **User Management**: Role-based access control
- **File Attachments**: Support for multiple file types
- **Responsive Design**: Mobile-friendly interface with glassmorphism effects

### User Roles & Permissions

#### Requester
- Create tickets in any category
- View only their own tickets
- Add comments to their tickets
- Upload attachments

#### Analyst
- View tickets assigned to them
- Update ticket status and add comments
- Bulk status updates for multiple tickets
- Access to analyst dashboard

#### Admin/Master
- Full system access
- View all tickets
- Assign tickets to analysts
- Manage users and system settings
- Comprehensive reporting dashboard

### Design Philosophy
- **Apple-inspired Design**: Clean, minimal interface with attention to detail
- **Glassmorphism Effects**: Modern blur and transparency effects
- **Elegant Color Palette**: Carefully selected colors (#972D7A, #AF286F, #FDFBFC, etc.)
- **Responsive Layout**: Optimized for all screen sizes
- **Accessibility**: Proper contrast ratios and keyboard navigation

## Installation

### Prerequisites
- Web server (Apache/Nginx)
- PHP 7.1 or higher
- SQL Server 2017 or higher
- PHP SQL Server drivers (SQLSRV)

### Database Setup

1. Create a new SQL Server database named `TicketRegistrationDB`
2. Run the schema script:
   ```sql
   -- Execute the contents of database/schema.sql
   ```

3. Update database connection settings in `config/database.php`:
   ```php
   $this->host = 'your_server_name';
   $this->database = 'TicketRegistrationDB';
   $this->username = 'your_username';
   $this->password = 'your_password';
   ```

### File Structure
```
ticket-registration-system/
├── index.html                 # Main triage page
├── dashboard.html             # User dashboard
├── ticket-detail.html         # Ticket detail with timeline
├── css/
│   └── style.css             # Main stylesheet
├── js/
│   └── app.js                # Frontend JavaScript
├── forms/
│   ├── pade-form.html        # PADE category form
│   ├── meta-form.html        # META category form
│   └── encarteiramento-form.html # Exception form
├── api/
│   ├── submit-ticket.php     # Ticket submission endpoint
│   ├── get-tickets.php       # Ticket listing with filters
│   ├── get-stats.php         # Dashboard statistics
│   └── get-timeline.php      # Timeline interactions
├── config/
│   └── database.php          # Database configuration
├── database/
│   └── schema.sql            # Database schema
├── uploads/                  # File upload directory
└── README.md
```

### Web Server Configuration

#### Apache (.htaccess)
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^api/(.*)$ api/$1.php [QSA,L]

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
```

#### Nginx
```nginx
location /api/ {
    try_files $uri $uri/ @api;
}

location @api {
    rewrite ^/api/(.*)$ /api/$1.php last;
}

# Security headers
add_header X-Content-Type-Options nosniff;
add_header X-Frame-Options DENY;
add_header X-XSS-Protection "1; mode=block";
```

## Usage

### Default Users
The system comes with three default users:

| Username | Password | Role |
|----------|----------|------|
| admin | admin123 | Admin |
| analyst1 | analyst123 | Analyst |
| user1 | user123 | Requester |

### Creating Tickets

1. Navigate to the main page
2. Select a category (PADE, META, or ENCARTEIRAMENTO)
3. Fill out the category-specific form
4. Upload any required attachments
5. Submit the ticket

### Managing Tickets

#### For Requesters
- Access dashboard to view your tickets
- Click on tickets to view details and timeline
- Add comments to provide updates

#### For Analysts
- View assigned tickets in dashboard
- Use bulk actions to update multiple tickets
- Change ticket status and add resolution notes

#### For Admins
- Access all tickets system-wide
- Assign tickets to analysts
- Manage user accounts and permissions
- View comprehensive reporting

## API Endpoints

### Ticket Management
- `POST /api/submit-ticket` - Submit new ticket
- `GET /api/get-tickets` - List tickets with filters
- `GET /api/get-ticket?id={id}` - Get specific ticket details
- `POST /api/update-ticket` - Update ticket information

### Timeline & Interactions
- `GET /api/get-timeline?ticket_id={id}` - Get ticket timeline
- `POST /api/add-comment` - Add comment to ticket

### Statistics & Reporting
- `GET /api/get-stats` - Dashboard statistics
- `GET /api/get-reports` - Generate reports

### User Management
- `POST /api/login` - User authentication
- `GET /api/get-users` - List users (Admin only)
- `POST /api/create-user` - Create new user (Admin only)

## Security Features

- **Input Sanitization**: All user inputs are sanitized
- **SQL Injection Prevention**: Prepared statements used throughout
- **CSRF Protection**: Token-based CSRF protection
- **Session Management**: Secure session handling
- **File Upload Security**: Restricted file types and size limits
- **Role-Based Access**: Strict permission controls

## Customization

### Adding New Categories
1. Update the main form selection in `index.html`
2. Create new form template in `forms/` directory
3. Add category handling in `api/submit-ticket.php`
4. Update database schema if needed

### Modifying Form Fields
1. Edit the respective form HTML files
2. Update the field mapping in `api/submit-ticket.php`
3. Add new fields to `TicketFormData` table if needed

### Styling Changes
- Main styles are in `css/style.css`
- CSS variables for colors at the top of the file
- Glassmorphism effects can be adjusted in `.glass-card` class

## Troubleshooting

### Common Issues

1. **Database Connection Errors**
   - Check SQL Server service is running
   - Verify connection credentials
   - Ensure SQLSRV extension is installed

2. **File Upload Issues**
   - Check upload directory permissions
   - Verify `max_file_size` PHP settings
   - Ensure `uploads/` directory exists

3. **JavaScript Errors**
   - Check browser console for errors
   - Verify all CDN resources are loading
   - Ensure proper API endpoint URLs

### Performance Optimization

- Enable database query caching
- Implement file caching for static assets
- Use CDN for Bootstrap and FontAwesome
- Optimize images and reduce file sizes

## Development

### Local Development Setup
1. Install XAMPP or similar local server
2. Copy files to web directory
3. Configure local SQL Server instance
4. Update database connection settings

### Testing
- Test all user roles and permissions
- Verify file upload functionality
- Test responsive design on various devices
- Validate form submissions and data integrity

## Contributing

1. Fork the repository
2. Create a feature branch
3. Implement changes following the existing code style
4. Test thoroughly
5. Submit a pull request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For support and questions:
- Check the troubleshooting section
- Review the API documentation
- Contact the development team

## Version History

- **v1.0.0** - Initial release with core functionality
- **v1.1.0** - Added timeline tracking and bulk operations
- **v1.2.0** - Enhanced security and performance optimizations

---

Built with ❤️ using modern web technologies and best practices.