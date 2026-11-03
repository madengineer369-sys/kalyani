/**
 * BLACK STONE LUXURY E-COMMERCE JAVASCRIPT (APP.JS)
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Navbar scroll effect
    const mainNav = document.getElementById('mainNav');
    if (mainNav) {
        window.addEventListener('scroll', () => {
            if (window.scrollY > 40) {
                mainNav.classList.add('scrolled');
            } else {
                mainNav.classList.remove('scrolled');
            }
        });
    }

    // 2. OTP 6-Box Auto-Advancing Inputs
    const otpBoxes = document.querySelectorAll('.otp-single-box');
    const otpFullInput = document.getElementById('full_otp_input');
    
    if (otpBoxes.length > 0) {
        otpBoxes[0].focus();

        otpBoxes.forEach((box, index) => {
            box.addEventListener('input', (e) => {
                const val = e.target.value.replace(/[^0-9]/g, '');
                box.value = val ? val.slice(-1) : '';

                if (box.value && index < otpBoxes.length - 1) {
                    otpBoxes[index + 1].focus();
                }
                syncFullOtp();
            });

            box.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !box.value && index > 0) {
                    otpBoxes[index - 1].focus();
                }
            });

            box.addEventListener('paste', (e) => {
                e.preventDefault();
                const pasteData = (e.clipboardData || window.clipboardData).getData('text').trim();
                const digits = pasteData.replace(/[^0-9]/g, '').slice(0, otpBoxes.length);
                
                digits.split('').forEach((char, i) => {
                    if (otpBoxes[i]) {
                        otpBoxes[i].value = char;
                    }
                });

                if (digits.length === otpBoxes.length) {
                    otpBoxes[otpBoxes.length - 1].focus();
                } else if (otpBoxes[digits.length]) {
                    otpBoxes[digits.length].focus();
                }
                syncFullOtp();
            });
        });

        function syncFullOtp() {
            let combined = '';
            otpBoxes.forEach(b => combined += b.value);
            if (otpFullInput) {
                otpFullInput.value = combined;
            }
            if (combined.length === 6) {
                const form = document.getElementById('otpVerifyForm');
                if (form) form.submit();
            }
        }
    }

    // 3. Countdown timer for OTP resend
    const countdownEl = document.getElementById('resendCountdown');
    const resendBtn   = document.getElementById('resendBtn');
    if (countdownEl && resendBtn) {
        let seconds = 60;
        const timer = setInterval(() => {
            seconds--;
            if (seconds <= 0) {
                clearInterval(timer);
                countdownEl.style.display = 'none';
                resendBtn.classList.remove('disabled');
                resendBtn.removeAttribute('disabled');
            } else {
                countdownEl.textContent = `(${seconds}s)`;
            }
        }, 1000);
    }

    // 4. Image Upload Preview
    const fileInput = document.getElementById('productImageInput');
    const previewImg = document.getElementById('productImagePreview');
    if (fileInput && previewImg) {
        fileInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    previewImg.style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        });
    }

    // 5. Size selector pills on product cards
    document.querySelectorAll('.size-selector-row').forEach(row => {
        row.querySelectorAll('.size-pill').forEach(pill => {
            pill.addEventListener('click', (e) => {
                e.preventDefault();
                row.querySelectorAll('.size-pill').forEach(p => p.classList.remove('active'));
                pill.classList.add('active');
                const card = row.closest('.product-card');
                if (card) {
                    const hiddenSize = card.querySelector('input[name="selected_size"]');
                    if (hiddenSize) hiddenSize.value = pill.getAttribute('data-size');
                }
            });
        });
    });
});

// Cart Drawer Controller
function toggleCart(open) {
    const drawer = document.getElementById('cartDrawer');
    if (!drawer) return;
    if (open) {
        drawer.classList.add('open');
        document.body.style.overflow = 'hidden';
    } else {
        drawer.classList.remove('open');
        document.body.style.overflow = '';
    }
}
