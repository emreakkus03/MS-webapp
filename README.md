# MS Infra Web Application  

![Laravel](https://img.shields.io/badge/Laravel-10.x-FF2D20?logo=laravel&logoColor=white)  
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white)  
![TailwindCSS](https://img.shields.io/badge/TailwindCSS-3.x-38B2AC?logo=tailwind-css&logoColor=white)  
![Docker](https://img.shields.io/badge/Docker-24.x-2496ED?logo=docker&logoColor=white)  
![DDEV](https://img.shields.io/badge/DDEV-latest-0A192F?logo=docker&logoColor=white)  

---

## 📖 About this application  
This web application is built for **MS Infra BV**.  
It allows users and administrators to manage schedules, tasks, and teams in an efficient and structured way.

---

## ✨ Features  

### 🔑 Authentication & Accounts
- Users can log in to access their personal schedules.  
- The admin can create new teams (accounts with username and password).  
- The admin can edit or delete teams.  
- Teams can be sorted or searched by name and role.  

### 📅 Scheduling & Tasks
- **Admin**:
  - Create, edit, delete, and reopen tasks.  
  - Assign tasks to different teams.  
  - View the full calendar for all teams (e.g., see tasks for today).  
  - Filter tasks by status.  
  - Search tasks by address.  
  - Manage notes and photos uploaded by users.  

- **User**:
  - View their own team’s schedule/calendar.  
  - Complete a task by adding a note (e.g., damage details).  
  - Upload photos as proof of completion.  
  - Uploaded photos are stored in **Dropbox** and linked to the task.  

### 📊 Dashboard
- Admin dashboard displays:
  - General statistics (total teams, total tasks today, completed vs open tasks).  
  - Quick navigation links.  
  - Real-time notifications when:
    - A new note is added.  
    - A task is completed.  

### 🔔 Notifications
- Admins receive live notifications via **Laravel Reverb** and **Echo** when tasks or notes are updated.  
- Includes browser notifications and badge counters.  

---

## 🛠 Tech Stack
- **Backend**: Laravel (Blade templates)  
- **Frontend**: Tailwind CSS, JavaScript (Alpine.js for interactivity)  
- **Database**: MySQL  
- **Realtime**: Laravel Reverb + Laravel Echo  
- **File Storage**: Dropbox integration for photo uploads  
- **Environment**: Docker & DDEV for local development  
- **Package Managers**: Composer, NPM  

---

## 📜 License & Usage
⚠️ This project is strictly intended for **MS Infra BV**.  
It may **not** be copied, reused, or redistributed without explicit permission from both:  
- The company (**MS Infra BV**)  
- The author (**Emre Akkus**)  

---

## 👨‍💻 Author
- **Emre Akkus**  
