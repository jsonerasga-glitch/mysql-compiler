<?php
require_once __DIR__ . '/../config/config.php';

function encrypt_secret(string $plain): string
{
    $ivLength = openssl_cipher_iv_length(ENCRYPTION_CIPHER);
    $iv = openssl_random_pseudo_bytes($ivLength);
    $cipherText = openssl_encrypt($plain, ENCRYPTION_CIPHER, ENCRYPTION_KEY, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $cipherText);
}

function decrypt_secret(?string $encoded): string
{
    if ($encoded === null || $encoded === '') {
        return '';
    }

    $raw = base64_decode($encoded);
    $ivLength = openssl_cipher_iv_length(ENCRYPTION_CIPHER);
    $iv = substr($raw, 0, $ivLength);
    $cipherText = substr($raw, $ivLength);
    $plain = openssl_decrypt($cipherText, ENCRYPTION_CIPHER, ENCRYPTION_KEY, OPENSSL_RAW_DATA, $iv);

    return $plain === false ? '' : $plain;
}
