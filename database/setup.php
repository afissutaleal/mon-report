<?php
// database/reset_with_24_personnel.php
require_once __DIR__ . '/../config/database.php';

$pdo = getDbConnection();

echo "Mereset dan membangun ulang database dengan 24 Personil Resmi Balmon Samarinda...\n";

// Drop existing tables in correct order
$pdo->exec("PRAGMA foreign_keys = OFF;");
$pdo->exec("DROP TABLE IF EXISTS notifications;");
$pdo->exec("DROP TABLE IF EXISTS chat_messages;");
$pdo->exec("DROP TABLE IF EXISTS monitoring_schedules;");
$pdo->exec("DROP TABLE IF EXISTS monitoring_reports;");
$pdo->exec("DROP TABLE IF EXISTS monitoring_categories;");
$pdo->exec("DROP TABLE IF EXISTS users;");
$pdo->exec("PRAGMA foreign_keys = ON;");

// 1. Table users
$pdo->exec("
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nip VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(30) DEFAULT 'petugas', -- admin, koordinator, petugas, kepala_balai
    jabatan VARCHAR(150) DEFAULT 'Petugas Monitoring',
    pangkat_gol VARCHAR(50) DEFAULT '',
    tim_kerja VARCHAR(150) DEFAULT '',
    phone VARCHAR(30) DEFAULT '',
    avatar VARCHAR(255) DEFAULT 'assets/images/avatar/1.jpg',
    theme_mode VARCHAR(10) DEFAULT 'light', -- light, dark
    status_active INTEGER DEFAULT 1, -- 1=active, 0=inactive
    last_active DATETIME DEFAULT CURRENT_TIMESTAMP,
    device_type VARCHAR(20) DEFAULT 'web', -- web, mobile
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
");

// 2. Table monitoring_categories
$pdo->exec("
CREATE TABLE monitoring_categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(200) NOT NULL,
    short_title VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50) DEFAULT 'fa-broadcast-tower',
    material_icon VARCHAR(50) DEFAULT 'sensors',
    badge_color VARCHAR(30) DEFAULT 'primary',
    target_annual INTEGER DEFAULT 100,
    unit VARCHAR(50) DEFAULT 'Kegiatan',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
");

// 3. Table monitoring_reports
$pdo->exec("
CREATE TABLE monitoring_reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    report_code VARCHAR(50) UNIQUE NOT NULL,
    category_id INTEGER NOT NULL,
    title VARCHAR(255) NOT NULL,
    user_id INTEGER NOT NULL,
    target_name VARCHAR(200) NOT NULL,
    frequency VARCHAR(100) DEFAULT '',
    location VARCHAR(255) NOT NULL,
    latitude REAL DEFAULT -0.502106,
    longitude REAL DEFAULT 117.153709,
    date_monitored DATE NOT NULL,
    status VARCHAR(30) DEFAULT 'draft', -- draft, submitted, verified, revision, rejected
    verified_by INTEGER DEFAULT NULL,
    verified_at DATETIME DEFAULT NULL,
    verification_notes TEXT DEFAULT '',
    attachments TEXT DEFAULT '',
    technical_data TEXT DEFAULT '{}',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES monitoring_categories(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (verified_by) REFERENCES users(id)
);
");

// 4. Table monitoring_schedules
$pdo->exec("
CREATE TABLE monitoring_schedules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    spt_number VARCHAR(100) UNIQUE NOT NULL,
    spt_date DATE DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    category_id INTEGER NOT NULL,
    leader_id INTEGER NOT NULL,
    member_names TEXT NOT NULL,
    location VARCHAR(255) NOT NULL,
    target_object VARCHAR(255) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status VARCHAR(30) DEFAULT 'scheduled', -- scheduled, in_progress, completed, cancelled
    notes TEXT DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES monitoring_categories(id),
    FOREIGN KEY (leader_id) REFERENCES users(id)
);
");

// 5. Table chat_messages
$pdo->exec("
CREATE TABLE chat_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sender_id INTEGER NOT NULL,
    receiver_id INTEGER DEFAULT NULL, -- NULL means Team Room chat (broadcast to all)
    is_team_chat INTEGER DEFAULT 0, -- 1=team broadcast, 0=direct message
    message TEXT NOT NULL,
    attachment VARCHAR(255) DEFAULT '',
    is_read INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id),
    FOREIGN KEY (receiver_id) REFERENCES users(id)
);
");

// 6. Table notifications
$pdo->exec("
CREATE TABLE notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255) DEFAULT '',
    is_read INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
");

echo "Tabel database berhasil dibuat ulang.\n";

// 2. Insert 9 Standard Categories
$categories = [
    [
        'code' => 'spektrum_frekuensi',
        'name' => 'Monitoring SFR (Spektrum Frekuensi Radio)',
        'short_title' => 'Monitoring SFR',
        'desc' => 'Pengawasan dan monitoring penggunaan spektrum frekuensi radio untuk memastikan kepatuhan terhadap Izin Stasiun Radio (ISR) dan parameter teknis pancaran.',
        'icon' => 'fa-tower-broadcast',
        'material_icon' => 'cell_tower',
        'badge_color' => 'primary',
        'target_annual' => 120,
        'unit' => 'Kanal / Stasiun'
    ],
    [
        'code' => 'microwavelink',
        'name' => 'Inspeksi Stasiun Radio Microwavelink',
        'short_title' => 'Microwavelink',
        'desc' => 'Inspeksi teknis stasiun radio gelombang mikro (microwave link) point-to-point untuk cross-polarization, frekuensi carrier, dan koordinasi kanal seluler/backhaul.',
        'icon' => 'fa-network-wired',
        'material_icon' => 'settings_input_antenna',
        'badge_color' => 'info',
        'target_annual' => 80,
        'unit' => 'Hop Link'
    ],
    [
        'code' => 'radio_fm',
        'name' => 'Pengukuran Stasiun Radio Siaran FM',
        'short_title' => 'Radio Siaran FM',
        'desc' => 'Pengukuran deviasi frekuensi maksimum (±75 kHz), kuat medan, bandwidth pancaran, dan harmonisa stasiun siaran radio FM komersial dan komunitas.',
        'icon' => 'fa-radio',
        'material_icon' => 'radio',
        'badge_color' => 'success',
        'target_annual' => 60,
        'unit' => 'Stasiun'
    ],
    [
        'code' => 'tv_digital',
        'name' => 'Pengukuran Stasiun Radio TV Digital',
        'short_title' => 'TV Digital',
        'desc' => 'Pengukuran kualitas pancaran televisi siaran digital terrestrial DVB-T2 mencakup Modulation Error Rate (MER), Bit Error Rate (BER), dan level sinyal (dBm).',
        'icon' => 'fa-tv',
        'material_icon' => 'live_tv',
        'badge_color' => 'warning',
        'target_annual' => 50,
        'unit' => 'Mux Kanal'
    ],
    [
        'code' => 'monitoring_hf',
        'name' => 'Monitoring HF (High Frequency)',
        'short_title' => 'Monitoring HF',
        'desc' => 'Pemantauan spektrum frekuensi tinggi (HF 3 - 30 MHz) untuk maritim (distress frequency), penerbangan aeronautikal jarak jauh, dan radio komunikasi kebencanaan.',
        'icon' => 'fa-wave-square',
        'material_icon' => 'waves',
        'badge_color' => 'secondary',
        'target_annual' => 45,
        'unit' => 'Kanal HF'
    ],
    [
        'code' => 'alat_telekomunikasi',
        'name' => 'Monitoring APT (Alat dan Perangkat Telekomunikasi)',
        'short_title' => 'Monitoring APT',
        'desc' => 'Pengawasan peredaran alat dan perangkat telekomunikasi (label sertifikasi Ditjend Infrastruktur Digital, verifikasi IMEI, dan standarisasi perangkat nirkabel di pasar ritel/distributor).',
        'icon' => 'fa-walkie-talkie',
        'material_icon' => 'devices',
        'badge_color' => 'danger',
        'target_annual' => 90,
        'unit' => 'Toko / Vendor'
    ],
    [
        'code' => 'penertiban_frekuensi',
        'name' => 'Penertiban SFR (Spektrum Frekuensi Radio)',
        'short_title' => 'Penertiban SFR',
        'desc' => 'Operasi penertiban penggunaan frekuensi radio tanpa izin (ilegal) atau menimbulkan interferensi merugikan (harmful interference) terhadap frekuensi penerbangan/keamanan.',
        'icon' => 'fa-gavel',
        'material_icon' => 'gavel',
        'badge_color' => 'danger',
        'target_annual' => 30,
        'unit' => 'Kasus Penertiban'
    ],
    [
        'code' => 'penertiban_alat',
        'name' => 'Penertiban APT (Alat dan Perangkat Telekomunikasi)',
        'short_title' => 'Penertiban APT',
        'desc' => 'Penindakan dan penyegelan terhadap peredaran perangkat penguat sinyal ilegal (cellular repeater booster) dan perangkat telekomunikasi tanpa sertifikasi resmi.',
        'icon' => 'fa-shield-halved',
        'material_icon' => 'security',
        'badge_color' => 'dark',
        'target_annual' => 25,
        'unit' => 'Penyitaan / BAP'
    ],
    [
        'code' => 'penugasan_lainnya',
        'name' => 'Penugasan Lainnya (Dukungan VVIP, IKN, Posko)',
        'short_title' => 'Penugasan Khusus / IKN',
        'desc' => 'Pengamanan spektrum radio kenegaraan/VVIP (Presiden/Wakil Presiden), monitoring khusus kawasan Ibu Kota Nusantara (IKN), posko pengamanan lebaran, dan satgas perbatasan.',
        'icon' => 'fa-star',
        'material_icon' => 'stars',
        'badge_color' => 'primary',
        'target_annual' => 40,
        'unit' => 'Giat Pengamanan'
    ],
];

foreach ($categories as $cat) {
    $stmt = $pdo->prepare("INSERT INTO monitoring_categories (code, name, short_title, description, icon, material_icon, badge_color, target_annual, unit) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $cat['code'],
        $cat['name'],
        $cat['short_title'],
        $cat['desc'],
        $cat['icon'],
        $cat['material_icon'],
        $cat['badge_color'],
        $cat['target_annual'],
        $cat['unit']
    ]);
}
echo "9 Kategori Monitoring berhasil dimasukkan.\n";

// 3. Insert 24 Personnel
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
    $stmt = $pdo->prepare("
        INSERT INTO users (
            id, nip, name, email, password, role, jabatan, 
            pangkat_gol, tim_kerja, phone, avatar, theme_mode, 
            status_active, last_active, device_type
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'light', 1, datetime('now'), 'web')
    ");
    $stmt->execute([
        $p['no'],
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
}
echo "24 Personil resmi berhasil didaftarkan (ID 1 sampai 24).\n";

// 4. Insert 9 Realistic Sample Reports
$reports = [
    // 1. Spektrum Frekuensi
    [
        'code' => 'MON-SPF-2026-001',
        'category_code' => 'spektrum_frekuensi',
        'title' => 'Monitoring Spektrum Frekuensi Land Mobile Radio PT Pertamina Hulu Mahakam',
        'user_id' => 5, // Sri Sukisno, S.T
        'target_name' => 'PT Pertamina Hulu Mahakam (Site Senipah)',
        'frequency' => '456.250 MHz',
        'location' => 'Kec. Samboja, Kab. Kutai Kartanegara',
        'lat' => -1.023400,
        'lng' => 117.021100,
        'date' => '2026-09-18',
        'status' => 'verified',
        'verified_by' => 2, // Andreo Yustiantoro Anjaya, ST
        'notes' => 'Parameter teknis frekuensi 456.250 MHz telah sesuai dengan ISR No. 01882912-001.',
        'tech' => json_encode([
            'isr_number' => '01882912-001/LMR/2024',
            'bandwidth' => '12.5 kHz (Narrowband)',
            'measured_frequency' => '456.2502 MHz (Offset +200 Hz)',
            'field_strength' => '68.4 dBuV/m',
            'power_output' => '25 Watt (Sesuai ISR)',
            'spurious_emission' => '-62 dBc (Memenuhi standar ITU-R SM.329)',
            'polarization' => 'Vertical',
            'antenna_type' => 'Omnidirectional 6 dBi',
            'compliance_status' => 'COMPLIANT (Sesuai Izin)'
        ])
    ],
    // 2. Microwavelink
    [
        'code' => 'MON-MWL-2026-002',
        'category_code' => 'microwavelink',
        'title' => 'Inspeksi Cross-Polarization dan Frekuensi Link Microwave PT Telkomsel Palaran - Sambutan',
        'user_id' => 3, // Duiyanti Milo Rundupadang, ST
        'target_name' => 'PT Telkomsel (Hop Site Palaran ➔ Site Sambutan)',
        'frequency' => '7.245 GHz / 7.395 GHz',
        'location' => 'Bukit Sambutan, Samarinda Seberang',
        'lat' => -0.521800,
        'lng' => 117.182300,
        'date' => '2026-09-19',
        'status' => 'verified',
        'verified_by' => 2, // Andreo Yustiantoro Anjaya, ST
        'notes' => 'Link microwave stabil, margin fading 35 dB dan modulasi 1024-QAM bekerja optimal.',
        'tech' => json_encode([
            'hop_name' => 'PALARAN_SMD_01 ➔ SAMBUTAN_SMD_02',
            'channel_spacing' => '56 MHz',
            'tx_rx_frequency' => 'Tx: 7.2450 GHz | Rx: 7.3950 GHz',
            'antenna_diameter' => '1.2 Meter Solid Dish',
            'azimuth' => '42.5° True North',
            'received_signal_level' => '-41.8 dBm (RSL Nominal -42 dBm)',
            'xpd_value' => '32.1 dB (Cross-Polarization Discrimination Baik)',
            'modulation' => '1024-QAM Adaptive Coding',
            'interference_level' => 'Noise floor -104 dBm (Clear)'
        ])
    ],
    // 3. Radio FM
    [
        'code' => 'MON-RFM-2026-003',
        'category_code' => 'radio_fm',
        'title' => 'Pengukuran Deviasi Frekuensi dan Kuat Medan PT Radio Suara Mahakam Samarinda',
        'user_id' => 10, // Andi Muhammad Syafa'at,ST
        'target_name' => 'PT Radio Suara Mahakam FM (88.50 MHz)',
        'frequency' => '88.50 MHz',
        'location' => 'Jl. Pangeran Suryanata, Air Putih, Samarinda Ulu',
        'lat' => -0.478900,
        'lng' => 117.119200,
        'date' => '2026-09-20',
        'status' => 'verified',
        'verified_by' => 2, // Andreo Yustiantoro Anjaya, ST
        'notes' => 'Deviasi puncak tercatat 69.2 kHz, di bawah batas ambang regulasi 75 kHz.',
        'tech' => json_encode([
            'call_sign' => 'PM8FSM (Suara Mahakam)',
            'carrier_frequency' => '88.5002 MHz (Deviasi carrier +200 Hz)',
            'max_deviation' => '69.2 kHz (Batas Max Permenkominfo: ±75 kHz)',
            'field_strength' => '84.2 dBuV/m (Penerimaan Sangat Baik)',
            'occupied_bandwidth' => '186.4 kHz',
            'rds_subcarrier' => 'Aktif (57 kHz subcarrier, deviation 2.1 kHz)',
            'audio_harmonics' => '< -65 dBc',
            'coverage_condition' => 'Stereo jernih, multipath minimal'
        ])
    ],
    // 4. TV Digital
    [
        'code' => 'MON-TVD-2026-004',
        'category_code' => 'tv_digital',
        'title' => 'Pengukuran Kualitas Siaran DVB-T2 MUX TVRI Kaltim Kanal 30 UHF',
        'user_id' => 8, // Taman Dwi Wijayanti, A.md
        'target_name' => 'LPP TVRI Stasiun Kalimantan Timur (MUX CH 30)',
        'frequency' => '546.00 MHz (CH 30 UHF)',
        'location' => 'Gunung Manggah, Kota Samarinda',
        'lat' => -0.505400,
        'lng' => 117.161000,
        'date' => '2026-09-21',
        'status' => 'submitted',
        'verified_by' => NULL,
        'notes' => 'Menunggu verifikasi ketua tim. Nilai MER 32.8 dB (Sangat Bagus).',
        'tech' => json_encode([
            'channel_uhf' => 'CH 30 UHF (542 - 550 MHz)',
            'center_frequency' => '546.000 MHz',
            'mer_value' => '32.8 dB (Batas min: 24 dB)',
            'ber_value' => '< 1.0E-8 (Error Free)',
            'received_level' => '-48.2 dBm',
            'guard_interval' => '1/16',
            'constellation' => '64-QAM / 32K FFT',
            'mux_operator' => 'LPP TVRI Kaltim',
            'content_programs' => 'TVRI Nasional, TVRI Kaltim, TVRI World, TVRI Sport',
            'video_quality' => 'HD 1080i (Lancar / No Glitch)'
        ])
    ],
    // 5. Monitoring HF
    [
        'code' => 'MON-HFR-2026-005',
        'category_code' => 'monitoring_hf',
        'title' => 'Monitoring Frekuensi Maritim dan Distress Call HF 6215 kHz Selat Makassar',
        'user_id' => 6, // Roy Mallombasi, S.T
        'target_name' => 'Stasiun Radio Pantai (SROP) Samarinda & Pelayaran Rakyat',
        'frequency' => '6215.0 kHz / 8291.0 kHz',
        'location' => 'Pos Monitoring HF Balmon Samarinda, Loa Janan',
        'lat' => -0.551100,
        'lng' => 117.112200,
        'date' => '2026-09-22',
        'status' => 'submitted',
        'verified_by' => NULL,
        'notes' => 'Pantauan jalur maritim Selat Makassar, tidak terdeteksi interferensi berbahaya.',
        'tech' => json_encode([
            'emission_mode' => 'J3E (USB) & F1B (DSC)',
            'direction_finding_azimuth' => '115° (Arah Pesisir Muara Pegah)',
            'utc_time' => '02:30 UTC / 10:30 WITA',
            'signal_characteristics' => 'Pancaran jelas, propagasi ionosfer normal',
            'observed_stations' => 'PKJ-2 (SROP Samarinda), KM Bahtera Mulia, Kapal Tugboat',
            'interference_detected' => 'Nihil / Bersih',
            'recommendation' => 'Kanal maritim internasional dalam kondisi aman.'
        ])
    ],
    // 6. Monitoring Alat
    [
        'code' => 'MON-APT-2026-006',
        'category_code' => 'alat_telekomunikasi',
        'title' => 'Pengawasan Peredaran Perangkat HT dan Repeater Toko Elektronik Samarinda',
        'user_id' => 7, // Siti Nurhayati, SH
        'target_name' => 'Toko Komunikasi Borneo Lestari',
        'frequency' => 'VHF / UHF Band',
        'location' => 'Pertokoan Citra Niaga, Kota Samarinda',
        'lat' => -0.501500,
        'lng' => 117.148100,
        'date' => '2026-09-22',
        'status' => 'revision',
        'verified_by' => NULL,
        'notes' => 'Ditemukan 4 unit HT merk impor tanpa label SDPPI. Perlu kelengkapan surat imbauan.',
        'tech' => json_encode([
            'device_type' => 'Handy Talkie (HT) & Antenna Base',
            'brand_model' => 'Baofeng UV-5R & Alinco DJ-W10',
            'serial_numbers' => 'BF-2026-8812, BF-2026-8813, AL-99120',
            'certification_sdppi' => 'Sebagian tidak berlabel resmi Ditjen SDPPI',
            'store_type' => 'Distributor & Toko Ritel Elektronik',
            'units_observed' => 24,
            'compliance_status' => 'Perlu Pembinaan & Edukasi Sertifikasi',
            'action_taken' => 'Pemberian edukasi label SDPPI dan teguran persuasif.'
        ])
    ],
    // 7. Penertiban Spektrum Frekuensi
    [
        'code' => 'PEN-SPF-2026-007',
        'category_code' => 'penertiban_frekuensi',
        'title' => 'Operasi Penertiban Pemancar Radio Ilegal Pengganggu Frekuensi Penerbangan',
        'user_id' => 2, // Andreo Yustiantoro Anjaya, ST
        'target_name' => 'Pemancar Ilegal Tanpa ISR (Wilayah Dekat Bandara APT Pranoto)',
        'frequency' => '118.300 MHz (VHF Airband)',
        'location' => 'Kec. Sungai Pinang, Samarinda Utara',
        'lat' => -0.420100,
        'lng' => 117.200300,
        'date' => '2026-09-23',
        'status' => 'verified',
        'verified_by' => 1, // Edi Mulyono, S.Kom, M.M
        'notes' => 'Berita Acara Penghentian Pancaran telah ditandatangani oleh pelanggar.',
        'tech' => json_encode([
            'warrant_number' => 'ST.PENERTIBAN/BALMON.SMD/09/2026/044',
            'violation_type' => 'Spurious transmitter menginterferensi Air Traffic Control (ATC)',
            'violator_identity' => 'Sdr. Hendra / Bengkel Elektronik Rakitan',
            'action_type' => 'Penghentian Pancaran & Penyegelan Exciter Transmitter',
            'evidence_items' => '1 unit Exciter FM 150W, 1 unit Power Supply 30A',
            'bap_number' => 'BAP-PPNS-BALMON-SMD/044/IX/2026',
            'ppns_officer' => 'Andreo Yustiantoro Anjaya, ST / PPNS Balmon Samarinda',
            'legal_followup' => 'Surat Peringatan 1 dan Surat Pernyataan Tidak Mengulangi'
        ])
    ],
    // 8. Penertiban Alat
    [
        'code' => 'PEN-APT-2026-008',
        'category_code' => 'penertiban_alat',
        'title' => 'Operasi Penertiban Penguat Sinyal (Cellular Repeater/Booster) Ilegal',
        'user_id' => 17, // Rifani Tri Agustin
        'target_name' => 'Gedung Ruko Distribusi Kelontong (Booster Liar GSM)',
        'frequency' => '900 MHz / 1800 MHz (Uplink Booster)',
        'location' => 'Jl. Jakarta, Kel. Loa Bakung, Kota Samarinda',
        'lat' => -0.521300,
        'lng' => 117.098400,
        'date' => '2026-09-23',
        'status' => 'submitted',
        'verified_by' => NULL,
        'notes' => 'Booster ilegal menyebabkan BTS Telkomsel dan Indosat mengalami drop call parah.',
        'tech' => json_encode([
            'warrant_number' => 'ST.OPERASI-APT/BALMON-SMD/09/2026/012',
            'device_confiscated' => 'Dual Band Cellular Repeater Booster 900/1800 MHz Tanpa Izin',
            'brand' => 'Lintratek Non-Certified',
            'quantity' => '2 unit booster + 4 antena donor/service',
            'impact' => 'Interferensi Uplink Noise Floor meningkat +35 dB pada 3 BTS sekitar',
            'bap_number' => 'BAP-SIT-012/BALMON-SMD/2026',
            'owner_statement' => 'Bersedia membongkar perangkat dan menandatangani pakta integritas',
            'sanction' => 'Penyegelan perangkat dan pencopotan instalasi outdoor'
        ])
    ],
    // 9. Penugasan Lainnya (IKN)
    [
        'code' => 'TGS-IKN-2026-009',
        'category_code' => 'penugasan_lainnya',
        'title' => 'Pengamanan Spektrum Frekuensi Kunjungan Kerja Kenegaraan Presiden RI di IKN',
        'user_id' => 1, // Edi Mulyono, S.Kom, M.M
        'target_name' => 'Kawasan Inti Pusat Pemerintahan (KIPP) IKN Nusantara',
        'frequency' => 'Semua Band VHF/UHF/Microwave (30 MHz - 6 GHz)',
        'location' => 'Kec. Sepaku, Kab. Penajam Paser Utara (IKN)',
        'lat' => -0.963200,
        'lng' => 116.702100,
        'date' => '2026-09-24',
        'status' => 'verified',
        'verified_by' => 1, // Edi Mulyono, S.Kom, M.M
        'notes' => 'Sterilisasi spektrum pengamanan Paspampres dan frekuensi drone berhasil lancar.',
        'tech' => json_encode([
            'mission_name' => 'Dukungan Frekuensi Pengamanan Kunjungan VVIP Presiden RI',
            'coverage_area' => 'Istana Presiden IKN, Kantor Kemenko, dan Helipad KIPP',
            'equipment_deployed' => 'Mobile Monitoring Unit (MMU) Samarinda & Handheld Spectrum Analyzer R&S',
            'team_members' => 'Andreo Yustiantoro Anjaya, ST, Sri Sukisno, S.T, Ade Ulfah Laelatussifa,Amd',
            'spectrum_health' => '100% Bebas Gangguan Interferensi (Green Status)',
            'drones_monitored' => '5 drone resmi Paspampres, 0 drone liar / non-authorized',
            'official_report_to' => 'Paspampres Group A & Ditjen SDPPI Komdigi'
        ])
    ],
];

foreach ($reports as $r) {
    // Find category id
    $stmtCat = $pdo->prepare("SELECT id FROM monitoring_categories WHERE code = ?");
    $stmtCat->execute([$r['category_code']]);
    $catRow = $stmtCat->fetch();
    $catId = $catRow ? $catRow['id'] : 1;

    $stmtIns = $pdo->prepare("
        INSERT INTO monitoring_reports (
            report_code, category_id, title, user_id, target_name, frequency,
            location, latitude, longitude, date_monitored, status,
            verified_by, verified_at, verification_notes, technical_data
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), ?, ?)
    ");
    $stmtIns->execute([
        $r['code'],
        $catId,
        $r['title'],
        $r['user_id'],
        $r['target_name'],
        $r['frequency'],
        $r['location'],
        $r['lat'],
        $r['lng'],
        $r['date'],
        $r['status'],
        $r['verified_by'],
        $r['notes'],
        $r['tech']
    ]);
}
echo "9 Laporan Monitoring riil berhasil dimasukkan.\n";

// 5. Insert Sample Schedules (SPT)
$schedules = [
    [
        'spt' => 'SPT/042/BALMON.SMD/IX/2026',
        'title' => 'Pengukuran Deviasi Frekuensi Stasiun Radio FM Kota Samarinda & Kukar',
        'cat_code' => 'radio_fm',
        'leader_id' => 2, // Andreo Yustiantoro Anjaya, ST
        'members' => 'Duiyanti Milo Rundupadang, ST, Andi Muhammad Syafa\'at,ST',
        'location' => 'Samarinda & Tenggarong',
        'target' => 'Radio Siaran Swasta dan Komunitas',
        'start' => '2026-09-25',
        'end' => '2026-09-27',
        'status' => 'in_progress',
        'notes' => 'Penggunaan Mobil Unit Monitoring Frekuensi (MUMF) No. Pol KT-1290-BM.'
    ],
    [
        'spt' => 'SPT/043/BALMON.SMD/IX/2026',
        'title' => 'Inspeksi Sarana dan Pemeliharaan Stasiun Sensor Monitoring Bontang',
        'cat_code' => 'microwavelink',
        'leader_id' => 4, // Rifyal, ST (Ketua Tim Sarpras)
        'members' => 'Rendy Tri Atmadja, Erlangga Hari Prasetyo, Fathul Mufiidah',
        'location' => 'Kota Bontang (Pupuk Kaltim & Badak LNG)',
        'target' => 'Tower Sensor Fixed & Perangkat Monitoring Remote',
        'start' => '2026-09-28',
        'end' => '2026-09-30',
        'status' => 'scheduled',
        'notes' => 'Kalibrasi antena receiver dan uji backhaul link satelit.'
    ],
    [
        'spt' => 'SPT/044/BALMON.SMD/X/2026',
        'title' => 'Pengukuran Kualitas Layanan Seluler & Siaran di Kawasan Ibu Kota Nusantara (IKN)',
        'cat_code' => 'penugasan_lainnya',
        'leader_id' => 9, // Muhammad Alif A.F, ST (Ketua Tim Kualitas Layanan)
        'members' => 'Tantowi Pahala Hamonangan Sianipar,S.Kom, Binti Herwindasari, Togap Sihite',
        'location' => 'Kawasan Inti IKN Nusantara (Sepaku)',
        'target' => 'Kualitas Coverage 4G/5G Operator Telekomunikasi & Siaran MUX TVRI',
        'start' => '2026-10-02',
        'end' => '2026-10-04',
        'status' => 'scheduled',
        'notes' => 'Drive Test dengan Scanner R&S TSMx dan pelaporan ke Ditjen SDPPI Pusat.'
    ],
    [
        'spt' => 'SPT/045/BALMON.SMD/X/2026',
        'title' => 'Operasi Penertiban Alat Perangkat Telekomunikasi Ilegal Pasar Ritel Samarinda',
        'cat_code' => 'penertiban_alat',
        'leader_id' => 2, // Andreo Yustiantoro Anjaya, ST
        'members' => 'Siti Nurhayati, SH, Ade Ulfah Laelatussifa,Amd, Junaedi',
        'location' => 'Pusat Niaga Samarinda & Pergudangan Pergudangan Sungai Kunjang',
        'target' => 'Toko Distributor & Ritel Perangkat Komunikasi Nirkabel',
        'start' => '2026-10-06',
        'end' => '2026-10-08',
        'status' => 'scheduled',
        'notes' => 'Pemeriksaan label sertifikasi Ditjen SDPPI dan Berita Acara Pemeriksaan.'
    ]
];

foreach ($schedules as $s) {
    $stmtCat = $pdo->prepare("SELECT id FROM monitoring_categories WHERE code = ?");
    $stmtCat->execute([$s['cat_code']]);
    $catRow = $stmtCat->fetch();
    $catId = $catRow ? $catRow['id'] : 1;

    $stmtIns = $pdo->prepare("
        INSERT INTO monitoring_schedules (
            spt_number, title, category_id, leader_id, member_names,
            location, target_object, start_date, end_date, status, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtIns->execute([
        $s['spt'],
        $s['title'],
        $catId,
        $s['leader_id'],
        $s['members'],
        $s['location'],
        $s['target'],
        $s['start'],
        $s['end'],
        $s['status'],
        $s['notes']
    ]);
}
echo "Jadwal SPT berhasil dimasukkan.\n";

// 6. Insert Sample Chat Messages
$messages = [
    // Team broadcast messages
    [
        'sender' => 1, // Edi Mulyono (Kepala Balai)
        'receiver' => NULL,
        'is_team' => 1,
        'message' => 'Selamat pagi rekan-rekan Balmon Kelas I Samarinda. Mohon perhatian seluruh Ketua Tim Kerja agar percepatan target RKT triwulan ini dimaksimalkan.'
    ],
    [
        'sender' => 2, // Andreo (Ketua Tim Montib)
        'receiver' => NULL,
        'is_team' => 1,
        'message' => 'Siap Bapak Kepala Balai. Untuk Tim Kerja Montib SFR dan APT, pengukuran FM dan monitoring HF maritim telah selesai dan laporan sudah masuk ke sistem.'
    ],
    [
        'sender' => 4, // Rifyal (Ketua Tim Sarpras)
        'receiver' => NULL,
        'is_team' => 1,
        'message' => 'Untuk Tim Sarpras, jadwal inspeksi sensor fixed dan MUMF di Bontang minggu depan sudah siap diberangkatkan.'
    ],
    [
        'sender' => 9, // Alif (Ketua Tim Kualitas Layanan)
        'receiver' => NULL,
        'is_team' => 1,
        'message' => 'Tim Kualitas Layanan sedang mempersiapkan instrumen drive test R&S untuk giat pengukuran jaringan seluler di kawasan IKN Sepaku.'
    ],
    [
        'sender' => 11, // Ade Ulfah (Admin Tim)
        'receiver' => NULL,
        'is_team' => 1,
        'message' => 'Seluruh berkas SPT dan kelengkapan administrasi SPPD sudah kami arsipkan di sistem. Rekan-rekan petugas lapangan silakan mengunggah hasil monitoring.'
    ],
    // Direct chat
    [
        'sender' => 2, // Andreo
        'receiver' => 3, // Duiyanti Milo
        'is_team' => 0,
        'message' => 'Bu Duiyanti, untuk laporan microwave hop Sambutan - Palaran sudah saya verifikasi di sistem ya.'
    ],
    [
        'sender' => 3, // Duiyanti Milo
        'receiver' => 2, // Andreo
        'is_team' => 0,
        'message' => 'Terima kasih banyak Pak Andreo. Nilai cross-polarization discrimination (XPD) dan RSL semuanya sudah memenuhi standar ISR.'
    ],
    [
        'sender' => 1, // Edi Mulyono
        'receiver' => 2, // Andreo
        'is_team' => 0,
        'message' => 'Pak Andreo, bagaimana hasil penertiban pemancar liar di dekat bandara APT Pranoto kemarin?'
    ],
    [
        'sender' => 2, // Andreo
        'receiver' => 1, // Edi Mulyono
        'is_team' => 0,
        'message' => 'Alhamdulillah sudah ditertibkan Pak. Pelanggar kooperatif menandatangani Berita Acara dan exciter pemancar sudah diamankan.'
    ]
];

foreach ($messages as $m) {
    $stmtIns = $pdo->prepare("INSERT INTO chat_messages (sender_id, receiver_id, is_team_chat, message, is_read, created_at) VALUES (?, ?, ?, ?, 1, datetime('now', '-20 minutes'))");
    $stmtIns->execute([$m['sender'], $m['receiver'], $m['is_team'], $m['message']]);
}
echo "Pesan chat interaksi tim & direct berhasil dimasukkan.\n";

// 7. Table perjanjian_kinerja (Nota Dinas No: 224/DJID.6/PR.04.01/02/2026)
$pdo->exec("DROP TABLE IF EXISTS perjanjian_kinerja;");
$pdo->exec("
CREATE TABLE perjanjian_kinerja (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code VARCHAR(20) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    indicator TEXT NOT NULL,
    target_annual REAL NOT NULL,
    unit VARCHAR(50) DEFAULT '%',
    realization REAL DEFAULT 0,
    formula TEXT NOT NULL,
    manual_measurement TEXT NOT NULL,
    app_reference VARCHAR(100) DEFAULT '',
    icon VARCHAR(50) DEFAULT 'insights',
    badge_color VARCHAR(30) DEFAULT 'primary',
    notes TEXT DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
");

$pkItemsSetup = [
    [
        'code' => 'PK.1',
        'title' => 'Monitoring SFR di Kabupaten/Kota',
        'indicator' => 'Persentase (%) Monitoring Penggunaan Spektrum Frekuensi Radio Dan Pengenaan Sanksi Terhadap Pengguna Spektrum Frekuensi Radio Di Kabupaten/Kota',
        'target_annual' => 80.0,
        'unit' => '% Kab/Kota',
        'realization' => 82.5,
        'formula' => '(Jumlah Kabupaten Kota Termonitor / Jumlah Kabupaten Kota Wil. Kerja UPT) x 100%',
        'manual_measurement' => 'Syarat Kab/Kota Termonitor: 15 Pita Frekuensi Termonitor, 60% ISR acuan Report Online (ROL), 100% hasil monitor SFR teridentifikasi, dan 100% tindak lanjut pengenaan sanksi atas temuan.',
        'app_reference' => 'Report Online (ROL)',
        'icon' => 'radar',
        'badge_color' => 'primary',
        'notes' => 'Wilayah kerja Balmon Samarinda: 10 Kab/Kota di Kaltim & IKN Nusantara.'
    ],
    [
        'code' => 'PK.2',
        'title' => 'Pemeriksaan Stasiun Radio & Sanksi',
        'indicator' => 'Persentase (%) Pemeriksaan Stasiun Radio Dan Pengenaan Sanksi Pelanggaran Terhadap Pemeriksaan Stasiun Radio',
        'target_annual' => 100.0,
        'unit' => '%',
        'realization' => 91.4,
        'formula' => '((Jumlah Pemeriksaan Stasiun Radio + Tindak Lanjut Sanksi Pelanggaran) / (Total Target Sampling + Total Pelanggaran)) x 100%',
        'manual_measurement' => 'Microwave link remote site min 80% & open shelter min 60% via APSTARD; Pengukuran parameter teknis Radio FM min 50 ISR (Balmon Samarinda: 76 ISR); TV Digital min 20 ISR.',
        'app_reference' => 'APSTARD & ROL',
        'icon' => 'settings_input_antenna',
        'badge_color' => 'info',
        'notes' => 'Prioritas: microwave operator seluler/non-seluler, Radio FM off air 2025, TV digital terestrial DVB-T2.'
    ],
    [
        'code' => 'PK.3',
        'title' => 'Penyelesaian Gangguan Frekuensi Radio',
        'indicator' => 'Persentase (%) Penyelesaian Gangguan Spektrum Frekuensi Radio',
        'target_annual' => 95.0,
        'unit' => '%',
        'realization' => 96.2,
        'formula' => '(Jumlah Gangguan SFR yang Terselesaikan / Jumlah Total Gangguan SFR) x 100%',
        'manual_measurement' => '100% aduan tertangani via SPT (1x24 jam untuk dinas keselamatan, 7x24 jam non-keselamatan). 95% terselesaikan dengan status closed di aplikasi trouble ticket / Nota Dinas.',
        'app_reference' => 'Trouble Ticket SFR',
        'icon' => 'warning_amber',
        'badge_color' => 'danger',
        'notes' => 'Tautan sistem: laporgangguansfr.postel.go.id. Khusus HF dikoordinir pusat.'
    ],
    [
        'code' => 'PK.4',
        'title' => 'Monitoring Perangkat Telekomunikasi (APT)',
        'indicator' => 'Persentase (%) Monitoring Alat Perangkat Telekomunikasi',
        'target_annual' => 90.0,
        'unit' => '%',
        'realization' => 88.0,
        'formula' => '(Σ Perangkat Termonitor / Σ Perangkat Target Monitoring) x 100%',
        'manual_measurement' => 'Target 300 perangkat/tahun di 80% Kab/Kota (min 10 Kab/Kota). Wajib min 5 kategori Tabel 1 (perangkat wajib standar teknis) & min 3 kategori Tabel 2 (fitur telekomunikasi). Input Berita Acara via SMART.',
        'app_reference' => 'Aplikasi SMART',
        'icon' => 'devices_other',
        'badge_color' => 'warning',
        'notes' => 'Pengawasan peredaran di toko/distributor/marketplace, penindakan booster/repeater ilegal.'
    ],
    [
        'code' => 'PK.5',
        'title' => 'Kepatuhan Standar Teknis & Sanksi APT',
        'indicator' => 'Persentase (%) Tingkat Kepatuhan Standar Teknis Alat dan/atau Perangkat Telekomunikasi dan Digital',
        'target_annual' => 100.0,
        'unit' => '%',
        'realization' => 100.0,
        'formula' => '(Σ Tindak Lanjut Pelanggaran / Σ Total Data Pelanggaran) x 100%',
        'manual_measurement' => '100% pelanggaran ditindaklanjuti dengan penerbitan Surat Teguran administratif dan pelaporan via aplikasi SMART dan tautan resmi Komdigi.',
        'app_reference' => 'Aplikasi SMART',
        'icon' => 'gavel',
        'badge_color' => 'dark',
        'notes' => 'Pelanggaran wajib dimonitor kembali setelah 7 hari kalender sejak teguran tertulis diterima.'
    ],
    [
        'code' => 'PK.6',
        'title' => 'Operasional & SLA Stasiun Monitor (SMFR)',
        'indicator' => 'Persentase (%) Terjaganya Operasional Dan Fungsi Monitoring Dari Stasiun Monitor Frekuensi Radio di UPT',
        'target_annual' => 86.0,
        'unit' => '% SLA',
        'realization' => 89.5,
        'formula' => '(SLA Stasiun Monitor Tetap/Transportable + SLA Stasiun Bergerak) / 2',
        'manual_measurement' => 'Stasiun Tetap/Transportable: SLA 100% jika upload data okupansi SMSN min 1x/minggu (4x/bulan). Bergerak: SLA 100% jika upload min 1x/bulan. Pelaporan via smfr.postel.go.id/v2/ & SMSN.',
        'app_reference' => 'SMFR v2 & SMSN',
        'icon' => 'cell_tower',
        'badge_color' => 'success',
        'notes' => 'Menghitung ketersediaan perangkat utama (Receiver/RDF, Antena, Software) & pendukung (PC, Router, Mast, PMU).'
    ],
    [
        'code' => 'PK.7',
        'title' => 'Penanganan Sanksi Denda Administratif',
        'indicator' => 'Persentase (%) Penanganan Sanksi Denda Administratif',
        'target_annual' => 85.0,
        'unit' => '%',
        'realization' => 78.5,
        'formula' => '(Σ Surat Tagihan Terbayar + (Bobot x Tindak Lanjut Surat Tagihan Belum Terbayar)) / Σ Total Surat Tagihan x 100%',
        'manual_measurement' => 'Pengelolaan Surat Tagihan denda pelanggaran IPFR/SFR/APT untuk optimalisasi PNBP. Target bertahap: TW I >= 60%, TW II >= 65%, TW III >= 70%, TW IV >= 75%, Des 81%, Tahunan 85%.',
        'app_reference' => 'Aplikasi Denda Administratif',
        'icon' => 'payments',
        'badge_color' => 'secondary',
        'notes' => 'Bobot: Tagihan 1 = 1, Tagihan 2 = 0.9, Tagihan 3 = 0.8, Optimalisasi = 0.7, Pelimpahan KPKNL = 0.6.'
    ],
    [
        'code' => 'PK.8',
        'title' => 'Data Kualitas Layanan Seluler (QoS)',
        'indicator' => 'Persentase (%) Tersedianya Data Hasil Pengukuran Kualitas Layanan',
        'target_annual' => 100.0,
        'unit' => '% Kab/Kota',
        'realization' => 90.0,
        'formula' => '(Jumlah Kab/Kota yang Sudah Diukur / Jumlah 80% Kab/Kota Wilayah Kerja) x 100%',
        'manual_measurement' => 'Pengukuran kualitas layanan telekomunikasi di 80% Kab/Kota wilayah kerja UPT via Drive Test (min 5 jam kecepatan ~40 km/jam) dan Static Test (min 3 PoI per kab/kota). Post-processing via PMT.',
        'app_reference' => 'Pusat Monitoring Telekomunikasi (PMT)',
        'icon' => 'speed',
        'badge_color' => 'info',
        'notes' => 'Mengukur FTP Speed, Browsing, YouTube test, Video Quality, WhatsApp Call/Message, 4G/5G Quality.'
    ]
];

$stmtPKIns = $pdo->prepare("
    INSERT INTO perjanjian_kinerja (
        code, title, indicator, target_annual, unit, realization, formula, manual_measurement, app_reference, icon, badge_color, notes
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
foreach ($pkItemsSetup as $pk) {
    $stmtPKIns->execute([
        $pk['code'], $pk['title'], $pk['indicator'], $pk['target_annual'], $pk['unit'], $pk['realization'],
        $pk['formula'], $pk['manual_measurement'], $pk['app_reference'], $pk['icon'], $pk['badge_color'], $pk['notes']
    ]);
}
echo "Tabel Perjanjian Kinerja (PK) 2026 berhasil dibuat dan diisi.\n";

// Tabel Riwayat Impor & Audit Tracing
$pdo->exec("
CREATE TABLE IF NOT EXISTS import_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    import_type VARCHAR(50) NOT NULL,
    indicator_code VARCHAR(20) DEFAULT 'PK.1',
    batch_code VARCHAR(50) UNIQUE NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    saved_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size INTEGER DEFAULT 0,
    file_extension VARCHAR(10) DEFAULT '',
    total_rows INTEGER DEFAULT 0,
    valid_rows INTEGER DEFAULT 0,
    invalid_rows INTEGER DEFAULT 0,
    realization_before REAL DEFAULT NULL,
    realization_after REAL DEFAULT NULL,
    summary_result TEXT DEFAULT '',
    status VARCHAR(20) DEFAULT 'success',
    notes TEXT DEFAULT '',
    user_id INTEGER NOT NULL,
    uploader_name VARCHAR(150) NOT NULL,
    uploader_nip VARCHAR(50) DEFAULT '',
    ip_address VARCHAR(50) DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS import_history_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    import_history_id INTEGER NOT NULL,
    row_number INTEGER NOT NULL,
    row_identifier VARCHAR(150) DEFAULT '',
    row_data TEXT NOT NULL,
    status VARCHAR(20) DEFAULT 'valid',
    evaluation_notes TEXT DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (import_history_id) REFERENCES import_history(id) ON DELETE CASCADE
);
");
echo "Tabel import_history dan import_history_logs berhasil disiapkan.\n";

echo "\n>>> Database Balmon Samarinda berhasil diperbarui dengan 24 Personil Resmi & 8 Indikator PK 2026! <<<\n";
