<?php
require __DIR__ . '/../includes/auth.php';
$pdo = getDbConnection();

// Check if penanganan_gangguan exists
$check = $pdo->prepare("SELECT id FROM monitoring_categories WHERE code = ?");
$check->execute(['penanganan_gangguan']);
$row1 = $check->fetch();
if (!$row1) {
    $stmt = $pdo->prepare("
        INSERT INTO monitoring_categories 
        (code, name, short_title, description, icon, material_icon, badge_color, target_annual, unit)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        'penanganan_gangguan',
        'Penanganan Gangguan Frekuensi Radio',
        'Penanganan Gangguan',
        'Investigasi, pelacakan arah sinyal (DF), dan eliminasi gangguan/interferensi frekuensi radio merugikan (harmful interference).',
        'fa-triangle-exclamation',
        'report_problem',
        'warning',
        30,
        'Kasus Gangguan'
    ]);
    echo "Inserted Penanganan Gangguan.\n";
} else {
    echo "Penanganan Gangguan already exists (ID: {$row1['id']}).\n";
}

// Check if kualitas_layanan_qos exists
$check->execute(['kualitas_layanan_qos']);
$row2 = $check->fetch();
if (!$row2) {
    $stmt = $pdo->prepare("
        INSERT INTO monitoring_categories 
        (code, name, short_title, description, icon, material_icon, badge_color, target_annual, unit)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        'kualitas_layanan_qos',
        'Pengukuran Kualitas Layanan (QoS)',
        'Kualitas Layanan (QoS)',
        'Pengukuran dan pengawasan parameter teknis kualitas layanan (Quality of Service) telekomunikasi seluler dan internet broadband.',
        'fa-gauge-high',
        'speed',
        'info',
        40,
        'Titik Ukur / Rute'
    ]);
    echo "Inserted Kualitas Layanan (QoS).\n";
} else {
    echo "Kualitas Layanan (QoS) already exists (ID: {$row2['id']}).\n";
}
