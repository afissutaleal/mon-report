<?php
// database/migrate_24_personnel.php
require_once __DIR__ . '/../config/database.php';

$pdo = getDbConnection();

echo "Mempersiapkan migrasi 24 personil Balmon Samarinda...\n";

// 1. Add columns pangkat_gol and tim_kerja if not exist
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN pangkat_gol VARCHAR(50) DEFAULT ''");
    echo "- Kolom pangkat_gol ditambahkan.\n";
} catch (Exception $e) {
    // Column might already exist
}

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN tim_kerja VARCHAR(150) DEFAULT ''");
    echo "- Kolom tim_kerja ditambahkan.\n";
} catch (Exception $e) {
    // Column might already exist
}

// 2. Data of 24 Officers from Balmon Kelas I Samarinda
$personnel = [
    [
        'no' => 1,
        'name' => 'Edi Mulyono, S.Kom, M.M',
        'nip' => '19711129 199103 1 000',
        'jabatan' => 'Kepala Balai Monitor SFR dan ID Kelas I Samarinda',
        'pangkat_gol' => 'Pembina – IV/a',
        'tim_kerja' => 'Kepala Balai',
        'role' => 'kepala_balai',
        'email' => 'edim004@komdigi.go.id',
        'phone' => '',
        'avatar' => 'assets/images/avatar/1.jpg'
    ],
    [
        'no' => 2,
        'name' => 'Andreo Yustiantoro Anjaya, ST',
        'nip' => '19770506 200803 1 001',
        'jabatan' => 'Pengendali Frekuensi Radio Ahli Madya/PPNS',
        'pangkat_gol' => 'Pembina – IV/a',
        'tim_kerja' => 'Tim Kerja Montib SFR dan APT',
        'role' => 'koordinator',
        'email' => 'andr005@komdigi.go.id',
        'phone' => '',
        'avatar' => 'assets/images/avatar/2.jpg'
    ],
    [
        'no' => 3,
        'name' => 'Duiyanti Milo Rundupadang, ST',
        'nip' => '19770709 200604 2 002',
        'jabatan' => 'Pengendali Frekuensi Radio Ahli Muda',
        'pangkat_gol' => 'Penata TK.1- III/d',
        'tim_kerja' => 'Tim Kerja Montib SFR dan APT',
        'role' => 'petugas',
        'email' => 'duiy001@komdigi.go.id',
        'phone' => '',
        'avatar' => 'assets/images/avatar/3.jpg'
    ],
    [
        'no' => 4,
        'name' => 'Rifyal, ST',
        'nip' => '19880924 200803 1 001',
        'jabatan' => 'Pengendali Frekuensi Radio Ahli Muda',
        'pangkat_gol' => 'Penata – III/c',
        'tim_kerja' => 'Tim Kerja Sarana dan Prasarana',
        'role' => 'koordinator',
        'email' => 'rify001@komdigi.go.id',
        'phone' => '',
        'avatar' => 'assets/images/avatar/4.jpg'
    ],
    [
        'no' => 5,
        'name' => 'Sri Sukisno, S.T',
        'nip' => '19831107 200502 1 001',
        'jabatan' => 'Pengendali Frekuensi Radio Ahli Muda/PPNS',
        'pangkat_gol' => 'Penata Tingkat I - III/d',
        'tim_kerja' => 'Tim Kerja Montib SFR dan APT',
        'role' => 'petugas',
        'email' => 'sris008@komdigi.go.id',
        'phone' => '',
        'avatar' => 'assets/images/avatar/5.jpg'
    ],
    [
        'no' => 6,
        'name' => 'Roy Mallombasi, S.T',
        'nip' => '19800329 200604 1 002',
        'jabatan' => 'Pengendali Frekuensi Radio Ahli Muda/PPNS',
        'pangkat_gol' => 'Penata TK.I - III/d',
        'tim_kerja' => 'Tim Kerja Montib SFR dan APT',
        'role' => 'petugas',
        'email' => 'roym001@komdigi.go.id',
        'phone' => '',
        'avatar' => 'assets/images/avatar/7.jpg'
    ],
    [
        'no' => 7,
        'name' => 'Siti Nurhayati, SH',
        'nip' => '19730504 200312 2 001',
        'jabatan' => 'Analis Sumber Daya Monitor Spektrum Frekuensi Radio Level 2/PPNS',
        'pangkat_gol' => 'Penata - III/c',
        'tim_kerja' => 'Tim Kerja Montib SFR dan APT',
        'role' => 'petugas',
        'email' => 'siti026@komdigi.go.id',
        'phone' => '',
        'avatar' => 'assets/images/avatar/6.jpg'
    ],
    [
        'no' => 8,
        'name' => 'Taman Dwi Wijayanti, A.md',
        'nip' => '19840810 200912 2 001',
        'jabatan' => 'Pengendali Frekuensi Radio Terampil',
        'pangkat_gol' => 'Penata Muda – III/a',
        'tim_kerja' => 'Tim Kerja Montib SFR dan APT',
        'role' => 'petugas',
        'email' => 'tama001@komdigi.go.id',
        'phone' => '',
        'avatar' => 'assets/images/avatar/8.jpg'
    ],
    [
        'no' => 9,
        'name' => 'Muhammad Alif A.F, ST',
        'nip' => '19950215 201902 1 002',
        'jabatan' => 'Pengendali Frekuensi Radio Pertama',
        'pangkat_gol' => 'Pengatur Muda Tingkat I-IIIb',
        'tim_kerja' => 'Tim Kerja Kualitas Layanan',
        'role' => 'koordinator',
        'email' => 'muha166@komdigi.go.id',
        'phone' => '',
        'avatar' => 'assets/images/avatar/1.jpg'
    ],
    [
        'no' => 10,
        'name' => "Andi Muhammad Syafa'at,ST",
        'nip' => '19941219 201902 1 004',
        'jabatan' => 'Pengendali Frekuensi Radio Ahli Pertama/PPNS',
        'pangkat_gol' => 'Penata Muda - III/a',
        'tim_kerja' => 'Tim Kerja Montib SFR dan APT',
        'role' => 'petugas',
        'email' => 'andi051@komdigi.go.id',
        'phone' => '',
        'avatar' => 'assets/images/avatar/2.jpg'
    ],
    [
        'no' => 11,
        'name' => 'Ade Ulfah Laelatussifa,Amd',
        'nip' => '19960310 201902 2 006',
        'jabatan' => 'Pengendali Frekuensi Radio Terampil Mahir',
        'pangkat_gol' => 'Pengatur TK.1– II/d',
        'tim_kerja' => 'Tim Kerja Montib SFR dan APT',
        'role' => 'admin',
        'email' => 'adeu001@komdigi.go.id',
        'phone' => '',
        'avatar' => 'assets/images/avatar/9.jpg'
    ],
    [
        'no' => 12,
        'name' => 'Tantowi Pahala Hamonangan Sianipar,S.Kom',
        'nip' => '19940107 202321 1 021',
        'jabatan' => 'Pengendali Frekuensi Radio Ahli Pertama',
        'pangkat_gol' => 'P3K',
        'tim_kerja' => 'Tim Kerja Kualitas Layanan',
        'role' => 'petugas',
        'email' => 'tantowi.sianipar@komdigi.go.id',
        'phone' => '',
        'avatar' => 'assets/images/avatar/4.jpg'
    ],
    [
        'no' => 13,
        'name' => 'Rendy Tri Atmadja',
        'nip' => '19930531 202421 1 001',
        'jabatan' => 'Pengendali Frekuensi Radio Ahli Pertama',
        'pangkat_gol' => 'P3K',
        'tim_kerja' => 'Tim Kerja Sarana dan Prasarana',
        'role' => 'petugas',
        'email' => 'rendy.atmadja@komdigi.go.id',
        'phone' => '',
        'avatar' => 'assets/images/avatar/5.jpg'
    ],
    [
        'no' => 14,
        'name' => 'Erlangga Hari Prasetyo',
        'nip' => '20011112 202504 1 002',
        'jabatan' => 'Pengendali Frekuensi Radio Ahli Pertama',
        'pangkat_gol' => 'Penata Muda - III/a',
        'tim_kerja' => 'Tim Kerja Sarana dan Prasarana',
        'role' => 'petugas',
        'email' => 'erlangga.prasetyo@komdigi.go.id',
        'phone' => '',
        'avatar' => 'assets/images/avatar/7.jpg'
    ],
    [
        'no' => 15,
        'name' => 'Fathul Mufiidah',
        'nip' => '20000417 202504 2 005',
        'jabatan' => 'Pengendali Frekuensi Radio Ahli Pertama',
        'pangkat_gol' => 'Penata Muda - III/a',
        'tim_kerja' => 'Tim Kerja Sarana dan Prasarana',
        'role' => 'petugas',
        'email' => 'fathul.mufiidah@komdigi.go.id',
        'phone' => '',
        'avatar' => 'assets/images/avatar/3.jpg'
    ],
    [
        'no' => 16,
        'name' => 'Binti Herwindasari',
        'nip' => '19910810 202504 2 000',
        'jabatan' => 'Pengendali Frekuensi Radio Terampil Pelaksana',
        'pangkat_gol' => 'Pengatur - II/c',
        'tim_kerja' => 'Tim Kerja Kualitas Layanan',
        'role' => 'petugas',
        'email' => 'binti.herwindasari@komdigi.go.id',
        'phone' => '',
        'avatar' => 'assets/images/avatar/6.jpg'
    ],
    [
        'no' => 17,
        'name' => 'Rifani Tri Agustin',
        'nip' => '20000825 202504 2 003',
        'jabatan' => 'Pengendali Frekuensi Radio Terampil Pelaksana',
        'pangkat_gol' => 'Pengatur - II/c',
        'tim_kerja' => 'Tim Kerja Montib SFR dan APT',
        'role' => 'petugas',
        'email' => 'rifani.agustin@komdigi.go.id',
        'phone' => '',
        'avatar' => 'assets/images/avatar/8.jpg'
    ],
    [
        'no' => 18,
        'name' => 'Junaedi',
        'nip' => '19750208 202521 1 000',
        'jabatan' => 'Pengadministrasi Perkantoran',
        'pangkat_gol' => 'Pengatur Muda (PPPK) (V)',
        'tim_kerja' => 'Tim Kerja Montib SFR dan APT',
        'role' => 'petugas',
        'email' => 'junaedi@komdigi.go.id',
        'phone' => '',
        'avatar' => 'assets/images/avatar/1.jpg'
    ],
    [
        'no' => 19,
        'name' => 'Nur Syamsul Bahri',
        'nip' => '19770810 202521 1 000',
        'jabatan' => 'Pengadministrasi Perkantoran',
        'pangkat_gol' => 'Pengatur Muda (PPPK) (V)',
        'tim_kerja' => 'Tim Kerja Montib SFR dan APT',
        'role' => 'petugas',
        'email' => 'nur.syamsul@komdigi.go.id',
        'phone' => '',
        'avatar' => 'assets/images/avatar/2.jpg'
    ],
    [
        'no' => 20,
        'name' => 'Togap Sihite',
        'nip' => '19720906 202521 1 019',
        'jabatan' => 'Pengadministrasi Perkantoran',
        'pangkat_gol' => 'Pengatur Muda (PPPK) (V)',
        'tim_kerja' => 'Tim Kerja Kualitas Layanan',
        'role' => 'petugas',
        'email' => 'togap.sihite@komdigi.go.id',
        'phone' => '',
        'avatar' => 'assets/images/avatar/4.jpg'
    ],
    [
        'no' => 21,
        'name' => 'Mustopo',
        'nip' => '19800510 202521 1 030',
        'jabatan' => 'Pengadministrasi Perkantoran',
        'pangkat_gol' => 'Pengatur Muda (PPPK) (V)',
        'tim_kerja' => 'Tim Kerja Kualitas Layanan',
        'role' => 'petugas',
        'email' => 'mustopo@komdigi.go.id',
        'phone' => '',
        'avatar' => 'assets/images/avatar/5.jpg'
    ],
    [
        'no' => 22,
        'name' => 'Maryono',
        'nip' => '19750715 202521 1 025',
        'jabatan' => 'Pengadministrasi Perkantoran',
        'pangkat_gol' => 'Pengatur Muda (PPPK) (V)',
        'tim_kerja' => 'Tim Kerja Kualitas Layanan',
        'role' => 'petugas',
        'email' => 'maryono@komdigi.go.id',
        'phone' => '',
        'avatar' => 'assets/images/avatar/7.jpg'
    ],
    [
        'no' => 23,
        'name' => 'Andriyanto',
        'nip' => '19781129 202521 1 017',
        'jabatan' => 'Pengadministrasi Perkantoran',
        'pangkat_gol' => 'Pengatur Muda (PPPK) (V)',
        'tim_kerja' => 'Tim Kerja Sarana dan Prasarana',
        'role' => 'petugas',
        'email' => 'andriyanto@komdigi.go.id',
        'phone' => '',
        'avatar' => 'assets/images/avatar/1.jpg'
    ],
    [
        'no' => 24,
        'name' => 'Hadi Sucipto',
        'nip' => '19750208 202521 1 018',
        'jabatan' => 'Pengadministrasi Perkantoran',
        'pangkat_gol' => 'Pengatur Muda (PPPK) (V)',
        'tim_kerja' => 'Tim Kerja Sarana dan Prasarana',
        'role' => 'petugas',
        'email' => 'hadi.sucipto@komdigi.go.id',
        'phone' => '',
        'avatar' => 'assets/images/avatar/2.jpg'
    ]
];

$defaultPassword = password_hash('password123', PASSWORD_BCRYPT);

foreach ($personnel as $p) {
    $nipClean = str_replace(' ', '', $p['nip']);
    
    // Check if user exists by exact NIP, clean NIP, or by matching ID 1, 2, 5
    $stmtCheck = $pdo->prepare("
        SELECT id FROM users 
        WHERE nip = ? 
           OR replace(nip, ' ', '') = ? 
           OR email = ?
           OR (id = ? AND ? IN (1, 2, 5))
    ");
    $stmtCheck->execute([$p['nip'], $nipClean, $p['email'], $p['no'], $p['no']]);
    $existing = $stmtCheck->fetch();

    if ($existing) {
        $stmtUp = $pdo->prepare("
            UPDATE users SET 
                nip = ?, 
                name = ?, 
                email = ?, 
                role = ?, 
                jabatan = ?, 
                pangkat_gol = ?, 
                tim_kerja = ?, 
                phone = ?, 
                avatar = ?, 
                status_active = 1
            WHERE id = ?
        ");
        $stmtUp->execute([
            $p['nip'],
            $p['name'],
            $p['email'],
            $p['role'],
            $p['jabatan'],
            $p['pangkat_gol'],
            $p['tim_kerja'],
            $p['phone'],
            $p['avatar'],
            $existing['id']
        ]);
        echo "Updated [ID: {$existing['id']}] {$p['name']} ({$p['nip']})\n";
    } else {
        $stmtIns = $pdo->prepare("
            INSERT INTO users (
                nip, name, email, password, role, jabatan, 
                pangkat_gol, tim_kerja, phone, avatar, theme_mode, 
                status_active, last_active, device_type
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'light', 1, datetime('now'), 'web')
        ");
        $stmtIns->execute([
            $p['nip'],
            $p['name'],
            $p['email'],
            $defaultPassword,
            $p['role'],
            $p['jabatan'],
            $p['pangkat_gol'],
            $p['tim_kerja'],
            $p['phone'],
            $p['avatar']
        ]);
        $newId = $pdo->lastInsertId();
        echo "Inserted [ID: $newId] {$p['name']} ({$p['nip']})\n";
    }
}

// Update report team members and schedules with realistic team assignments from the 24 staff
$pdo->exec("
    UPDATE monitoring_reports 
    SET technical_data = REPLACE(technical_data, 'Fajar Siddiq', 'Duiyanti Milo Rundupadang, ST')
    WHERE id = 1
");

$pdo->exec("
    UPDATE monitoring_reports 
    SET technical_data = REPLACE(technical_data, 'Rian Pratama', 'Sri Sukisno, S.T')
    WHERE id = 2
");

echo "\nMigrasi 24 Personil selesai dengan sukses!\n";
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
echo "Total Personil di Database: $totalUsers orang\n";
