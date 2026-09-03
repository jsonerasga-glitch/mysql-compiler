<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/exam.php';

require_login_page();
require_exam_admin_page();
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Exam Setup — MySQL Client</title>

<link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/vendor/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="exam-setup-body">

<nav class="navbar navbar-expand navbar-dark bg-dark px-2 app-navbar">
  <span class="navbar-brand mb-0"><i class="bi bi-mortarboard-fill me-1"></i>Exam Setup</span>
  <div class="ms-auto d-flex align-items-center gap-2">
    <a href="index.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i> SQL Client</a>
    <button id="btn-logout" class="btn btn-outline-light btn-sm" title="Logout">
      <i class="bi bi-box-arrow-right"></i> <span class="d-none d-sm-inline">Logout</span>
    </button>
  </div>
</nav>

<div class="container-fluid p-3">
  <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
    <div>
      <h5 class="mb-0">Exams</h5>
      <div class="text-muted small">
        Assign a MySQL username per exam, then Sync to GRANT SELECT on that exam's
        reference tables in <code>dbms_exam</code> to the assigned user.
      </div>
    </div>
    <button id="btn-refresh-exams" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-clockwise"></i> Refresh
    </button>
  </div>

  <div id="exams-error" class="alert alert-danger py-1 px-2 small d-none"></div>

  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle">
      <thead>
        <tr>
          <th style="width:90px">Exam ID</th>
          <th style="width:240px">Username</th>
          <th style="width:110px">Question</th>
          <th style="width:110px">Sync</th>
          <th style="width:110px">Check</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody id="exams-tbody">
        <tr><td colspan="6" class="text-muted small">Loading...</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Question Modal -->
<div class="modal fade" id="question-modal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Exam Question <span id="question-modal-exam-id" class="text-muted small"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <pre id="question-modal-body" class="mb-0" style="white-space: pre-wrap; font-family: inherit;"></pre>
      </div>
    </div>
  </div>
</div>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/exam_setup.js"></script>
</body>
</html>
