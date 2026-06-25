<?php
require_once 'config.php';

// Only logged in administrators can access files, unless a valid temp preview token is supplied
$authenticated = is_logged_in();

if (!$authenticated) {
    $file_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $token = $_GET['token'] ?? '';
    
    if ($file_id && !empty($token)) {
        try {
            $stmt = $pdo->prepare("SELECT stored_name FROM `files` WHERE `id` = ?");
            $stmt->execute([$file_id]);
            $stored_name = $stmt->fetchColumn();
            if ($stored_name) {
                $expected_token = hash_hmac('sha256', $file_id . $stored_name, DB_PASS);
                if (hash_equals($expected_token, $token)) {
                    $authenticated = true;
                }
            }
        } catch (PDOException $e) {
            // Ignore DB errors
        }
    }
}

if (!$authenticated) {
    header("HTTP/1.1 403 Forbidden");
    echo "<h1>403 Forbidden</h1><p>You must be logged in as an administrator to access this file.</p>";
    exit;
}

$type = $_GET['type'] ?? '';

// If requesting customer profile photo/avatar
if ($type === 'avatar') {
    $customer_id = filter_input(INPUT_GET, 'customer_id', FILTER_VALIDATE_INT);
    if ($customer_id) {
        try {
            $stmt = $pdo->prepare("SELECT profile_photo, avatar_encrypted FROM `customers` WHERE `id` = ?");
            $stmt->execute([$customer_id]);
            $cust = $stmt->fetch();
            $photo = $cust['profile_photo'] ?? null;
            $avatar_encrypted = $cust['avatar_encrypted'] ?? 0;
            
            if ($photo) {
                $file_path = UPLOAD_DIR . basename($photo);
                if (file_exists($file_path)) {
                    $ext = strtolower(pathinfo($photo, PATHINFO_EXTENSION));
                    $mimes = [
                        'jpg' => 'image/jpeg',
                        'jpeg' => 'image/jpeg',
                        'png' => 'image/png',
                        'gif' => 'image/gif',
                        'webp' => 'image/webp'
                    ];
                    $mime = $mimes[$ext] ?? 'image/jpeg';
                    
                    header('Content-Type: ' . $mime);
                    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                    header('Pragma: no-cache');
                    
                    if ($avatar_encrypted == 1) {
                        $enc_data = file_get_contents($file_path);
                        $dec_data = decrypt_file_content($enc_data);
                        header('Content-Length: ' . strlen($dec_data));
                        echo $dec_data;
                    } else {
                        header('Content-Length: ' . filesize($file_path));
                        readfile($file_path);
                    }
                    exit;
                }
            }
        } catch (PDOException $e) {
            // Ignore database errors and serve default
        }
    }
    
    // Serve default SVG avatar placeholder
    header('Content-Type: image/svg+xml');
    header('Cache-Control: max-age=86400');
    echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="100" height="100"><rect width="100%" height="100%" fill="#151e30"/><circle cx="12" cy="8" r="4" fill="#6366f1"/><path d="M12 14c-4.4 0-8 2-8 6v1h16v-1c0-4-3.6-6-8-6z" fill="#6366f1"/></svg>';
    exit;
}

$file_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$file_id) {
    header("HTTP/1.1 400 Bad Request");
    echo "<h1>400 Bad Request</h1><p>Invalid file ID requested.</p>";
    exit;
}

try {
    // Retrieve file info from database
    $stmt = $pdo->prepare("SELECT * FROM `files` WHERE `id` = ?");
    $stmt->execute([$file_id]);
    $file = $stmt->fetch();
    
    if (!$file) {
        header("HTTP/1.1 404 Not Found");
        echo "<h1>404 Not Found</h1><p>The requested file does not exist in the database.</p>";
        exit;
    }
    
    // Build path and sanitize stored filename to prevent directory traversal
    $stored_name = basename($file['stored_name']);
    $file_path = UPLOAD_DIR . $stored_name;
    
    if (!file_exists($file_path)) {
        header("HTTP/1.1 404 Not Found");
        echo "<h1>404 Not Found</h1><p>The file could not be found on the server disk.</p>";
        exit;
    }
    
    // Clear output buffer to prevent corrupted downloads
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Security headers
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    
    // Set content type
    $mime_type = $file['mime_type'];
    if (empty($mime_type)) {
        $mime_type = 'application/octet-stream';
    }
    header("Content-Type: " . $mime_type);
    
    // Decide whether to view inline or force download
    // Images, PDFs, Plain text, and MS Office documents can be displayed inline in the preview modal
    $previewable_types = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'text/plain',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-powerpoint'
    ];
    $disposition = in_array($mime_type, $previewable_types) ? 'inline' : 'attachment';
    
    // Encode filename for content disposition to avoid issues with special characters
    $encoded_filename = rawurlencode($file['original_name']);
    header("Content-Disposition: {$disposition}; filename*=UTF-8''{$encoded_filename}");
    
    // Stream the file
    if (isset($file['is_encrypted']) && $file['is_encrypted'] == 1) {
        $enc_data = file_get_contents($file_path);
        $dec_data = decrypt_file_content($enc_data);
        header("Content-Length: " . strlen($dec_data));
        echo $dec_data;
    } else {
        header("Content-Length: " . $file['file_size']);
        readfile($file_path);
    }
    exit;
    
} catch (PDOException $e) {
    header("HTTP/1.1 500 Internal Server Error");
    echo "<h1>500 Internal Server Error</h1><p>A database error occurred: " . h($e->getMessage()) . "</p>";
    exit;
}
