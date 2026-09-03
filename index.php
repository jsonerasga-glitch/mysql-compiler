<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_login_page();
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>MySQL Client</title>

<link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/vendor/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/vendor/codemirror/lib/codemirror.min.css" rel="stylesheet">
<link href="assets/vendor/codemirror/theme/eclipse.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand navbar-dark bg-dark px-2 app-navbar">
  <button id="btn-toggle-sidebar" class="btn btn-dark d-md-none p-1 me-1" title="Toggle sidebar">
    <i class="bi bi-list fs-4"></i>
  </button>
  <span class="navbar-brand mb-0"><i class="bi bi-database-fill-gear me-1"></i>MySQL Client</span>

  <div class="d-flex align-items-center gap-2 ms-md-3 navbar-selects">
    <select id="active-connection-select" class="form-select form-select-sm" disabled>
      <option value="">No connection selected</option>
    </select>
    <select id="active-database-select" class="form-select form-select-sm" disabled>
      <option value="">(default database)</option>
    </select>
  </div>

  <div class="ms-auto d-flex align-items-center gap-2 navbar-actions">
    <span id="connection-status" class="badge text-bg-secondary">Not connected</span>
    <button id="btn-highlight-line" class="btn btn-outline-warning btn-sm" title="Highlight statement at cursor">
      <i class="bi bi-highlighter"></i> Highlight <kbd class="ms-1 d-none d-sm-inline">Ctrl+Alt+H</kbd>
    </button>
    <button id="btn-run" class="btn btn-success btn-sm" disabled>
      <i class="bi bi-play-fill"></i> Run <kbd class="ms-1 d-none d-sm-inline">Ctrl+Enter</kbd>
    </button>
    <button id="btn-logout" class="btn btn-outline-light btn-sm" title="Logout">
      <i class="bi bi-box-arrow-right"></i> <span class="d-none d-sm-inline">Logout</span>
    </button>
  </div>
</nav>
<div id="sidebar-backdrop"></div>

<div class="app-body">

  <aside class="sidebar border-end">
    <div class="sidebar-header d-flex align-items-center justify-content-between px-2 py-2 border-bottom">
      <strong class="small text-uppercase text-secondary">Connections</strong>
      <div class="d-flex align-items-center gap-1">
        <button id="btn-new-connection" class="btn btn-sm btn-primary py-0 px-1" title="New Connection">
          <i class="bi bi-plus-lg"></i>
        </button>
        <button id="btn-close-sidebar" class="btn btn-sm btn-outline-secondary py-0 px-1 d-md-none" title="Close">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
    </div>
    <ul id="connection-list" class="list-group list-group-flush connection-list"></ul>

    <div class="sidebar-header d-flex align-items-center justify-content-between px-2 py-2 border-bottom border-top">
      <strong class="small text-uppercase text-secondary">Schema</strong>
      <button id="btn-refresh-schema" class="btn btn-sm btn-outline-secondary py-0 px-1" title="Refresh schema" disabled>
        <i class="bi bi-arrow-clockwise"></i>
      </button>
    </div>
    <div id="schema-tree" class="schema-tree px-1 py-1">
      <div class="text-muted small px-2 py-3">Connect to a database to browse its schema.</div>
    </div>
  </aside>

  <main class="main-area">
    <div class="editor-pane">
      <div class="editor-toolbar d-flex align-items-center justify-content-between px-2 py-1 border-bottom bg-light">
        <span class="small text-secondary"><i class="bi bi-code-slash"></i> SQL Editor</span>
        <button id="btn-clear-editor" class="btn btn-sm btn-outline-secondary py-0 px-2">Clear</button>
      </div>
      <textarea id="sql-editor">SELECT 1;</textarea>
    </div>

    <div class="results-pane">
      <ul class="nav nav-tabs px-2 pt-1" id="results-tabs">
        <li class="nav-item">
          <button class="nav-link active" data-tab="grid">Result Grid</button>
        </li>
        <li class="nav-item">
          <button class="nav-link" data-tab="messages">Messages</button>
        </li>
      </ul>

      <div class="results-content">
        <div id="tab-grid" class="results-tab-panel h-100">
          <div id="result-grid-wrapper" class="h-100 overflow-auto">
            <div class="text-muted small p-3">No results yet. Run a query to see output here.</div>
          </div>
        </div>
        <div id="tab-messages" class="results-tab-panel h-100 d-none">
          <div id="messages-log" class="h-100 overflow-auto p-2 font-monospace small"></div>
        </div>
      </div>

      <div class="results-statusbar px-2 py-1 border-top small text-secondary d-flex justify-content-between">
        <span id="status-left">Ready.</span>
        <span id="status-right"></span>
      </div>
    </div>
  </main>
</div>

<!-- Add / Edit Connection Modal -->
<div class="modal fade" id="connection-modal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="connection-form">
        <div class="modal-header">
          <h5 class="modal-title" id="connection-modal-title">New Connection</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="conn-id">
          <div class="mb-2">
            <label class="form-label small">Connection Name</label>
            <input type="text" class="form-control form-control-sm" id="conn-name" required placeholder="My Local MySQL">
          </div>
          <div class="row g-2 mb-2">
            <div class="col-8">
              <label class="form-label small">Host</label>
              <input type="text" class="form-control form-control-sm" id="conn-host" required placeholder="127.0.0.1">
            </div>
            <div class="col-4">
              <label class="form-label small">Port</label>
              <input type="number" class="form-control form-control-sm" id="conn-port" value="3306">
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label small">Username</label>
            <input type="text" class="form-control form-control-sm" id="conn-username" required placeholder="root">
          </div>
          <div class="mb-2">
            <label class="form-label small">Password</label>
            <input type="password" class="form-control form-control-sm" id="conn-password" placeholder="(leave blank to keep existing)">
          </div>
          <div class="mb-2">
            <label class="form-label small">Default Database <span class="text-muted">(optional)</span></label>
            <input type="text" class="form-control form-control-sm" id="conn-database" placeholder="">
          </div>
          <div id="test-connection-result" class="small mt-2"></div>
        </div>
        <div class="modal-footer">
          <button type="button" id="btn-test-connection" class="btn btn-outline-secondary btn-sm me-auto">
            <i class="bi bi-plug"></i> Test Connection
          </button>
          <button type="button" id="btn-delete-connection" class="btn btn-outline-danger btn-sm d-none">Delete</button>
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/codemirror/lib/codemirror.min.js"></script>
<script src="assets/vendor/codemirror/mode/sql/sql.min.js"></script>
<script>window.APP_CONFIG = { labExamMode: <?= LAB_EXAM_MODE ? 'true' : 'false' ?> };</script>
<script src="assets/js/app.js"></script>
</body>
</html>
