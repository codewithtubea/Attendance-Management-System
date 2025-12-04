# Attendance Management System

A web-based attendance tracking system built with PHP and MySQL for educational institutions. This system allows faculty members to create courses, set attendance codes, and track student attendance, while students can join courses and mark their attendance using codes provided by faculty.

## Features

### Student Features
- **Dashboard**: View enrolled courses and pending enrollment requests
- **Course Management**: Browse available courses and request to join
- **Attendance Tracking**: Mark attendance using codes provided by faculty
- **Attendance Reports**: View personal attendance history and statistics

### Faculty Features
- **Course Management**: Create and manage courses
- **Session Management**: Schedule sessions and generate attendance codes
- **Enrollment Management**: Approve or reject student join requests
- **Attendance Reports**: View attendance records for courses and individual sessions

### Admin Features
- **User Management**: Manage users (students, faculty, admins)
- **Role Management**: Change user roles and permissions
- **Course Oversight**: View and manage all system courses
- **System Statistics**: Monitor enrollment rates, pending requests, and system activity
- **Security Monitoring**: Track login attempts and failed authentications

## System Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache web server (or compatible)
- XAMPP (for local development)

## Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/codewithtubea/Attendance-Management-System.git
   cd Attendance-Management-System
   ```

2. **Set up the database**
   - Create a MySQL database named `course_management`
   - Import the database schema (if provided)
   - Update database credentials in `individual/includes/database.php`

3. **Configure database connection**
   - Edit `individual/includes/database.php`
   - Set your database credentials:
     ```php
     $host = 'localhost';
     $dbname = 'course_management';
     $username = 'root';
     $password = '';
     ```

4. **Start the application**
   - Place the project in your XAMPP `htdocs` directory
   - Start Apache and MySQL from XAMPP Control Panel
   - Navigate to `http://localhost/activity_02/individual/`

## File Structure

```
individual/
├── includes/
│   ├── auth.php              # Authentication and authorization functions
│   └── database.php          # Database connection
├── admin_dashboard.php       # Admin dashboard and management
├── faculty_dashboard.php     # Faculty course and enrollment management
├── student_dashboard.php     # Student main dashboard
├── courses.php              # Student course list and enrollment
├── mark_attendance.php      # Attendance marking interface
├── student_attendance_report.php # Student attendance history
├── login.php                # Login page
├── register.php             # Registration page
├── logout.php               # Logout handler
├── request_join.php         # Course join request handler
└── style.css                # Styling
```

## Recent Bug Fixes (Lab 5)

### Fixed Issues

1. **Student Join Course Not Working**
   - **Problem**: Students could not request to join courses created by faculty
   - **Root Cause**: Inconsistent database table naming (`enrollment` vs `enrollments`)
   - **Solution**: Standardized all queries to use the correct `enrollments` table
   - **Files Modified**: 
     - `request_join.php`
     - `student_dashboard.php`
     - `admin_dashboard.php`
     - `mark_attendance.php`

2. **Attendance Tracking Disabled**
   - **Problem**: "Attendance tracking coming soon" placeholder message prevented students from marking attendance
   - **Solution**: Replaced placeholder with functional link to `mark_attendance.php`
   - **Files Modified**: `courses.php`

## Database Schema

### Key Tables

- **users**: Stores user information (students, faculty, admins)
- **courses**: Course information and faculty assignments
- **enrollments**: Student enrollment status in courses
- **sessions**: Course sessions scheduled by faculty
- **attendance**: Student attendance records for sessions
- **login_attempts**: Security logging for login attempts

## Usage

### For Students
1. Log in with student credentials
2. Go to "Join Course" to request enrollment in available courses
3. Wait for faculty approval
4. Once approved, access courses from "My Courses"
5. Use the course's attendance code to mark attendance

### For Faculty
1. Log in with faculty credentials
2. Create courses from the dashboard
3. Set up sessions and generate attendance codes
4. View and approve/reject student join requests
5. Monitor attendance records

### For Admins
1. Log in with admin credentials
2. Manage users (create, edit, change roles)
3. View system statistics and security logs
4. Manage courses and enrollment requests

## Security Features

- **CSRF Protection**: All forms include CSRF token validation
- **Session Management**: Automatic session expiration after 30 minutes
- **Password Hashing**: Uses PHP's `password_hash()` for secure password storage
- **Input Sanitization**: All user inputs are sanitized to prevent SQL injection
- **Role-Based Access Control**: Different permissions for students, faculty, and admins
- **Login Attempt Tracking**: Records and monitors failed login attempts

## Testing

To test the system locally:

1. **Create test accounts**: Register as student, faculty, and admin
2. **Create courses**: Log in as faculty and create test courses
3. **Test enrollment**: Log in as student and request to join courses
4. **Approve requests**: Log in as faculty and approve student requests
5. **Mark attendance**: Create sessions with codes and test attendance marking

## Troubleshooting

### Database Connection Errors
- Ensure MySQL is running in XAMPP
- Verify database credentials in `includes/database.php`
- Check that the `course_management` database exists

### Login Issues
- Clear browser cookies and session data
- Check that user accounts exist in the database
- Verify user role is set correctly

### Attendance Not Marking
- Confirm the attendance code is correct
- Verify the student is enrolled in the course
- Check that today's session exists in the database

## Future Enhancements

- Email notifications for enrollment approvals
- QR code generation for attendance codes
- Advanced attendance analytics and reporting
- Course schedule calendar view
- Mobile app support
- Multi-language support

## License

This project is part of an educational activity. All rights reserved.

## Support

For issues or questions, contact the system administrator or development team.

---

**Last Updated**: December 4, 2025  
**Branch**: final_attendance_management
