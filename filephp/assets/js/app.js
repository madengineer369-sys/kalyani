/**
 * SecureShop — Client-Side JavaScript
 * Client-side is UX ONLY — all security validation is on the server.
 */
(function () {
    'use strict';

    // ===== DOM Ready =====
    document.addEventListener('DOMContentLoaded', function () {
        initNavScroll();
        initPasswordStrength();
        initPasswordToggle();
        initClientValidation();
        initSessionTimeout();
        initConfirmModals();
        initToasts();
        initScrollTop();
        initSearchAutocomplete();
        initQuantityInputs();
        initCartAjax();
        initTooltips();
        showFlashAsToast();
    });

    // ===== Navbar Scroll Effect =====
    function initNavScroll() {
        var nav = document.getElementById('mainNav');
        if (!nav) return;
        window.addEventListener('scroll', function () {
            nav.classList.toggle('scrolled', window.scrollY > 20);
        }, { passive: true });
    }

    // ===== Password Strength Meter =====
    function initPasswordStrength() {
        var inputs = document.querySelectorAll('[data-pwd-strength]');
        inputs.forEach(function (input) {
            var barId    = input.dataset.pwdStrength;
            var bar      = document.getElementById(barId + '-fill');
            var label    = document.getElementById(barId + '-label');
            if (!bar) return;

            var labels = ['Too Weak', 'Weak', 'Fair', 'Strong', 'Very Strong'];
            var colors = ['#ff4757', '#ff6b6b', '#ffc107', '#4ecdc4', '#20c997'];

            input.addEventListener('input', function () {
                var score = getPasswordScore(input.value);
                bar.className = 'pwd-strength-fill s' + score;
                bar.style.background = colors[score];
                if (label) {
                    label.textContent = input.value.length ? labels[score] : '';
                    label.style.color = colors[score];
                }
            });
        });
    }

    function getPasswordScore(pwd) {
        if (!pwd) return 0;
        var score = 0;
        if (pwd.length >= 8)  score++;
        if (pwd.length >= 12) score++;
        if (/[A-Z]/.test(pwd) && /[a-z]/.test(pwd)) score++;
        if (/[0-9]/.test(pwd) && /[\W_]/.test(pwd)) score++;
        return score;
    }

    // ===== Password Toggle (show/hide) =====
    function initPasswordToggle() {
        document.querySelectorAll('[data-pwd-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var targetId = btn.dataset.pwdToggle;
                var input    = document.getElementById(targetId);
                if (!input) return;
                var isText = input.type === 'text';
                input.type = isText ? 'password' : 'text';
                var icon = btn.querySelector('i');
                if (icon) icon.className = isText ? 'bi bi-eye' : 'bi bi-eye-slash';
            });
        });
    }

    // ===== Client-Side Form Validation (UX only) =====
    function initClientValidation() {
        var forms = document.querySelectorAll('.needs-validation');
        forms.forEach(function (form) {
            form.addEventListener('submit', function (e) {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                form.classList.add('was-validated');
            });
        });
    }

    // ===== Session Timeout =====
    function initSessionTimeout() {
        var dataEl   = document.getElementById('session-data');
        if (!dataEl) return;

        var loggedIn = dataEl.dataset.loggedIn === '1';
        if (!loggedIn) return;

        var remaining  = parseInt(dataEl.dataset.remaining, 10) || 0;
        var warning    = parseInt(dataEl.dataset.warning, 10)   || 300;
        var modal      = document.getElementById('sessionTimeoutModal');
        var countdown  = document.getElementById('countdown');
        var extendBtn  = document.getElementById('extendSession');
        var bsModal    = modal ? new bootstrap.Modal(modal) : null;
        var warned     = false;

        if (remaining <= 0) return;

        var tick = setInterval(function () {
            remaining--;
            if (remaining <= 0) {
                clearInterval(tick);
                window.location.href = 'logout.php?reason=timeout';
                return;
            }
            // Show warning modal when within warning window
            if (remaining <= warning && !warned && bsModal) {
                warned = true;
                bsModal.show();
            }
            // Update countdown
            if (countdown && remaining <= warning) {
                var m = Math.floor(remaining / 60);
                var s = remaining % 60;
                countdown.textContent = m + ':' + (s < 10 ? '0' : '') + s;
            }
        }, 1000);

        // Extend session via invisible ping
        if (extendBtn) {
            extendBtn.addEventListener('click', function () {
                fetch('dashboard.php?ping=1', { credentials: 'same-origin' })
                    .then(function () {
                        remaining = parseInt(dataEl.dataset.timeout, 10) || 1800;
                        warned = false;
                        if (bsModal) bsModal.hide();
                        showToast('Session extended successfully.', 'success');
                    })
                    .catch(function () {
                        window.location.reload();
                    });
            });
        }
    }

    // ===== Confirm Modals =====
    function initConfirmModals() {
        var modal   = document.getElementById('confirmModal');
        var okBtn   = document.getElementById('confirmModalOk');
        var bodyEl  = document.getElementById('confirmModalBody');
        if (!modal || !okBtn) return;

        var bsModal     = new bootstrap.Modal(modal);
        var pendingForm = null;
        var pendingHref = null;

        document.querySelectorAll('[data-confirm]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                var msg = el.dataset.confirm || 'Are you sure? This action cannot be undone.';
                if (bodyEl) bodyEl.textContent = msg;

                if (el.tagName === 'A') {
                    pendingHref = el.href;
                    pendingForm = null;
                } else if (el.type === 'submit') {
                    pendingForm = el.closest('form');
                    pendingHref = null;
                }

                bsModal.show();
            });
        });

        okBtn.addEventListener('click', function () {
            bsModal.hide();
            if (pendingHref) { window.location.href = pendingHref; }
            if (pendingForm) { pendingForm.submit(); }
        });
    }

    // ===== Toast System =====
    function initToasts() {
        window.showToast = showToast;
    }

    function showToast(message, type, duration) {
        type     = type     || 'info';
        duration = duration || 4000;

        var container = document.getElementById('toastContainer');
        if (!container) return;

        var icons = { success: 'check-circle-fill', danger: 'exclamation-triangle-fill', warning: 'exclamation-circle-fill', info: 'info-circle-fill' };
        var icon  = icons[type] || 'bell-fill';

        var toast = document.createElement('div');
        toast.className = 'toast toast-notification toast-' + type + ' show align-items-center mb-2';
        toast.setAttribute('role', 'alert');
        toast.innerHTML = '<div class="d-flex align-items-start gap-3 p-3">'
            + '<i class="bi bi-' + icon + ' mt-1 flex-shrink-0"></i>'
            + '<div class="flex-grow-1 small">' + escapeHtml(message) + '</div>'
            + '<button type="button" class="btn-close btn-close-white ms-auto flex-shrink-0" style="font-size:.7rem"></button>'
            + '</div>';

        container.appendChild(toast);

        toast.querySelector('.btn-close').addEventListener('click', function () {
            toast.remove();
        });

        setTimeout(function () {
            toast.classList.remove('show');
            setTimeout(function () { toast.remove(); }, 300);
        }, duration);
    }

    // Convert alert flash messages to toasts after a delay
    function showFlashAsToast() {
        var flashContainer = document.getElementById('flash-container');
        if (!flashContainer) return;

        setTimeout(function () {
            var alerts = flashContainer.querySelectorAll('.alert');
            alerts.forEach(function (alert) {
                // Extract type from class
                var type = 'info';
                if (alert.classList.contains('alert-success')) type = 'success';
                if (alert.classList.contains('alert-danger'))  type = 'danger';
                if (alert.classList.contains('alert-warning')) type = 'warning';
                var msg = alert.textContent.trim();
                showToast(msg, type);
                alert.remove();
            });
        }, 200);
    }

    // ===== Scroll To Top =====
    function initScrollTop() {
        var btn = document.getElementById('scrollTop');
        if (!btn) return;

        window.addEventListener('scroll', function () {
            btn.classList.toggle('visible', window.scrollY > 300);
        }, { passive: true });

        btn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // ===== Search Autocomplete (basic) =====
    function initSearchAutocomplete() {
        var input = document.querySelector('.search-input');
        if (!input) return;

        var debounceTimer;
        input.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            var q = input.value.trim();
            if (q.length < 2) return;

            debounceTimer = setTimeout(function () {
                // Could implement AJAX search suggestions here
                // For now, just submits on Enter
            }, 300);
        });
    }

    // ===== Quantity Inputs =====
    function initQuantityInputs() {
        document.querySelectorAll('.qty-control').forEach(function (ctrl) {
            var dec   = ctrl.querySelector('[data-qty-dec]');
            var inc   = ctrl.querySelector('[data-qty-inc]');
            var input = ctrl.querySelector('input[type="number"]');
            if (!input) return;

            if (dec) dec.addEventListener('click', function () {
                var val = parseInt(input.value, 10);
                var min = parseInt(input.min, 10) || 1;
                input.value = Math.max(min, val - 1);
                input.dispatchEvent(new Event('change'));
            });

            if (inc) inc.addEventListener('click', function () {
                var val = parseInt(input.value, 10);
                var max = parseInt(input.max, 10) || 999;
                input.value = Math.min(max, val + 1);
                input.dispatchEvent(new Event('change'));
            });
        });
    }

    // ===== Cart AJAX updates =====
    function initCartAjax() {
        // Auto-submit quantity change forms in cart page
        document.querySelectorAll('.cart-qty-input').forEach(function (input) {
            input.addEventListener('change', function () {
                var form = input.closest('form');
                if (form) form.submit();
            });
        });
    }

    // ===== Bootstrap Tooltips =====
    function initTooltips() {
        var triggers = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        triggers.forEach(function (el) {
            new bootstrap.Tooltip(el);
        });
    }

    // ===== HTML Escape (for toast) =====
    function escapeHtml(str) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(str));
        return d.innerHTML;
    }

    // ===== Scroll-to-Top button DOM insert =====
    var scrollBtn = document.createElement('div');
    scrollBtn.id = 'scrollTop';
    scrollBtn.innerHTML = '<i class="bi bi-chevron-up"></i>';
    scrollBtn.title = 'Back to top';
    document.body.appendChild(scrollBtn);

})();
