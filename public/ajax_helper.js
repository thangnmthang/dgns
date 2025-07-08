/**
 * AJAX Helper Functions
 * For handling common CRUD operations with notifications
 */

/**
 * Send AJAX request
 * @param {string} url - The URL to send the request to
 * @param {string} method - The HTTP method (GET, POST, PUT, DELETE)
 * @param {Object} data - The data to send with the request
 * @param {Function} successCallback - Function to call on success
 * @param {Function} errorCallback - Function to call on error
 */
function sendAjaxRequest(url, method, data, successCallback, errorCallback) {
    // Create XHR object
    const xhr = new XMLHttpRequest();
    
    // Initialize request
    xhr.open(method, url, true);
    
    // Set headers
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    
    // Setup callback
    xhr.onload = function() {
        if (xhr.status >= 200 && xhr.status < 300) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (typeof successCallback === 'function') {
                    successCallback(response);
                }
            } catch (e) {
                console.error('Error parsing JSON response', e);
                if (typeof errorCallback === 'function') {
                    errorCallback('Lỗi xử lý phản hồi từ máy chủ');
                } else {
                    showError('Lỗi xử lý phản hồi từ máy chủ');
                }
            }
        } else {
            if (typeof errorCallback === 'function') {
                errorCallback('Lỗi kết nối đến máy chủ: ' + xhr.status);
            } else {
                showError('Lỗi kết nối đến máy chủ: ' + xhr.status);
            }
        }
    };
    
    // Error handling
    xhr.onerror = function() {
        if (typeof errorCallback === 'function') {
            errorCallback('Lỗi kết nối đến máy chủ');
        } else {
            showError('Lỗi kết nối đến máy chủ');
        }
    };
    
    // Send request
    xhr.send(JSON.stringify(data));
}

/**
 * Perform CRUD operation via AJAX
 * @param {string} url - The URL to send the request to
 * @param {string} method - The HTTP method (GET, POST, PUT, DELETE)
 * @param {Object} data - The data to send with the request
 * @param {Object} options - Additional options
 * @param {Function} options.successCallback - Function to call on success
 * @param {Function} options.errorCallback - Function to call on error
 * @param {boolean} options.reload - Whether to reload the page on success
 * @param {string} options.redirectUrl - URL to redirect to on success
 */
function performCrudAction(url, method, data, options = {}) {
    sendAjaxRequest(url, method, data, function(response) {
        // Show notification based on response
        if (handleResponse(response)) {
            // Success actions
            if (typeof options.successCallback === 'function') {
                options.successCallback(response);
            }
            
            // Redirect or reload if specified
            if (options.redirectUrl) {
                window.location.href = options.redirectUrl;
            } else if (options.reload) {
                window.location.reload();
            }
        } else {
            // Error handling
            if (typeof options.errorCallback === 'function') {
                options.errorCallback(response);
            }
        }
    }, function(errorMessage) {
        // Error handling for connection errors
        showError(errorMessage);
        if (typeof options.errorCallback === 'function') {
            options.errorCallback({ status: 'error', message: errorMessage });
        }
    });
}

/**
 * Confirm action with modal before performing it
 * @param {string} message - Confirmation message
 * @param {Function} callback - Function to call if confirmed
 */
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
} 