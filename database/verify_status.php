<?php
require __DIR__ . '/../includes/auth.php';
$pdo = getDbConnection();

echo "=== CATEGORIES ===\n";
foreach ($pdo->query("SELECT id, code, short_title, target_annual FROM monitoring_categories ORDER BY id ASC")->fetchAll() as $c) {
    echo "ID {$c['id']} | {$c['code']} | {$c['short_title']} | Target: {$c['target_annual']}\n";
}

echo "\n=== REPORTS CAT 10 ===\n";
foreach ($pdo->query("SELECT id, report_code, target_name, frequency, location, date_monitored, status, technical_data FROM monitoring_reports WHERE category_id = 10")->fetchAll() as $r) {
    echo "ID {$r['id']} | {$r['report_code']} | {$r['target_name']} | {$r['frequency']} | {$r['location']} | {$r['date_monitored']} | {$r['status']}\n";
    $tech = json_decode($r['technical_data'], true);
    echo "   BAP: " . ($tech['bap_number'] ?? '-') . "\n";
    echo "   Sumber: " . ($tech['source_identified'] ?? '-') . "\n";
}

echo "\n=== SCHEDULES 46 & 113 ===\n";
foreach ($pdo->query("SELECT id, spt_number, category_id, title, start_date, end_date FROM monitoring_schedules WHERE spt_number IN ('46/BALMON.64/KP.01.06/04/2026', '113/BALMON.64/KP.01.06/08/2026')")->fetchAll() as $s) {
    echo "ID {$s['id']} | SPT: {$s['spt_number']} | CatID: {$s['category_id']} | {$s['title']} | {$s['start_date']} to {$s['end_date']}\n";
}

echo "\n=== PK.3 MONTH 4 & 8 ===\n";
foreach ($pdo->query("SELECT month_num, activity_title, report_number, report_date, status FROM pk_monthly_rekap WHERE pk_code = 'PK.3' AND year = 2026 AND month_num IN (4, 8)")->fetchAll() as $p) {
    echo "Bulan {$p['month_num']} | {$p['activity_title']} | {$p['report_number']} | {$p['report_date']} | {$p['status']}\n";
}
