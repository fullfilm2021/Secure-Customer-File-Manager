<?php
require_once 'config.php';

// 1. Authentication Check
if (!is_logged_in()) {
    header("HTTP/1.1 401 Unauthorized");
    json_response(false, 'Session expired. Please log in again.');
}

// 2. CSRF Token Verification for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check both standard POST field and AJAX header
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf_token($token)) {
        header("HTTP/1.1 403 Forbidden");
        json_response(false, 'CSRF token validation failed. Please refresh the page.');
    }
}

// Get action
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    
    // ==========================================
    // CUSTOMER ACTIONS
    // ==========================================
    
    case 'get_customers':
        try {
            $search = trim($_GET['search'] ?? '');
            if ($search !== '') {
                $stmt = $pdo->prepare("SELECT c.*, 
                    (SELECT COUNT(*) FROM `files` f WHERE f.customer_id = c.id) as file_count 
                    FROM `customers` c 
                    WHERE c.customer_name LIKE ? OR c.customer_email LIKE ? OR c.customer_phone LIKE ? 
                    ORDER BY c.customer_name ASC");
                $search_param = "%{$search}%";
                $stmt->execute([$search_param, $search_param, $search_param]);
            } else {
                $stmt = $pdo->query("SELECT c.*, 
                    (SELECT COUNT(*) FROM `files` f WHERE f.customer_id = c.id) as file_count 
                    FROM `customers` c 
                    ORDER BY c.customer_name ASC");
            }
            $customers = $stmt->fetchAll();
            json_response(true, 'Customers retrieved successfully.', ['customers' => $customers]);
        } catch (PDOException $e) {
            json_response(false, 'Failed to fetch customers: ' . $e->getMessage());
        }
        break;

    case 'get_customer':
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            json_response(false, 'Invalid customer ID.');
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM `customers` WHERE id = ?");
            $stmt->execute([$id]);
            $customer = $stmt->fetch();
            if (!$customer) {
                json_response(false, 'Customer not found.');
            }
            json_response(true, 'Customer details retrieved.', ['customer' => $customer]);
        } catch (PDOException $e) {
            json_response(false, 'Database error: ' . $e->getMessage());
        }
        break;
        
    case 'add_customer':
        $name = trim($_POST['customer_name'] ?? '');
        $email = trim($_POST['customer_email'] ?? '');
        $phone = trim($_POST['customer_phone'] ?? '');
        $sex = trim($_POST['sex'] ?? '');
        $dob = trim($_POST['dob'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        if (empty($name)) {
            json_response(false, 'Customer name is required.');
        }
        
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(false, 'Please enter a valid email address.');
        }
        
        $dob_val = !empty($dob) ? $dob : null;
        $sex_val = !empty($sex) ? $sex : null;
        
        // Handle Profile Photo Upload
        $profile_photo = null;
        $avatar_encrypted = 0;
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $photo_temp = $_FILES['profile_photo']['tmp_name'];
            $photo_name = $_FILES['profile_photo']['name'];
            
            // Validate image extension
            $photo_ext = strtolower(pathinfo($photo_name, PATHINFO_EXTENSION));
            if (!in_array($photo_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                json_response(false, 'Forbidden photo format. Allowed: JPG, JPEG, PNG, GIF, WEBP.');
            }
            
            // Validate MIME type
            if (!class_exists('finfo')) {
                json_response(false, 'PHP Fileinfo extension is disabled.');
            }
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $photo_mime = $finfo->file($photo_temp);
            if (!in_array($photo_mime, ['image/jpeg', 'image/pjpeg', 'image/png', 'image/x-png', 'image/gif', 'image/webp'])) {
                json_response(false, 'Security Alert: Profile photo content is not a valid image.');
            }
            
            // Enforce size limit (e.g. 5MB)
            if ($_FILES['profile_photo']['size'] > 5 * 1024 * 1024) {
                json_response(false, 'Profile photo exceeds size limit of 5MB.');
            }
            
            // Hashed name
            $stored_photo = 'avatar_' . bin2hex(random_bytes(16)) . '.' . $photo_ext;
            $photo_raw = file_get_contents($photo_temp);
            $photo_enc = encrypt_file_content($photo_raw);
            if (file_put_contents(UPLOAD_DIR . $stored_photo, $photo_enc) !== false) {
                $profile_photo = $stored_photo;
                $avatar_encrypted = 1;
            } else {
                json_response(false, 'Failed to save profile photo to disk.');
            }
        }
        
        try {
            $stmt = $pdo->prepare("INSERT INTO `customers` (customer_name, customer_email, customer_phone, sex, dob, profile_photo, address, notes, avatar_encrypted) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $phone, $sex_val, $dob_val, $profile_photo, $address, $notes, $avatar_encrypted]);
            log_action('Register Customer', "Registered customer '{$name}' successfully.");
            json_response(true, 'Customer added successfully.');
        } catch (PDOException $e) {
            if ($profile_photo) {
                @unlink(UPLOAD_DIR . $profile_photo);
            }
            json_response(false, 'Failed to add customer: ' . $e->getMessage());
        }
        break;
        
    case 'edit_customer':
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $name = trim($_POST['customer_name'] ?? '');
        $email = trim($_POST['customer_email'] ?? '');
        $phone = trim($_POST['customer_phone'] ?? '');
        $sex = trim($_POST['sex'] ?? '');
        $dob = trim($_POST['dob'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        if (!$id) {
            json_response(false, 'Invalid customer ID.');
        }
        if (empty($name)) {
            json_response(false, 'Customer name is required.');
        }
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(false, 'Please enter a valid email address.');
        }
        
        $dob_val = !empty($dob) ? $dob : null;
        $sex_val = !empty($sex) ? $sex : null;
        
        // Fetch current photo and avatar_encrypted flag
        try {
            $curr_stmt = $pdo->prepare("SELECT profile_photo, avatar_encrypted FROM `customers` WHERE id = ?");
            $curr_stmt->execute([$id]);
            $curr_cust = $curr_stmt->fetch();
            $current_photo = $curr_cust['profile_photo'] ?? null;
            $current_avatar_encrypted = $curr_cust['avatar_encrypted'] ?? 0;
        } catch (PDOException $e) {
            json_response(false, 'Database error: ' . $e->getMessage());
        }
        
        $profile_photo = $current_photo;
        $avatar_encrypted = $current_avatar_encrypted;
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $photo_temp = $_FILES['profile_photo']['tmp_name'];
            $photo_name = $_FILES['profile_photo']['name'];
            
            // Validate extension
            $photo_ext = strtolower(pathinfo($photo_name, PATHINFO_EXTENSION));
            if (!in_array($photo_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                json_response(false, 'Forbidden photo format. Allowed: JPG, JPEG, PNG, GIF, WEBP.');
            }
            
            // Validate MIME
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $photo_mime = $finfo->file($photo_temp);
            if (!in_array($photo_mime, ['image/jpeg', 'image/pjpeg', 'image/png', 'image/x-png', 'image/gif', 'image/webp'])) {
                json_response(false, 'Security Alert: Profile photo content is not a valid image.');
            }
            
            // Enforce size (5MB)
            if ($_FILES['profile_photo']['size'] > 5 * 1024 * 1024) {
                json_response(false, 'Profile photo exceeds size limit of 5MB.');
            }
            
            // Hash and save
            $stored_photo = 'avatar_' . bin2hex(random_bytes(16)) . '.' . $photo_ext;
            $photo_raw = file_get_contents($photo_temp);
            $photo_enc = encrypt_file_content($photo_raw);
            if (file_put_contents(UPLOAD_DIR . $stored_photo, $photo_enc) !== false) {
                // Delete old avatar from disk
                if ($current_photo) {
                    @unlink(UPLOAD_DIR . basename($current_photo));
                }
                $profile_photo = $stored_photo;
                $avatar_encrypted = 1;
            } else {
                json_response(false, 'Failed to save profile photo to disk.');
            }
        }
        
        try {
            $stmt = $pdo->prepare("UPDATE `customers` SET customer_name = ?, customer_email = ?, customer_phone = ?, sex = ?, dob = ?, profile_photo = ?, address = ?, notes = ?, avatar_encrypted = ? WHERE id = ?");
            $stmt->execute([$name, $email, $phone, $sex_val, $dob_val, $profile_photo, $address, $notes, $avatar_encrypted, $id]);
            log_action('Modify Customer', "Updated customer details for '{$name}' (ID: {$id}).");
            json_response(true, 'Customer updated successfully.');
        } catch (PDOException $e) {
            json_response(false, 'Failed to update customer: ' . $e->getMessage());
        }
        break;
        
    case 'delete_customer':
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            json_response(false, 'Invalid customer ID.');
        }
        
        try {
            // First, find all files for this customer to delete them from disk
            $stmt = $pdo->prepare("SELECT stored_name FROM `files` WHERE customer_id = ?");
            $stmt->execute([$id]);
            $files = $stmt->fetchAll();
            
            // Delete physical files
            foreach ($files as $file) {
                $file_path = UPLOAD_DIR . basename($file['stored_name']);
                if (file_exists($file_path)) {
                    @unlink($file_path);
                }
            }
            
            // Delete customer profile photo from disk
            $photo_stmt = $pdo->prepare("SELECT profile_photo FROM `customers` WHERE id = ?");
            $photo_stmt->execute([$id]);
            $photo = $photo_stmt->fetchColumn();
            if ($photo) {
                $photo_path = UPLOAD_DIR . basename($photo);
                if (file_exists($photo_path)) {
                    @unlink($photo_path);
                }
            }
            
            // Delete customer (cascade will delete database file records)
            $stmt = $pdo->prepare("DELETE FROM `customers` WHERE id = ?");
            $stmt->execute([$id]);
            log_action('Delete Customer', "Permanently deleted customer profile (ID: {$id}) and physical documents.");
            
            json_response(true, 'Customer and all associated documents deleted successfully.');
        } catch (PDOException $e) {
            json_response(false, 'Failed to delete customer: ' . $e->getMessage());
        }
        break;
        
    // ==========================================
    // FILE ACTIONS
    // ==========================================
    
    case 'get_files':
        $customer_id = filter_input(INPUT_GET, 'customer_id', FILTER_VALIDATE_INT);
        if (!$customer_id) {
            json_response(false, 'Invalid customer ID.');
        }
        
        $search = trim($_GET['search'] ?? '');
        $type = trim($_GET['type'] ?? 'all');
        
        try {
            $query = "SELECT * FROM `files` WHERE customer_id = ?";
            $params = [$customer_id];
            
            if ($type !== 'all' && in_array($type, ['pdf', 'image', 'document'])) {
                $query .= " AND file_type = ?";
                $params[] = $type;
            }
            
            if ($search !== '') {
                $query .= " AND (original_name LIKE ? OR notes LIKE ?)";
                $search_param = "%{$search}%";
                $params[] = $search_param;
                $params[] = $search_param;
            }
            
            $query .= " ORDER BY uploaded_at DESC";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $files = $stmt->fetchAll();
            
            // Add human readable sizes and preview tokens
            foreach ($files as &$file) {
                $file['formatted_size'] = format_size($file['file_size']);
                $file['token'] = hash_hmac('sha256', $file['id'] . $file['stored_name'], DB_PASS);
            }
            
            json_response(true, 'Files retrieved successfully.', ['files' => $files]);
        } catch (PDOException $e) {
            json_response(false, 'Failed to retrieve files: ' . $e->getMessage());
        }
        break;
        
    case 'upload_file':
        $customer_id = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT);
        $notes = trim($_POST['file_notes'] ?? '');
        
        if (!$customer_id) {
            json_response(false, 'Invalid customer ID target.');
        }
        
        // Check if customer exists
        try {
            $check = $pdo->prepare("SELECT id FROM `customers` WHERE id = ?");
            $check->execute([$customer_id]);
            if (!$check->fetch()) {
                json_response(false, 'Target customer does not exist.');
            }
        } catch (PDOException $e) {
            json_response(false, 'Database error during customer check: ' . $e->getMessage());
        }
        
        // Check if file was uploaded
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $error_code = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
            $errors = [
                UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
                UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
                UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.'
            ];
            $msg = $errors[$error_code] ?? 'Unknown upload error.';
            json_response(false, $msg);
        }
        
        $temp_path = $_FILES['file']['tmp_name'];
        $original_name = $_FILES['file']['name'];
        $file_size = $_FILES['file']['size'];
        
        // 1. Enforce Max File Size (e.g. 10MB)
        $max_size = 10 * 1024 * 1024; // 10MB
        if ($file_size > $max_size) {
            json_response(false, 'File size exceeds maximum limit of 10MB.');
        }
        
        // 2. Validate File Extension (Whitelist)
        $path_info = pathinfo($original_name);
        $extension = isset($path_info['extension']) ? strtolower($path_info['extension']) : '';
        $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'];
        
        if (!in_array($extension, $allowed_extensions)) {
            json_response(false, 'Forbidden file extension. Allowed: PDF, JPG, JPEG, PNG, GIF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT.');
        }
        
        // 3. Verify MIME Type (Double-layered safety)
        if (!class_exists('finfo')) {
            json_response(false, 'PHP Fileinfo extension is not enabled on this server.');
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($temp_path);
        
        $allowed_mimes = [
            'application/pdf',
            'image/jpeg',
            'image/pjpeg',
            'image/png',
            'image/x-png',
            'image/gif',
            'image/webp',
            'application/msword',
            'application/vnd.ms-word',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            // Office application fallbacks
            'application/vnd.ms-office',
            'application/msexcel',
            'application/x-msexcel',
            'application/x-ms-excel',
            'application/x-excel',
            'application/xls',
            'application/x-xls',
            // ZIP archive type (finfo frequently reports xlsx, docx, pptx as zip)
            'application/zip',
            'application/x-zip-compressed',
            // Fallback for generic binary data
            'application/octet-stream',
            // Common portal spreadsheet export fallbacks (HTML tables, XML spreadsheets, CSVs, and RTF text)
            'text/html',
            'text/xml',
            'application/xml',
            'text/csv',
            'application/rtf',
            'text/rtf'
        ];
        
        if (!in_array($mime_type, $allowed_mimes)) {
            json_response(false, 'Security Alert: File contents do not match its extension (MIME type mismatch: detected "' . $mime_type . '"). Upload rejected.');
        }
        
        // 4. Rename File to unique SHA-256 hash to prevent path traversal & remote execution
        $random_hash = bin2hex(random_bytes(32));
        // Storing without extension makes it non-executable even if RCE is attempted
        $stored_name = $random_hash;
        $dest_path = UPLOAD_DIR . $stored_name;
        
        // Classify broad file type for UI icons
        $file_type = 'document';
        if (strpos($mime_type, 'image/') === 0) {
            $file_type = 'image';
        } elseif ($mime_type === 'application/pdf') {
            $file_type = 'pdf';
        }
        
        // Encrypt and write to disk
        $file_raw = file_get_contents($temp_path);
        $file_enc = encrypt_file_content($file_raw);
        if (file_put_contents($dest_path, $file_enc) === false) {
            json_response(false, 'Failed to save file to secure storage. Check permissions.');
        }
        
        try {
            $stmt = $pdo->prepare("INSERT INTO `files` (customer_id, original_name, stored_name, file_type, mime_type, file_size, notes, is_encrypted) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$customer_id, $original_name, $stored_name, $file_type, $mime_type, $file_size, $notes, 1]);
            log_action('Upload File', "Uploaded and secured file '{$original_name}' (size: " . format_size($file_size) . ") for customer ID {$customer_id}.");
            json_response(true, 'File uploaded and secured successfully.');
        } catch (PDOException $e) {
            // Cleanup file on database error
            @unlink($dest_path);
            json_response(false, 'Failed to record file metadata in database: ' . $e->getMessage());
        }
        break;
        
    case 'delete_file':
        $file_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$file_id) {
            json_response(false, 'Invalid file ID.');
        }
        
        try {
            // Find file metadata
            $stmt = $pdo->prepare("SELECT stored_name FROM `files` WHERE id = ?");
            $stmt->execute([$file_id]);
            $file = $stmt->fetch();
            
            if (!$file) {
                json_response(false, 'File not found in database.');
            }
            
            // Delete physical file from disk
            $file_path = UPLOAD_DIR . basename($file['stored_name']);
            if (file_exists($file_path)) {
                if (!@unlink($file_path)) {
                    // Log error or continue
                }
            }
            
            // Delete database record
            $stmt = $pdo->prepare("DELETE FROM `files` WHERE id = ?");
            $stmt->execute([$file_id]);
            log_action('Delete File', "Permanently deleted file ID {$file_id} from storage.");
            
            json_response(true, 'Document permanently deleted.');
        } catch (PDOException $e) {
            json_response(false, 'Database error: ' . $e->getMessage());
        }
        break;

    case 'rename_file':
        $file_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $new_name = trim($_POST['new_name'] ?? '');
        
        if (!$file_id) {
            json_response(false, 'Invalid file ID.');
        }
        if ($new_name === '') {
            json_response(false, 'File name cannot be empty.');
        }
        
        try {
            // Find existing file record
            $stmt = $pdo->prepare("SELECT original_name FROM `files` WHERE id = ?");
            $stmt->execute([$file_id]);
            $original_name = $stmt->fetchColumn();
            
            if (!$original_name) {
                json_response(false, 'File not found in database.');
            }
            
            // Extract original extension
            $path_info = pathinfo($original_name);
            $extension = $path_info['extension'] ?? '';
            
            // Sanitize new filename: remove invalid characters (\ / ? : * " < > |)
            $sanitized_name = preg_replace('/[\\\\\/*?"<>|]/', '', $new_name);
            // Remove control characters and traversal sequences
            $sanitized_name = str_replace(['..', "\0"], '', $sanitized_name);
            $sanitized_name = trim($sanitized_name);
            
            if ($sanitized_name === '') {
                json_response(false, 'Invalid file name characters.');
            }
            
            // Reconstruct full filename with extension
            $final_name = $sanitized_name;
            if ($extension !== '') {
                $final_name .= '.' . $extension;
            }
            
            // Update database
            $stmt = $pdo->prepare("UPDATE `files` SET original_name = ? WHERE id = ?");
            $stmt->execute([$final_name, $file_id]);
            log_action('Rename File', "Renamed file ID {$file_id} to '{$final_name}' (previously '{$original_name}').");
            
            json_response(true, 'File renamed successfully.', ['new_name' => $final_name]);
        } catch (PDOException $e) {
            json_response(false, 'Failed to rename file: ' . $e->getMessage());
        }
        break;

    // ==========================================
    // STATISTICS ACTIONS
    // ==========================================
    
    case 'get_stats':
        try {
            // Total customers
            $total_cust = $pdo->query("SELECT COUNT(*) FROM `customers`")->fetchColumn();
            
            // Total files
            $total_files = $pdo->query("SELECT COUNT(*) FROM `files`")->fetchColumn();
            
            // Total storage space
            $total_bytes = $pdo->query("SELECT SUM(file_size) FROM `files`")->fetchColumn() ?? 0;
            $formatted_bytes = format_size($total_bytes);
            
            // Group by file type counts
            $type_counts = $pdo->query("SELECT file_type, COUNT(*) as count FROM `files` GROUP BY file_type")->fetchAll();
            $types = ['image' => 0, 'pdf' => 0, 'document' => 0];
            foreach ($type_counts as $tc) {
                $types[$tc['file_type']] = (int)$tc['count'];
            }
            
            json_response(true, 'Statistics compiled.', [
                'stats' => [
                    'customers' => (int)$total_cust,
                    'files' => (int)$total_files,
                    'storage' => $formatted_bytes,
                    'storage_bytes' => (int)$total_bytes,
                    'types' => $types
                ]
            ]);
        } catch (PDOException $e) {
            json_response(false, 'Failed to gather statistics: ' . $e->getMessage());
        }
        break;
        
    case 'change_password':
        $current_pwd = $_POST['current_password'] ?? '';
        $new_pwd = $_POST['new_password'] ?? '';
        $confirm_pwd = $_POST['confirm_password'] ?? '';
        
        if (empty($current_pwd) || empty($new_pwd) || empty($confirm_pwd)) {
            json_response(false, 'All password fields are required.');
        }
        
        if (strlen($new_pwd) < 6) {
            json_response(false, 'New password must be at least 6 characters long.');
        }
        
        if ($new_pwd !== $confirm_pwd) {
            json_response(false, 'New passwords do not match.');
        }
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM `admins` WHERE `id` = ?");
            $stmt->execute([$_SESSION['admin_id']]);
            $admin = $stmt->fetch();
            
            if (!$admin || !password_verify($current_pwd, $admin['password_hash'])) {
                json_response(false, 'Current password is incorrect.');
            }
            
            $new_hash = password_hash($new_pwd, PASSWORD_BCRYPT);
            
            $update = $pdo->prepare("UPDATE `admins` SET `password_hash` = ? WHERE `id` = ?");
            $update->execute([$new_hash, $_SESSION['admin_id']]);
            
            log_action('Change Password', "Administrator '{$_SESSION['admin_username']}' successfully changed their password.");
            
            json_response(true, 'Password updated successfully.');
        } catch (PDOException $e) {
            json_response(false, 'Failed to update password: ' . $e->getMessage());
        }
        break;
        
    default:
        json_response(false, 'Invalid API endpoint action requested.');
        break;
}
