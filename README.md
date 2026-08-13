# 🛒 CampusMart — Student-to-Student Campus Marketplace

> Buy. Sell. Connect. Within Your Campus.

CampusMart is a secure, responsive student-to-student marketplace web application designed to make buying and selling items within a university community simple, convenient, and safer.

Students can list products, browse and search listings, communicate with sellers, request purchases, manage transactions, leave reviews, and report inappropriate listings.

## ✨ Features

### 👨‍🎓 Student
- Registration and secure authentication
- Student profile management
- Product listings
- Multiple product image uploads
- Product search
- Category filtering
- Price and condition filtering
- Sorting
- Favorites / wishlist
- Purchase requests
- Order tracking
- Purchase history
- Sales history
- Internal messaging
- Notifications
- Reviews and ratings
- Listing reports
- Personalized dashboard

### 🛡️ Admin
- Admin dashboard
- User management
- Account suspension/reactivation
- Category management
- Listing moderation
- Listing approval/rejection
- Order monitoring
- Report management
- Review moderation
- Marketplace statistics
- System settings

### ⚡ Smart Features
- Listing expiration
- Product view tracking
- Personalized recommendations
- Popular/trending products
- Notification system
- Seller reputation
- Trusted seller indication

### 🔐 Security
- PDO prepared statements
- Password hashing
- Session-based authentication
- CSRF protection
- XSS protection
- Role-based authorization
- Ownership validation
- Secure image uploads
- Server-side validation

## 🛠️ Technology Stack

| Technology | Purpose |
|---|---|
| HTML5 | Structure |
| CSS3 | Styling & responsive UI |
| JavaScript | Client-side interactions |
| PHP 8+ | Backend |
| MySQL/MariaDB | Database |
| XAMPP | Local development server |
| PDO | Database access |

## 🏗️ Project Architecture

```text
campusmart/
├── admin/
├── ajax/
├── assets/
├── config/
├── database/
├── includes/
├── user/
├── index.php
├── login.php
├── register.php
├── products.php
├── product-details.php
└── sell.php
