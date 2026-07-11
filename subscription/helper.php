<?php
$encryption = include DOCUMENT_ROOT . '/config/encryption.php';

function encryptData($data)
{
    global $encryption;
    $cipher = $encryption['encryption_method'];
    $secretKey = $encryption['secret_key'];
    $key = hash('sha256', $secretKey, true);

    $ivLength = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivLength);

    $encrypted = openssl_encrypt($data, $cipher, $key, OPENSSL_RAW_DATA, $iv);

    // Combine IV + encrypted data
    return base64_encode($iv . $encrypted);
}

function decryptData($encryptedData)
{
    global $encryption;
    $cipher = $encryption['encryption_method'];
    $secretKey = $encryption['secret_key'];
    $key = hash('sha256', $secretKey, true);

    $data = base64_decode($encryptedData);
    $ivLength = openssl_cipher_iv_length($cipher);

    $iv = substr($data, 0, $ivLength);
    $encrypted = substr($data, $ivLength);

    return openssl_decrypt($encrypted, $cipher, $key, OPENSSL_RAW_DATA, $iv);
}
