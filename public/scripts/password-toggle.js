document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.password-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var field = btn.closest('.password-field');
            if (!field) return;

            var input = field.querySelector('input');
            var icon = btn.querySelector('i');
            if (!input || !icon) return;

            var showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            icon.classList.toggle('bi-eye', showing);
            icon.classList.toggle('bi-eye-slash', !showing);

            var label = showing ? 'Mostrar palavra-passe' : 'Ocultar palavra-passe';
            btn.setAttribute('aria-label', label);
            btn.setAttribute('title', label);
        });
    });
});
