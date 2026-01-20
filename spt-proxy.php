<?php
// spt-proxy.php
header('Content-Type: application/json');

// URL Web App Apps Script
$apiUrl = 'https://script.google.com/macros/s/AKfycby7RonxOoGNnb8AQAOElECCssdW9Q8g8rLuvD3h1QVZv9GDjDoboKz6TF-G8fYGs7W0/exec';

// Ambil data dari Apps Script
$result = file_get_contents($apiUrl);

// Kalau gagal ambil, kirimkan array kosong biar nggak error di JS
if ($result === FALSE) {
    echo json_encode([]);
    exit;
}

// Keluarkan ke browser
echo $result;
