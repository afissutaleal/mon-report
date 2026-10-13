<?php
// database/migrate_15_pita_to_sfr.php
// Migrasi kategori: Monitoring 15 Pita Transportable & Bergerak masuk kategori Monitoring SFR (1), bukan Monitoring Lainnya (9)

require_once __DIR__ . '/../config/database.php';

$pdo = getDbConnection();

echo "Starting migration: 15 Pita Transportable & Bergerak -> Monitoring SFR (Cat 1)\n\n";

$stmt = $pdo->query("SELECT id, spt_number, title, category_id, category_ids FROM monitoring_schedules ORDER BY id ASC");
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

$updatedCount = 0;

$updateStmt = $pdo->prepare("UPDATE monitoring_schedules SET category_id = :category_id, category_ids = :category_ids WHERE id = :id");

foreach ($schedules as $s) {
    $titleLower = strtolower($s['title']);
    $is15Pita = (strpos($titleLower, '15 pita') !== false || strpos($titleLower, 'transportable') !== false || strpos($titleLower, 'bergerak') !== false);

    if (!$is15Pita) {
        continue;
    }

    $cids = json_decode($s['category_ids'] ?? '[]', true) ?: [(int)$s['category_id']];
    $originalCids = $cids;
    $originalCatId = (int)$s['category_id'];

    // Primary category must be 1 (Monitoring SFR)
    $newCatId = 1;

    // Ensure 1 is in category_ids
    if (!in_array(1, $cids)) {
        $cids[] = 1;
    }

    // Check if the title explicitly mentions "penugasan lainnya" (e.g. ID 91)
    $hasExplicitOther = (strpos($titleLower, 'penugasan lainnya') !== false || strpos($titleLower, 'monitoring lainnya') !== false);

    // If it doesn't explicitly mention "penugasan lainnya", remove 9
    if (!$hasExplicitOther && in_array(9, $cids)) {
        $cids = array_values(array_diff($cids, [9]));
    }

    // Detect other activities mentioned in title:
    // QoS / Kualitas Layanan (11)
    if ((strpos($titleLower, 'kualitas layanan') !== false || strpos($titleLower, 'qos') !== false) && !in_array(11, $cids)) {
        $cids[] = 11;
    }

    // HF (5)
    if (strpos($titleLower, 'hf') !== false && !in_array(5, $cids)) {
        $cids[] = 5;
    }

    // APT / Alat Perangkat (6)
    if ((strpos($titleLower, 'alat perangkat') !== false || strpos($titleLower, 'pendataan perangkat') !== false) && !in_array(6, $cids)) {
        $cids[] = 6;
    }

    $cids = array_values(array_unique(array_map('intval', $cids)));
    sort($cids);

    $newCatIdsJson = json_encode($cids);

    if ($originalCatId !== $newCatId || json_encode($originalCids) !== $newCatIdsJson) {
        $updateStmt->execute([
            ':category_id' => $newCatId,
            ':category_ids' => $newCatIdsJson,
            ':id' => $s['id']
        ]);
        $updatedCount++;
        echo "Updated [ID {$s['id']} - {$s['spt_number']}]:\n";
        echo "  Title: {$s['title']}\n";
        echo "  CatID: {$originalCatId} -> {$newCatId}\n";
        echo "  CatIDs: " . json_encode($originalCids) . " -> {$newCatIdsJson}\n\n";
    }
}

echo "Migration complete! Total schedules updated: {$updatedCount}\n";
