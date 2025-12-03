# MS Infra Web Application  

![Laravel](https://img.shields.io/badge/Laravel-11.x-FF2D20?logo=laravel&logoColor=white)  
![MySQL](https://img.shields.io/badge/MySQL-8.2-4479A1?logo=mysql&logoColor=white)  
![TailwindCSS](https://img.shields.io/badge/TailwindCSS-3.x-38B2AC?logo=tailwind-css&logoColor=white)  
![Docker](https://img.shields.io/badge/Docker-24.x-2496ED?logo=docker&logoColor=white)  
![DDEV](https://img.shields.io/badge/DDEV-latest-0A192F?logo=docker&logoColor=white)  

---

## 📖 About this application  
This web application is built for **MS Infra BV**.  
It allows users and administrators to manage schedules, tasks, leave requests, and notifications in an efficient and structured way.

---

## ✨ Features  

### 🔑 Authentication & Accounts
- Secure login system for both **admin** and **team users**.  
- Admin can create, edit, and delete teams (each team has its own login credentials).  
- Teams can be sorted or searched by name and role.  
- Teams are listed with **Admin first**, followed by **Ploeg 1, Ploeg 2**, etc.  

---

### 📅 Scheduling & Tasks
- **Admin:**
  - Create, edit, delete, and reopen tasks.  
  - Assign tasks to specific teams.  
  - Filter and search tasks by address or status.  
  - View all team schedules via the calendar.  
  - Manage and review uploaded photos and notes.  

- **User (Team):**
  - View their own assigned schedule and upcoming tasks.  
  - Mark tasks as completed and add notes.  
  - Upload photos as proof of completion.  
  - Photo uploads are now optimized:
    - First uploaded to **Cloudflare R2** for speed and reduced hosting costs.  
    - Then asynchronously transferred to **Dropbox** via **Laravel queue workers**.  

---

### 🌴 Leave Management (New 6/11/2025)
- Each **team user** can log into their account and submit a **leave request (verlofaanvraag)**.  
- Leave requests include:
  - Team member name  
  - Type of leave (e.g., vacation, medical, etc.)  
  - Start and end dates  
  - Optional note  

- **Admin** can:
  - View all leave requests on the new **Leave Management Page**.  
  - Approve or reject requests.  
  - Receive instant notifications when a new leave request is submitted.  

- **Notifications:**
  - **Normal users** receive a notification when their own leave request is approved or rejected.  
  - **Admins** receive notifications when:
    - A task is completed.  
    - A note is added to a task.  
    - A leave request is submitted.  

---

### 📨 Daily Email Summary (New 6/11/2025)
- Every day at **17:00**, a summary email is automatically sent to selected **admins**.  
- Emails are delivered through **Brevo (Sendinblue)**.  
- The summary includes key daily updates such as completed tasks and team activity.  

---

### 📊 Dashboard
- Admin dashboard provides:
  - Real-time overview of all active teams and tasks.  
  - Statistics for today’s planned, ongoing, and completed tasks.  
  - Quick navigation links.  
  - Live notification feed for all updates.  

---

### 🔔 Notifications
- Powered by **Laravel Reverb** and **Echo** for live updates.  
- Real-time browser notifications when:
  - Tasks are completed.  
  - Notes are added.  
  - Leave requests are submitted or updated.  
- Visual badge counters and color indicators for quick admin overview.  

---

## 🛠 Tech Stack
- **Backend**: Laravel 11 (Blade templates)  
- **Frontend**: Tailwind CSS + Vanilla JS (Alpine.js for interactivity)  
- **Database**: MySQL 8.2  
- **Realtime**: Laravel Reverb + Echo  
- **File Storage**: Cloudflare R2 + Dropbox (via Laravel Queues)  
- **Email Delivery**: Brevo (Sendinblue)  
- **Environment**: Docker & DDEV for containerized local development  
- **Package Managers**: Composer & NPM  

---

## 🔒 Security & Performance
- CSRF, XSS, and session fixation protections enabled.  
- Secure session regeneration on login/logout.  
- Optimized background processing with Laravel Queues for uploads.  
- Caching disabled on login page for safety.  

---

## 📜 License & Usage
⚠️ This project is strictly intended for **MS Infra BV**.  
It may **not** be copied, reused, or redistributed without explicit permission from both:  
- The company (**MS Infra BV**)  
- The author (**Emre Akkus**)  

---

## 👨‍💻 Author
- **Emre Akkus**  


php artisan r2:retry-all => als de foto's in r2 blijven hangen terwijl de adres in behandeling of voltooid is en de foto's niet in dropbox komen gebruik deze command

php artisan r2:download-all => gebruik deze command om alle foto's die in r2 bucket blijven hangen te downloaden in een zip
php artisan r2:publish-zip => gebruik deze command direct na de stap hierboven om de zip in de publieke folder te zetten om te kunnen downloaden en surf dan naar deze url: https://ms-webapp-main-yfswth.laravel.cloud/download-r2-backup

php artisan r2:clear => gebruik deze command om de bucket te clearen dus de hangende foto's te verwijderen na het downloaden van de zip
