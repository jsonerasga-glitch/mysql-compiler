<?php

function get_client_ip(): ?string
{
    // Behind a proxy/load balancer, the real client IP is the first entry
    // in X-Forwarded-For. Note this header is client-suppliable and can be
    // spoofed, so only trust it if this app is deployed behind a proxy you
    // control; otherwise REMOTE_ADDR alone is authoritative.
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($forwarded !== '') {
        $ip = trim(explode(',', $forwarded)[0]);
        if ($ip !== '') {
            return $ip;
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? null;
}
