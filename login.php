<?php
require_once __DIR__ . '/includes/auth.php';

ensure_session();
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}
$asset_v = date('Ymd');
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SQL Client — Login</title>

<link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/vendor/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/css/style.css?v=<?= $asset_v ?>" rel="stylesheet">
</head>
<body class="login-body">

<div class="login-wrapper d-flex align-items-center justify-content-center">
  <div class="card login-card shadow-sm">
    <div class="card-body p-4">
      <div class="text-center mb-3">
        <i class="bi bi-database-fill-gear display-5 text-primary"></i>
        <h4 class="mt-2 mb-0">SQL Client</h4>
        <div class="text-muted small">Connect to a MySQL server</div>
      </div>

      <form id="login-form">
        <div class="mb-2">
          <label class="form-label small">Host</label>
          <input type="text" class="form-control" id="login-host" required placeholder="127.0.0.1" autofocus>
        </div>
        <div class="row g-2 mb-2">
          <div class="col-8">
            <label class="form-label small">Username</label>
            <input type="text" class="form-control" id="login-username" required placeholder="root">
          </div>
          <div class="col-4">
            <label class="form-label small">Port</label>
            <input type="number" class="form-control" id="login-port" value="3306">
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label small">Password</label>
          <input type="password" class="form-control" id="login-password" placeholder="">
        </div>
        <div class="mb-3">
          <label class="form-label small">Database <span class="text-muted">(optional)</span></label>
          <input type="text" class="form-control" id="login-database" placeholder="">
        </div>

        <div id="login-error" class="alert alert-danger py-1 px-2 small d-none"></div>

        <button type="submit" id="btn-login" class="btn btn-primary w-100">
          <i class="bi bi-plug"></i> Connect
        </button>
      </form>
    </div>
  </div>
</div>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/login.js?v=<?= $asset_v ?>"></script>
</body>
</html>
