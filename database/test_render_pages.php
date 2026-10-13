<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user_id'] = 1;
$_SESSION['user'] = [
    'id' => 1,
    'username' => 'admin',
    'name' => 'Warko, S.Kom, M.Si',
    'role' => 'admin',
    'jabatan' => 'Kepala Balai Monitor',
    'nip' => '19680815 199003 1 006',
    'avatar' => 'assets/images/avatar/1.jpg'
];

ob_start();
require __DIR__ . '/../monitoring_penanganan_gangguan.php';
$htmlGangguan = ob_get_clean();

echo "Gangguan HTML Length: " . strlen($htmlGangguan) . "\n";
echo "Contains Kideco: " . (strpos($htmlGangguan, 'Kideco') !== false ? 'YES' : 'NO') . "\n";
echo "Contains MHU: " . (strpos($htmlGangguan, 'Multi Harapan') !== false ? 'YES' : 'NO') . "\n";
echo "Contains REP-2026-04-KIDECO: " . (strpos($htmlGangguan, 'REP-2026-04-KIDECO') !== false ? 'YES' : 'NO') . "\n";
echo "Contains REP-2026-08-MHU: " . (strpos($htmlGangguan, 'REP-2026-08-MHU') !== false ? 'YES' : 'NO') . "\n";

ob_start();
require __DIR__ . '/../monitoring_kualitas_layanan.php';
$htmlQos = ob_get_clean();
echo "QoS HTML Length: " . strlen($htmlQos) . "\n";

$_GET['id'] = 19;
ob_start();
require __DIR__ . '/../report_detail.php';
$htmlRep19 = ob_get_clean();
echo "Report 19 Detail Length: " . strlen($htmlRep19) . "\n";
echo "Report 19 has BAP: " . (strpos($htmlRep19, 'T-67/BALMON.64/SP.03.05/04/2026') !== false ? 'YES' : 'NO') . "\n";

$_GET['id'] = 20;
ob_start();
require __DIR__ . '/../report_detail.php';
$htmlRep20 = ob_get_clean();
echo "Report 20 Detail Length: " . strlen($htmlRep20) . "\n";
echo "Report 20 has BAP: " . (strpos($htmlRep20, 'T-229/BALMON.64/SP.03.03/08/2026') !== false ? 'YES' : 'NO') . "\n";
