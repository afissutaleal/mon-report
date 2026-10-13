<?php
// api/dashboard.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getDbConnection();

try {
    // 1. Categories and target vs realisasi
    $stmtCats = $pdo->query("SELECT * FROM monitoring_categories ORDER BY id ASC");
    $categories = $stmtCats->fetchAll();

    $categoryStats = [];
    $totalAnnualTarget = 0;
    $totalRealized = 0;
    $totalVerified = 0;
    $totalPending = 0;
    $totalRevision = 0;

    foreach ($categories as $cat) {
        $catId = $cat['id'];
        $stmtCount = $pdo->prepare("
            SELECT 
                COUNT(*) as total_count,
                SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified_count,
                SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted_count,
                SUM(CASE WHEN status = 'revision' THEN 1 ELSE 0 END) as revision_count,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_count
            FROM monitoring_reports
            WHERE category_id = ?
        ");
        $stmtCount->execute([$catId]);
        $counts = $stmtCount->fetch();

        $realized = (int)$counts['total_count'];
        $verified = (int)$counts['verified_count'];
        $target = (int)$cat['target_annual'];
        $percentage = $target > 0 ? round(($realized / $target) * 100, 1) : 0;

        $totalAnnualTarget += $target;
        $totalRealized += $realized;
        $totalVerified += $verified;
        $totalPending += (int)$counts['submitted_count'];
        $totalRevision += (int)$counts['revision_count'];

        $categoryStats[] = [
            'id' => $catId,
            'code' => $cat['code'],
            'name' => $cat['name'],
            'short_title' => $cat['short_title'],
            'icon' => $cat['icon'],
            'badge_color' => $cat['badge_color'],
            'target_annual' => $target,
            'realized' => $realized,
            'verified' => $verified,
            'pending' => (int)$counts['submitted_count'],
            'revision' => (int)$counts['revision_count'],
            'draft' => (int)$counts['draft_count'],
            'percentage' => $percentage,
            'unit' => $cat['unit']
        ];
    }

    $overallPercentage = $totalAnnualTarget > 0 ? round(($totalRealized / $totalAnnualTarget) * 100, 1) : 0;

    // 2. Monthly realization trend for current year
    $monthlyData = array_fill(1, 12, 0);
    $stmtMonthly = $pdo->query("
        SELECT strftime('%m', date_monitored) as month, COUNT(*) as cnt
        FROM monitoring_reports
        WHERE strftime('%Y', date_monitored) = '2026'
        GROUP BY month
    ");
    while ($row = $stmtMonthly->fetch()) {
        $m = (int)$row['month'];
        if ($m >= 1 && $m <= 12) {
            $monthlyData[$m] = (int)$row['cnt'];
        }
    }

    // 3. Recent 5 Reports
    $stmtRecent = $pdo->query("
        SELECT r.*, c.name as category_name, c.short_title, c.badge_color, u.name as officer_name, u.avatar as officer_avatar
        FROM monitoring_reports r
        JOIN monitoring_categories c ON r.category_id = c.id
        JOIN users u ON r.user_id = u.id
        ORDER BY r.date_monitored DESC, r.id DESC
        LIMIT 6
    ");
    $recentReports = $stmtRecent->fetchAll();

    // 4. Active schedules
    $stmtSchedules = $pdo->query("
        SELECT s.*, c.short_title as category_title, u.name as leader_name
        FROM monitoring_schedules s
        JOIN monitoring_categories c ON s.category_id = c.id
        JOIN users u ON s.leader_id = u.id
        ORDER BY s.start_date ASC
        LIMIT 4
    ");
    $upcomingSchedules = $stmtSchedules->fetchAll();

    // 5. Active officers summary
    $officers = getActiveOfficersList();
    $onlineOfficers = array_values(array_filter($officers, fn($o) => $o['is_online']));

    echo json_encode([
        'success' => true,
        'kpi_summary' => [
            'total_target' => $totalAnnualTarget,
            'total_realized' => $totalRealized,
            'total_verified' => $totalVerified,
            'total_pending' => $totalPending,
            'total_revision' => $totalRevision,
            'overall_percentage' => $overallPercentage
        ],
        'categories' => $categoryStats,
        'monthly_trend' => array_values($monthlyData),
        'recent_reports' => $recentReports,
        'upcoming_schedules' => $upcomingSchedules,
        'online_officers' => $onlineOfficers
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
