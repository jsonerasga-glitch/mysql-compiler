<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';

ensure_session();
$_SESSION = [];
session_destroy();

json_response(['ok' => true]);
