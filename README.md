# Secure Customer File Manager

A premium, high-security document repository and customer profile directory featuring **AES-256 Encryption at Rest**, real-time dashboard statistics, and full security audit logging. Designed for managing confidential files (such as Voter IDs, Aadhaar documents, and scanned PDFs) with strict privacy measures.

---

## 🛡️ Key Features

- **AES-256 Encryption at Rest**: All uploaded documents and profile photos are physically encrypted on the server disk using AES-256-CBC with secure randomized IV keys.
- **Zero Direct Access**: Files reside in a private directory (`secure_data/uploads/`) guarded against direct HTTP access. Downloads and previews are dynamically authenticated, decrypted, and streamed on-the-fly.
- **Security Audit Trails**: Track all system activities (logins, uploads, modifications, and deletions) in a central `audit_logs` table, storing the administrator's name, action, details, and IP address.
- **CSRF & Session Protection**: 
  - Dual-layer CSRF token verification for all POST requests (via forms and AJAX headers).
  - Hardened PHP sessions with `HttpOnly`, `SameSite=Strict`, and `Secure` attributes to prevent session hijack/XSS vulnerabilities.
- **Strict File Upload Validation**:
  - Whitelisted file extension matching.
  - Double-layer MIME-type analysis using the PHP `fileinfo` extension.
  - Automatic conversion of filenames to random SHA-256 hashes without extensions to prevent Remote Code Execution (RCE) and Directory Traversal.
- **Modern User Interface**: Responsive dashboard featuring:
  - Dark and Light theme toggle (saved via `localStorage`).
  - Sleek Glassmorphism components and Outfit typography.
  - Dynamic file search, category filtering, and instant preview modal.

---

## 📂 Project Structure

```text
file_manager/
│
├── index.php             # Router/Entry point (redirects to dashboard or login)
├── config.php            # Core configs, DB auto-migration, encryption & CSRF helpers
├── login.php             # Secure admin authentication page with theme support
├── admin.php             # Primary administrator dashboard UI
├── ajax.php              # API backend endpoints for CRUD & statistics
├── download.php          # Decrypts and serves stored files/avatars securely
├── logout.php            # Destroys sessions and clears cookies
│
├── assets/
│   ├── css/
│   │   └── style.css     # Premium styling, glassmorphism layouts & animations
│   └── js/
│       └── app.js        # Core Javascript logic for AJAX, search, and themes
│
└── secure_data/          # Private data storage (uploads stored here)
```

---

## 🗄️ Database Schema

The database uses InnoDB tables with a character set of `utf8mb4_unicode_ci` and contains the following tables:

1. **`admins`**: Stores manager credentials (`id`, `username`, `password_hash`, `full_name`, `created_at`).
2. **`customers`**: Holds customer records (`id`, `customer_name`, `customer_email`, `customer_phone`, `sex`, `dob`, `profile_photo`, `address`, `notes`, `created_at`, `avatar_encrypted`).
3. **`files`**: Stores metadata of encrypted documents (`id`, `customer_id`, `original_name`, `stored_name`, `file_type`, `mime_type`, `file_size`, `notes`, `uploaded_at`, `is_encrypted`).
4. **`audit_logs`**: Logs administration actions (`id`, `admin_username`, `action`, `details`, `ip_address`, `created_at`).

---

## 🚀 Installation & Setup

### Prerequisites
- A local web server (e.g. Apache via **XAMPP**, WampServer, or Laragon).
- **PHP 7.4+** with the following extensions enabled:
  - `PDO` (with `pdo_mysql` driver)
  - `openssl` (for AES-256 encryption)
  - `fileinfo` (for MIME verification)
- **MySQL / MariaDB** database server.

### Steps

1. **Clone or Copy the Files**: 
   Place the project directory into your web server's root (e.g. `C:\xampp\htdocs\file_manager`).

2. **Configure Database Credentials**:
   Open [config.php](file:///C:/xampp/htdocs/file_manager/config.php) and verify/edit the database credentials to match your MySQL server configuration:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'filemanager');
   ```

3. **Automatic Migration**:
   You do **not** need to import database scripts manually. The application automatically detects if the database, tables, and required columns exist upon the first access of any page. It will automatically run the schema migrations.

4. **Default Administrator Login**:
   - Access the dashboard at `http://localhost/file_manager/`
   - Use the default credentials:
     - **Username**: `admin`
     - **Password**: `admin123`
   
   > [!IMPORTANT]
   > For security purposes, immediately change the admin password after logging in by clicking the **Settings Gear** (`⚙️`) icon in the dashboard's top bar.

---

## 🔒 Security Practices & Maintenance

- **Configuring `.htaccess`**: Ensure direct browser access to the `secure_data` directory is blocked. You can verify or add a `.htaccess` file inside `secure_data/` containing:
  ```apache
  Deny from all
  ```
- **Password Policies**: Ensure any new administrator password meets strict standards.
- **Log Monitoring**: Administrators can check recent activity logs by clicking on the log view button or visiting `view_logs.php`.

---

## 🛠️ Built With
- **Backend**: PHP 8.x + PDO (MySQL)
- **Security**: OpenSSL AES-256-CBC
- **Frontend**: HTML5, Vanilla CSS3 (Custom Glassmorphism styling), Bootstrap 5, FontAwesome 6, Google Fonts (Outfit).
