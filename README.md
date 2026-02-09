# 🎓 Placement Portal

**Complete Placement Preparation Platform with 8 Integrated Modules**

## Overview

Placement Portal is a comprehensive web application designed to help students prepare for campus placements. It offers aptitude tests, coding practice, company-specific preparation, community Q&A, focus tracking, resume building, and administrative tools.

## ✨ Features

### 1. **Auth & Profile** 🔐
- Secure user registration and login
- Password recovery mechanism
- Personalized dashboard with statistics
- Profile management

### 2. **Aptitude Engine** 🧠
- Timed mock tests (Quantitative, Logical, Verbal)
- Instant feedback and scoring
- Detailed question-wise analysis
- Test history and performance tracking

### 3. **Smart Code Studio** 💻
- Integrated online compiler (C, C++, Java, Python)
- Problem-solving platform
- Time and space complexity tracking
- Test case validation
- Submission history

### 4. **Company Specific** 🏢
- Company profiles (TCS, Infosys, Wipro, Cognizant, Accenture)
- Round-wise preparation materials
- Previous year questions
- Eligibility criteria and package details

### 5. **Community Q&A** 💬
- Ask and answer questions
- Upvote/downvote system
- Peer solution verification
- Leaderboard based on contributions
- Tag-based organization

### 6. **Focus Mode (USP)** 🎯
- Real-time distraction detection
- Tab switching detection
- Focus score calculation
- Analytics and reports
- Violation logging

### 7. **Career Toolkit** 📄
- Resume builder with multiple templates
- PDF export functionality
- Secure document locker
- Certificate storage

### 8. **Admin Panel** 👨‍💼
- User management
- Content management (tests, problems, companies)
- Analytics dashboard
- Support ticket system
- System settings

## 🚀 Installation

### Prerequisites
- XAMPP (PHP 8.x + MySQL 8.x)
- Web browser (Chrome, Firefox, Edge)
- Text editor (VS Code recommended)

### Setup Steps

1. **Install XAMPP**
   - Download from [https://www.apachefriends.org](https://www.apachefriends.org)
   - Install and start Apache and MySQL

2. **Clone/Copy Project**
   ```
   Copy the placementportal folder to: C:\xampp\htdocs\
   ```

3. **Create Database**
   - Open phpMyAdmin: `http://localhost/phpmyadmin`
   - Click "Import" tab
   - Select `database.sql` file from project root
   - Click "Go" to import

4. **Configure Settings** (Optional)
   - Edit `config/config.php` for custom settings
   - Update database credentials if needed
   - Add Judge0 API key for code compilation

5. **Access Application**
   ```
   Open browser and navigate to: http://localhost/placementportal
   ```

### Default Admin Account
```
Email: admin@placementportal.com
Password: admin123
```

**⚠️ Please change the admin password after first login!**

## 📁 Project Structure

```
placementportal/
├── config/
│   └── config.php                 # Main configuration
├── classes/
│   ├── Database.php               # PDO wrapper
│   ├── Auth.php                   # Authentication
│   ├── User.php                   # User management
│   ├── Aptitude.php               # Aptitude tests
│   ├── Compiler.php               # Code execution
│   ├── FocusMode.php              # Focus tracking
│   └── Community.php              # Q&A system
├── modules/
│   ├── auth/                      # Login/Register/Logout
│   ├── dashboard/                 # Student dashboard
│   ├── aptitude/                  # Aptitude tests
│   ├── coding/                    # Code studio
│   ├── companies/                 # Company prep
│   ├── community/                 # Q&A forum
│   ├── toolkit/                   # Resume & documents
│   ├── focus/                     # Focus analytics
│   └── admin/                     # Admin panel
├── assets/
│   ├── css/                       # Stylesheets
│   ├── js/                        # JavaScript files
│   │   └── focus-tracker.js       # Focus detection
│   └── uploads/                   # User uploads
├── api/
│   └── focus-track.php            # Focus API
├── database.sql                   # Database schema
├── index.php                      # Landing page
└── README.md                      # This file
```

## 🛠️ Technology Stack

- **Backend**: PHP 8.x (OOP)
- **Database**: MySQL 8.x
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Code Execution**: Judge0 CE API
- **Styling**: Custom CSS with gradients
- **Security**: PDO prepared statements, password hashing, CSRF protection

## 📊 Database Schema

### Core Tables
- `users` - User accounts
- `user_profiles` - Extended profile info
- `roles` - User roles (student, admin)

### Aptitude Module
- `aptitude_categories` - Test categories
- `aptitude_questions` - Question bank
- `aptitude_tests` - Test configurations
- `aptitude_attempts` - User attempts
- `aptitude_answers` - Question responses

### Code Studio
- `coding_problems` - Problem statements
- `test_cases` - Input/output cases
- `coding_submissions` - User submissions
- `supported_languages` - Programming languages

### Community
- `questions` - User questions
- `answers` - Responses
- `votes` - Upvote/downvote data
- `leaderboard` - User rankings

### Focus Mode
- `focus_sessions` - Study sessions
- `focus_violations` - Distraction logs
- `focus_analytics` - Aggregated stats

## 🔑 Key Features Implementation

### Focus Mode Detection
The system tracks:
- Tab switching (`visibilitychange` event)
- Window blur (switching apps)
- Copy/paste attempts
- Right-click actions
- DevTools access
- Focus score: 100% - (violations × 5%)

### Compiler Integration
- Uses Judge0 CE API for secure execution
- Supports C, C++, Java, Python
- Time/space complexity estimation
- Test case validation
- Sandboxed environment

### Security Features
- Password hashing with bcrypt
- SQL injection prevention (PDO)
- XSS protection
- CSRF token validation
- Session management
- File upload validation

## 📝 Usage Guide

### For Students

1. **Register** → Create account with email and password
2. **Dashboard** → View your statistics and progress
3. **Take Tests** → Navigate to Aptitude Engine
4. **Practice Coding** → Solve problems in Code Studio
5. **Ask Questions** → Use Community Q&A
6. **Build Resume** → Create professional resumes
7. **Track Focus** → Monitor your concentration

### For Admins

1. **Login** → Use admin credentials
2. **Manage Users** → View/edit/delete users
3. **Add Content** → Create tests, problems, questions
4. **View Analytics** → Check system statistics
5. **Handle Support** → Respond to tickets

## 🐛 Troubleshooting

### Database Connection Error
- Ensure MySQL is running in XAMPP
- Check credentials in `config/config.php`
- Verify database exists

### Code Execution Not Working
- Add Judge0 API key in `config/config.php`
- Check internet connection
- Verify API quota

### Focus Mode Not Detecting
- Enable JavaScript in browser
- Check browser console for errors
- Ensure session is started

## 📈 Future Enhancements

- Email verification system
- Mobile responsive design improvements
- Chat functionality
- Video tutorials integration
- Interview preparation module
- Mock interview scheduling
- Placement statistics dashboard

## 👨‍💻 Developer Notes

### Adding New Features
1. Create class in `classes/` directory
2. Add module in `modules/` directory
3. Update database schema if needed
4. Add navigation links

### Code Standards
- Use PSR-12 coding standards
- Comment complex logic
- Use prepared statements
- Validate all inputs
- Handle errors gracefully

## 📄 License

This project is created for educational purposes.

## 🤝 Support

For issues or questions:
- Check documentation
- Review error logs
- Contact administrator

## 🎉 Credits

Created by Placement Portal Team
Version 1.0.0 - January 2026

---

**Happy Coding! 🚀**
