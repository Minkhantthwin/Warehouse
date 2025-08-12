# Warehouse Borrowing System - Admin Dashboard

A comprehensive admin dashboard for managing warehouse borrowing operations for ecommerce businesses. This is a final year university project built with modern web technologies.

## 🚀 Features

### Dashboard Overview
- Real-time statistics and key metrics
- Recent activity monitoring
- Low stock alerts
- Visual charts and analytics

### User Management
- **Customers**: Manage customer accounts and profiles
- **Employees**: Handle employee records and roles
- **Admins**: Manage administrative users
- Bulk operations for user management
- Role-based access control

### Inventory Management
- Material catalog with categories
- Stock level tracking
- Location management
- Bulk import/export capabilities
- Low stock notifications
- Stock adjustment tracking

### Borrowing Request System
- Request approval workflow
- Status tracking (Pending, Approved, Active, Returned, Overdue)
- Item-level tracking
- Customer and employee assignment
- Purpose and notes documentation

### Transaction Management
- Borrowing transaction records
- Return processing
- Partial return handling
- Damage reporting
- Transaction history

### Reports & Analytics
- Borrowing patterns analysis
- Inventory utilization reports
- User activity reports
- Damage and loss tracking

### Service Management
- Service categories
- Service pricing
- Service request handling

## 🛠️ Technology Stack

- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **CSS Framework**: Tailwind CSS
- **Icons**: Font Awesome 6.0
- **Database Schema**: MySQL (see init.sql)
- **Server**: Apache (XAMPP)

## 📁 Project Structure

```
Warehouse/
├── index.html              # Main dashboard page
├── borrowing-requests.html # Borrowing requests management
├── inventory.html          # Inventory management
├── user-management.html    # User management system
├── init.sql               # Database schema
├── css/
│   └── style.css          # Custom styles
├── js/
│   ├── dashboard.js       # Main dashboard functionality
│   ├── borrowing-requests.js # Borrowing requests logic
│   ├── inventory.js       # Inventory management logic
│   └── user-management.js # User management logic
└── README.md              # Project documentation
```

## 🗄️ Database Schema

The system is built around the following main entities:

### Core Tables
- **Admin**: System administrators
- **Employee**: Warehouse employees
- **Customer**: End customers
- **Location**: Storage locations
- **Material_Categories**: Material classification
- **Material**: Inventory items
- **Inventory**: Stock levels per location

### Business Logic Tables
- **Service_Categories**: Service classification
- **Service**: Available services
- **Borrowing_Request**: Main borrowing request entity
- **Borrowing_Items**: Items in each request
- **Borrowing_Transaction**: Transaction records
- **Return_Items**: Return processing
- **Damage_Report**: Damage tracking

## 🚀 Getting Started

### Prerequisites
- XAMPP or similar Apache/MySQL/PHP stack
- Modern web browser
- Text editor or IDE

### Installation

1. **Clone or download the project**
   ```bash
   # Place the project folder in your htdocs directory
   # For XAMPP: C:\xampp\htdocs\Warehouse
   ```

2. **Set up the database**
   ```sql
   -- Import the init.sql file into your MySQL database
   -- This will create all necessary tables and relationships
   ```

3. **Start XAMPP services**
   - Start Apache
   - Start MySQL

4. **Access the dashboard**
   ```
   http://localhost/Warehouse/index.html
   ```

## 📱 Key Features in Detail

### Dashboard
- **Statistics Cards**: Quick overview of key metrics
- **Recent Activity**: Latest borrowing transactions
- **Low Stock Alerts**: Automated inventory warnings
- **Charts**: Visual representation of data trends

### Borrowing Requests
- **Request Management**: Create, view, edit, approve/reject requests
- **Status Tracking**: Full lifecycle from request to return
- **Item Management**: Multiple items per request
- **Approval Workflow**: Admin approval required
- **Notifications**: Automated reminders for overdue items

### Inventory Management
- **Material Catalog**: Comprehensive item database
- **Stock Tracking**: Real-time quantity monitoring
- **Location Management**: Multi-warehouse support
- **Category Organization**: Hierarchical classification
- **Bulk Operations**: Mass updates and imports

### User Management
- **Multi-Role Support**: Customers, Employees, Admins
- **Profile Management**: Complete user information
- **Status Control**: Activate/deactivate users
- **Bulk Operations**: Mass user management
- **Search & Filtering**: Advanced user discovery

## 🎨 UI/UX Features

### Responsive Design
- Mobile-friendly interface
- Adaptive layouts for all screen sizes
- Touch-friendly controls

### Modern Interface
- Clean, professional design
- Intuitive navigation
- Consistent color scheme
- Loading states and animations

### Accessibility
- Keyboard navigation support
- Screen reader friendly
- High contrast ratios
- Semantic HTML structure

## 🔧 Customization

### Adding New Features
1. Create new HTML pages following the existing structure
2. Add corresponding JavaScript modules
3. Update navigation in the main dashboard
4. Add database tables if needed

### Styling
- Modify `css/style.css` for custom styles
- Use Tailwind utility classes for rapid development
- Maintain consistent design patterns

### Database Extensions
- Add new tables to `init.sql`
- Maintain foreign key relationships
- Document any schema changes

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

## 🚦 Current Status

This is a frontend prototype with:
- ✅ Complete UI/UX implementation
- ✅ JavaScript functionality for all features
- ✅ Responsive design
- ✅ Database schema
- ⚠️ Mock data implementation (no backend API)
- ⚠️ No authentication system
- ⚠️ No real database connectivity

## 🔮 Future Enhancements

### Backend Integration
- REST API development
- Real database connectivity
- Authentication and authorization
- Data persistence

### Advanced Features
- Real-time notifications
- Email notifications
- Advanced reporting
- Data visualization
- Mobile app integration

### Analytics
- Usage analytics
- Performance monitoring
- Business intelligence
- Predictive analytics

## 🎓 Academic Context

This project demonstrates:
- Full-stack web development concepts
- Database design principles
- User interface design
- Business process modeling
- Project management
- Documentation practices

## 📄 License

This project is created for educational purposes as part of a university final year project.

## 👨‍💻 Author

Created as a final year university project for warehouse borrowing system management.

---

**Note**: This is a frontend prototype designed to demonstrate the complete user interface and user experience for a warehouse borrowing system. For production use, backend API integration and security measures would need to be implemented.
