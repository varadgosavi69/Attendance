document.addEventListener('DOMContentLoaded', () => {
    // Password Toggle Visibility
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');

    togglePassword.addEventListener('click', function () {
        // Toggle the type attribute
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);

        // Toggle the eye icon
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });

    // Form Validation and Animation
    const loginForm = document.getElementById('loginForm');
    const inputs = document.querySelectorAll('.input-wrapper input');

    // Add focus class to wrapper on input focus
    inputs.forEach(input => {
        input.addEventListener('focus', () => {
            input.parentElement.parentElement.classList.add('focused');
        });

        input.addEventListener('blur', () => {
            if (input.value === '') {
                input.parentElement.parentElement.classList.remove('focused');
            }
        });
    });

    loginForm.addEventListener('submit', (e) => {
        e.preventDefault(); // Handle via AJAX

        const username = document.getElementById('username').value;
        const password = document.getElementById('password').value;
        let isValid = true;

        if (!username) {
            isValid = false;
            shakeInput(document.getElementById('username'));
        }

        if (!password) {
            isValid = false;
            shakeInput(document.getElementById('password'));
        }

        if (isValid) {
            // Show loading state
            const btn = document.querySelector('.btn-login');
            const originalBtnText = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Signing In...';
            btn.style.opacity = '0.8';
            btn.style.pointerEvents = 'none';

            // AJAX Request
            const formData = new FormData();
            formData.append('username', username);
            formData.append('password', password);

            fetch('api/login.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = data.redirect || 'dashboard.php';
                    } else {
                        // Reset button
                        btn.innerHTML = originalBtnText;
                        btn.style.opacity = '1';
                        btn.style.pointerEvents = 'auto';
                        alert(data.message || 'Login failed');
                        shakeInput(document.getElementById('username'));
                        shakeInput(document.getElementById('password'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Reset button
                    btn.innerHTML = originalBtnText;
                    btn.style.opacity = '1';
                    btn.style.pointerEvents = 'auto';
                    alert('An error occurred. Please try again.');
                });
        }
    });

    function shakeInput(input) {
        const wrapper = input.parentElement;
        wrapper.style.animation = 'shake 0.5s';
        wrapper.style.borderColor = '#ef233c'; // Red error color

        // Remove animation after it plays
        setTimeout(() => {
            wrapper.style.animation = '';
            wrapper.style.borderColor = '#e0e0e0'; // Reset color (or keep it red until typo fixed)
        }, 500);
    }
});

// Inject Shake Keyframes
const style = document.createElement('style');
style.innerHTML = `
    @keyframes shake {
        0% { transform: translateX(0); }
        25% { transform: translateX(-10px); }
        50% { transform: translateX(10px); }
        75% { transform: translateX(-10px); }
        100% { transform: translateX(0); }
    }
`;
document.head.appendChild(style);
