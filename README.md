# Warehouse Borrowing System - Full Stack Application

A comprehensive warehouse borrowing management system with both admin dashboard and customer portal. This is a final year university project built with modern web technologies, featuring a complete PHP backend with MySQL database integration.

## 🚀 Features

### Admin Dashboard
- **Real-time Dashboard**: Statistics, charts, and activity monitoring
- **User Management**: Customers, Employees, and Admins with role-based access
- **Borrowing Management**: Request approval workflow and transaction tracking
- **Reports & Analytics**: Comprehensive reporting system
- **Authentication**: Secure login with session management

### Customer Portal
- **Modern Landing Page**: Professional equipment borrowing service interface
- **User Registration/Login**: Secure customer account management with password strength validation
- **Equipment Browsing**: Browse available equipment categories
- **Borrowing Requests**: Submit and track borrowing requests with multiple items
- **Request History**: View status of current and past requests

### Core Functionality
- **Request Workflow**: Full lifecycle from request to approval to return
- **Item Management**: Multiple items per request with quantity tracking
- **Status Tracking**: Pending, Approved, Active, Returned, Overdue
- **Location Management**: Multi-warehouse support
- **Damage Reporting**: Track equipment condition and damage reports

## 🛠️ Technology Stack

### Backend
- **PHP 8.x**: Server-side scripting with modern features
- **MySQL**: Relational database with proper schema design
- **PDO**: Secure database operations with prepared statements
- **Session Management**: Secure authentication and authorization

### Frontend
- **HTML5**: Semantic markup with accessibility features
- **CSS3**: Modern styling with animations and transitions
- **Tailwind CSS**: Utility-first CSS framework for rapid development
- **JavaScript (ES6+)**: Modern JavaScript with async/await patterns
- **Font Awesome 6.0**: Professional icon library

### Development Environment
- **XAMPP**: Cross-platform Apache/MySQL/PHP development stack
- **Git**: Version control and collaboration

## 📁 Project Structure

```
Warehouse/
├── index.php                    # Main admin dashboard
├── borrowing-requests.php       # Borrowing requests management
├── borrowing-transactions.php   # Transaction management
├── return-items.php            # Return processing
├── reports.php                 # Reports and analytics
├── init_borrowing_only.sql     # Database schema
├── 
├── admin/                      # Admin-specific functionality
├── api/                        # Backend API endpoints
│   ├── admins.php              # Admin management API
│   ├── auth.php                # Authentication API
│   ├── borrowing-requests.php  # Borrowing requests API
│   ├── borrowing-transactions.php # Transactions API
│   ├── customers.php           # Customer management API
│   ├── employees.php           # Employee management API
│   ├── locations.php           # Location management API
│   └── ...                     # Other API endpoints
├── 
├── auth/                       # Authentication system
│   ├── login.php               # Admin login
│   ├── logout.php              # Session termination
│   └── register.php            # Admin registration
├── 
├── customer-side/              # Customer portal
│   ├── index.php               # Customer landing page
│   ├── login.php               # Customer authentication
│   ├── register.php            # Customer registration
│   ├── submit-request.php      # Borrowing request submission
│   ├── style.css               # Customer portal styles
│   └── migrate.php             # Database migration script
├── 
├── user-management/            # User management system
│   ├── admin-management.php    # Admin user management
│   ├── customer-management.php # Customer management
│   └── employee-management.php # Employee management
├── 
├── includes/                   # Shared components
│   ├── config.php              # Database configuration
│   ├── auth.php                # Authentication functions
│   ├── navbar.php              # Navigation component
│   └── sidebar.php             # Sidebar component
├── 
├── css/
│   └── style.css               # Main application styles
├── js/
│   ├── dashboard.js            # Dashboard functionality
│   ├── admin-management.js     # Admin management logic
│   ├── customer-management.js  # Customer management logic
│   ├── employee-management.js  # Employee management logic
│   └── user-management.js      # User management logic
└── README.md                   # Project documentation
```

## 🗄️ Database Schema

The system uses a comprehensive MySQL database schema focused on borrowing operations:

### Core Tables
- **Admin**: System administrators with role-based permissions
- **Employee**: Warehouse staff with department and position tracking
- **Customer**: Customer accounts with authentication and profile management
- **Location**: Warehouse locations for equipment storage and distribution

### Borrowing System Tables
- **Borrowing_Item_Types**: Equipment categories and types available for borrowing
- **Borrowing_Request**: Main request entity linking customers, employees, and locations
- **Borrowing_Items**: Individual items within each borrowing request
- **Borrowing_Transaction**: Transaction records for borrowing and return operations
- **Return_Items**: Return processing with condition tracking
- **Damage_Report**: Equipment damage and loss reporting

### Features
- **Foreign Key Relationships**: Proper relational database design
- **ENUM Fields**: Controlled vocabulary for status and categorization
- **JSON Fields**: Flexible permission storage for admin roles
- **Timestamps**: Automatic creation and update tracking
- **Sample Data**: Pre-populated test data for development

## 🚀 Getting Started

### Prerequisites
- **XAMPP/WAMP/LAMP**: Apache/MySQL/PHP development environment
- **PHP 8.0+**: Modern PHP version with required extensions
- **MySQL 5.7+**: Database server
- **Modern Web Browser**: Chrome, Firefox, Safari, or Edge
- **Text Editor/IDE**: VS Code, PhpStorm, or similar

### Installation

1. **Download and Setup XAMPP**
   ```bash
   # Download XAMPP from https://www.apachefriends.org/
   # Install and start Apache and MySQL services
   ```

2. **Clone/Download the Project**
   ```bash
   # Place the project in your web server directory
   # For XAMPP: C:\xampp\htdocs\Warehouse
   # For WAMP: C:\wamp64\www\Warehouse
   ```

3. **Database Setup**
   ```sql
   # 1. Open phpMyAdmin (http://localhost/phpmyadmin)
   # 2. Create a new database named 'warehouse_system'
   # 3. Import the init_borrowing_only.sql file
   # 4. Verify all tables are created successfully
   ```

4. **Configuration**
   ```php
   # Edit includes/config.php if needed
   # Default database connection:
   # Host: localhost
   # Database: warehouse_system
   # Username: root
   # Password: (empty for XAMPP)
   ```

5. **Start the Application**
   ```
   # Admin Dashboard: http://localhost/Warehouse/index.php
   # Customer Portal: http://localhost/Warehouse/customer-side/index.php
   ```

### Default Admin Accounts
```
Email: john.admin@warehouse.com
Password: password (hashed in database)

Email: sarah.manager@warehouse.com  
Password: password (hashed in database)
```

### First Time Setup
1. **Run Database Migration**: Visit `/customer-side/migrate.php` to ensure customer password fields are added
2. **Test Customer Registration**: Create a test customer account via the customer portal
3. **Verify Admin Access**: Login to admin dashboard with default credentials
4. **Explore Features**: Navigate through different modules to familiarize yourself

## 📱 Key Features in Detail

### Admin Dashboard
- **Real-time Statistics**: Live metrics and KPIs with visual charts
- **User Management**: Complete CRUD operations for customers, employees, and admins
- **Request Approval**: Workflow management for borrowing requests
- **Transaction Tracking**: Full audit trail of all borrowing activities
- **Reports**: Comprehensive analytics and reporting system
- **Role-based Access**: Granular permissions for different admin levels

### Customer Portal
- **Modern Landing Page**: Professional design with hero section and features
- **Authentication System**: Secure registration and login with password strength validation
- **Equipment Catalog**: Browse available equipment with descriptions and pricing
- **Request Submission**: Multi-item borrowing requests with date/time selection
- **Status Tracking**: Real-time updates on request status and history
- **Responsive Design**: Mobile-friendly interface with smooth animations

### Borrowing Workflow
1. **Customer Request**: Submit borrowing request through customer portal
2. **Admin Review**: Admin reviews and approves/rejects requests
3. **Item Allocation**: Assign specific items and quantities
4. **Transaction Creation**: Generate borrowing transaction record
5. **Equipment Delivery**: Track delivery to customer location
6. **Return Processing**: Handle returns with condition assessment
7. **Damage Reporting**: Document any equipment damage or loss

### Technical Features
- **RESTful APIs**: Well-structured API endpoints for all operations
- **Input Validation**: Both client-side and server-side validation
- **SQL Injection Protection**: Prepared statements and parameterized queries
- **Session Security**: Secure session management with proper timeout
- **Password Security**: Bcrypt hashing with salt for all passwords
- **Error Handling**: Comprehensive error logging and user feedback

## 🎨 UI/UX Features

### Design System
- **Consistent Color Palette**: Primary blue theme with semantic colors
- **Typography**: Professional font hierarchy with readable sizes
- **Spacing**: Consistent margin and padding throughout
- **Component Library**: Reusable UI components and patterns

### Responsive Design
- **Mobile-first Approach**: Optimized for mobile devices
- **Breakpoint System**: Tailored layouts for different screen sizes
- **Touch-friendly Interface**: Proper button sizes and touch targets
- **Cross-browser Compatibility**: Tested on major browsers

### Interactive Elements
- **Smooth Animations**: CSS transitions and keyframe animations
- **Loading States**: Visual feedback during async operations
- **Form Validation**: Real-time validation with helpful error messages
- **Modal System**: Professional overlay dialogs with backdrop blur
- **Notification System**: Toast notifications for user feedback

### Accessibility
- **Keyboard Navigation**: Full keyboard accessibility support
- **Screen Reader Support**: Proper ARIA labels and semantic HTML
- **High Contrast**: Sufficient color contrast ratios
- **Focus Management**: Visible focus indicators throughout the interface

## 🔧 Development & Customization

### API Structure
```php
# All API endpoints follow RESTful conventions
GET    /api/customers.php         # List customers
POST   /api/customers.php         # Create customer
PUT    /api/customers.php         # Update customer
DELETE /api/customers.php         # Delete customer

# Similar structure for:
# - /api/employees.php
# - /api/admins.php  
# - /api/borrowing-requests.php
# - /api/borrowing-transactions.php
```

### Adding New Features
1. **Create API Endpoint**: Add new PHP file in `/api/` directory
2. **Add Database Tables**: Update `init_borrowing_only.sql` 
3. **Create Frontend**: Add HTML/CSS/JS in appropriate directory
4. **Update Navigation**: Modify sidebar and navbar components
5. **Test Integration**: Verify all components work together

### Code Organization
- **Separation of Concerns**: Clear separation between frontend and backend
- **Modular Architecture**: Reusable components and functions
- **Configuration Management**: Centralized config in `/includes/config.php`
- **Error Handling**: Consistent error responses and logging
- **Security First**: Input validation and SQL injection prevention

### Database Customization
```sql
# Add new tables following existing patterns
CREATE TABLE New_Entity (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (related_id) REFERENCES Related_Table(id)
);
```

### Frontend Customization
- **Tailwind CSS**: Use utility classes for rapid styling
- **Component System**: Modify existing components or create new ones
- **JavaScript Modules**: Add new functionality in separate JS files
- **CSS Variables**: Customize colors and spacing in `:root` selector

## 📊 Development Notes

### Code Organization
- Modular JavaScript architecture
- Separation of concerns
- Reusable components
- Event-driven programming

### Best Practices
- Semantic HTML markup
- Progressive enhancement
- Error handling
- Input validation
- Security considerations

### Performance
- Optimized asset loading
- Efficient DOM manipulation
- Debounced search functions
- Lazy loading where applicable

## 🚦 Current Status & Features

### ✅ Completed Features
- **Full Backend Implementation**: Complete PHP API with MySQL integration
- **Authentication System**: Secure login/logout for both admin and customer portals
- **User Management**: Full CRUD operations for customers, employees, and admins
- **Borrowing System**: End-to-end borrowing request workflow
- **Customer Portal**: Professional landing page with registration and request submission
- **Admin Dashboard**: Comprehensive management interface with real-time data
- **Database Integration**: Fully functional MySQL database with sample data
- **Responsive Design**: Mobile-friendly interface across all pages
- **Security Implementation**: Password hashing, session management, SQL injection protection

### 🎯 Core Functionality
- **Customer Registration/Login**: Secure account creation with password validation
- **Equipment Browsing**: Browse available equipment types and descriptions
- **Request Submission**: Submit multi-item borrowing requests with scheduling
- **Admin Approval Workflow**: Review, approve, or reject customer requests
- **Status Tracking**: Real-time status updates throughout the borrowing lifecycle
- **Transaction Management**: Complete transaction history and reporting
- **User Role Management**: Different permission levels for various user types

### 🔧 Technical Implementation
- **RESTful API Architecture**: Well-structured backend endpoints
- **Modern PHP**: Object-oriented programming with PDO for database operations
- **Secure Authentication**: Session-based auth with proper token management
- **Input Validation**: Comprehensive validation on both client and server side
- **Error Handling**: Proper error logging and user-friendly error messages
- **Code Organization**: Modular structure with separation of concerns

## 🔮 Future Enhancements

### Advanced Features
- **Email Notifications**: Automated email alerts for request status changes
- **SMS Integration**: Text message notifications for urgent updates
- **File Uploads**: Equipment photos and document attachments
- **QR Code Integration**: QR codes for equipment tracking and quick access
- **Advanced Reporting**: Custom report builder with export capabilities
- **Mobile App**: Native iOS/Android application

### Business Intelligence
- **Analytics Dashboard**: Advanced charts and data visualization
- **Predictive Analytics**: Equipment demand forecasting
- **Performance Metrics**: KPI tracking and benchmarking
- **Usage Analytics**: Equipment utilization patterns and optimization

### Integration Capabilities
- **ERP Integration**: Connect with existing enterprise resource planning systems
- **Accounting Software**: QuickBooks, SAP, or similar integrations
- **Calendar Systems**: Google Calendar, Outlook integration
- **Inventory Management**: Real-time inventory tracking systems
- **Third-party APIs**: Shipping, payment processing, and notification services

### Technical Improvements
- **Real-time Updates**: WebSocket implementation for live notifications
- **Caching System**: Redis or Memcached for improved performance
- **API Documentation**: Swagger/OpenAPI documentation
- **Unit Testing**: Comprehensive test suite with PHPUnit
- **CI/CD Pipeline**: Automated testing and deployment
- **Docker Containerization**: Easy deployment and scaling

## 🎓 Academic & Learning Context

### Demonstrated Concepts
- **Full-Stack Development**: End-to-end web application development
- **Database Design**: Relational database modeling and optimization
- **API Development**: RESTful service architecture and implementation
- **Security Practices**: Authentication, authorization, and data protection
- **User Experience Design**: Responsive design and accessibility principles
- **Project Management**: Requirements analysis and feature implementation

### Technical Skills Showcased
- **Backend Development**: PHP, MySQL, PDO, Session Management
- **Frontend Development**: HTML5, CSS3, JavaScript ES6+, Tailwind CSS
- **Database Management**: Schema design, relationships, indexing, optimization
- **Security Implementation**: Password hashing, input validation, SQL injection prevention
- **Version Control**: Git workflow and collaborative development
- **Documentation**: Comprehensive project documentation and code comments

### Software Engineering Principles
- **SOLID Principles**: Clean code architecture and design patterns
- **Separation of Concerns**: Modular code organization
- **DRY (Don't Repeat Yourself)**: Reusable components and functions
- **Error Handling**: Robust error management and user feedback
- **Testing Mindset**: Input validation and edge case handling
- **Performance Optimization**: Efficient database queries and frontend optimization

## 🚀 Production Readiness

### Security Checklist
- ✅ Password hashing with bcrypt
- ✅ SQL injection prevention with prepared statements
- ✅ Session security with proper timeout
- ✅ Input validation and sanitization
- ✅ Error handling without information disclosure
- ⚠️ HTTPS implementation (requires SSL certificate)
- ⚠️ Rate limiting for API endpoints
- ⚠️ CSRF protection tokens

### Deployment Considerations
- **Environment Configuration**: Separate development/production configs
- **Database Optimization**: Proper indexing and query optimization
- **Backup Strategy**: Automated database backups
- **Monitoring**: Error logging and performance monitoring
- **Scalability**: Load balancing and database replication planning

## 📄 License

This project is created for educational purposes as part of a university final year project. The code is available for learning and academic use.

## 👨‍💻 Contributors

**Primary Developer**: Final Year Computer Science Student  
**Project Type**: University Capstone Project  
**Academic Year**: 2024-2025  
**Institution**: [University Name]

### Project Supervision
- **Academic Supervisor**: [Supervisor Name]
- **Industry Mentor**: [Mentor Name] (if applicable)

## 🙏 Acknowledgments

- **University Faculty**: For guidance and technical support
- **Open Source Community**: For tools and frameworks used
- **Tailwind CSS**: For the utility-first CSS framework
- **Font Awesome**: For the comprehensive icon library
- **PHP Community**: For excellent documentation and resources

---

## 📧 Contact & Support

For questions about this project or potential improvements:

- **Academic Inquiries**: [minkhantthwin17@gmail.com]
- **Technical Issues**: Please create an issue in the repository
- **Collaboration**: Open to academic collaboration and code reviews

---

**Note**: This is a fully functional warehouse borrowing management system with complete backend implementation, designed for educational purposes and demonstration of full-stack web development capabilities. The system includes both administrative and customer-facing interfaces with comprehensive database integration and security features.
