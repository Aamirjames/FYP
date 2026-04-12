document.addEventListener('DOMContentLoaded', function () {

    const forms = document.querySelectorAll('form.needs-validation');

    forms.forEach((form) => {
        form.addEventListener('submit', (event) => {
            validatePasswordMinLength();
            validatePasswordsMatch();

            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });


    const pwd = document.getElementById('password');
    const cpwd = document.getElementById('confirm_password');

    function validatePasswordMinLength() {
        if (!pwd) return;

        if (!pwd.value) {
            pwd.setCustomValidity('');
            pwd.classList.remove('is-invalid', 'is-valid');
            return;
        }

        const ok = pwd.value.length >= 6;
        pwd.setCustomValidity(ok ? '' : 'Password must be at least 6 characters');

        // Only mark invalid — never force green here
        pwd.classList.toggle('is-invalid', !ok);
        if (ok) pwd.classList.remove('is-invalid');
    }

    function validatePasswordsMatch() {
        if (!pwd || !cpwd) return;

        if (!cpwd.value) {
            cpwd.setCustomValidity('');
            cpwd.classList.remove('is-invalid', 'is-valid');
            return;
        }

        const same = (pwd.value === cpwd.value);
        cpwd.setCustomValidity(same ? '' : 'Passwords do not match');

        // Only mark invalid — never force green here
        cpwd.classList.toggle('is-invalid', !same);
        if (same) cpwd.classList.remove('is-invalid');
    }

    if (pwd) {
        pwd.addEventListener('input', () => {
            validatePasswordMinLength();
            validatePasswordsMatch();
        });
    }
    if (cpwd) {
        cpwd.addEventListener('input', () => {
            validatePasswordMinLength();
            validatePasswordsMatch();
        });
    }
});