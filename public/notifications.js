/**
 * Global notification functions
 * For showing action modals on CRUD operations
 */

/**
 * Show success notification
 * @param {string} message - The success message to display
 */
function showSuccess(message) {
    if (typeof showActionModal === 'function') {
        showActionModal('success', 'Thành công', message);
    } else {
        console.error('Action modal not initialized');
        // Fallback to alert if modal not available
        alert('Thành công: ' + message);
    }
}

/**
 * Show error notification
 * @param {string} message - The error message to display
 */
function showError(message) {
    if (typeof showActionModal === 'function') {
        showActionModal('error', 'Xảy ra lỗi', message);
    } else {
        console.error('Action modal not initialized');
        // Fallback to alert if modal not available
        alert('Lỗi: ' + message);
    }
}

/**
 * Show warning notification
 * @param {string} message - The warning message to display
 */
function showWarning(message) {
    if (typeof showActionModal === 'function') {
        showActionModal('warning', 'Cảnh báo', message);
    } else {
        console.error('Action modal not initialized');
        // Fallback to alert if modal not available
        alert('Cảnh báo: ' + message);
    }
}

/**
 * Show info notification
 * @param {string} message - The info message to display
 */
function showInfo(message) {
    if (typeof showActionModal === 'function') {
        showActionModal('info', 'Thông báo', message);
    } else {
        console.error('Action modal not initialized');
        // Fallback to alert if modal not available
        alert('Thông báo: ' + message);
    }
}

/**
 * Handle AJAX response and show appropriate notification
 * @param {Object} response - AJAX response object
 */
function handleResponse(response) {
    if (response.status === 'success') {
        showSuccess(response.message);
        return true;
    } else {
        showError(response.message || 'Có lỗi xảy ra');
        return false;
    }
} 