(function () {
  'use strict';

  const form = document.getElementById('login-form');
  const errorBox = document.getElementById('login-error');
  const btn = document.getElementById('btn-login');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    errorBox.classList.add('d-none');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Connecting...';

    const payload = {
      host: document.getElementById('login-host').value.trim(),
      port: parseInt(document.getElementById('login-port').value, 10) || 3306,
      username: document.getElementById('login-username').value.trim(),
      password: document.getElementById('login-password').value,
      database_name: document.getElementById('login-database').value.trim(),
    };

    try {
      const res = await fetch('api/login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();

      if (!res.ok) {
        throw new Error(data.error || 'Login failed.');
      }

      if (data.ok) {
        window.location.href = 'index.php';
      } else {
        errorBox.textContent = data.message || 'Could not connect. Check your credentials.';
        errorBox.classList.remove('d-none');
      }
    } catch (err) {
      errorBox.textContent = err.message;
      errorBox.classList.remove('d-none');
    } finally {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-plug"></i> Connect';
    }
  });
})();
