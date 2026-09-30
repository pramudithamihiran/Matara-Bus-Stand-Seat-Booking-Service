# 🚌 Matara Bus Stand - Seat Booking & Management System

A complete web-based bus seat booking and management system developed for **Matara Bus Stand**, Sri Lanka. This application allows passengers to search routes, view available buses, select seats interactively, and book tickets online with instant email confirmation. Admins, bus owners, and conductors get dedicated dashboards to manage operations efficiently.

![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![Chart.js](https://img.shields.io/badge/Chart.js-FF6384?style=for-the-badge&logo=chart.js&logoColor=white)
![PHPMailer](https://img.shields.io/badge/PHPMailer-4A90E2?style=for-the-badge&logo=mail.ru&logoColor=white)

---

## 📖 Table of Contents

- [About the Project](#-about-the-project)
- [Key Features](#-key-features)
- [Tech Stack](#-tech-stack)
- [Database Design](#-database-design)
- [System Roles](#-system-roles)
- [Installation Guide](#-installation-guide)
- [Project Structure](#-project-structure)
- [Security Features](#-security-features)
- [Validation Features](#-validation-features)
- [Screenshots](#-screenshots)
- [Future Enhancements](#-future-enhancements)
- [Author](#-author)

---

## 📌 About the Project

The **Matara Bus Stand Seat Booking Service** is designed to replace the traditional **manual seat allocation and paper ticket system** with a modern, reliable online booking platform.

### 🎯 Problem Solved:
- ❌ No real-time seat availability
- ❌ Duplicate & conflicting bookings
- ❌ Human errors in fare handling
- ❌ Long queues and delays during peak hours

### ✅ Our Solution:
- ✅ Real-time seat availability
- ✅ Interactive seat selection with locking
- ✅ Automated email confirmations
- ✅ Admin & Conductor dashboards
- ✅ Cancellation handling with audit logs

---

## ✨ Key Features

### 👤 Passenger Features
- 🔍 **Search Routes** — Filter buses by route, date, and destination
- 💺 **Interactive Seat Map** — Select preferred seats with real-time availability
- 🎫 **E-Ticket Generation** — Unique reference code with QR-based verification
- 📧 **Email Confirmation** — Automated booking confirmation via PHPMailer
- 📱 **My Bookings** — View booking history and cancel upcoming trips
- 🖨️ **Print Ticket** — Print-friendly ticket layout

### 👨‍💼 Admin Features (Super Admin)
- 🚌 **Manage Buses** — Add, edit, delete buses
- 🛣️ **Manage Routes & Stops** — Add stops with distance-based fare calculation
- 💰 **Fare Settings** — Configure rate per km dynamically
- 📊 **Statistics Dashboard** — View bookings, revenue, top routes (Chart.js)
- 📧 **Contact Messages** — View and manage passenger inquiries
- 🔐 **Role Management** — Create bus owners and conductors

### 🚍 Bus Owner Features
- 📋 **View Own Buses** — Only see buses they own
- 📅 **Block Dates** — Mark maintenance/holiday dates as unavailable
- 📈 **Own Statistics** — Bookings and revenue for their buses
- 🎫 **Passenger List** — View all bookings for their buses

### 🎫 Conductor Features
- 📋 **Today's Passengers** — View all passengers for today's trips
- 🚦 **Ride Status Control** — Start and complete rides
- 🔍 **Verify Tickets** — Check booking reference codes
- 📊 **Daily Statistics** — Today's bookings and seats count

---

## 🛠️ Tech Stack

| Layer | Technology |
|-------|-----------|
| **Frontend** | HTML5, CSS3, JavaScript |
| **Backend** | PHP (Core PHP) |
| **Database** | MySQL |
| **Email** | PHPMailer (SMTP via Gmail) |
| **Charts** | Chart.js |
| **Icons** | Font Awesome 6 |
| **Fonts** | Google Fonts (Poppins) |
| **Server** | XAMPP (Apache + MySQL) |
| **Version Control** | Git & GitHub |

---

## 🗄️ Database Design

The system uses **9 normalized tables (3NF)** with foreign key relationships to prevent data redundancy.

### Tables:

| # | Table Name | Purpose |
|---|-----------|---------|
| 1 | `users` | Passengers, owners, conductors, admins |
| 2 | `buses` | Vehicle details, capacity, owner info |
| 3 | `bookings` | Reservation records with seat numbers |
| 4 | `routes` | Origin, destination, timings |
| 5 | `route_stops` | Ordered stops with distances |
| 6 | `bus_unavailable_dates` | Maintenance/holiday blocks |
| 7 | `contact_messages` | Passenger inquiries |
| 8 | `fare_settings` | Dynamic fare rates |
| 9 | `booking_cancellations` | Cancellation audit log |

### Key Relationships:
