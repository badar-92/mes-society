# MES Society Website

A comprehensive web application for managing the MES (Mechanical Engineering Society) of a university. This platform facilitates society operations, member management, event organization, and public engagement through a multi-level user system.

## 🌟 Features

### Public Features
- **Homepage** - Society overview and announcements
- **About Us** - Society information and mission
- **Events** - Upcoming and past events
- **Gallery** - Photo gallery of society activities
- **Team** - Current society leadership and members
- **Competitions** - Competition listings and registration
- **Contact Form** - Public inquiry system
- **Application System** - New member applications

### Member Features
- **Member Dashboard** - Personalized member portal
- **Profile Management** - Update personal information
- **Digital ID Card** - Generate and download member ID
- **Event Registration** - Sign up for society events
- **Duty Management** - View assigned responsibilities
- **Application Review** - Process new member applications
- **Media Gallery** - Access society media

### Admin Features
- **Admin Dashboard** - Comprehensive management console
- **User Management** - Full member and user administration
- **Event Management** - Create, edit, and manage events
- **Competition Management** - Organize and track competitions
- **Gallery Management** - Upload and organize media
- **Application Processing** - Review and manage applications
- **Reporting** - Generate various reports
- **System Settings** - Platform configuration

## 🚀 Quick Start

### Prerequisites
- Web server (Apache/Nginx)
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Composer (for dependencies)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/your-username/mes-society-website.git
   cd mes-society-website
   ```

2. **Database Setup**
   ```bash
   # Import the database schema
   mysql -u username -p database_name < config/database.sql
   ```

3. **Configuration**
   ```bash
   # Update database configuration
   nano includes/config.php
   ```

4. **File Permissions**
   ```bash
   chmod -R 755 uploads/
   chmod -R 755 assets/images/uploads/
   ```

5. **Access the Application**
   - Public site: `https://yourdomain.com/public/`
   - Member login: `https://yourdomain.com/member/login.php`
   - Admin login: `https://yourdomain.com/admin/`

## 📁 Project Structure

```
mes-society-website/
├── admin/                 # Admin panel components
├── includes/             # Core PHP includes and utilities
├── member/              # Member portal components
├── public/              # Public-facing pages
├── assets/              # Static assets (CSS, JS, images)
├── uploads/             # File upload directories
├── api/                 # API endpoints
└── config/              # Configuration files and database
```

### Key Directories

- **`/admin`** - Administrative backend with full system control
- **`/member`** - Member portal for registered society members
- **`/public`** - Public website accessible to all visitors
- **`/includes`** - Core application logic and utilities
- **`/uploads`** - User-generated content storage
- **`/api`** - RESTful API endpoints for extended functionality

## 🛠️ Technical Stack

### Backend
- **PHP** - Server-side scripting
- **MySQL** - Database management
- **PDF Generation** - Dynamic document creation
- **Session Management** - Secure user authentication

### Frontend
- **Bootstrap 5** - Responsive framework
- **jQuery** - JavaScript library
- **Custom CSS** - Brand-specific styling
- **Responsive Design** - Mobile-friendly interface

### Security Features
- User authentication and authorization
- Role-based access control
- Secure file upload handling
- SQL injection prevention
- XSS protection

## 🔧 Configuration

### Database Setup
1. Create a MySQL database
2. Import `config/database.sql`
3. Update credentials in `includes/config.php`

### Email Configuration
Update SMTP settings in relevant files for:
- Password reset functionality
- Notification system
- Confirmation letters

### File Uploads
Ensure proper permissions for:
- `uploads/profile-pictures/`
- `uploads/event-images/`
- `uploads/gallery/`
- `uploads/resumes/`
- `uploads/id-cards/`

## 👥 User Roles

### 1. Public Users
- Browse public content
- Register for events
- Submit applications
- Contact society

### 2. Society Members
- Access member portal
- Manage personal profile
- Register for events
- View assigned duties

### 3. Administrators
- Full system access
- User management
- Content management
- Reporting and analytics

## 📊 Management Features

### Event Management
- Create and publish events
- Manage registrations
- Generate participation certificates
- Track attendance

### Competition System
- Competition creation and management
- Participant registration
- Result publication
- Certificate generation

### Application Processing
- New member application review
- Interview scheduling
- Status tracking
- Bulk operations

### Gallery Management
- Image upload and organization
- Category management
- Public/private gallery control

## 🔐 Security

- Password hashing
- SQL injection prevention
- XSS protection
- Session security
- File upload validation
- Role-based access control

## 📱 Responsive Design

The website is fully responsive and optimized for:
- Desktop computers
- Tablets
- Mobile devices

## 🚀 Deployment

### Production Checklist
- [ ] Configure database connection
- [ ] Set up SSL certificate
- [ ] Configure file permissions
- [ ] Set up email services
- [ ] Test all user flows
- [ ] Backup strategy implementation

### Recommended Hosting
- Linux-based hosting environment
- PHP 7.4+ with required extensions
- MySQL 5.7+ database
- SSD storage for better performance

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## 📄 License

This project is licensed under the [Your License] - see the LICENSE file for details.

## 🆘 Support

For support and documentation:
- Email: mesuolofficial@gmail.com
- Documentation: [Link to Documentation]
- Issue Tracker: [GitHub Issues Link]

## 🙏 Acknowledgments

- University Administration
- MES Society Members
- Development Team
- Open Source Contributors

---

**Live Demo**: [MES UOL ](https://mesuol.xo.je/mes-society/public/)

*Built by BADAR AHMAD for the Mechanical Engineering Society*
