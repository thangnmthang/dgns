<?php
/**
 * Action Modal Component
 * Used to display success/error notifications with better UI
 * Will be hidden by default and shown via JavaScript
 */
?>

<!-- Action Modal -->
<div id="actionModal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="absolute inset-0 bg-black opacity-50"></div>
    <div class="bg-white rounded-lg shadow-xl z-10 w-full max-w-md mx-4 overflow-hidden transform transition-all">
        <!-- Modal Header -->
        <div id="modal-header" class="px-6 py-4 border-b flex items-center justify-between">
            <h3 id="modal-title" class="text-lg font-semibold text-gray-800 flex items-center">
                <i id="modal-icon" class="fas mr-2"></i>
                <span id="modal-title-text"></span>
            </h3>
            <button type="button" class="close-modal text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <!-- Modal Body -->
        <div class="px-6 py-4">
            <p id="modal-message" class="text-gray-700"></p>
        </div>
        
        <!-- Modal Footer -->
        <div class="px-6 py-3 bg-gray-50 flex justify-end">
            <button type="button" class="close-modal bg-gray-600 hover:bg-gray-700 text-white py-2 px-4 rounded">
                Đóng
            </button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Check if there are any messages in the session
        <?php if (isset($_SESSION['success'])): ?>
            showActionModal('success', 'Thành công', '<?= $_SESSION['success'] ?>');
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            showActionModal('error', 'Lỗi', '<?= $_SESSION['error'] ?>');
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        // Close modal when clicking close buttons
        const closeButtons = document.querySelectorAll('.close-modal');
        closeButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                hideActionModal();
            });
        });
        
        // Close modal when clicking outside
        document.getElementById('actionModal').addEventListener('click', function(event) {
            if (event.target === this) {
                hideActionModal();
            }
        });
        
        // Close modal with ESC key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && !document.getElementById('actionModal').classList.contains('hidden')) {
                hideActionModal();
            }
        });
    });
    
    // Function to show the action modal
    function showActionModal(type, title, message) {
        const modal = document.getElementById('actionModal');
        const modalHeader = document.getElementById('modal-header');
        const modalIcon = document.getElementById('modal-icon');
        const modalTitle = document.getElementById('modal-title-text');
        const modalMessage = document.getElementById('modal-message');
        
        // Set modal content based on type
        if (type === 'success') {
            modalHeader.className = 'px-6 py-4 border-b flex items-center justify-between bg-green-50';
            modalIcon.className = 'fas fa-check-circle mr-2 text-green-500';
            modalTitle.textContent = title || 'Thành công';
        } else if (type === 'error') {
            modalHeader.className = 'px-6 py-4 border-b flex items-center justify-between bg-red-50';
            modalIcon.className = 'fas fa-exclamation-circle mr-2 text-red-500';
            modalTitle.textContent = title || 'Lỗi';
        } else if (type === 'warning') {
            modalHeader.className = 'px-6 py-4 border-b flex items-center justify-between bg-yellow-50';
            modalIcon.className = 'fas fa-exclamation-triangle mr-2 text-yellow-500';
            modalTitle.textContent = title || 'Cảnh báo';
        } else {
            modalHeader.className = 'px-6 py-4 border-b flex items-center justify-between bg-blue-50';
            modalIcon.className = 'fas fa-info-circle mr-2 text-blue-500';
            modalTitle.textContent = title || 'Thông báo';
        }
        
        // Set message
        modalMessage.textContent = message;
        
        // Show modal with animation
        modal.classList.remove('hidden');
        
        // Auto hide after 5 seconds for success messages
        if (type === 'success') {
            setTimeout(function() {
                hideActionModal();
            }, 2000);
        }
    }
    
    // Function to hide the action modal
    function hideActionModal() {
        const modal = document.getElementById('actionModal');
        modal.classList.add('hidden');
    }
</script> 