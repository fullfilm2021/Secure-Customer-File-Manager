<?php
require_once 'config.php';

// Enforce admin login check for security
require_login();

// Retrieve the latest 100 log entries
try {
    $stmt = $pdo->query("SELECT * FROM `audit_logs` ORDER BY `created_at` DESC LIMIT 100");
    $logs = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Audit Logs - Secure File Manager</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <!-- Google Fonts - Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #0f0f12;
            background-image: 
                radial-gradient(at 0% 0%, rgba(168, 85, 247, 0.05) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(236, 72, 153, 0.05) 0px, transparent 50%);
            background-attachment: fixed;
            color: #f3f4f6;
            min-height: 100vh;
            padding: 30px 15px;
        }
        .logs-card {
            background: rgba(30, 30, 35, 0.6);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
            padding: 24px;
        }
        .table {
            color: #f3f4f6;
            border-color: rgba(255, 255, 255, 0.08);
            font-size: 14px;
        }
        .table th {
            color: #a855f7;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.02);
        }
        .table td {
            background: transparent !important;
            vertical-align: middle;
        }
        .badge-action {
            background: rgba(168, 85, 247, 0.15);
            color: #d8b4fe;
            border: 1px solid rgba(168, 85, 247, 0.3);
            font-weight: 500;
        }
        .btn-glass {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #f3f4f6;
            transition: all 0.2s ease;
        }
        .btn-glass:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 255, 255, 0.16);
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logs-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="m-0 fw-600"><i class="fa-solid fa-list-check text-indigo me-2"></i>Security Audit Logs</h3>
                <a href="admin.php" class="btn btn-sm btn-glass">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Log ID</th>
                            <th style="width: 180px;">Timestamp</th>
                            <th style="width: 150px;">Admin User</th>
                            <th style="width: 160px;">Event Action</th>
                            <th>Description / Activity Details</th>
                            <th style="width: 140px;">IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($logs) === 0): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="fa-solid fa-shield-halved fs-3 mb-2 d-block opacity-50"></i>
                                    No audit log records found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="text-secondary fw-500">#<?= h($log['id']) ?></td>
                                    <td class="text-muted"><?= h($log['created_at']) ?></td>
                                    <td>
                                        <i class="fa-solid fa-user-shield text-muted me-1"></i>
                                        <span class="fw-500 text-white"><?= h($log['admin_username']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge badge-action px-2.5 py-1.5"><?= h($log['action']) ?></span>
                                    </td>
                                    <td class="text-secondary"><?= h($log['details']) ?></td>
                                    <td class="font-monospace text-muted"><?= h($log['ip_address']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
