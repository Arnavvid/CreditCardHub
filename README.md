## CardHub India
CardHub India is a robust web application designed to help users discover, compare, and manage credit cards from major Indian and international banks. The platform features an advanced comparison engine, secure user authentication, and a data-driven administrative dashboard.

## Features
### For Users
- Smart Discovery: Browse cards from top banks including HDFC, SBI, ICICI, Axis, and Amex.
- Comparison Engine: Select up to three cards to view a side-by-side breakdown of rewards, fees, and eligibility.
- Personalized Profiles: Secure account management where users can update details or change passwords.
- Dark Mode Support: A fully responsive UI that supports both light and dark themes for better accessibility.
- Search: Instant filtering and logging of search terms to help find the right card quickly.

### For Administrators
- Data Analytics: A dedicated dashboard using Chart.js to visualize card popularity and user search trends.
- Card Management: Full CRUD (Create, Read, Update, Delete) functionality to manage the credit card database via a web interface.
- Activity Logging: Tracks comparison and search history to provide insights into user preferences.

## Tech Stack
- Frontend: HTML5, CSS3 (with Custom Variables), JavaScript (ES6+), and jQuery.
- Backend: PHP.
- Database: MySQL.
- Visualizations: Chart.js.
- Frameworks: Bootstrap 5 (used in landing pages).


## Project Structure
- start.html: The landing page.
- mainpage.html: The core application interface for searching and comparing cards.
- admin.html: The administrative control panel and analytics hub.
- database.php: The important backend script handling all database interactions and table setups.
- get_stats.php: Fetches analytical data for the admin charts.
- profile.html: User account and settings management.

## Setup
### Clone the Repository: 

  ```git clone https://github.com/yourusername/repo-name.git```

### Server Environment:
  
  Move the project folder to your local server directory (e.g., htdocs for XAMPP or www for WAMP).

  Ensure PHP and MySQL are active.

### Database Configuration:

  Create a database named cardhub in PHPMyAdmin.

  Open ```database.php``` and verify the ```$servername```, ```$username```, and ```$password``` match your local SQL credentials.

  The script is designed to automatically create the necessary tables (auth_users, credit_cards, card_scores, etc.) upon the first connection.

### Launch:

  Open ```start.html``` in your browser.

### Usage:
  
  User Login: 
  Register a new account via signup.html or log in via login.html.


  Admin Access:
  
  - Email: admin@123
  
  - Password: 12345


