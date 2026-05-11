/**
 * Contact Page Logic - Enhanced UX/UI
 */

import Swal from 'sweetalert2';

export const initContactPage = () => {
    try {
        const form = document.getElementById('laca-contact-form');
        if (!form) return;

        // Safety check for themeData
        if (typeof themeData === 'undefined' || !themeData.ajaxurl) {
            console.error('themeData not found. Contact form cannot function.');
            return;
        }

        const siteKey = form.getAttribute('data-sitekey') || '';
        const submitBtn = form.querySelector('button[type="submit"]');
        const formStatus = document.querySelector('.contact-form-wrapper .form-status');

        // Helper: Get theme colors
        const getThemeColors = () => ({
            background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1a1a1a' : '#fff',
            color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#fff' : '#000',
        });

        // Helper: Show inline error
        const showFieldError = (fieldId, message) => {
            const field = document.getElementById(fieldId);
            const errorSpan = document.getElementById(`${fieldId.replace('form-', '')}-error`);
            if (field && errorSpan) {
                field.classList.add('invalid');
                field.setAttribute('aria-invalid', 'true');
                errorSpan.textContent = message;
                errorSpan.style.display = 'block';
            }
        };

        // Helper: Clear field error
        const clearFieldError = (fieldId) => {
            const field = document.getElementById(fieldId);
            const errorSpan = document.getElementById(`${fieldId.replace('form-', '')}-error`);
            if (field && errorSpan) {
                field.classList.remove('invalid');
                field.setAttribute('aria-invalid', 'false');
                errorSpan.textContent = '';
                errorSpan.style.display = 'none';
            }
        };

        // Helper: Clear all errors
        const clearAllErrors = () => {
            ['form-name', 'form-phone', 'form-email', 'form-message'].forEach(clearFieldError);
        };

        // Helper: Validate email
        const isValidEmail = (email) => {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        };

        // Helper: Validate Vietnam phone number
        const isValidPhone = (phone) => {
            return /(03|05|07|08|09|01[2|6|8|9])+([0-9]{8})\b/.test(phone);
        };

        // Helper: Client-side validation
        const validateForm = () => {
            clearAllErrors();
            let isValid = true;

            const name = document.getElementById('form-name').value.trim();
            const phone = document.getElementById('form-phone').value.trim();
            const email = document.getElementById('form-email').value.trim();
            const message = document.getElementById('form-message').value.trim();

            if (!name || name.length < 2) {
                showFieldError('form-name', 'Vui lòng nhập tên (ít nhất 2 ký tự)');
                isValid = false;
            }

            if (!phone) {
                showFieldError('form-phone', 'Vui lòng nhập số điện thoại');
                isValid = false;
            } else if (!isValidPhone(phone)) {
                showFieldError('form-phone', 'Số điện thoại không hợp lệ');
                isValid = false;
            }

            if (email && !isValidEmail(email)) {
                showFieldError('form-email', 'Email không hợp lệ');
                isValid = false;
            }

            if (!message || message.length < 10) {
                showFieldError('form-message', 'Vui lòng nhập nội dung (ít nhất 10 ký tự)');
                isValid = false;
            }

            return isValid;
        };

        // Add real-time validation on blur
        ['form-name', 'form-phone', 'form-email', 'form-message'].forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.addEventListener('blur', () => {
                    if (field.value.trim()) {
                        clearFieldError(fieldId);
                    }
                });
            }
        });

        // 1. Initial Status Check (returning users)
        fetch(themeData.ajaxurl + '?action=laca_check_submission_status')
            .then(response => response.json())
            .then(res => {
                if (res.success && res.data.submitted) {
                    Swal.fire({
                        title: 'Chào bạn quay lại!',
                        text: res.data.message,
                        icon: 'info',
                        showCancelButton: true,
                        confirmButtonText: 'Tôi muốn gửi tiếp',
                        cancelButtonText: 'Đã rõ',
                        ...getThemeColors()
                    }).then((result) => {
                        if (result.isConfirmed) {
                            form.setAttribute('data-resubmit', 'true');
                        }
                    });
                }
            })
            .catch(err => console.error("Error checking submission status:", err));

        // 2. Form Submission Handler
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            // Client-side validation
            if (!validateForm()) {
                return;
            }

            // UI: Start loading state
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
            submitBtn.setAttribute('aria-busy', 'true');
            if (formStatus) {
                formStatus.textContent = 'Đang gửi...';
            }

            const processSubmit = function(token) {
                document.getElementById('recaptcha-response').value = token;

                // Prepare FormData
                const formData = new FormData(form);
                if (form.getAttribute('data-resubmit') === 'true') {
                    formData.append('resubmit_confirmed', 'true');
                }

                // Send AJAX Request
                fetch(themeData.ajaxurl, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(res => {
                    // UI: Stop loading
                    submitBtn.classList.remove('loading');
                    submitBtn.disabled = false;
                    submitBtn.setAttribute('aria-busy', 'false');
                    if (formStatus) {
                        formStatus.textContent = '';
                    }

                    if (res.success) {
                        // Success
                        Swal.fire({
                            title: '✓ Thành công!',
                            text: res.data.message,
                            icon: 'success',
                            confirmButtonText: 'Đóng',
                            ...getThemeColors()
                        });
                        form.reset();
                        clearAllErrors();
                    } else {
                        if (res.data && res.data.code === 'recently_submitted') {
                            Swal.fire({
                                title: '⚠ Thông báo',
                                text: res.data.message,
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonText: 'Tôi vẫn muốn gửi',
                                cancelButtonText: 'Để sau',
                                ...getThemeColors()
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    form.setAttribute('data-resubmit', 'true');
                                    Swal.fire({
                                        text: '✓ Bạn có thể gửi lại.',
                                        icon: 'success',
                                        timer: 1500,
                                        showConfirmButton: false,
                                        ...getThemeColors()
                                    });
                                }
                            });
                        } else {
                            const errorMessage = (res.data && res.data.message) || 'Đã có lỗi xảy ra.';
                            Swal.fire({
                                title: 'Thất bại',
                                text: errorMessage,
                                icon: 'error',
                                confirmButtonText: 'Thử lại',
                                ...getThemeColors()
                            });
                        }
                    }
                })
                .catch(err => {
                    submitBtn.classList.remove('loading');
                    submitBtn.disabled = false;
                    submitBtn.setAttribute('aria-busy', 'false');
                    if (formStatus) {
                        formStatus.textContent = '';
                    }

                    console.error('Network error:', err);
                    Swal.fire({
                        title: '✕ Lỗi kết nối',
                        text: 'Không thể kết nối đến máy chủ. Vui lòng kiểm tra kết nối internet.',
                        icon: 'error',
                        confirmButtonText: 'Đã hiểu',
                        ...getThemeColors()
                    });
                });
            };

            // Execute reCAPTCHA (if available)
            if (typeof grecaptcha !== 'undefined' && siteKey) {
                grecaptcha.ready(function() {
                    grecaptcha.execute(siteKey, {action: 'contact'}).then(processSubmit);
                });
            } else {
                // Fallback: Submit without reCAPTCHA
                console.warn("reCAPTCHA not loaded or siteKey missing, bypassing verification");
                processSubmit('');
            }
        });

    } catch (err) {
        console.error("Critical error in contact page:", err);
        
        // Safe alert fallback if Swal is not available
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Lỗi JavaScript',
                html: 'Đã xảy ra lỗi: <code>' + err.message + '</code><br><small>Vui lòng thử hard reload (Cmd+Shift+R)</small>',
                icon: 'error',
                confirmButtonText: 'Đã hiểu'
            });
        } else {
            alert('Lỗi JavaScript: ' + err.message + '\n\nVui lòng hard reload trang (Cmd+Shift+R hoặc Ctrl+Shift+F5)');
        }
    }
};
