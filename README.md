# 🏥 Community Health Record System (CHR)

A full-featured web-based Community Health Record management system built with PHP and MySQL. It connects patients, doctors, hospitals, laboratories, and administrators into a centralized healthcare ecosystem.

---

## ✨ Features & User Roles

- **👤 Patients**:
  - Book doctor appointments with date & time preference.
  - Book laboratory tests.
  - Upload & manage personal health records and documents.
  - View doctor consultation reports and lab test results.
  - Receive real-time in-app notifications.

- **🩺 Doctors**:
  - Review, accept, reject, or reschedule patient appointments.
  - Mark doctor leave dates (automatically unavailable in patient booking calendar).
  - Write and upload medical consultation reports.
  - Manage doctor profile (specialization, qualification, contact info).

- **🏥 Hospitals**:
  - Register and manage affiliated doctors.
  - Update hospital profile, operating hours, and location on map.
  - Interactive Leaflet / OpenStreetMap hospital locator.

- **🔬 Laboratories**:
  - Accept or reject test bookings.
  - Upload test result reports (PDF / images).
  - Notify patients when test reports are ready.

- **🛡️ Admin**:
  - Administrative dashboard.
  - Manage hospitals and healthcare facilities.

---

## 🗄️ Database (`database.sql`)

The repository includes your complete database dump exported directly from XAMPP in [`database.sql`](database.sql), including all existing patients, doctors, appointments, hospitals, laboratories, and reports.

## 🚀 Deployment Guide

### Option 1: Deploying to Render (Recommended ⭐)

Render is the **recommended** platform for this application because it runs a persistent Docker container supporting Apache, native PHP sessions, and file uploads.

#### Step 1: Create a Free Cloud MySQL Database
Since Render's free tier provides PostgreSQL, you can use any free MySQL provider:
1. **[TiDB Cloud (Free Serverless Tier)](https://tidbcloud.com/)** or **[Aiven MySQL (Free Tier)](https://aiven.io/)** or **[Railway](https://railway.app/)**.
2. Create a MySQL database (e.g. named `chr_db`).
3. Connect using MySQL Workbench, phpMyAdmin, or terminal, and import [`database.sql`](database.sql).

#### Step 2: Deploy on Render
1. Log in to your [Render Dashboard](https://dashboard.render.com/).
2. Click **New +** > **Web Service**.
3. Select **Build and deploy from a Git repository** and connect:
   `https://github.com/ubedkhatri3305-byte/Community-Health-Record-System`
4. Configure service settings:
   - **Name:** `community-health-record-system`
   - **Region:** Any (e.g., Oregon or Frankfurt)
   - **Branch:** `main`
   - **Runtime:** `Docker` (Render will automatically detect the [`Dockerfile`](Dockerfile))
   - **Instance Type:** `Free`
5. Under **Environment Variables**, add:
   - `DB_HOST`: *Your cloud MySQL host*
   - `DB_USER`: *Your cloud MySQL username*
   - `DB_PASS`: *Your cloud MySQL password*
   - `DB_NAME`: *Your database name (e.g., `chr_db`)*
   - `DB_PORT`: *Your MySQL port (e.g., `3306` or `4000`)*
   - *(Optional)* `DB_SSL`: `true` (if using TiDB or Aiven SSL)
6. Click **Create Web Service**. Render will build and deploy your container!

---

### Option 2: Deploying to Vercel

The project includes [`vercel.json`](vercel.json) using the community `vercel-php` runtime.

> ⚠️ **Important Vercel Considerations:**
> - Vercel functions are **serverless**: local files uploaded to `uploads/` will not persist across requests unless stored on an external cloud storage provider (such as AWS S3 or Cloudinary).
> - PHP session files stored in `/tmp` are stateless across different serverless invocations.
> - For full production stability with PHP sessions and file uploads, **Render** is recommended.

#### Deployment Steps:
1. Import the repository in [Vercel](https://vercel.com/new).
2. Set Environment Variables in Project Settings:
   - `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`, `DB_PORT`
3. Click **Deploy**.

---

## 💻 Local Development (XAMPP)

1. Clone or place the project in `C:/xampp/htdocs/chr`.
2. Start **Apache** and **MySQL** from XAMPP Control Panel.
3. Open `http://localhost/phpmyadmin`, create a database `chr_db`, and import [`database.sql`](database.sql).
4. Visit `http://localhost/chr` in your web browser.