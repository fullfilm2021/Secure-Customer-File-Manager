<?php
require_once 'config.php';

// Enforce admin login check
require_login();

// Generate token for forms and AJAX headers
$csrf_token = get_csrf_token();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">

<head>
    <script>
        (function () {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Secure Customer File Manager</title>
    <!-- CSRF Token Meta -->
    <meta name="csrf-token" content="<?= h($csrf_token) ?>">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <!-- Google Fonts - Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Custom Style Sheet -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

    <!-- Nav Bar -->
    <nav class="navbar navbar-expand navbar-dark navbar-custom sticky-top">
        <div class="container-fluid px-3">
            <div class="d-flex align-items-center gap-2">
                <!-- Mobile Sidebar Toggle -->
                <button id="mobileSidebarToggle"
                    class="btn btn-sm btn-glass text-white d-lg-none d-flex align-items-center justify-content-center"
                    title="Toggle Customers List" style="width: 32px; height: 32px; padding: 0;">
                    <i class="fa-solid fa-bars" style="font-size: 18px;"></i>
                </button>
                <span class="navbar-brand d-flex align-items-center gap-2 m-0">
                    <i class="fa-solid fa-shield-halved text-indigo fs-4"></i>
                    <span class="d-none d-sm-inline">Avinandan File Manager</span>
                    <span class="d-inline d-sm-none">File Manager</span>
                </span>
            </div>
            <div class="ms-auto d-flex align-items-center gap-3">
                <!-- Theme Toggle Button -->
                <button id="themeToggleBtn"
                    class="btn btn-sm btn-glass text-warning d-flex align-items-center justify-content-center"
                    title="Toggle Light/Dark Theme" style="width: 32px; height: 32px; padding: 0;">
                    <i class="fa-solid fa-sun" id="themeToggleIcon" style="font-size: 16px;"></i>
                </button>
                <span class="text-secondary small d-none d-sm-inline">
                    <i class="fa-solid fa-circle-user me-1"></i>Logged as: <strong
                        class="text-white"><?= h($_SESSION['admin_name']) ?></strong>
                </span>
                <!-- Settings Button -->
                <button id="settingsBtn"
                    class="btn btn-sm btn-glass text-info d-flex align-items-center justify-content-center"
                    title="Change Password" data-bs-toggle="modal" data-bs-target="#changePasswordModal" style="width: 32px; height: 32px; padding: 0;">
                    <i class="fa-solid fa-gear" style="font-size: 16px;"></i>
                </button>
                <a href="logout.php" class="btn btn-sm btn-glass text-danger">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </a>
            </div>
        </div>
    </nav>

    <!-- Sidebar Backdrop for Mobile -->
    <div id="sidebarBackdrop" class="sidebar-backdrop"></div>

    <!-- Main Container -->
    <div class="container-fluid g-0">
        <div class="row g-0">

            <!-- Left Sidebar: Customer Directory -->
            <div class="col-lg-4 col-xl-3 sidebar-panel p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="m-0 fw-600 text-white">Customers</h5>
                    <button class="btn btn-sm btn-indigo px-3" data-bs-toggle="modal"
                        data-bs-target="#addCustomerModal">
                        <i class="fa-solid fa-user-plus me-1"></i>Add
                    </button>
                </div>

                <!-- Search -->
                <div class="mb-3">
                    <div class="input-group"
                        style="background: var(--bg-input); border: 1px solid var(--border-glass); border-radius: 20px; overflow: hidden; transition: var(--transition-smooth);">
                        <span class="input-group-text bg-transparent border-0 text-muted"><i
                                class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" id="searchCustomer"
                            class="form-control bg-transparent border-0 text-white shadow-none ps-0"
                            placeholder="Search customers..." style="font-size: 14px;">
                    </div>
                </div>

                <!-- Customer List -->
                <div id="customerList" class="customer-list-container">
                    <!-- Loaded dynamically via AJAX -->
                    <div class="text-center p-4">
                        <div class="spinner-border text-primary spinner-border-sm" role="status"></div>
                    </div>
                </div>
            </div>

            <!-- Right Panel: Work Space -->
            <div class="col-lg-8 col-xl-9 main-panel">

                <!-- Empty Dashboard State / Welcome View -->
                <!-- Empty Dashboard State / Welcome View -->
                <div id="emptyDashboardState" class="fade show">
                    <!-- Hero Welcome Banner -->
                    <div class="glass-card welcome-hero-card p-4 p-md-5 mb-4 position-relative overflow-hidden">
                        <!-- Glowing decorative background blur -->
                        <div class="welcome-hero-glow"></div>
                        
                        <div class="position-relative z-1">
                            <span class="badge welcome-badge mb-3"><i class="fa-solid fa-circle-check text-emerald me-1"></i> System Active</span>
                            <h1 class="welcome-title fw-800 mb-2">Welcome to Secure File Panel</h1>
                            <p class="welcome-text text-secondary col-lg-9 fs-5 leading-relaxed">
                                A high-security repository for Voter IDs, Aadhaar documents, and official scans, featuring <strong class="text-white">AES-256 Encryption at Rest</strong>. Select a customer from the directory sidebar to start managing files, uploading documents, or updating profiles.
                            </p>
                        </div>

                        <!-- Mini Security Info Grid -->
                        <div class="row mt-4 pt-2 g-3 position-relative z-1">
                            <div class="col-md-4">
                                <div class="security-card p-3 rounded h-100">
                                    <div class="security-card-icon-wrapper danger-glow">
                                        <i class="fa-solid fa-shield-halved text-rose"></i>
                                    </div>
                                    <h6 class="text-white fw-600 mt-3 mb-2">Zero Direct Access</h6>
                                    <p class="small text-muted mb-0">Files reside in a private directory guarded by server rules. Direct HTTP links are blocked entirely.</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="security-card p-3 rounded h-100">
                                    <div class="security-card-icon-wrapper indigo-glow">
                                        <i class="fa-solid fa-lock text-indigo"></i>
                                    </div>
                                    <h6 class="text-white fw-600 mt-3 mb-2">AES-256 Encryption</h6>
                                    <p class="small text-muted mb-0">All files and profile photos are physically encrypted at rest on server disk using AES-256-CBC with secure randomized IV keys.</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="security-card p-3 rounded h-100">
                                    <div class="security-card-icon-wrapper success-glow">
                                        <i class="fa-solid fa-list-check text-emerald"></i>
                                    </div>
                                    <h6 class="text-white fw-600 mt-3 mb-2">Audit Logging</h6>
                                    <p class="small text-muted mb-0">All events are monitored and stored securely in the database log for tracking and audit trials.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Stats Header -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-700 text-white m-0 fs-6 uppercase tracking-wider text-muted-custom">Storage & Overview Statistics</h5>
                        <span class="badge bg-secondary-custom"><i class="fa-solid fa-chart-pie me-1"></i> Real-time</span>
                    </div>

                    <!-- Stats Cards Grid -->
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-4">
                            <div class="glass-card stat-card-premium p-4 h-100">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <span class="text-muted fw-500 fs-7">Active Directory</span>
                                    <div class="stat-icon-wrapper indigo">
                                        <i class="fa-solid fa-users"></i>
                                    </div>
                                </div>
                                <h3 id="statTotalCustomers" class="fw-800 m-0 text-white">0</h3>
                                <div class="text-secondary small mt-2 d-flex align-items-center gap-1 flex-wrap">
                                    <span class="text-emerald fw-600"><i class="fa-solid fa-arrow-trend-up"></i> Registered</span>
                                    <span>customers profiles</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="glass-card stat-card-premium p-4 h-100">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <span class="text-muted fw-500 fs-7">Secured Documents</span>
                                    <div class="stat-icon-wrapper violet">
                                        <i class="fa-solid fa-shield-halved"></i>
                                    </div>
                                </div>
                                <h3 id="statTotalFiles" class="fw-800 m-0 text-white">0</h3>
                                <div class="text-secondary small mt-2 d-flex align-items-center gap-1 flex-wrap">
                                    <span class="text-indigo fw-600"><i class="fa-solid fa-lock"></i> Encrypted</span>
                                    <span>physical assets</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="glass-card stat-card-premium p-4 h-100">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <span class="text-muted fw-500 fs-7">Vault Storage</span>
                                    <div class="stat-icon-wrapper emerald">
                                        <i class="fa-solid fa-hard-drive"></i>
                                    </div>
                                </div>
                                <h3 id="statStorageSize" class="fw-800 m-0 text-white">0 B</h3>
                                <div class="text-secondary small mt-2 d-flex align-items-center gap-1 flex-wrap">
                                    <span class="text-emerald fw-600"><i class="fa-solid fa-server"></i> Active Disk</span>
                                    <span>allocation size</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Distribution visual breakdown -->
                    <div class="glass-card p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="fw-700 text-white m-0 fs-6">Document Breakdown by Format</h6>
                            <span class="text-muted small">Vault Distribution</span>
                        </div>

                        <!-- Progress Bars -->
                        <div class="stat-progress-item mb-4">
                            <div class="d-flex justify-content-between text-secondary small mb-2">
                                <span class="d-flex align-items-center"><i class="fa-solid fa-file-pdf text-rose me-2 fs-5"></i>PDF Vault</span>
                                <span class="progress-percentage text-white fw-600">0 files (0%)</span>
                            </div>
                            <div class="progress-premium" style="height: 8px;">
                                <div id="typeBarPdf" class="progress-bar-premium bg-gradient-pdf" role="progressbar" style="width: 0%"
                                    aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>

                        <div class="stat-progress-item mb-4">
                            <div class="d-flex justify-content-between text-secondary small mb-2">
                                <span class="d-flex align-items-center"><i class="fa-solid fa-file-image text-emerald me-2 fs-5"></i>Image & Photo Scans</span>
                                <span class="progress-percentage text-white fw-600">0 files (0%)</span>
                            </div>
                            <div class="progress-premium" style="height: 8px;">
                                <div id="typeBarImage" class="progress-bar-premium bg-gradient-image" role="progressbar"
                                    style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>

                        <div class="stat-progress-item mb-0">
                            <div class="d-flex justify-content-between text-secondary small mb-2">
                                <span class="d-flex align-items-center"><i class="fa-solid fa-file-lines text-indigo me-2 fs-5"></i>MS Office Docs & Texts</span>
                                <span class="progress-percentage text-white fw-600">0 files (0%)</span>
                            </div>
                            <div class="progress-premium" style="height: 8px;">
                                <div id="typeBarDoc" class="progress-bar-premium bg-gradient-doc" role="progressbar"
                                    style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer Manager Area (Shown when customer selected) -->
                <div id="customerManagerPanel" style="display: none;">

                    <!-- Customer Header Details Card -->
                    <div class="glass-card premium-workspace-header p-4 mb-3 position-relative overflow-hidden">
                        <!-- Subtle Background Glow Orb -->
                        <div class="premium-header-glow"></div>

                        <div class="d-flex flex-column gap-3 position-relative" style="z-index: 1;">
                            <!-- Top Row: Avatar, Name, Status Badge, Edit Actions -->
                            <div
                                class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 pb-3 border-bottom border-secondary border-opacity-10">
                                <div class="d-flex align-items-center gap-3">
                                    <!-- Avatar Frame with Status Indicator (Double click to upload) -->
                                    <div class="avatar-frame-container position-relative flex-shrink-0" title="Double click to upload new photo">
                                        <img id="infoPhoto" src="download.php?type=avatar"
                                            class="rounded-circle border border-2 border-indigo shadow-lg avatar-img-large"
                                            alt="Profile"
                                            style="width: 60px; height: 60px; object-fit: cover; background: rgba(0,0,0,0.3);">
                                        <span class="status-pulse-dot"></span>
                                    </div>

                                    <div class="overflow-hidden">
                                        <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                                            <span class="workspace-active-badge">
                                                <span class="pulse-indicator-dot"></span>Active Workspace
                                            </span>
                                        </div>
                                        <h3 id="displayCustomerName" data-field="customer_name"
                                            class="fw-700 text-white mb-0 text-break header-customer-title clickable-inline">Customer
                                            Profile</h3>
                                    </div>
                                </div>

                                <!-- Quick Actions -->
                                <div
                                    class="d-flex gap-2 align-self-stretch align-self-md-center justify-content-end ms-md-auto">
                                    <button
                                        class="btn btn-sm btn-glass-premium text-white d-flex align-items-center justify-content-center gap-1.5 px-3 py-2"
                                        data-bs-toggle="modal" data-bs-target="#editCustomerModal" title="Edit Profile">
                                        <i class="fa-solid fa-pen-to-square" style="font-size: 14px;"></i>
                                    </button>
                                    <button onclick="confirmDeleteCustomer()"
                                        class="btn btn-sm btn-glass-premium-danger text-danger d-flex align-items-center justify-content-center gap-1.5 px-3 py-2"
                                        title="Delete Profile">
                                        <i class="fa-solid fa-user-minus" style="font-size: 14px;"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Middle Row: Customer Details Grid (Phone, Email, Gender, DOB) -->
                            <div class="row g-2.5">
                                <div class="col-12 col-sm-6 col-md-3">
                                    <div class="detail-card-premium h-100">
                                        <div class="detail-card-icon text-indigo"><i class="fa-solid fa-envelope"></i>
                                        </div>
                                        <div class="detail-card-info overflow-hidden">
                                            <div class="detail-card-label">Email Address</div>
                                            <div id="infoEmail" data-field="customer_email" class="detail-card-value text-white text-truncate clickable-inline">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-3">
                                    <div class="detail-card-premium h-100">
                                        <div class="detail-card-icon text-indigo"><i class="fa-solid fa-phone"></i>
                                        </div>
                                        <div class="detail-card-info overflow-hidden">
                                            <div class="detail-card-label">Phone Number</div>
                                            <div id="infoPhone" data-field="customer_phone" class="detail-card-value text-white text-truncate clickable-inline">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-3">
                                    <div class="detail-card-premium h-100">
                                        <div class="detail-card-icon text-indigo"><i class="fa-solid fa-venus-mars"></i>
                                        </div>
                                        <div class="detail-card-info overflow-hidden">
                                            <div class="detail-card-label">Gender</div>
                                            <div id="infoSex" data-field="sex" class="detail-card-value text-white text-truncate clickable-inline"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-3">
                                    <div class="detail-card-premium h-100">
                                        <div class="detail-card-icon text-indigo"><i class="fa-solid fa-calendar-days"></i></div>
                                        <div class="detail-card-info overflow-hidden">
                                            <div class="detail-card-label">Date of Birth</div>
                                            <div id="infoDob" data-field="dob" class="detail-card-value text-white text-truncate clickable-inline"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Bottom Row: Address and Notes -->
                            <div class="row g-2.5">
                                <div class="col-12 col-md-6">
                                    <div class="note-box-premium h-100">
                                        <div class="note-box-title text-indigo"><i
                                                class="fa-solid fa-location-dot me-1.5"></i>Residential Address</div>
                                        <div id="infoAddress" data-field="address" class="note-box-content text-secondary clickable-inline"></div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="note-box-premium h-100">
                                        <div class="note-box-title text-indigo"><i
                                                class="fa-solid fa-note-sticky me-1.5"></i>Internal Notes</div>
                                        <div id="infoNotes" data-field="notes" class="note-box-content text-secondary clickable-inline"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- Drag and Drop file upload zone -->
                    <div class="glass-card premium-upload-card p-4 mb-3 position-relative overflow-hidden">
                        <div class="premium-header-glow"></div>
                        
                        <div class="position-relative" style="z-index: 1;">
                            <h6 class="fw-600 text-white mb-3 fs-6 d-flex align-items-center">
                                <i class="fa-solid fa-cloud-arrow-up me-2 text-indigo"></i>Upload Secured Documents
                            </h6>

                            <div id="dropzone" class="premium-dropzone p-4">
                                <input type="file" id="fileInput" class="d-none" multiple
                                    accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt">
                                <div class="dropzone-icon-container mb-2">
                                    <i class="fa-solid fa-cloud-arrow-up text-indigo dropzone-icon"></i>
                                </div>
                                <h6 class="text-white fw-600 mb-1">Drag & drop files here or click to browse</h6>
                                
                                <!-- Supported File Formats Micro-Badges -->
                                <div class="d-flex justify-content-center gap-1.5 flex-wrap my-3">
                                    <span class="format-badge pdf"><i class="fa-solid fa-file-pdf me-1"></i>PDF</span>
                                    <span class="format-badge image"><i class="fa-solid fa-file-image me-1"></i>Images</span>
                                    <span class="format-badge word"><i class="fa-solid fa-file-word me-1"></i>Word</span>
                                    <span class="format-badge text"><i class="fa-solid fa-file-code me-1"></i>Text</span>
                                </div>

                                <!-- Custom Notes block on Upload -->
                                <div class="row justify-content-center">
                                    <div class="col-md-8 col-lg-6">
                                        <div class="input-group notes-input-group-premium">
                                            <span class="input-group-text bg-transparent border-0 text-muted py-1.5 px-2.5"><i class="fa-solid fa-tag text-indigo"></i></span>
                                            <input type="text" id="uploadNotes"
                                                class="form-control bg-transparent border-0 text-white shadow-none py-1.5 ps-0"
                                                placeholder="Optional document tag (e.g. Aadhaar Card Scan)" style="font-size: 13px;">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Upload progress indicator -->
                            <div id="progressContainer" class="upload-progress-container mt-3">
                                <div class="d-flex justify-content-between text-secondary small mb-1">
                                    <span class="fw-500">Securing and hashing upload...</span>
                                    <span id="progressPercent" class="fw-600 text-indigo">0%</span>
                                </div>
                                <div class="progress progress-premium bg-dark" style="height: 6px; border-radius: 4px;">
                                    <div id="progressBar" class="progress-bar-custom" style="width: 0%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Customer's Document Vault list and toolbar controls -->
                    <div class="vault-toolbar-premium d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3 p-3 glass-card">
                        <h5 class="fw-600 text-white m-0 text-nowrap d-flex align-items-center fs-6">
                            <i class="fa-solid fa-folder-open me-2 text-indigo"></i>
                            <span>Document Vault</span>
                        </h5>

                        <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-2 flex-grow-1 justify-content-md-end w-100">
                            <!-- File Type Filter pills/buttons -->
                            <div class="d-flex gap-1 file-type-filter-group flex-wrap" role="group" aria-label="File Type Filter">
                                <button type="button" class="btn btn-filter-pill active filter-type-btn" data-type="all">All</button>
                                <button type="button" class="btn btn-filter-pill filter-type-btn" data-type="pdf"><i class="fa-solid fa-file-pdf text-danger me-1"></i>PDFs</button>
                                <button type="button" class="btn btn-filter-pill filter-type-btn" data-type="image"><i class="fa-solid fa-file-image text-emerald me-1"></i>Images</button>
                                <button type="button" class="btn btn-filter-pill filter-type-btn" data-type="document"><i class="fa-solid fa-file-lines text-indigo me-1"></i>Docs</button>
                            </div>

                            <!-- Search Files Input -->
                            <div class="input-group vault-search-box-premium">
                                <span class="input-group-text bg-transparent border-0 text-muted py-0 px-2 d-flex align-items-center"><i class="fa-solid fa-magnifying-glass" style="font-size: 12px;"></i></span>
                                <input type="text" id="searchFile"
                                    class="form-control bg-transparent border-0 text-white shadow-none ps-0 py-0"
                                    placeholder="Search vault..." style="font-size: 12px; height: 30px;">
                                <button type="button" class="btn btn-link text-muted border-0 d-none"
                                    id="clearFileSearch" style="padding: 0 8px; font-size: 12px;" title="Clear Search">
                                    <i class="fa-solid fa-circle-xmark"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div id="filesList">
                        <!-- Loaded dynamically via AJAX -->
                    </div>

                </div>
            </div>

        </div>
    </div>

    <!-- ==========================================
    MODALS CONTAINER
    ========================================== -->

    <!-- Add Customer Modal -->
    <div class="modal fade" id="addCustomerModal" tabindex="-1" aria-labelledby="addCustomerModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-content-custom">
                <div class="modal-header">
                    <h5 class="modal-title fw-600" id="addCustomerModalLabel"><i
                            class="fa-solid fa-user-plus text-indigo me-2"></i>Register New Customer</h5>
                    <button type="button" class="btn-close-custom" data-bs-dismiss="modal" aria-label="Close"><i
                            class="fa-solid fa-xmark"></i></button>
                </div>
                <form id="addCustomerForm">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label for="customer_name" class="form-label text-secondary small fw-500">Customer Full Name
                                <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-custom" id="customer_name"
                                name="customer_name" required placeholder="Enter customer name">
                        </div>
                        <div class="mb-3">
                            <label for="customer_email" class="form-label text-secondary small fw-500">Email Address
                                (Optional)</label>
                            <input type="email" class="form-control form-control-custom" id="customer_email"
                                name="customer_email" placeholder="customer@example.com">
                        </div>
                        <div class="mb-3">
                            <label for="customer_phone" class="form-label text-secondary small fw-500">Phone Number
                                (Optional)</label>
                            <input type="text" class="form-control form-control-custom" id="customer_phone"
                                name="customer_phone" placeholder="Enter contact number">
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="sex" class="form-label text-secondary small fw-500">Sex (Optional)</label>
                                <select class="form-select form-control-custom" id="sex" name="sex">
                                    <option value="">Select Sex</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="dob" class="form-label text-secondary small fw-500">Date of Birth
                                    (Optional)</label>
                                <input type="date" class="form-control form-control-custom" id="dob" name="dob">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="profile_photo" class="form-label text-secondary small fw-500">Profile Photo
                                (Optional)</label>
                            <input type="file" class="form-control form-control-custom" id="profile_photo"
                                name="profile_photo" accept="image/*">
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label text-secondary small fw-500">Residential Address
                                (Optional)</label>
                            <textarea class="form-control form-control-custom" id="address" name="address" rows="2"
                                placeholder="Enter customer address"></textarea>
                        </div>
                        <div class="mb-0">
                            <label for="notes" class="form-label text-secondary small fw-500">Additional Notes
                                (Optional)</label>
                            <textarea class="form-control form-control-custom" id="notes" name="notes" rows="3"
                                placeholder="Enter registration notes (e.g. document expectations)"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-glass" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-indigo px-4">Register Customer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Customer Modal -->
    <div class="modal fade" id="editCustomerModal" tabindex="-1" aria-labelledby="editCustomerModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-content-custom">
                <div class="modal-header">
                    <h5 class="modal-title fw-600" id="editCustomerModalLabel"><i
                            class="fa-solid fa-pen-to-square text-indigo me-2"></i>Modify Customer Details</h5>
                    <button type="button" class="btn-close-custom" data-bs-dismiss="modal" aria-label="Close"><i
                            class="fa-solid fa-xmark"></i></button>
                </div>
                <form id="editCustomerForm">
                    <input type="hidden" id="editCustomerId" name="id">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label for="editCustomerName" class="form-label text-secondary small fw-500">Customer Full
                                Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-custom" id="editCustomerName"
                                name="customer_name" required placeholder="Enter customer name">
                        </div>
                        <div class="mb-3">
                            <label for="editCustomerEmail" class="form-label text-secondary small fw-500">Email
                                Address</label>
                            <input type="email" class="form-control form-control-custom" id="editCustomerEmail"
                                name="customer_email" placeholder="customer@example.com">
                        </div>
                        <div class="mb-3">
                            <label for="editCustomerPhone" class="form-label text-secondary small fw-500">Phone
                                Number</label>
                            <input type="text" class="form-control form-control-custom" id="editCustomerPhone"
                                name="customer_phone" placeholder="Enter contact number">
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editSex" class="form-label text-secondary small fw-500">Sex</label>
                                <select class="form-select form-control-custom" id="editSex" name="sex">
                                    <option value="">Select Sex</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="editDob" class="form-label text-secondary small fw-500">Date of
                                    Birth</label>
                                <input type="date" class="form-control form-control-custom" id="editDob" name="dob">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="editProfilePhoto" class="form-label text-secondary small fw-500">Profile Photo
                                (Upload new to replace)</label>
                            <input type="file" class="form-control form-control-custom" id="editProfilePhoto"
                                name="profile_photo" accept="image/*">
                        </div>
                        <div class="mb-3">
                            <label for="editAddress" class="form-label text-secondary small fw-500">Residential
                                Address</label>
                            <textarea class="form-control form-control-custom" id="editAddress" name="address" rows="2"
                                placeholder="Enter customer address"></textarea>
                        </div>
                        <div class="mb-0">
                            <label for="editNotes" class="form-label text-secondary small fw-500">Additional
                                Notes</label>
                            <textarea class="form-control form-control-custom" id="editNotes" name="notes" rows="3"
                                placeholder="Enter customer description"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-glass" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-indigo px-4">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Premium Full-Screen Lightbox Preview -->
    <div id="lightboxOverlay" class="lightbox-overlay d-none">
        <!-- Lightbox Header Toolbar -->
        <div class="lightbox-toolbar d-flex align-items-center justify-content-between px-3 py-2">
            <div class="d-flex align-items-center gap-2 text-white overflow-hidden">
                <i class="fa-solid fa-shield-halved text-indigo fs-5 flex-shrink-0"></i>
                <span class="d-none d-sm-inline fw-500 flex-shrink-0" style="font-size: 14px; white-space: nowrap;">Secure File Preview -</span>
                <span id="lightboxTitle" class="fw-500 text-truncate"
                    style="max-width: 250px; font-size: 14px;">Document Preview</span>
            </div>

            <!-- Dynamic Toolbar Controls -->
            <div class="d-flex align-items-center gap-2" id="lightboxControls">
                <!-- Image-specific controls -->
                <div id="imageControlGroup" class="d-none d-flex align-items-center gap-1">
                    <button type="button" class="btn btn-sm btn-glass text-white border-0" id="zoomOutBtn"
                        title="Zoom Out"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
                    <span id="zoomPercent" class="text-secondary small mx-1"
                        style="font-size: 11px; min-width: 32px; text-align: center;">100%</span>
                    <button type="button" class="btn btn-sm btn-glass text-white border-0" id="zoomInBtn"
                        title="Zoom In"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
                    <div class="vr bg-secondary opacity-30 mx-2" style="height: 16px;"></div>
                    <button type="button" class="btn btn-sm btn-glass text-white border-0" id="rotateCcwBtn"
                        title="Rotate Counterclockwise"><i class="fa-solid fa-rotate-left"></i></button>
                    <button type="button" class="btn btn-sm btn-glass text-white border-0" id="rotateCwBtn"
                        title="Rotate Clockwise"><i class="fa-solid fa-rotate-right"></i></button>
                </div>

                <div class="vr bg-secondary opacity-30 mx-2" id="controlSeparator" style="height: 16px;"></div>

                <!-- General actions -->
                <button type="button" class="btn btn-sm btn-glass text-white border-0" id="lightboxPrintBtn"
                    title="Print Document"><i class="fa-solid fa-print"></i></button>
                <a href="#" class="btn btn-sm btn-glass text-white border-0" id="lightboxDownloadBtn" download
                    title="Download Securely"><i class="fa-solid fa-download"></i></a>
                <button type="button" class="btn btn-sm btn-glass text-white border-0 ms-2" id="lightboxCloseBtn"
                    title="Close Preview"
                    style="background: rgba(244, 63, 94, 0.15) !important; color: #fb7185 !important;"><i
                        class="fa-solid fa-xmark" style="font-size: 12px;"></i></button>
            </div>
        </div>

        <!-- Lightbox Content Viewer Area -->
        <div class="lightbox-viewer" id="lightboxViewer">
            <!-- Dynamic Preview Content -->
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-content-custom">
                <div class="modal-header">
                    <h5 class="modal-title fw-600" id="changePasswordModalLabel"><i
                            class="fa-solid fa-key text-indigo me-2"></i>Change Admin Password</h5>
                    <button type="button" class="btn-close-custom" data-bs-dismiss="modal" aria-label="Close"><i
                            class="fa-solid fa-xmark"></i></button>
                </div>
                <form id="changePasswordForm">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label for="currentPassword" class="form-label text-secondary small fw-500">Current Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control form-control-custom" id="currentPassword"
                                name="current_password" required placeholder="Enter current password">
                        </div>
                        <div class="mb-3">
                            <label for="newPassword" class="form-label text-secondary small fw-500">New Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control form-control-custom" id="newPassword"
                                name="new_password" required placeholder="Enter new password (min. 6 characters)">
                        </div>
                        <div class="mb-3">
                            <label for="confirmPassword" class="form-label text-secondary small fw-500">Confirm New Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control form-control-custom" id="confirmPassword"
                                name="confirm_password" required placeholder="Confirm new password">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-glass" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-indigo px-4">Update Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reusable Confirmation Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-content-custom">
                <div class="modal-header">
                    <h5 class="modal-title fw-600 text-white" id="confirmModalLabel"><i
                            class="fa-solid fa-triangle-exclamation text-warning me-2"></i>Confirmation Required</h5>
                    <button type="button" class="btn-close-custom" data-bs-dismiss="modal" aria-label="Close"><i
                            class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="modal-body p-4">
                    <p id="confirmModalMessage" class="m-0 text-white"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-glass" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmModalActionBtn" class="btn btn-danger px-4">Confirm Action</button>
                </div>
            </div>
        </div>
    </div>



    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz"
        crossorigin="anonymous"></script>

    <!-- Custom Application JS -->
    <script src="assets/js/app.js"></script>
    <script>
        // Init Dropzone after elements are loaded
        window.addEventListener('load', () => {
            initUploadDropzone();
        });
    </script>
</body>

</html>