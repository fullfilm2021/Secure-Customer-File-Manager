<?php
require_once 'config.php';

// If already logged in, redirect to admin dashboard
if (is_logged_in()) {
    header("Location: admin.php");
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Verify CSRF
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Security token invalid. Please refresh and try again.';
    } elseif (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        try {
            // Find administrator by username
            $stmt = $pdo->prepare("SELECT * FROM `admins` WHERE `username` = ? LIMIT 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();
            
            if ($admin && password_verify($password, $admin['password_hash'])) {
                // Password matches, regenerate session ID to prevent fixation
                session_regenerate_id(true);
                
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_name'] = $admin['full_name'];
                
                // Log successful login
                log_action('Login Success', "Administrator '{$admin['username']}' logged in successfully.");
                
                // Regenerate CSRF for future requests
                regenerate_csrf_token();
                
                header("Location: admin.php");
                exit;
            } else {
                $error = 'Invalid username or password.';
                // Log failed login
                log_action('Login Failure', "Failed login attempt for username '{$username}'.");
            }
        } catch (PDOException $e) {
            $error = 'Database error occurred: ' . $e->getMessage();
        }
    }
}

// Generate CSRF token for the form
$token = get_csrf_token();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Secure File Manager</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <style>
        :root, [data-bs-theme="dark"] {
            --bg-gradient: linear-gradient(135deg, #090d16 0%, #0f172a 60%, #082f49 100%);
            --glass-bg: rgba(255, 255, 255, 0.02);
            --glass-border: rgba(255, 255, 255, 0.06);
            --glass-focus: rgba(255, 255, 255, 0.12);
            --accent-primary: #3b82f6; /* Cobalt Blue */
            --accent-glow: rgba(59, 130, 246, 0.3);
            --text-muted: #94a3b8;
            --body-color: #f8fafc;
        }

        [data-bs-theme="light"] {
            --bg-gradient: linear-gradient(135deg, #f8fafc 0%, #e0f2fe 60%, #ecfeff 100%);
            --glass-bg: rgba(255, 255, 255, 0.5);
            --glass-border: rgba(0, 0, 0, 0.06);
            --glass-focus: rgba(0, 0, 0, 0.12);
            --accent-primary: #2563eb; /* Royal Blue */
            --accent-glow: rgba(37, 99, 235, 0.15);
            --text-muted: #475569;
            --body-color: #0f172a;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--body-color);
            overflow-x: hidden;
            position: relative;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Abstract decorative shapes */
        .shape {
            position: absolute;
            border-radius: 50%;
            filter: blur(100px);
            z-index: 0;
            opacity: 0.4;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .shape-1 {
            width: 300px;
            height: 300px;
            background: #3b82f6; /* Cobalt Blue */
            top: 10%;
            left: 15%;
        }
        .shape-2 {
            width: 400px;
            height: 400px;
            background: #06b6d4; /* Cyber Cyan */
            bottom: 10%;
            right: 15%;
        }

        .login-container {
            z-index: 1;
            width: 100%;
            max-width: 390px;
            padding: 15px;
        }

        .login-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 30px 24px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .login-card:hover {
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.35), 0 0 25px rgba(139, 92, 246, 0.1);
        }

        .brand-logo {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--accent-primary), #3b82f6);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            box-shadow: 0 0 15px var(--accent-glow);
        }

        .brand-logo i {
            font-size: 22px;
            color: #ffffff;
        }

        .login-header h2 {
            font-weight: 700;
            font-size: 20px;
            letter-spacing: -0.5px;
            margin-bottom: 4px;
        }

        .form-label {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .input-group {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .input-group:focus-within {
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px var(--accent-glow);
        }

        .input-group-text {
            background: transparent;
            border: none;
            color: var(--text-muted);
            padding-left: 12px;
            padding-right: 4px;
        }

        .form-control {
            background: transparent;
            border: none;
            color: var(--body-color);
            font-size: 14px;
            padding: 10px 12px;
        }

        .form-control:focus {
            background: transparent;
            border: none;
            box-shadow: none;
            color: var(--body-color);
        }

        .form-control::placeholder {
            color: #64748b;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent-primary) 0%, #6d28d9 100%);
            border: none;
            border-radius: 8px;
            padding: 10px;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.2);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #9333ea 0%, #5b21b6 100%);
            transform: translateY(-1.5px);
            box-shadow: 0 6px 15px rgba(139, 92, 246, 0.3);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            border-radius: 8px;
            font-size: 13px;
            padding: 10px 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-card {
            background: rgba(59, 130, 246, 0.05);
            border: 1px solid rgba(59, 130, 246, 0.15);
            border-radius: 8px;
            font-size: 12px;
            color: #93c5fd;
            padding: 10px 12px;
            margin-top: 20px;
            display: flex;
            align-items: flex-start;
            gap: 8px;
            transition: all 0.3s ease;
        }

        /* Glass buttons */
        .btn-glass {
            background: rgba(255, 255, 255, 0.05);
            color: var(--body-color);
            border: 1px solid var(--glass-border);
            transition: all 0.3s ease;
        }

        .btn-glass:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--body-color);
        }

        [data-bs-theme="light"] .login-card {
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.06);
        }

        [data-bs-theme="light"] .info-card {
            background: rgba(37, 99, 235, 0.06);
            border-color: rgba(37, 99, 235, 0.18);
            color: #1d4ed8;
        }

        [data-bs-theme="light"] .btn-glass {
            background: rgba(0, 0, 0, 0.04);
            border-color: rgba(0, 0, 0, 0.08);
        }

        [data-bs-theme="light"] .btn-glass:hover {
            background: rgba(0, 0, 0, 0.08);
        }

        [data-bs-theme="light"] .input-group {
            background: rgba(255, 255, 255, 0.85);
            border-color: rgba(0, 0, 0, 0.1);
        }

        [data-bs-theme="light"] .input-group:focus-within {
            border-color: var(--accent-primary);
        }

        [data-bs-theme="light"] .shape-1 {
            background: #a78bfa;
            opacity: 0.15;
        }

        [data-bs-theme="light"] .shape-2 {
            background: #93c5fd;
            opacity: 0.15;
        }
    </style>
</head>
<body>

    <!-- Background shapes -->
    <div class="shape shape-1"></div>
    <div class="shape shape-2"></div>

    <div class="login-container">
        <div class="login-card position-relative">
            <!-- Theme Toggle Button -->
            <button id="themeToggleBtn" class="btn btn-sm btn-glass text-warning position-absolute" title="Toggle Light/Dark Theme" style="top: 20px; right: 20px; width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center; border-radius: 50%; z-index: 10;">
                <i class="fa-solid fa-sun" id="themeToggleIcon" style="font-size: 14px;"></i>
            </button>
            
            <div class="text-center mb-4">
                <div class="brand-logo">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <div class="login-header">
                    <h2>Secure File Manager</h2>
                    <p class="text-muted small">Administrator Control Panel</p>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger mb-4" role="alert">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <div><?= h($error) ?></div>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username" placeholder="Enter username" value="<?= h($username) ?>" required autocomplete="username">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-key"></i></span>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Enter password" required autocomplete="current-password">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-2">
                    <i class="fa-solid fa-right-to-bracket me-2"></i>Sign In
                </button>
            </form>

            <div class="info-card">
                <i class="fa-solid fa-circle-info mt-0.5"></i>
                <div>
                    <strong>Default setup active:</strong><br>
                    Use username <code class="text-white">admin</code> and password <code class="text-white">admin123</code> to log in. Please update your password immediately in your database.
                </div>
            </div>
            
        </div>
    </div>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
    
    <script>
        // Theme Toggle Logic for Login page
        const themeToggleBtn = document.getElementById('themeToggleBtn');
        const themeToggleIcon = document.getElementById('themeToggleIcon');
        
        function syncToggleIcon() {
            const currentTheme = document.documentElement.getAttribute('data-bs-theme') || 'dark';
            if (currentTheme === 'light') {
                themeToggleIcon.className = 'fa-solid fa-moon';
                themeToggleBtn.className = 'btn btn-sm btn-glass text-dark position-absolute';
                themeToggleBtn.title = 'Switch to Dark Mode';
            } else {
                themeToggleIcon.className = 'fa-solid fa-sun';
                themeToggleBtn.className = 'btn btn-sm btn-glass text-warning position-absolute';
                themeToggleBtn.title = 'Switch to Light Mode';
            }
        }
        
        if (themeToggleBtn) {
            syncToggleIcon();
            themeToggleBtn.addEventListener('click', () => {
                const currentTheme = document.documentElement.getAttribute('data-bs-theme') || 'dark';
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-bs-theme', newTheme);
                localStorage.setItem('theme', newTheme);
                syncToggleIcon();
            });
        }
    </script>
</body>
</html>
