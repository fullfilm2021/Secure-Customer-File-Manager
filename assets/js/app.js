// Secure File Manager Frontend Controller

let selectedCustomerId = null;
let csrfToken = '';
let currentFileSearchQuery = '';
let currentFileTypeFilter = 'all';
let zoomScale = 1.0;
let rotateAngle = 0;
let currentPreviewType = '';
let currentPreviewUrl = '';
let isPanning = false;
let startX = 0;
let startY = 0;
let panX = 0;
let panY = 0;
let uploadQueue = [];
let currentUploadIndex = 0;
let totalUploadCount = 0;

document.addEventListener('DOMContentLoaded', () => {
    // 1. Fetch CSRF Token from meta tag
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta) {
        csrfToken = csrfMeta.content;
    }

    // Sync theme toggle icon
    syncThemeToggleIcon();

    // 2. Load initial dashboard data
    loadCustomers();
    loadStats();

    // 3. Bind Event Listeners
    setupEventListeners();
});

function setupEventListeners() {
    // Theme Toggle Click Listener
    const themeToggleBtn = document.getElementById('themeToggleBtn');
    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-bs-theme') || 'dark';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-bs-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            syncThemeToggleIcon();
        });
    }

    // Search Customer input
    const searchInput = document.getElementById('searchCustomer');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                loadCustomers(e.target.value);
            }, 300);
        });
    }

    // Search File input
    const searchFileInput = document.getElementById('searchFile');
    const clearFileSearchBtn = document.getElementById('clearFileSearch');
    if (searchFileInput) {
        let fileSearchTimeout;
        searchFileInput.addEventListener('input', (e) => {
            currentFileSearchQuery = e.target.value;
            
            // Show/hide clear button
            if (clearFileSearchBtn) {
                if (currentFileSearchQuery !== '') {
                    clearFileSearchBtn.classList.remove('d-none');
                } else {
                    clearFileSearchBtn.classList.add('d-none');
                }
            }
            
            clearTimeout(fileSearchTimeout);
            fileSearchTimeout = setTimeout(() => {
                if (selectedCustomerId) {
                    loadCustomerFiles(selectedCustomerId);
                }
            }, 300);
        });
    }

    // Clear File Search button
    if (clearFileSearchBtn) {
        clearFileSearchBtn.addEventListener('click', () => {
            if (searchFileInput) {
                searchFileInput.value = '';
            }
            currentFileSearchQuery = '';
            clearFileSearchBtn.classList.add('d-none');
            if (selectedCustomerId) {
                loadCustomerFiles(selectedCustomerId);
            }
        });
    }

    // File Type Filter Buttons
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.filter-type-btn');
        if (btn) {
            e.preventDefault();
            // Remove active from peers
            const group = btn.closest('.file-type-filter-group');
            if (group) {
                group.querySelectorAll('.filter-type-btn').forEach(b => b.classList.remove('active'));
            }
            btn.classList.add('active');
            
            currentFileTypeFilter = btn.dataset.type || 'all';
            if (selectedCustomerId) {
                loadCustomerFiles(selectedCustomerId);
            }
        }
    });

    // Add Customer Form Submit
    const addCustomerForm = document.getElementById('addCustomerForm');
    if (addCustomerForm) {
        addCustomerForm.addEventListener('submit', (e) => {
            e.preventDefault();
            handleCustomerSubmit('add_customer', addCustomerForm, 'addCustomerModal');
        });
    }

    // Edit Customer Form Submit
    const editCustomerForm = document.getElementById('editCustomerForm');
    if (editCustomerForm) {
        editCustomerForm.addEventListener('submit', (e) => {
            e.preventDefault();
            handleCustomerSubmit('edit_customer', editCustomerForm, 'editCustomerModal');
        });
    }

    // Change Password Form Submit
    const changePasswordForm = document.getElementById('changePasswordForm');
    if (changePasswordForm) {
        changePasswordForm.addEventListener('submit', (e) => {
            e.preventDefault();
            handleChangePasswordSubmit(changePasswordForm);
        });
    }

    // Lightbox Controls Binding
    const lightboxCloseBtn = document.getElementById('lightboxCloseBtn');
    if (lightboxCloseBtn) {
        lightboxCloseBtn.addEventListener('click', closeLightbox);
    }

    const zoomInBtn = document.getElementById('zoomInBtn');
    if (zoomInBtn) {
        zoomInBtn.addEventListener('click', () => {
            zoomScale = Math.min(zoomScale + 0.25, 3.0);
            updateImageTransform();
        });
    }

    const zoomOutBtn = document.getElementById('zoomOutBtn');
    if (zoomOutBtn) {
        zoomOutBtn.addEventListener('click', () => {
            zoomScale = Math.max(zoomScale - 0.25, 0.5);
            updateImageTransform();
        });
    }

    const rotateCwBtn = document.getElementById('rotateCwBtn');
    if (rotateCwBtn) {
        rotateCwBtn.addEventListener('click', () => {
            rotateAngle = (rotateAngle + 90) % 360;
            updateImageTransform();
        });
    }

    const rotateCcwBtn = document.getElementById('rotateCcwBtn');
    if (rotateCcwBtn) {
        rotateCcwBtn.addEventListener('click', () => {
            rotateAngle = (rotateAngle - 90 + 360) % 360;
            updateImageTransform();
        });
    }

    const lightboxPrintBtn = document.getElementById('lightboxPrintBtn');
    if (lightboxPrintBtn) {
        lightboxPrintBtn.addEventListener('click', () => {
            if (currentPreviewType === 'pdf') {
                const iframe = document.querySelector('#lightboxViewer iframe');
                if (iframe) {
                    printPdf(iframe);
                }
            } else if (currentPreviewType === 'image') {
                printImage(currentPreviewUrl);
            } else {
                window.print();
            }
        });
    }

    // Escape key to close lightbox
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeLightbox();
        }
    });

    // Lightbox image panning
    const lightboxViewer = document.getElementById('lightboxViewer');
    if (lightboxViewer) {
        lightboxViewer.addEventListener('mousedown', (e) => {
            const img = lightboxViewer.querySelector('img');
            if (img && e.target === img) {
                e.preventDefault(); // Prevent standard browser drag image action
                isPanning = true;
                img.classList.add('grabbing');
                startX = e.clientX - panX;
                startY = e.clientY - panY;
            }
        });
        
        // Lightbox image mouse wheel scroll to zoom
        lightboxViewer.addEventListener('wheel', (e) => {
            if (currentPreviewType === 'image') {
                e.preventDefault();
                const delta = e.deltaY;
                if (delta < 0) {
                    zoomScale = Math.min(zoomScale + 0.1, 3.0);
                } else {
                    zoomScale = Math.max(zoomScale - 0.1, 0.5);
                }
                updateImageTransform();
            }
        }, { passive: false });

        window.addEventListener('mousemove', (e) => {
            if (isPanning) {
                const img = lightboxViewer.querySelector('img');
                if (img) {
                    panX = e.clientX - startX;
                    panY = e.clientY - startY;
                    updateImageTransform();
                }
            }
        });

        const stopPanning = () => {
            if (isPanning) {
                isPanning = false;
                const img = lightboxViewer.querySelector('img');
                if (img) {
                    img.classList.remove('grabbing');
                }
            }
        };

        window.addEventListener('mouseup', stopPanning);
        lightboxViewer.addEventListener('mouseleave', stopPanning);
    }

    // Mobile Sidebar Drawer Toggle
    const mobileToggle = document.getElementById('mobileSidebarToggle');
    const sidebarPanel = document.querySelector('.sidebar-panel');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');
    
    if (mobileToggle && sidebarPanel && sidebarBackdrop) {
        mobileToggle.addEventListener('click', () => {
            sidebarPanel.classList.toggle('show-mobile-sidebar');
            sidebarBackdrop.classList.toggle('show');
        });
        
        sidebarBackdrop.addEventListener('click', () => {
            sidebarPanel.classList.remove('show-mobile-sidebar');
            sidebarBackdrop.classList.remove('show');
        });
    }

    // Inline Profile Editing (Desktop only)
    document.addEventListener('dblclick', (e) => {
        if (window.innerWidth < 992) return;
        const target = e.target.closest('.clickable-inline');
        if (target) {
            startInlineEdit(target);
        }
    });

    // Avatar Frame Double Click to Upload Photo
    const avatarFrame = document.querySelector('.avatar-frame-container');
    if (avatarFrame) {
        avatarFrame.addEventListener('dblclick', () => {
            triggerAvatarUpload();
        });
    }
}

// ==========================================
// CUSTOMER AJAX OPERATIONS
// ==========================================

function loadCustomers(searchQuery = '') {
    const listContainer = document.getElementById('customerList');
    if (!listContainer) return;

    fetch(`ajax.php?action=get_customers&search=${encodeURIComponent(searchQuery)}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderCustomerList(data.customers);
            } else {
                showAlert('danger', data.message);
            }
        })
        .catch(err => {
            console.error('Error fetching customers:', err);
            showAlert('danger', 'Failed to connect to backend.');
        });
}

function renderCustomerList(customers) {
    const listContainer = document.getElementById('customerList');
    listContainer.innerHTML = '';

    if (customers.length === 0) {
        listContainer.innerHTML = `
            <div class="text-center text-muted p-4">
                <i class="fa-solid fa-users fs-3 mb-2 d-block opacity-50"></i>
                No customers found.
            </div>`;
        return;
    }

    customers.forEach(customer => {
        const item = document.createElement('div');
        item.className = `customer-item ${selectedCustomerId == customer.id ? 'active' : ''}`;
        item.dataset.id = customer.id;
        item.innerHTML = `
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3 overflow-hidden">
                    <img src="download.php?type=avatar&customer_id=${customer.id}" class="rounded-circle border border-1 border-secondary" alt="" style="width: 28px; height: 28px; object-fit: cover; flex-shrink: 0; background: rgba(0,0,0,0.2);">
                    <div class="overflow-hidden">
                        <div class="customer-name text-white fw-500 text-truncate" style="max-width: 140px;">${escapeHTML(customer.customer_name)}</div>
                        <div class="customer-meta">
                            <span><i class="fa-solid fa-phone me-1"></i>${escapeHTML(customer.customer_phone || 'N/A')}</span>
                        </div>
                    </div>
                </div>
                <span class="customer-badge">${customer.file_count} docs</span>
            </div>
        `;
        item.addEventListener('click', () => selectCustomer(customer.id));
        listContainer.appendChild(item);
    });
}

function selectCustomer(id) {
    selectedCustomerId = id;
    
    // Highlight active customer in list
    document.querySelectorAll('.customer-item').forEach(item => {
        if (item.dataset.id == id) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });

    // Close mobile sidebar drawer and backdrop if open
    const sidebarPanel = document.querySelector('.sidebar-panel');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');
    if (sidebarPanel) {
        sidebarPanel.classList.remove('show-mobile-sidebar');
    }
    if (sidebarBackdrop) {
        sidebarBackdrop.classList.remove('show');
    }

    // Toggle Panels
    document.getElementById('emptyDashboardState').style.display = 'none';
    document.getElementById('customerManagerPanel').style.display = 'block';

    // Reset upload state
    resetUploadProgress();

    // Reset file search & filter UI
    currentFileSearchQuery = '';
    currentFileTypeFilter = 'all';
    const searchFileInput = document.getElementById('searchFile');
    if (searchFileInput) {
        searchFileInput.value = '';
    }
    const clearFileSearchBtn = document.getElementById('clearFileSearch');
    if (clearFileSearchBtn) {
        clearFileSearchBtn.classList.add('d-none');
    }
    document.querySelectorAll('.file-type-filter-group .filter-type-btn').forEach(btn => {
        if (btn.dataset.type === 'all') {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });

    // Fetch customer details
    fetch(`ajax.php?action=get_customer&id=${id}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderCustomerInfo(data.customer);
                loadCustomerFiles(id);
            } else {
                showAlert('danger', data.message);
            }
        })
        .catch(err => {
            console.error('Error fetching customer details:', err);
            showAlert('danger', 'Failed to load customer details.');
        });
}

function renderCustomerInfo(customer) {
    document.getElementById('displayCustomerName').innerText = customer.customer_name;
    document.getElementById('infoEmail').innerText = customer.customer_email || 'Not Provided';
    document.getElementById('infoPhone').innerText = customer.customer_phone || 'Not Provided';
    document.getElementById('infoSex').innerText = customer.sex || 'Not Provided';
    document.getElementById('infoDob').innerText = customer.dob || 'Not Provided';
    document.getElementById('infoAddress').innerText = customer.address || 'Not Provided';
    document.getElementById('infoNotes').innerText = customer.notes || 'No extra notes.';
    
    // Update profile photo with cache buster
    document.getElementById('infoPhoto').src = `download.php?type=avatar&customer_id=${customer.id}&t=${new Date().getTime()}`;
    
    // Pre-populate Edit Modal fields
    document.getElementById('editCustomerId').value = customer.id;
    document.getElementById('editCustomerName').value = customer.customer_name;
    document.getElementById('editCustomerEmail').value = customer.customer_email || '';
    document.getElementById('editCustomerPhone').value = customer.customer_phone || '';
    document.getElementById('editSex').value = customer.sex || '';
    document.getElementById('editDob').value = customer.dob || '';
    document.getElementById('editAddress').value = customer.address || '';
    document.getElementById('editProfilePhoto').value = '';
}

function handleCustomerSubmit(action, form, modalId) {
    const formData = new FormData(form);
    
    fetch(`ajax.php?action=${action}`, {
        method: 'POST',
        headers: {
            'X-CSRF-Token': csrfToken
        },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Hide modal
            const modalEl = document.getElementById(modalId);
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            
            form.reset();
            
            // Reload
            loadCustomers(document.getElementById('searchCustomer').value);
            loadStats();
            
            if (selectedCustomerId && action === 'edit_customer') {
                selectCustomer(selectedCustomerId);
            }
            
            showAlert('success', data.message);
        } else {
            showAlert('danger', data.message);
        }
    })
    .catch(err => {
        console.error('Customer action error:', err);
        showAlert('danger', 'An error occurred processing customer.');
    });
}

function handleChangePasswordSubmit(form) {
    const currentPwd = document.getElementById('currentPassword').value;
    const newPwd = document.getElementById('newPassword').value;
    const confirmPwd = document.getElementById('confirmPassword').value;

    if (newPwd.length < 6) {
        showAlert('danger', 'New password must be at least 6 characters long.');
        return;
    }

    if (newPwd !== confirmPwd) {
        showAlert('danger', 'New passwords do not match.');
        return;
    }

    const formData = new FormData(form);

    fetch('ajax.php?action=change_password', {
        method: 'POST',
        headers: {
            'X-CSRF-Token': csrfToken
        },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Hide modal
            const modalEl = document.getElementById('changePasswordModal');
            let modal = bootstrap.Modal.getInstance(modalEl);
            if (!modal) {
                modal = new bootstrap.Modal(modalEl);
            }
            if (modal) modal.hide();
            
            form.reset();
            showAlert('success', data.message);
        } else {
            showAlert('danger', data.message);
        }
    })
    .catch(err => {
        console.error('Change password error:', err);
        showAlert('danger', 'An error occurred updating the password.');
    });
}

function startInlineEdit(element) {
    if (!selectedCustomerId || element.classList.contains('editing-inline')) return;
    element.classList.add('editing-inline');
    
    const fieldName = element.dataset.field;
    let currentValue = element.innerText.trim();
    
    // Clear default placeholders
    if (currentValue === 'Not Provided' || currentValue === 'No extra notes.') {
        currentValue = '';
    }
    
    let inputEl;
    if (fieldName === 'sex') {
        inputEl = document.createElement('select');
        inputEl.className = 'form-select form-control-custom inline-edit-input';
        
        const options = [
            { val: '', label: 'Select Sex' },
            { val: 'Male', label: 'Male' },
            { val: 'Female', label: 'Female' },
            { val: 'Other', label: 'Other' }
        ];
        
        options.forEach(opt => {
            const o = document.createElement('option');
            o.value = opt.val;
            o.text = opt.label;
            if (opt.val === currentValue) o.selected = true;
            inputEl.appendChild(o);
        });
    } else if (fieldName === 'dob') {
        inputEl = document.createElement('input');
        inputEl.type = 'date';
        inputEl.className = 'form-control form-control-custom inline-edit-input';
        const modalDob = document.getElementById('editDob').value;
        inputEl.value = modalDob || '';
    } else if (fieldName === 'address' || fieldName === 'notes') {
        inputEl = document.createElement('textarea');
        inputEl.className = 'form-control form-control-custom inline-edit-input';
        inputEl.rows = fieldName === 'address' ? 2 : 3;
        inputEl.value = currentValue;
    } else {
        inputEl = document.createElement('input');
        inputEl.type = fieldName === 'customer_email' ? 'email' : 'text';
        inputEl.className = 'form-control form-control-custom inline-edit-input';
        inputEl.value = currentValue;
    }
    
    // Store original HTML to restore if cancelled
    const originalHTML = element.innerHTML;
    
    element.innerHTML = '';
    element.appendChild(inputEl);
    inputEl.focus();
    if (inputEl.select) inputEl.select();
    
    let finished = false;
    
    function cancelEdit() {
        if (finished) return;
        finished = true;
        element.classList.remove('editing-inline');
        element.innerHTML = originalHTML;
    }
    
    function saveEdit() {
        if (finished) return;
        finished = true;
        
        const newValue = inputEl.value.trim();
        
        const id = selectedCustomerId;
        const name = fieldName === 'customer_name' ? newValue : document.getElementById('editCustomerName').value;
        const email = fieldName === 'customer_email' ? newValue : document.getElementById('editCustomerEmail').value;
        const phone = fieldName === 'customer_phone' ? newValue : document.getElementById('editCustomerPhone').value;
        const sex = fieldName === 'sex' ? newValue : document.getElementById('editSex').value;
        const dob = fieldName === 'dob' ? newValue : document.getElementById('editDob').value;
        const address = fieldName === 'address' ? newValue : document.getElementById('editAddress').value;
        const notes = fieldName === 'notes' ? newValue : document.getElementById('editNotes').value;
        
        if (fieldName === 'customer_name' && name === '') {
            showAlert('danger', 'Customer name is required.');
            cancelEdit();
            return;
        }
        
        element.innerHTML = `<span class="spinner-border spinner-border-sm text-indigo" role="status"></span>`;
        
        const formData = new FormData();
        formData.append('id', id);
        formData.append('customer_name', name);
        formData.append('customer_email', email);
        formData.append('customer_phone', phone);
        formData.append('sex', sex);
        formData.append('dob', dob);
        formData.append('address', address);
        formData.append('notes', notes);
        
        fetch('ajax.php?action=edit_customer', {
            method: 'POST',
            headers: {
                'X-CSRF-Token': csrfToken
            },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showAlert('success', 'Profile updated successfully.');
                loadCustomers(document.getElementById('searchCustomer').value);
                selectCustomer(id);
            } else {
                showAlert('danger', data.message);
                cancelEdit();
            }
        })
        .catch(err => {
            console.error('Inline edit error:', err);
            showAlert('danger', 'Failed to save changes.');
            cancelEdit();
        });
    }
    
    inputEl.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && fieldName !== 'address' && fieldName !== 'notes') {
            e.preventDefault();
            saveEdit();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            cancelEdit();
        }
    });
    
    inputEl.addEventListener('blur', (e) => {
        // Use timeout to let clicks on select lists or date dialogs register without instantly blurring
        setTimeout(() => {
            saveEdit();
        }, 220);
    });
}

function triggerAvatarUpload() {
    if (!selectedCustomerId) return;
    
    // Create a dynamic file input if not exists
    let avatarInput = document.getElementById('dynamicAvatarInput');
    if (!avatarInput) {
        avatarInput = document.createElement('input');
        avatarInput.type = 'file';
        avatarInput.id = 'dynamicAvatarInput';
        avatarInput.accept = 'image/*';
        avatarInput.style.display = 'none';
        document.body.appendChild(avatarInput);
        
        avatarInput.addEventListener('change', () => {
            if (avatarInput.files.length > 0) {
                uploadAvatarFile(avatarInput.files[0]);
            }
        });
    }
    
    avatarInput.click();
}

function uploadAvatarFile(file) {
    if (!selectedCustomerId) return;
    
    // Client-side validation: must be an image
    if (!file.type.startsWith('image/')) {
        showAlert('danger', 'Please select a valid image file for the profile photo.');
        return;
    }
    
    // Size check: 5MB
    if (file.size > 5 * 1024 * 1024) {
        showAlert('danger', 'Profile photo exceeds size limit of 5MB.');
        return;
    }
    
    const id = selectedCustomerId;
    const name = document.getElementById('editCustomerName').value;
    const email = document.getElementById('editCustomerEmail').value;
    const phone = document.getElementById('editCustomerPhone').value;
    const sex = document.getElementById('editSex').value;
    const dob = document.getElementById('editDob').value;
    const address = document.getElementById('editAddress').value;
    const notes = document.getElementById('editNotes').value;
    
    const formData = new FormData();
    formData.append('id', id);
    formData.append('customer_name', name);
    formData.append('customer_email', email);
    formData.append('customer_phone', phone);
    formData.append('sex', sex);
    formData.append('dob', dob);
    formData.append('address', address);
    formData.append('notes', notes);
    formData.append('profile_photo', file);
    
    // Show loading state
    const imgEl = document.getElementById('infoPhoto');
    const originalSrc = imgEl.src;
    imgEl.style.opacity = '0.5';
    
    fetch('ajax.php?action=edit_customer', {
        method: 'POST',
        headers: {
            'X-CSRF-Token': csrfToken
        },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        imgEl.style.opacity = '1';
        if (data.success) {
            showAlert('success', 'Profile photo updated successfully.');
            loadCustomers(document.getElementById('searchCustomer').value);
            selectCustomer(id);
        } else {
            showAlert('danger', data.message);
            imgEl.src = originalSrc;
        }
    })
    .catch(err => {
        console.error('Avatar upload error:', err);
        showAlert('danger', 'Failed to upload profile photo.');
        imgEl.style.opacity = '1';
        imgEl.src = originalSrc;
    });
}

function confirmDeleteCustomer() {
    if (!selectedCustomerId) return;
    
    const name = document.getElementById('displayCustomerName').innerText;
    const msg = `Are you sure you want to permanently delete customer "${name}"?\n\nThis will permanently delete the customer profile AND ALL of their uploaded files on disk. This action is irreversible.`;
    
    showConfirmModal(msg, () => {
        const formData = new FormData();
        formData.append('id', selectedCustomerId);
        
        fetch('ajax.php?action=delete_customer', {
            method: 'POST',
            headers: {
                'X-CSRF-Token': csrfToken
            },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                selectedCustomerId = null;
                document.getElementById('customerManagerPanel').style.display = 'none';
                document.getElementById('emptyDashboardState').style.display = 'block';
                loadCustomers();
                loadStats();
                showAlert('success', data.message);
            } else {
                showAlert('danger', data.message);
            }
        })
        .catch(err => {
            console.error('Delete customer error:', err);
            showAlert('danger', 'Error connecting to delete API.');
        });
    });
}

// ==========================================
// FILES AJAX OPERATIONS
// ==========================================

function loadCustomerFiles(customerId) {
    const fileContainer = document.getElementById('filesList');
    if (!fileContainer) return;
    
    fileContainer.innerHTML = `<div class="text-center p-4"><div class="spinner-border text-primary" role="status"></div></div>`;

    fetch(`ajax.php?action=get_files&customer_id=${customerId}&search=${encodeURIComponent(currentFileSearchQuery)}&type=${encodeURIComponent(currentFileTypeFilter)}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderFilesList(data.files);
            } else {
                fileContainer.innerHTML = `<div class="alert alert-danger">${escapeHTML(data.message)}</div>`;
            }
        })
        .catch(err => {
            console.error('Error loading files:', err);
            fileContainer.innerHTML = `<div class="alert alert-danger">Failed to load files list.</div>`;
        });
}

function renderFilesList(files) {
    const fileContainer = document.getElementById('filesList');
    fileContainer.innerHTML = '';
    
    if (files.length === 0) {
        const isActiveFilter = currentFileSearchQuery !== '' || currentFileTypeFilter !== 'all';
        const title = isActiveFilter ? 'No matching files found' : 'No secured files found';
        const desc = isActiveFilter 
            ? 'Try adjusting your search terms or changing your type filter.' 
            : 'Use the drag-and-drop area above or browse to upload Voter ID, Aadhaar card scans, or other customer documents.';
        fileContainer.innerHTML = `
            <div class="empty-state">
                <i class="fa-solid fa-folder-open"></i>
                <p class="fs-5 fw-500 mb-1">${title}</p>
                <p class="small text-muted">${desc}</p>
            </div>`;
        return;
    }
    
    files.forEach(file => {
        // Classify icon based on extension
        const ext = file.original_name.split('.').pop().toLowerCase();
        let icon = 'fa-file';
        
        switch (ext) {
            case 'pdf':
                icon = 'fa-file-pdf';
                break;
            case 'doc':
            case 'docx':
                icon = 'fa-file-word';
                break;
            case 'xls':
            case 'xlsx':
                icon = 'fa-file-excel';
                break;
            case 'ppt':
            case 'pptx':
                icon = 'fa-file-powerpoint';
                break;
            case 'jpg':
            case 'jpeg':
            case 'png':
            case 'gif':
            case 'webp':
                icon = 'fa-file-image';
                break;
            case 'txt':
                icon = 'fa-file-lines';
                break;
            case 'zip':
            case 'rar':
            case 'tar':
            case 'gz':
            case '7z':
                icon = 'fa-file-zipper';
                break;
            default:
                if (file.file_type === 'image') icon = 'fa-file-image';
                else if (file.file_type === 'pdf') icon = 'fa-file-pdf';
                else if (file.file_type === 'document') icon = 'fa-file-lines';
                break;
        }
        
        // Match specific document tags for quick categorization
        let badgeHTML = '';
        const nameAndNotes = (file.original_name + ' ' + (file.notes || '')).toLowerCase();
        
        if (nameAndNotes.includes('voter')) {
            badgeHTML = `<span class="doc-badge voter me-2">Voter ID</span>`;
        } else if (nameAndNotes.includes('aadhaar') || nameAndNotes.includes('aadhar') || nameAndNotes.includes('uidai')) {
            badgeHTML = `<span class="doc-badge aadhaar me-2">Aadhaar Card</span>`;
        } else {
            badgeHTML = `<span class="doc-badge other me-2">${escapeHTML(file.file_type)}</span>`;
        }
        
        // Preview check
        const officeMimes = [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.ms-powerpoint'
        ];
        const isPreviewable = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'text/plain',
            ...officeMimes
        ].includes(file.mime_type);
            
        const card = document.createElement('div');
        card.id = `file-card-${file.id}`;
        card.className = 'glass-card p-3 mb-3 file-card';
        if (isPreviewable) {
            card.classList.add('previewable-card');
            card.style.cursor = 'pointer';
        }
        
        let secureBadgeHTML = '';
        if (file.is_encrypted == 1) {
            secureBadgeHTML = `
                <span class="badge-secure-premium" title="AES-256-CBC Encrypted & Secured on Disk">
                    <i class="fa-solid fa-lock text-emerald"></i> AES-256 Secure
                </span>
            `;
        }
        
        card.innerHTML = `
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3">
                <div class="d-flex align-items-center overflow-hidden w-100">
                    <div class="file-icon-box ${file.file_type} flex-shrink-0">
                        <i class="fa-solid ${icon}"></i>
                    </div>
                    <div class="overflow-hidden flex-grow-1">
                        <div class="text-truncate fw-500 text-white file-name-display" data-id="${file.id}" data-name="${escapeHTML(file.original_name)}" title="Double click to rename" style="cursor: pointer; user-select: none;">${escapeHTML(file.original_name)}</div>
                        <div class="d-flex flex-wrap align-items-center mt-1 gap-1">
                            ${badgeHTML}
                            <span class="text-muted small">${file.formatted_size}</span>
                            <span class="text-muted small">•</span>
                            <span class="text-muted small">${file.uploaded_at}</span>
                            ${secureBadgeHTML ? `<span class="text-muted small">•</span>${secureBadgeHTML}` : ''}
                        </div>
                        ${file.notes ? `<div class="text-secondary small mt-1 italic" style="word-break: break-all;">Note: ${escapeHTML(file.notes)}</div>` : ''}
                    </div>
                </div>
                <div class="file-actions d-flex ms-auto ms-sm-0 flex-shrink-0" style="position: relative; z-index: 2;">
                    <a href="download.php?id=${file.id}" class="btn btn-sm btn-glass text-success me-1" title="Secure Download"><i class="fa-solid fa-download"></i></a>
                    <button onclick="confirmDeleteFile(${file.id})" class="btn btn-sm btn-glass text-danger" title="Permanently Delete"><i class="fa-solid fa-trash"></i></button>
                </div>
            </div>
        `;
        
        // Bind single click row preview
        if (isPreviewable) {
            card.addEventListener('click', (e) => {
                const isActionClick = e.target.closest('.file-actions') || e.target.closest('.file-name-edit-input') || e.target.closest('.file-name-display');
                if (!isActionClick) {
                    previewFile(file.id, file.original_name, file.mime_type, file.token);
                }
            });
        }
        
        // Bind double click renaming listener
        const nameDisplayEl = card.querySelector('.file-name-display');
        if (nameDisplayEl) {
            nameDisplayEl.addEventListener('dblclick', () => {
                startFileNameEdit(nameDisplayEl, file.id, file.original_name);
            });
        }
        
        fileContainer.appendChild(card);
    });
}

function startFileNameEdit(element, fileId, originalName) {
    if (element.classList.contains('editing')) return;
    element.classList.add('editing');
    
    // Extract filename and extension
    const lastDotIndex = originalName.lastIndexOf('.');
    let nameWithoutExt = originalName;
    let ext = '';
    if (lastDotIndex > 0) {
        nameWithoutExt = originalName.substring(0, lastDotIndex);
        ext = originalName.substring(lastDotIndex);
    }
    
    // Create custom styled input
    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'file-name-edit-input';
    input.value = nameWithoutExt;
    
    element.innerHTML = '';
    element.appendChild(input);
    input.focus();
    input.select();
    
    let finished = false;
    
    function cancelRename() {
        if (finished) return;
        finished = true;
        element.classList.remove('editing');
        element.innerHTML = escapeHTML(originalName);
    }
    
    function confirmRename() {
        if (finished) return;
        finished = true;
        
        const newNameVal = input.value.trim();
        
        // Revert if empty or unchanged
        if (newNameVal === '' || newNameVal === nameWithoutExt) {
            element.classList.remove('editing');
            element.innerHTML = escapeHTML(originalName);
            return;
        }
        
        element.innerHTML = `<span class="spinner-border spinner-border-sm text-indigo me-1" role="status"></span>Saving...`;
        
        const formData = new FormData();
        formData.append('id', fileId);
        formData.append('new_name', newNameVal);
        
        fetch('ajax.php?action=rename_file', {
            method: 'POST',
            headers: {
                'X-CSRF-Token': csrfToken
            },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
                loadCustomerFiles(selectedCustomerId);
                loadStats();
            } else {
                showAlert('danger', data.message);
                element.classList.remove('editing');
                element.innerHTML = escapeHTML(originalName);
            }
        })
        .catch(err => {
            console.error('Rename error:', err);
            showAlert('danger', 'Failed to communicate with rename API.');
            element.classList.remove('editing');
            element.innerHTML = escapeHTML(originalName);
        });
    }
    
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            confirmRename();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            cancelRename();
        }
    });
    
    input.addEventListener('blur', () => {
        setTimeout(() => {
            confirmRename();
        }, 120);
    });
}

// Drag & Drop Dropzone Initializer (called from page load)
function initUploadDropzone() {
    const dropzone = document.getElementById('dropzone');
    const fileInput = document.getElementById('fileInput');
    const notesInput = document.getElementById('uploadNotes');
    
    if (!dropzone || !fileInput) return;
    
    // Trigger file dialog
    dropzone.addEventListener('click', (e) => {
        if (e.target !== notesInput && !notesInput.contains(e.target)) {
            fileInput.click();
        }
    });
    
    fileInput.addEventListener('change', () => {
        if (fileInput.files.length > 0) {
            triggerUploads(fileInput.files);
        }
    });
    
    // Drag and Drop styling
    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.add('dragover');
        }, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('dragover');
        }, false);
    });
    
    dropzone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files.length > 0) {
            triggerUploads(files);
        }
    });
}

function triggerUploads(files) {
    if (!selectedCustomerId) {
        showAlert('warning', 'Please select a customer first before uploading files.');
        return;
    }
    
    const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'];
    const maxSizeBytes = 10 * 1024 * 1024; // 10MB
    const validFiles = [];
    
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        const ext = file.name.split('.').pop().toLowerCase();
        
        if (!allowedExtensions.includes(ext)) {
            showAlert('danger', `File "${file.name}" has an invalid extension. Allowed extensions are: PDF, JPG, JPEG, PNG, GIF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT.`);
            continue;
        }
        
        if (file.size > maxSizeBytes) {
            showAlert('danger', `File "${file.name}" exceeds the maximum allowed size of 10MB.`);
            continue;
        }
        
        validFiles.push(file);
    }
    
    uploadQueue = validFiles;
    currentUploadIndex = 0;
    totalUploadCount = uploadQueue.length;
    
    if (totalUploadCount === 0) {
        document.getElementById('fileInput').value = '';
        return;
    }
    
    // Start processing queue
    processNextUpload();
}

function processNextUpload() {
    if (currentUploadIndex >= totalUploadCount) {
        // Complete queue
        showAlert('success', `All ${totalUploadCount} file(s) uploaded and secured successfully.`);
        
        const notesInput = document.getElementById('uploadNotes');
        if (notesInput) notesInput.value = '';
        
        document.getElementById('fileInput').value = '';
        loadCustomerFiles(selectedCustomerId);
        loadStats();
        // Update customer document counts in sidebar
        loadCustomers(document.getElementById('searchCustomer').value);
        
        resetUploadProgress();
        uploadQueue = [];
        return;
    }
    
    const file = uploadQueue[currentUploadIndex];
    uploadSingleFile(file);
}

function uploadSingleFile(file) {
    const notesInput = document.getElementById('uploadNotes');
    const notes = notesInput ? notesInput.value.trim() : '';
    
    const formData = new FormData();
    formData.append('file', file);
    formData.append('customer_id', selectedCustomerId);
    formData.append('file_notes', notes);
    
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    const progressPercent = document.getElementById('progressPercent');
    
    if (progressContainer) progressContainer.style.display = 'block';
    
    // Update progress bar labels to indicate queue index
    const labelSpan = progressContainer ? progressContainer.querySelector('span') : null;
    if (labelSpan) {
        labelSpan.innerText = `Securing and Hashing [${currentUploadIndex + 1}/${totalUploadCount}]: ${file.name}...`;
    }
    
    if (progressBar) progressBar.style.width = '0%';
    if (progressPercent) progressPercent.innerText = '0%';
    
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'ajax.php?action=upload_file', true);
    xhr.setRequestHeader('X-CSRF-Token', csrfToken);
    
    xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
            const percentage = Math.round((e.loaded / e.total) * 100);
            if (progressBar) progressBar.style.width = `${percentage}%`;
            if (progressPercent) progressPercent.innerText = `${percentage}%`;
        }
    });
    
    xhr.onload = () => {
        let response = { success: false, message: 'Upload communication failed.' };
        try {
            response = JSON.parse(xhr.responseText);
        } catch (ex) {
            console.error('Error parsing upload response', xhr.responseText);
        }
        
        if (xhr.status === 200 && response.success) {
            currentUploadIndex++;
            processNextUpload();
        } else {
            showAlert('danger', `Failed uploading "${file.name}": ${response.message || 'Error occurred.'}`);
            resetUploadProgress();
            uploadQueue = []; // Cancel queue on error
        }
    };
    
    xhr.onerror = () => {
        showAlert('danger', `Network error uploading "${file.name}".`);
        resetUploadProgress();
        uploadQueue = [];
    };
    
    xhr.send(formData);
}

function resetUploadProgress() {
    const progressContainer = document.getElementById('progressContainer');
    if (progressContainer) {
        progressContainer.style.display = 'none';
    }
}

function confirmDeleteFile(fileId) {
    const msg = 'Are you sure you want to permanently delete this document from secure storage? This action cannot be undone.';
    showConfirmModal(msg, () => {
        const formData = new FormData();
        formData.append('id', fileId);
        
        fetch('ajax.php?action=delete_file', {
            method: 'POST',
            headers: {
                'X-CSRF-Token': csrfToken
            },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
                
                // 1. Smoothly fade out and remove the file card from DOM without full list refresh
                const card = document.getElementById(`file-card-${fileId}`);
                if (card) {
                    card.classList.add('file-card-deleted');
                    card.addEventListener('transitionend', () => {
                        card.remove();
                        
                        // Check if file list is now empty, render empty state if so
                        const fileContainer = document.getElementById('filesList');
                        if (fileContainer && fileContainer.querySelectorAll('.file-card').length === 0) {
                            const isActiveFilter = currentFileSearchQuery !== '' || currentFileTypeFilter !== 'all';
                            const title = isActiveFilter ? 'No matching files found' : 'No secured files found';
                            const desc = isActiveFilter 
                                ? 'Try adjusting your search terms or changing your type filter.' 
                                : 'Use the drag-and-drop area above or browse to upload Voter ID, Aadhaar card scans, or other customer documents.';
                            fileContainer.innerHTML = `
                                <div class="empty-state">
                                    <i class="fa-solid fa-folder-open"></i>
                                    <p class="fs-5 fw-500 mb-1">${title}</p>
                                    <p class="small text-muted">${desc}</p>
                                </div>`;
                        }
                    });
                }
                
                // 2. Decrement the document count badge locally in the sidebar for the active customer
                const activeCustomer = document.querySelector('.customer-item.active');
                if (activeCustomer) {
                    const badge = activeCustomer.querySelector('.customer-badge');
                    if (badge) {
                        const count = parseInt(badge.innerText) || 0;
                        badge.innerText = Math.max(0, count - 1) + ' docs';
                    }
                }
                
                // 3. Update overall storage statistics in the background
                loadStats();
            } else {
                showAlert('danger', data.message);
            }
        })
        .catch(err => {
            console.error('Delete file error:', err);
            showAlert('danger', 'Failed to reach deletion endpoint.');
        });
    });
}

// ==========================================
// PREVIEW AND UTILITIES
// ==========================================

function previewFile(fileId, originalName, mimeType, token = '') {
    const overlay = document.getElementById('lightboxOverlay');
    const title = document.getElementById('lightboxTitle');
    const viewer = document.getElementById('lightboxViewer');
    const imgControls = document.getElementById('imageControlGroup');
    const separator = document.getElementById('controlSeparator');
    const downloadBtn = document.getElementById('lightboxDownloadBtn');
    
    if (!overlay || !title || !viewer) return;
    
    // Reset state
    zoomScale = 1.0;
    rotateAngle = 0;
    panX = 0;
    panY = 0;
    currentPreviewType = '';
    currentPreviewUrl = `download.php?id=${fileId}`;
    if (token) {
        currentPreviewUrl += `&token=${token}`;
    }
    
    title.innerText = originalName;
    viewer.innerHTML = `<div class="text-center py-5 text-muted"><div class="spinner-border text-primary" role="status"></div><br><span class="mt-2 d-inline-block">Loading secure preview...</span></div>`;
    
    // Setup download button
    if (downloadBtn) {
        downloadBtn.href = currentPreviewUrl;
        downloadBtn.setAttribute('download', originalName);
    }
    
    // Open Overlay
    overlay.classList.remove('d-none');
    document.body.style.overflow = 'hidden'; // Lock background scrolling
    
    // Check type and setup controls
    const isDoc = mimeType === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' || mimeType === 'application/msword' || originalName.endsWith('.docx') || originalName.endsWith('.doc');
    const isExcel = mimeType === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' || mimeType === 'application/vnd.ms-excel' || originalName.endsWith('.xlsx') || originalName.endsWith('.xls');
    const isPpt = mimeType === 'application/vnd.openxmlformats-officedocument.presentationml.presentation' || mimeType === 'application/vnd.ms-powerpoint' || originalName.endsWith('.pptx') || originalName.endsWith('.ppt');
    const isMsDoc = isDoc || isExcel || isPpt;

    if (mimeType.startsWith('image/')) {
        currentPreviewType = 'image';
        if (imgControls) imgControls.classList.remove('d-none');
        if (separator) separator.classList.remove('d-none');
    } else {
        if (mimeType === 'application/pdf') {
            currentPreviewType = 'pdf';
        } else if (isMsDoc) {
            currentPreviewType = 'ms-doc';
        } else {
            currentPreviewType = 'other';
        }
        if (imgControls) imgControls.classList.add('d-none');
        if (separator) separator.classList.add('d-none');
    }
    
    // Build content
    setTimeout(() => {
        if (currentPreviewType === 'image') {
            viewer.innerHTML = `<img src="${currentPreviewUrl}" alt="${escapeHTML(originalName)}">`;
            updateImageTransform();
        } else if (currentPreviewType === 'pdf') {
            viewer.innerHTML = `<iframe src="${currentPreviewUrl}"></iframe>`;
        } else if (currentPreviewType === 'ms-doc') {
            const absoluteUrl = new URL(currentPreviewUrl, window.location.href).href;
            const viewerUrl = `https://view.officeapps.live.com/op/embed.aspx?src=${encodeURIComponent(absoluteUrl)}`;
            viewer.innerHTML = `<iframe src="${viewerUrl}"></iframe>`;
        } else if (mimeType === 'text/plain') {
            fetch(currentPreviewUrl)
                .then(res => res.text())
                .then(text => {
                    viewer.innerHTML = `<pre>${escapeHTML(text)}</pre>`;
                })
                .catch(() => {
                    viewer.innerHTML = `<div class="text-center text-danger p-5">Failed to load text preview.</div>`;
                });
        } else {
            viewer.innerHTML = `
                <div class="text-center py-5 text-muted">
                    <i class="fa-solid fa-file-zipper fs-1 text-warning mb-3 d-block"></i>
                    <h5 class="text-white mb-2">Preview not supported for this file type</h5>
                    <p class="small text-muted mb-4">MIME type: ${escapeHTML(mimeType)}</p>
                    <a href="${currentPreviewUrl}" class="btn btn-indigo px-4"><i class="fa-solid fa-download me-2"></i>Download File</a>
                </div>`;
        }
    }, 300);
}

function closeLightbox() {
    const overlay = document.getElementById('lightboxOverlay');
    const viewer = document.getElementById('lightboxViewer');
    if (overlay) {
        overlay.classList.add('d-none');
        document.body.style.overflow = ''; // Unlock scrolling
    }
    if (viewer) {
        viewer.innerHTML = '';
    }
    currentPreviewType = '';
    currentPreviewUrl = '';
    zoomScale = 1.0;
    rotateAngle = 0;
    panX = 0;
    panY = 0;
}

function updateImageTransform() {
    const img = document.querySelector('#lightboxViewer img');
    if (img) {
        img.style.transform = `translate(${panX}px, ${panY}px) scale(${zoomScale}) rotate(${rotateAngle}deg)`;
        const percentEl = document.getElementById('zoomPercent');
        if (percentEl) {
            percentEl.innerText = `${Math.round(zoomScale * 100)}%`;
        }
    }
}

function printImage(url) {
    const iframe = document.createElement('iframe');
    iframe.style.position = 'fixed';
    iframe.style.right = '0';
    iframe.style.bottom = '0';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    document.body.appendChild(iframe);
    
    const doc = iframe.contentWindow.document;
    doc.write(`<html><head><title>Print Preview</title></head><body style="margin:0;display:flex;align-items:center;justify-content:center;"><img src="${url}" style="max-width:100%;max-height:100%;object-fit:contain;" onload="window.print();"></body></html>`);
    doc.close();
    
    iframe.contentWindow.addEventListener('afterprint', () => {
        iframe.remove();
    });
    
    setTimeout(() => {
        if (iframe.parentNode) iframe.remove();
    }, 60000);
}

function printPdf(iframe) {
    try {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
    } catch (e) {
        console.error('Failed to trigger native print on PDF iframe:', e);
        const win = window.open(iframe.src, '_blank');
        if (win) {
            win.focus();
            win.print();
        }
    }
}

function loadStats() {
    fetch('ajax.php?action=get_stats')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const stats = data.stats;
                // Update elements if they exist
                document.getElementById('statTotalCustomers').innerText = stats.customers;
                document.getElementById('statTotalFiles').innerText = stats.files;
                document.getElementById('statStorageSize').innerText = stats.storage;
                
                // Update small charts / lists of categories in dashboard if any
                const imageCount = stats.types.image || 0;
                const pdfCount = stats.types.pdf || 0;
                const docCount = stats.types.document || 0;
                const sum = imageCount + pdfCount + docCount;
                
                updateStatProgressBar('typeBarImage', imageCount, sum);
                updateStatProgressBar('typeBarPdf', pdfCount, sum);
                updateStatProgressBar('typeBarDoc', docCount, sum);
            }
        })
        .catch(err => console.error('Error fetching statistics:', err));
}

function updateStatProgressBar(elementId, val, sum) {
    const el = document.getElementById(elementId);
    if (!el) return;
    const pct = sum > 0 ? Math.round((val / sum) * 100) : 0;
    el.style.width = `${pct}%`;
    el.setAttribute('aria-valuenow', pct);
    // Find parent's text indicator
    const textLabel = el.closest('.stat-progress-item')?.querySelector('.progress-percentage');
    if (textLabel) {
        textLabel.innerText = `${val} files (${pct}%)`;
    }
}

// Alert Notification Manager (Animated Glass Toast)
function showAlert(type, message) {
    // Check if custom container exists
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'custom-toast-container';
        document.body.appendChild(container);
    }
    
    const toast = document.createElement('div');
    toast.className = `custom-toast ${type}`;
    
    let iconClass = 'fa-solid fa-circle-info text-indigo';
    if (type === 'danger') iconClass = 'fa-solid fa-triangle-exclamation text-danger';
    else if (type === 'success') iconClass = 'fa-solid fa-circle-check text-emerald';
    else if (type === 'warning') iconClass = 'fa-solid fa-circle-exclamation text-warning';
    else if (type === 'info') iconClass = 'fa-solid fa-circle-info text-indigo';
    
    toast.innerHTML = `
        <div class="custom-toast-icon">
            <i class="${iconClass}"></i>
        </div>
        <div class="custom-toast-content">${escapeHTML(message)}</div>
        <button class="custom-toast-close" title="Close"><i class="fa-solid fa-xmark"></i></button>
        <div class="custom-toast-progress">
            <div class="custom-toast-progress-bar" style="transform: scaleX(1);"></div>
        </div>
    `;
    
    container.appendChild(toast);
    
    // Close on click
    const closeBtn = toast.querySelector('.custom-toast-close');
    let isDismissed = false;
    
    function dismissToast() {
        if (isDismissed) return;
        isDismissed = true;
        toast.classList.add('hide');
        toast.addEventListener('animationend', () => {
            toast.remove();
        });
    }
    
    closeBtn.addEventListener('click', dismissToast);
    
    // Auto-dismiss after 4 seconds (4000ms) with interactive pausing
    const duration = 4000;
    let timeLeft = duration;
    const tickInterval = 20; // tick every 20ms
    let isHovered = false;
    
    const progressBar = toast.querySelector('.custom-toast-progress-bar');
    
    const timer = setInterval(() => {
        if (!isHovered) {
            timeLeft -= tickInterval;
            const percentage = Math.max(0, timeLeft / duration);
            progressBar.style.transform = `scaleX(${percentage})`;
            
            if (timeLeft <= 0) {
                clearInterval(timer);
                dismissToast();
            }
        }
    }, tickInterval);
    
    toast.addEventListener('mouseenter', () => {
        isHovered = true;
    });
    
    toast.addEventListener('mouseleave', () => {
        isHovered = false;
    });
}

// Confirmation Dialog Manager (Modal-based)
function showConfirmModal(message, confirmCallback) {
    const modalEl = document.getElementById('confirmModal');
    if (!modalEl) {
        if (confirm(message)) {
            confirmCallback();
        }
        return;
    }
    
    document.getElementById('confirmModalMessage').innerText = message;
    
    const actionBtn = document.getElementById('confirmModalActionBtn');
    // Clone button to strip existing event listeners
    const newActionBtn = actionBtn.cloneNode(true);
    actionBtn.parentNode.replaceChild(newActionBtn, actionBtn);
    
    const modal = new bootstrap.Modal(modalEl);
    
    newActionBtn.addEventListener('click', () => {
        modal.hide();
        confirmCallback();
    });
    
    modal.show();
}

// Security Escape Helpers
function escapeHTML(str) {
    if (!str) return '';
    return str.toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function escapeJS(str) {
    if (!str) return '';
    return str.toString()
        .replace(/\\/g, '\\\\')
        .replace(/'/g, "\\'")
        .replace(/"/g, '\\"')
        .replace(/\n/g, '\\n')
        .replace(/\r/g, '\\r');
}

function syncThemeToggleIcon() {
    const currentTheme = document.documentElement.getAttribute('data-bs-theme') || 'dark';
    const icon = document.getElementById('themeToggleIcon');
    const btn = document.getElementById('themeToggleBtn');
    if (!icon) return;
    
    if (currentTheme === 'light') {
        icon.className = 'fa-solid fa-moon';
        if (btn) {
            btn.className = 'btn btn-sm btn-glass text-dark d-flex align-items-center justify-content-center';
            btn.title = 'Switch to Dark Mode';
        }
    } else {
        icon.className = 'fa-solid fa-sun';
        if (btn) {
            btn.className = 'btn btn-sm btn-glass text-warning d-flex align-items-center justify-content-center';
            btn.title = 'Switch to Light Mode';
        }
    }
}
