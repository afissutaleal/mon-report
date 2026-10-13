<?php
// database/migrate_add_spt_date.php
require_once __DIR__ . '/../config/database.php';

$pdo = getDbConnection();

echo "=== MIGRATION: ADD spt_date TO monitoring_schedules ===\n";

// 1. Check if column exists
$columns = $pdo->query("PRAGMA table_info(monitoring_schedules)")->fetchAll(PDO::FETCH_ASSOC);
$colNames = array_column($columns, 'name');

if (!in_array('spt_date', $colNames)) {
    $pdo->exec("ALTER TABLE monitoring_schedules ADD COLUMN spt_date DATE DEFAULT NULL");
    echo "[SUCCESS] Column 'spt_date' added to 'monitoring_schedules'.\n";
} else {
    echo "[INFO] Column 'spt_date' already exists in 'monitoring_schedules'.\n";
}

// 2. Known specific SPT issue dates from official reports
$specificDates = [
    '24/BALMON.64/KP.01.06/02/2026'  => '2026-02-23',
    '46/BALMON.64/KP.01.06/04/2026'  => '2026-04-14',
    '57/BALMON.64/KP.01.06/04/2026'  => '2026-04-17',
    '78/BALMON.64/KP.01.06/05/2026'  => '2026-05-15',
    '104/BALMON.64/KP.01.06/07/2026' => '2026-07-17',
    '113/BALMON.64/KP.01.06/08/2026' => '2026-08-04',
    '120/BALMON.64/KP.01.06/08/2026' => '2026-08-18',
    '122/BALMON.64/KP.01.06/08/2026' => '2026-08-21',
    '133/BALMON.64/KP.01.06/09/2026' => '2026-09-11',
    '142/BALMON.64/KP.01.06/09/2026' => '2026-09-25',
];

$stmtUpdateSpecific = $pdo->prepare("UPDATE monitoring_schedules SET spt_date = ? WHERE spt_number = ?");
foreach ($specificDates as $sptNum => $dt) {
    $stmtUpdateSpecific->execute([$dt, $sptNum]);
}
echo "[SUCCESS] Set exact dates for known official SPTs.\n";

// 3. For any remaining records without spt_date (or if NULL), set realistic working day before start_date
$rows = $pdo->query("SELECT id, spt_number, start_date, spt_date FROM monitoring_schedules WHERE spt_date IS NULL OR spt_date = ''")->fetchAll(PDO::FETCH_ASSOC);

$stmtUpdateGen = $pdo->prepare("UPDATE monitoring_schedules SET spt_date = ? WHERE id = ?");
$updatedCount = 0;

foreach ($rows as $row) {
    $startTime = strtotime($row['start_date']);
    $dayOfWeek = (int)date('N', $startTime); // 1 = Monday, 7 = Sunday
    
    // If Monday (1), SPT issued on previous Friday (-3 days)
    // If Tuesday (2), SPT issued on Monday (-1 day) or Friday (-4 days)
    // If other days, 1 to 2 days prior
    if ($dayOfWeek === 1) {
        $issueTime = strtotime('-3 days', $startTime);
    } elseif ($dayOfWeek === 7) { // Sunday
        $issueTime = strtotime('-2 days', $startTime);
    } elseif ($dayOfWeek === 6) { // Saturday
        $issueTime = strtotime('-1 day', $startTime);
    } else {
        $issueTime = strtotime('-1 day', $startTime);
    }
    
    $issueDate = date('Y-m-d', $issueTime);
    $stmtUpdateGen->execute([$issueDate, $row['id']]);
    $updatedCount++;
}

echo "[SUCCESS] Updated {$updatedCount} schedules with calculated 'spt_date'.\n";

// 4. Verify results
$check = $pdo->query("SELECT id, spt_number, spt_date, start_date, end_date FROM monitoring_schedules ORDER BY id ASC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
echo "\n=== SAMPLE UPDATED RECORDS ===\n";
foreach ($check as $c) {
    echo "ID: {$c['id']} | SPT: {$c['spt_number']} | Tgl Keluar SPT: {$c['spt_date']} | Pelaksanaan: {$c['start_date']} s.d. {$c['end_date']}\n";
}
echo "\n=== MIGRATION COMPLETED SUCCESSFULLY ===\n";
