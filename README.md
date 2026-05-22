# MES Society Website

A comprehensive web application for managing the **Mechanical Engineering Society (MES)** at the University of Lahore. This platform supports society operations, member management, event organization, competitions, and public engagement with **PWA capabilities**, **push notifications**, and **APK version management**.

---

## ✨ Features

### 🌐 Public Features
- **Homepage** – Society overview, news, and announcements  
- **About Us** – Mission, vision, history, and achievements  
- **Events** – Browse upcoming and past events with full details  
- **Event Registration** – Public registration for events  
- **Competitions** – Listing and detailed competition pages  
- **Competition Registration & Results** – Register for competitions and view published results  
- **Gallery** – Public photo gallery of society activities  
- **Team** – Current leadership and member profiles  
- **Contact Form** – Public inquiry system with message storage  
- **Apply for Membership** – Online application submission (personal, academic, resume)  
- **Digital Certificates** – Public certificate verification  
- **Privacy Policy & Terms of Service** – Legal pages  
- **Download Mobile App** – APK download page (latest version auto‑fetched)

### 👥 Member Features (Logged‑in Members)
- **Member Dashboard** – Personalised portal with activity overview  
- **Profile Management** – Update personal information and profile picture  
- **Digital ID Card** – Generate and download member ID card (PDF)  
- **Event Management** – Register/unregister for events, view registered events  
- **My Duties** – View assigned responsibilities and schedules  
- **My Applications** – Track membership application status  
- **Application Review** – Process new member applications (if authorised)  
- **Competition Management** – Participate in competitions, view history  
- **Media Gallery** – Full access to society photos and albums  
- **Notifications** – In‑app notification centre  
- **Settings** – Change password and email preferences

### 🔧 Admin Features
- **Admin Dashboard** – Centralised management console  
- **User Management** – Create, edit, delete, and export users  
- **Role‑Based Access** – Assign roles (admin, member, team, etc.)  
- **Event Management** – Create, edit, delete events; manage registrations; generate confirmation letters  
- **Competition Management** – Create competitions, manage participants, publish results  
- **Gallery Management** – Upload, edit, delete images; organise albums  
- **Application Processing** – Review, shortlist, schedule interviews, approve/reject applications  
- **Duty Management** – Assign duties to members  
- **Department Management** – Manage society departments/teams  
- **Team Management** – Edit leadership and team member profiles  
- **Certificate Management** – Add/edit certificates for members  
- **Contact Messages** – View and export public inquiries  
- **Reports** – Generate user, event, competition, and activity reports (PDF/Excel)  
- **Push Notifications** – Send web push notifications to all subscribed members  
- **APK Manager** – Upload and manage mobile app versions (version control)  
- **System Settings** – Configure site name, contact details, social links, etc.  
- **Progress Reports** – Track society activity and member engagement

---

## 🚀 Quick Start

### Prerequisites
- Web server (Apache/Nginx recommended)  
- **PHP 7.4 or higher** (PHP 8.x compatible)  
- **MySQL 5.7 or higher** (MariaDB 10.2+)  
- **Composer** (for PDF library dependencies)  
- **OpenSSL** (for VAPID key generation)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/your-username/mes-society-website.git
   cd mes-society-website/mes-society   # web root is the mes-society folder
Install PHP dependencies (PDF generator)

bash
composer install
Database Setup

bash
# Create a database and import schema
mysql -u username -p database_name < config/database.sql
Configuration

Copy includes/config-sample.php to includes/config.php (if not present) and update:

php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'your_database');
define('SITE_URL', 'https://yourdomain.com/mes-society/');
Generate VAPID keys for push notifications (see Push Notification Setup below)

File Permissions (Linux)

bash
chmod -R 755 uploads/
chmod -R 755 assets/images/uploads/
chmod 755 config/vapid.php   # if created
Web Server Configuration

Point document root to the mes-society/ folder

Ensure .htaccess (Apache) or equivalent rewrites are enabled for clean URLs

Access the Application

Public site: https://yourdomain.com/

Member login: https://yourdomain.com/member/login.php

Admin login: https://yourdomain.com/admin/

Default admin credentials (change immediately):

Email: admin@mes.com

Password: admin123

📁 Project Structure
text
mes-society/
├── .well-known/                 # Android Asset Links (app linking)
│   └── assetlinks.json
├── admin/                       # Admin panel (full system control)
│   ├── apk-manager.php
│   ├── applications.php
│   ├── certificates*.php
│   ├── competitions*.php
│   ├── contact-messages.php
│   ├── dashboard.php
│   ├── departments-management.php
│   ├── duties.php
│   ├── events*.php
│   ├── export-*.php
│   ├── gallery*.php
│   ├── generate-*.php
│   ├── interviews.php
│   ├── notifications.php
│   ├── reports.php
│   ├── progress-report.php
│   ├── send-push.php
│   ├── settings.php
│   ├── sidebar.php
│   ├── team-management.php
│   ├── users*.php
│   └── view-users.php
├── member/                      # Member portal
│   ├── applications.php
│   ├── applications-review.php
│   ├── certificates.php
│   ├── competition-management.php
│   ├── dashboard.php
│   ├── duties.php
│   ├── event-management.php
│   ├── events.php
│   ├── export-applications.php
│   ├── gallery-edit.php
│   ├── id-card.php
│   ├── interviews.php
│   ├── login.php
│   ├── media-gallery.php
│   ├── notifications.php
│   ├── profile.php
│   ├── settings.php
│   └── sidebar.php
├── public/                      # Public-facing website
│   ├── .htaccess
│   ├── about.php
│   ├── access-denied.php
│   ├── apply.php
│   ├── certificates.php
│   ├── competitions.php
│   ├── competition-details.php
│   ├── competition-register.php
│   ├── competition-results.php
│   ├── contact.php
│   ├── download-app.php
│   ├── events.php
│   ├── event-details.php
│   ├── event-register.php
│   ├── event-unregister.php
│   ├── first-login.php
│   ├── gallery.php
│   ├── gallery-view.php
│   ├── human.txt
│   ├── index.php
│   ├── login.php
│   ├── offline.html
│   ├── onboarding.php
│   ├── privacy-policy.php
│   ├── push-test.php
│   ├── robots.txt
│   ├── sitemap.php
│   ├── splash.html
│   ├── team.php
│   ├── tearm-of-services.php   # (typo kept for compatibility)
│   ├── user.php
│   └── verified-badge*.png
├── includes/                    # Core libraries and helpers
│   ├── auth.php
│   ├── config.php
│   ├── database.php
│   ├── footer.php
│   ├── functions.php
│   ├── header.php
│   ├── logout.php
│   ├── pdf-generator/
│   │   └── PDFGenerator.php
│   ├── push_helper.php
│   └── session.php
├── api/                         # RESTful API endpoints (mobile app & AJAX)
│   ├── applications.php
│   ├── auth.php
│   ├── events.php
│   ├── gallery.php
│   ├── notifications.php
│   ├── save-subscription.php
│   └── team.php
├── Chatbox/                     # Real-time chat component
│   └── Chatbox.php
├── assets/                      # Static assets
│   ├── animations/
│   │   └── splash-animation.json
│   ├── audio/
│   │   └── notification.mp3
│   ├── css/
│   │   ├── custom.css
│   │   └── responsive.css
│   ├── images/
│   │   ├── uploads/ (dynamically populated)
│   │   ├── logo-mes.png
│   │   ├── favicon.ico
│   │   ├── manifest.php        # PWA manifest generator
│   │   └── ...
│   ├── js/
│   │   ├── custom.js
│   │   └── mobile-sidebar.js
│   └── vendor/                  # Composer dependencies (PDF libs)
├── uploads/                     # User-generated content
│   ├── apks/                    # Mobile app APK files (versioned)
│   ├── certificates/            # Generated member certificates
│   ├── compitition-results/     # Competition result files (typo)
│   ├── compititions/            # Competition related uploads
│   ├── confirmation-letters/    # Event registration letters
│   ├── event-images/            # Event banner/thumbnails
│   ├── gallery/                 # Public/private gallery images
│   ├── id-cards/                # Generated member ID cards
│   ├── profile-pictures/        # User avatars
│   └── resumes/                 # Membership application resumes
├── config/                      # Configuration & database files
│   ├── database.sql             # Full schema (includes apk_versions table)
│   ├── setup-competitions.php   # Competition seeding script
│   ├── setup.php                (empty placeholder)
│   └── vapid.php                # VAPID keys (auto-generated)
├── tools/                       # Utility scripts
│   └── generate-vapid.php       # CLI web push key generator
├── assistant.php                # (Legacy/helper entry point)
└── sw.js                        # Service Worker (PWA + push notifications)
🛠️ Technical Stack
Backend
PHP 7.4+ – Core logic, session management, authentication

MySQL – Database with foreign keys, indexes, and views

PDO – Parameterised queries (SQL injection prevention)

PDF Generation – Custom PDFGenerator class (dompdf/TCPDF based)

Web Push API – VAPID-based push notifications (via push_helper.php)

Frontend & PWA
Bootstrap 5 – Responsive grid and components

jQuery – DOM manipulation and AJAX

Custom CSS – Brand-specific styling + responsive overrides

Service Worker (sw.js) – Offline caching, push event handling

Web App Manifest – Generated by manifest.php (PWA installable)

Splash Screen – splash.html + Lottie animation (splash-animation.json)

Push Notifications
Web Push Protocol – Uses VAPID keys (stored in config/vapid.php)

Subscription Storage – push_subscriptions table (via api/save-subscription.php)

Admin Sending – admin/send-push.php targets all active subscriptions

Notification Audio – assets/audio/notification.mp3 plays on supported browsers

Security Features
Password hashing (bcrypt via password_hash())

Session-based authentication with role checks

CSRF protection on forms (tokens in functions.php)

File upload validation (type, size, MIME)

XSS filtering on output (htmlspecialchars())

Prepared statements for all SQL queries

.htaccess restrictions on sensitive directories

🔧 Configuration
Database
The config/database.sql schema includes all tables:

users, roles, events, event_registrations

competitions, competition_participants, competition_results

gallery, gallery_categories

applications, interviews, duties, certificates

contact_messages, notifications, push_subscriptions

apk_versions – Stores mobile app versions and download URLs

Email Configuration
To enable email features (password reset, confirmation letters):

Update SMTP settings in includes/config.php

Or configure mail() function with proper server MTA

VAPID Keys for Push Notifications
Run the key generator once:

bash
php tools/generate-vapid.php
This creates config/vapid.php with publicKey and privateKey.
The public key is exposed in sw.js and member/settings.php for subscription.

File Uploads
Ensure the following directories are writable by your web server:

uploads/apks/

uploads/profile-pictures/

uploads/event-images/

uploads/gallery/

uploads/resumes/

uploads/id-cards/

uploads/certificates/

uploads/confirmation-letters/

uploads/compititions/

uploads/compitition-results/

👥 User Roles & Permissions
Role	Access Scope
Public	Browse public pages, register for events, submit membership applications, contact form.
Member	Member dashboard, ID card, event registration, duty view, competition participation, media gallery, notifications.
Team Member	Additional permissions: review applications, manage events (limited), edit gallery.
Admin	Full system access: user/event/competition/gallery management, push notifications, APK version control, reports, system settings.
📊 Key Management Features
Event Management
Create/publish events with date, venue, capacity, image

Manage registrations, view attendee lists

Download confirmation letters (PDF)

Unregister participants

Competition System
Competition creation with description, rules, deadline

Participant registration (public or member-only)

Upload results (PDF/Excel) to compitition-results/

Generate participation certificates

Application Processing
Multi-step membership application form (personal, academic, resume upload)

Admin review with status: Pending → Shortlisted → Interview → Approved/Rejected

Schedule and track interviews

Gallery Management
Upload images with captions and categories

Public gallery vs. member-only albums

Bulk upload via gallery-upload.php

Push Notifications
Members subscribe via browser prompt (on supported devices)

Admin sends push message (title + body) to all subscribed members

Notifications appear even when the site is not open (if service worker is active)

APK Version Manager (admin/apk-manager.php)
Upload new APK files to uploads/apks/

Track version name, version code, release notes

Public download-app.php fetches the latest version automatically

Database table apk_versions stores metadata

📱 PWA & Mobile Features
Installable – Users can “Add to Home Screen” on Android/Chrome and iOS/Safari

Offline Fallback – offline.html is shown when network is unavailable

Splash Screen – Custom animated splash (splash.html + Lottie)

Push Notifications – Fully integrated with browser permission prompt

Asset Links – .well-known/assetlinks.json enables Android App Links (if companion native app exists)

🚢 Deployment
Production Checklist
Update includes/config.php with production database and URL

Generate VAPID keys and verify config/vapid.php exists

Set display_errors = Off in php.ini

Enable HTTPS (required for Push API and Service Worker)

Set correct file permissions (uploads/ directories 755, config/ 644)

Configure cron job (if needed) for automated notification cleanup

Test all user flows (registration, login, event signup, push notification)

Configure backup strategy (database + uploads folder)

Recommended Hosting
Linux VPS with Apache/Nginx

PHP 7.4+ with extensions: pdo_mysql, gd, zip, mbstring, json, openssl

MySQL 5.7+ or MariaDB 10.3+

SSL Certificate (Let’s Encrypt free)

SSD storage for faster gallery loading

🔧 Troubleshooting
Issue	Solution
Push notifications not working	Verify HTTPS, check VAPID keys in config/vapid.php, ensure sw.js is accessible at root.
PDF generation fails	Run composer update in assets/vendor/, check PHP memory limit.
File upload 403 error	Set proper permissions (chmod 755 uploads/ and subfolders).
Login redirect loop	Check SITE_URL constant in config.php, ensure .htaccess is not blocking cookies.
PWA not installable	Validate manifest.php returns correct JSON, ensure sw.js is registered.
🤝 Contributing
Contributions are welcome! Please follow these steps:

Fork the repository

Create a feature branch (git checkout -b feature/amazing-feature)

Commit your changes (git commit -m 'Add some amazing feature')

Push to the branch (git push origin feature/amazing-feature)

Open a Pull Request

📄 License
This project is licensed under the MIT License – see the LICENSE file for details.

📬 Support & Contact
Email: mesuolofficial@gmail.com

Website: https://mesuol.xo.je/mes-society/public/

GitHub Issues: Create an issue

🙏 Acknowledgements
University of Lahore – Faculty of Engineering

MES Society Executive Team

Open Source Libraries: Bootstrap, jQuery, Dompdf/TCPDF, Web-Push-PHP

Badar Ahmad – Lead Developer
