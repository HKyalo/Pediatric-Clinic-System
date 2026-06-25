PCASS: Pediatric Clinic Appointment Scheduling System with EHR Integration 

A web-based clinic system for pediatric healthcare facilities, integrating appointment scheduling, electronic health records (EHR), vaccination tracking, and specialist referrals.

Installation:

Prerequisites: XAMPP with PHP 7.4+ and MySQL 5.7+

Steps:
1. download the repository to htdocs folder
2. Start Apache and MySQL in XAMPP
3. Create a database named pcass in phpMyAdmin
4. Import pcass.sql into the database
5. Update config/db.php with your database credentials
6. Access at http://localhost/pcass/


Project Structure

```
PCASS/
├── config/db.php              # Database connection
├── assets/                    # CSS, JS, images
├── uploads/lab_results/       # Uploaded lab files
├── index.php                  # Login page
├── register.php               # Guardian registration
├── appointments.php           # Appointment booking
├── medical-history.php        # View medical records
├── doctor_*.php               # Doctor portals
├── admin_*.php                # Admin panel
├── manage_*.php               # Management modules for admin
└── pcass.sql                  # Database export
```
