<?php
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/db.php';

function ensure_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function is_logged_in(): bool
{
    ensure_session();
    return !empty($_SESSION['logged_in']) && !empty($_SESSION['connection_id']);
}

// The login username identifies "who" for the purpose of scoping saved
// connections so users can't see each other's. Sessions created before this
// field existed won't have it yet, so fall back to looking it up from the
// session's own active connection and cache it for next time.
function current_owner_username(): ?string
{
    ensure_session();

    if (!empty($_SESSION['username'])) {
        return $_SESSION['username'];
    }

    if (!empty($_SESSION['connection_id'])) {
        $stmt = app_db()->prepare('SELECT username FROM connections WHERE id = ?');
        $stmt->execute([$_SESSION['connection_id']]);
        $username = $stmt->fetchColumn();
        if ($username) {
            $_SESSION['username'] = $username;
            return $username;
        }
    }

    return null;
}

// Used by page scripts (index.php) — sends a browser redirect.
function require_login_page(): void
{
    ensure_session();
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

// Used by api/*.php scripts — sends a JSON 401 instead of HTML.
function require_login_api(): void
{
    ensure_session();
    if (!is_logged_in()) {
        json_error('Not authenticated. Please log in.', 401);
    }
}
