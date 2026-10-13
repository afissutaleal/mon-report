<?php
// includes/category_definitions.php

function getCategoryTechnicalFields($catId) {
    switch ($catId) {
        case 1: // Spektrum Frekuensi
            return [
                ['name' => 'bandwidth', 'label' => 'Bandwidth (kHz/MHz)', 'type' => 'text', 'placeholder' => 'Contoh: 12.5 kHz / 25 kHz', 'required' => true],
                ['name' => 'emission_mode', 'label' => 'Mode Emisi', 'type' => 'text', 'placeholder' => 'Contoh: 11K0F3E / 16K0F3E', 'required' => true],
                ['name' => 'call_sign', 'label' => 'Call Sign / Identitas Stasiun', 'type' => 'text', 'placeholder' => 'Contoh: JZ19BM / PHM-01', 'required' => false],
                ['name' => 'signal_level', 'label' => 'Kekuatan Sinyal (dBm / dBuV)', 'type' => 'text', 'placeholder' => 'Contoh: -65 dBm / 72 dBuV', 'required' => true],
                ['name' => 'field_strength', 'label' => 'Kuat Medan (dBuV/m)', 'type' => 'text', 'placeholder' => 'Contoh: 68.5 dBuV/m', 'required' => false],
                ['name' => 'isr_number', 'label' => 'Nomor Izin Stasiun Radio (ISR)', 'type' => 'text', 'placeholder' => 'Contoh: 01892344/DIGITAL/2026', 'required' => true],
                ['name' => 'isr_expiry', 'label' => 'Masa Berlaku ISR', 'type' => 'date', 'placeholder' => '', 'required' => false],
                ['name' => 'compliance_status', 'label' => 'Kesesuaian Penggunaan', 'type' => 'select', 'options' => ['Sesuai ISR', 'Tidak Sesuai Parameter ISR', 'Tanpa ISR (Ilegal)'], 'required' => true],
                ['name' => 'recommendation', 'label' => 'Analisis & Rekomendasi', 'type' => 'textarea', 'placeholder' => 'Kesesuaian alokasi pita frekuensi...', 'required' => false]
            ];

        case 2: // Microwavelink
            return [
                ['name' => 'link_name', 'label' => 'Nama Link / Hop', 'type' => 'text', 'placeholder' => 'Contoh: Site Samarinda Kota ke Site Palaran', 'required' => true],
                ['name' => 'antenna_polarization', 'label' => 'Polarisasi Antena', 'type' => 'select', 'options' => ['Vertical', 'Horizontal', 'Dual Polarized'], 'required' => true],
                ['name' => 'antenna_diameter', 'label' => 'Diameter Antena (meter)', 'type' => 'text', 'placeholder' => 'Contoh: 0.6 meter / 1.2 meter', 'required' => false],
                ['name' => 'antenna_height', 'label' => 'Ketinggian Antena AGL (meter)', 'type' => 'text', 'placeholder' => 'Contoh: 45 meter', 'required' => false],
                ['name' => 'tx_power', 'label' => 'Daya Pancar Tx Power (dBm/Watt)', 'type' => 'text', 'placeholder' => 'Contoh: +24 dBm', 'required' => true],
                ['name' => 'rsl', 'label' => 'Received Signal Level (RSL)', 'type' => 'text', 'placeholder' => 'Contoh: -42.5 dBm', 'required' => true],
                ['name' => 'azimuth', 'label' => 'Sudut Azimuth (°)', 'type' => 'text', 'placeholder' => 'Contoh: 142°', 'required' => false],
                ['name' => 'isr_number', 'label' => 'Nomor ISR Microwavelink', 'type' => 'text', 'placeholder' => 'Nomor ISR', 'required' => true],
                ['name' => 'device_condition', 'label' => 'Kondisi Fisik Perangkat', 'type' => 'select', 'options' => ['Baik / Prima', 'Perlu Pemeliharaan', 'Rusak / Deformasi Antena'], 'required' => true],
                ['name' => 'link_status', 'label' => 'Status Operasional Link', 'type' => 'select', 'options' => ['Aktif Beroperasi Normal', 'Interferensi / Link Degradasi', 'Nonaktif / Off Air'], 'required' => true]
            ];

        case 3: // Radio FM
            return [
                ['name' => 'erp_power', 'label' => 'Daya Pancar Pemancar (ERP)', 'type' => 'text', 'placeholder' => 'Contoh: 3000 Watt / 3 kW', 'required' => true],
                ['name' => 'freq_deviation', 'label' => 'Deviasi Frekuensi Maksimum (kHz)', 'type' => 'text', 'placeholder' => 'Standar: Max ±75 kHz (Contoh: 71.2 kHz)', 'required' => true],
                ['name' => 'field_strength', 'label' => 'Kuat Medan / Field Strength (dBuV/m)', 'type' => 'text', 'placeholder' => 'Contoh: 82.4 dBuV/m', 'required' => true],
                ['name' => 'modulation_depth', 'label' => 'Kedalaman Modulasi (%)', 'type' => 'text', 'placeholder' => 'Contoh: 92%', 'required' => false],
                ['name' => 'harmonic_spurious', 'label' => 'Emisi Harmonisa / Spurious (dBc)', 'type' => 'text', 'placeholder' => 'Contoh: -68 dBc (Standar min -60 dBc)', 'required' => true],
                ['name' => 'service_area', 'label' => 'Wilayah Layanan Siaran', 'type' => 'text', 'placeholder' => 'Samarinda, Kukar, sekitarnya', 'required' => false],
                ['name' => 'isr_number', 'label' => 'Nomor ISR Radio Siaran FM', 'type' => 'text', 'placeholder' => 'Nomor ISR', 'required' => true],
                ['name' => 'compliance_status', 'label' => 'Kesesuaian Standar Teknis Siaran', 'type' => 'select', 'options' => ['Memenuhi Standar Teknis', 'Deviasi Berlebih (>75 kHz)', 'Harmonisa Melebihi Batas', 'Tanpa ISR'], 'required' => true]
            ];

        case 4: // TV Digital
            return [
                ['name' => 'channel_uhf', 'label' => 'Kanal UHF (CH 22 - 48)', 'type' => 'text', 'placeholder' => 'Contoh: CH 30 UHF (546 MHz)', 'required' => true],
                ['name' => 'center_frequency', 'label' => 'Frekuensi Tengah (MHz)', 'type' => 'text', 'placeholder' => 'Contoh: 546.000 MHz', 'required' => true],
                ['name' => 'mer_value', 'label' => 'Modulation Error Ratio (MER dalam dB)', 'type' => 'text', 'placeholder' => 'Batas minimum 24 dB (Contoh: 32.8 dB)', 'required' => true],
                ['name' => 'ber_value', 'label' => 'Bit Error Rate (BER / VBER)', 'type' => 'text', 'placeholder' => 'Contoh: < 1.0E-8', 'required' => true],
                ['name' => 'received_level', 'label' => 'Level Sinyal Penerimaan (dBm/dBuV)', 'type' => 'text', 'placeholder' => 'Contoh: -48.2 dBm', 'required' => true],
                ['name' => 'guard_interval', 'label' => 'Guard Interval / Sinkronisasi SFN', 'type' => 'text', 'placeholder' => 'Contoh: 1/16, 1/32', 'required' => false],
                ['name' => 'constellation', 'label' => 'Konstelasi & Mode FFT', 'type' => 'text', 'placeholder' => 'Contoh: 64-QAM / 32K FFT', 'required' => false],
                ['name' => 'mux_operator', 'label' => 'Penyelenggara MUX', 'type' => 'text', 'placeholder' => 'TVRI Kaltim / Metro TV / Transmedia / VIVA', 'required' => true],
                ['name' => 'content_programs', 'label' => 'Daftar Program Siaran Dalam MUX', 'type' => 'text', 'placeholder' => 'Nama-nama stasiun TV yang disiarkan', 'required' => false],
                ['name' => 'video_quality', 'label' => 'Kualitas Video & Audio', 'type' => 'select', 'options' => ['HD 1080i (Lancar / Tanpa Glitch)', 'Terkadang Glitch / Mosaik', 'Sinyal Putus / No Signal'], 'required' => true]
            ];

        case 5: // Monitoring HF
            return [
                ['name' => 'emission_mode', 'label' => 'Mode Emisi / Modulasi', 'type' => 'select', 'options' => ['J3E (USB - Upper Side Band)', 'J3E (LSB - Lower Side Band)', 'A3E (AM)', 'A1A (CW Morse)', 'F1B (DSC Digital)'], 'required' => true],
                ['name' => 'direction_finding_azimuth', 'label' => 'Arah Kedatangan Sinyal (LoB / Azimuth °)', 'type' => 'text', 'placeholder' => 'Contoh: 115° (Pesisir Timur / Selat Makassar)', 'required' => true],
                ['name' => 'utc_time', 'label' => 'Waktu Deteksi (UTC & WITA)', 'type' => 'text', 'placeholder' => 'Contoh: 02:30 UTC / 10:30 WITA', 'required' => true],
                ['name' => 'signal_characteristics', 'label' => 'Karakteristik & Propagasi Sinyal', 'type' => 'text', 'placeholder' => 'Contoh: Fading normal, propagasi ionosfer baik', 'required' => false],
                ['name' => 'observed_stations', 'label' => 'Stasiun Teramati / Call Sign', 'type' => 'text', 'placeholder' => 'Identitas kapal, pesawat, atau stasiun pantai', 'required' => false],
                ['name' => 'interference_detected', 'label' => 'Indikasi Interferensi / Gangguan', 'type' => 'select', 'options' => ['Nihil / Bersih', 'Interferensi Co-channel', 'Interferensi Jamming', 'Penggunaan Ilegal Kanal Darurat'], 'required' => true],
                ['name' => 'recommendation', 'label' => 'Catatan & Rekomendasi Tindak Lanjut', 'type' => 'textarea', 'placeholder' => 'Rekomendasi teknis...', 'required' => false]
            ];

        case 6: // Alat Telekomunikasi (Monitoring APT)
            return [
                ['name' => 'device_type', 'label' => 'Jenis Perangkat Prioritas', 'type' => 'select', 'options' => [
                    'HF All Band Transceiver',
                    'Mobile Transceiver / Radio Rig',
                    'Wireless Access Point (WiFi Extender, Repeater Router)',
                    'Pemancar FM',
                    'Simbox / Modempool',
                    'Fake BTS / IMSI Catcher',
                    'Repeater / Penguat Sinyal Seluler',
                    'Jammer',
                    'GPS Tracker',
                    'Microwavelink (IDU/ODU)',
                    'Handy Talkie (HT)',
                    'Radio POC',
                    'Set Top Box (STB)',
                    'Perangkat Low Power (TWS, Wireless Mic, Speaker, Smart Watch, Keyboard)',
                    'Optical Line Terminal (OLT) & ONT',
                    'Alat Perangkat Telekomunikasi Rakitan',
                    'Perangkat Telekomunikasi Lainnya'
                ], 'required' => true],
                ['name' => 'brand_model', 'label' => 'Merek & Tipe / Model', 'type' => 'text', 'placeholder' => 'Contoh: Baofeng UV-5R / Ubiquiti Rocket / Lintratek', 'required' => true],
                ['name' => 'serial_numbers', 'label' => 'Nomor Seri Perangkat (S/N)', 'type' => 'text', 'placeholder' => 'Nomor seri perangkat yang diperiksa', 'required' => false],
                ['name' => 'certification_sdppi', 'label' => 'Status Sertifikat Komdigi / SDPPI', 'type' => 'select', 'options' => ['Bersertifikat Resmi & Berlabel', 'Label Sertifikat Palsu / Meragukan', 'Tidak Bersertifikat (Non-certified)'], 'required' => true],
                ['name' => 'store_type', 'label' => 'Lokasi / Objek Pemeriksaan', 'type' => 'select', 'options' => ['Toko / Distributor Elektronik', 'Gudang Ekspedisi / Logistik', 'Marketplace / Penjualan Online', 'Instalasi BTS / Site Seluler', 'Pengguna Akhir / Industri'], 'required' => true],
                ['name' => 'units_observed', 'label' => 'Jumlah Unit Diperiksa', 'type' => 'number', 'placeholder' => 'Contoh: 12', 'required' => true],
                ['name' => 'compliance_status', 'label' => 'Hasil Pengawasan', 'type' => 'select', 'options' => ['Memenuhi Standar Teknis', 'Perlu Pembinaan & Edukasi', 'Pelanggaran / Indikasi Ilegal (Dikenakan Sanksi)'], 'required' => true],
                ['name' => 'ba_number', 'label' => 'Nomor Berita Acara Pemeriksaan (BAP)', 'type' => 'text', 'placeholder' => 'Nomor BAP Pemeriksaan APT', 'required' => false],
                ['name' => 'smart_reported', 'label' => 'Status Pelaporan Aplikasi SMART', 'type' => 'select', 'options' => ['Sudah Dilaporkan di SMART', 'Dalam Proses Input SMART', 'Belum Dilaporkan'], 'required' => true],
                ['name' => 'action_taken', 'label' => 'Tindakan / Dokumen Pengenaan Sanksi', 'type' => 'textarea', 'placeholder' => 'Surat Teguran, BA Klarifikasi, BA Penghentian Perdagangan/Penggunaan...', 'required' => false]
            ];

        case 7: // Penertiban Frekuensi
            return [
                ['name' => 'warrant_number', 'label' => 'Nomor Surat Perintah Tugas Penertiban', 'type' => 'text', 'placeholder' => 'Contoh: 337/SP.03.03/09/2026', 'required' => true],
                ['name' => 'violation_type', 'label' => 'Jenis Pelanggaran Spektrum', 'type' => 'select', 'options' => ['Tanpa ISR (Stasiun Radio Ilegal)', 'Mengganggu Frekuensi Penerbangan / Maritim', 'Daya Pancar & Parameter Tidak Sesuai ISR', 'Penggunaan Pita Frekuensi Terlarang'], 'required' => true],
                ['name' => 'violator_identity', 'label' => 'Identitas Pelanggar / Pemilik Stasiun', 'type' => 'text', 'placeholder' => 'Nama orang / perusahaan pelanggar', 'required' => true],
                ['name' => 'action_type', 'label' => 'Tindakan Penertiban Yang Dilakukan', 'type' => 'select', 'options' => ['Peringatan & Teguran Tertulis', 'Penghentian Pancaran Secara Sukarela', 'Penyegelan Perangkat Pemancar', 'Penyitaan Barang Bukti oleh PPNS'], 'required' => true],
                ['name' => 'evidence_items', 'label' => 'Barang Bukti Diamankan / Disegel', 'type' => 'textarea', 'placeholder' => '1 unit Pemancar FM 100W, 1 unit Power Supply...', 'required' => false],
                ['name' => 'bap_number', 'label' => 'Nomor Berita Acara Penertiban (BAP)', 'type' => 'text', 'placeholder' => 'BAP-PPNS-BALMON-SMD/...', 'required' => true],
                ['name' => 'ppns_officer', 'label' => 'Penyidik PPNS Ditjend Infrastruktur Digital Yang Bertugas', 'type' => 'text', 'placeholder' => 'Nama Penyidik PPNS', 'required' => true],
                ['name' => 'legal_followup', 'label' => 'Tindak Lanjut Proses Hukum', 'type' => 'select', 'options' => ['Surat Peringatan 1 (SP1)', 'Surat Peringatan 2 (SP2)', 'Proses Penyidikan Pidana (Pro Justitia)', 'Selesai Dibina'], 'required' => true]
            ];

        case 8: // Penertiban Alat Telekomunikasi
            return [
                ['name' => 'warrant_number', 'label' => 'Nomor Surat Tugas Operasi Pasar', 'type' => 'text', 'placeholder' => 'ST.OPERASI-APT/BALMON-SMD/...', 'required' => true],
                ['name' => 'device_confiscated', 'label' => 'Perangkat Yang Ditertibkan', 'type' => 'text', 'placeholder' => 'Penguat Sinyal HP Ilegal / Jammer / HT Tanpa Izin', 'required' => true],
                ['name' => 'brand', 'label' => 'Merek & Tipe Perangkat', 'type' => 'text', 'placeholder' => 'Contoh: Lintratek Repeater Booster', 'required' => false],
                ['name' => 'quantity', 'label' => 'Jumlah Unit Ditertibkan', 'type' => 'text', 'placeholder' => 'Contoh: 3 unit booster + antena', 'required' => true],
                ['name' => 'impact', 'label' => 'Dampak Gangguan Yang Ditimbulkan', 'type' => 'textarea', 'placeholder' => 'Menaikkan noise floor pada BTS seluler...', 'required' => true],
                ['name' => 'bap_number', 'label' => 'Nomor Berita Acara Pemeriksaan / Penyitaan', 'type' => 'text', 'placeholder' => 'Nomor BAP', 'required' => true],
                ['name' => 'owner_statement', 'label' => 'Keterangan Pihak Terperiksa', 'type' => 'textarea', 'placeholder' => 'Pernyataan pemilik...', 'required' => false],
                ['name' => 'sanction', 'label' => 'Sanksi & Tindakan Pengamanan', 'type' => 'select', 'options' => ['Pembongkaran & Penyegelan Perangkat', 'Penyitaan Sementara untuk Pemeriksaan', 'Penyerahan Sukarela & Pakta Integritas', 'Sanksi Pidana Telekomunikasi'], 'required' => true]
            ];

        case 9: // Penugasan Lainnya
            return [
                ['name' => 'mission_name', 'label' => 'Nama / Agenda Penugasan Khusus', 'type' => 'text', 'placeholder' => 'Contoh: Pengawalan Frekuensi VVIP Kunjungan Presiden di IKN', 'required' => true],
                ['name' => 'coverage_area', 'label' => 'Wilayah Cakupan Operasi', 'type' => 'text', 'placeholder' => 'Contoh: Kawasan Inti IKN, Tol Balsam, Helipad', 'required' => true],
                ['name' => 'equipment_deployed', 'label' => 'Instrumen / Peralatan Yang Dikerahkan', 'type' => 'textarea', 'placeholder' => 'Mobil Unit Monitoring (MUMF), Spectrum Analyzer Rohde & Schwarz, Antena DF...', 'required' => true],
                ['name' => 'team_members', 'label' => 'Personel Tim Penugasan', 'type' => 'text', 'placeholder' => 'Daftar nama petugas yang terlibat', 'required' => true],
                ['name' => 'spectrum_health', 'label' => 'Kondisi Spektrum Selama Kegiatan', 'type' => 'select', 'options' => ['Bebas Gangguan (Green Status - Kondusif)', 'Terdeteksi Interferensi Minor (Telah Diatasi)', 'Gangguan Signifikan (Perlu Investigasi Lanjutan)'], 'required' => true],
                ['name' => 'drones_monitored', 'label' => 'Pengawasan Frekuensi Drone / UAV', 'type' => 'text', 'placeholder' => 'Contoh: 5 unit drone terdaftar Paspampres, 0 ilegal', 'required' => false],
                ['name' => 'official_report_to', 'label' => 'Pihak Terkait / Laporan Diteruskan Kepada', 'type' => 'text', 'placeholder' => 'Paspampres / Ditjend Infrastruktur Digital / Dishub', 'required' => false]
            ];

        case 10: // Penanganan Gangguan
            return [
                ['name' => 'complaint_number', 'label' => 'Nomor Surat Aduan / Tiket Gangguan', 'type' => 'text', 'placeholder' => 'Contoh: 145/180/C/III/2026-01', 'required' => true],
                ['name' => 'isr_numbers', 'label' => 'Nomor Izin Stasiun Radio (ISR) Terdampak', 'type' => 'text', 'placeholder' => 'Nomor ISR stasiun radio terganggu', 'required' => false],
                ['name' => 'affected_service', 'label' => 'Layanan / Pengguna Terdampak', 'type' => 'text', 'placeholder' => 'Contoh: Radio Komunikasi Tambang Land Mobile / Penerbangan / Maritim', 'required' => true],
                ['name' => 'source_identified', 'label' => 'Penyebab / Sumber Interferensi Ditemukan', 'type' => 'text', 'placeholder' => 'Contoh: Kebocoran Booster TV Kabel / Induksi Gardu Induk Listrik / Intermodulasi', 'required' => true],
                ['name' => 'action_taken', 'label' => 'Tindakan Lokalisir & Penanganan Teknis', 'type' => 'textarea', 'placeholder' => 'Pelacakan arah sinyal (DF), pengukuran modulasi, eliminasi sumber gangguan...', 'required' => true],
                ['name' => 'resolution_status', 'label' => 'Status Akhir Penanganan', 'type' => 'select', 'options' => ['Terselesaikan Tuntas (Clean - Bebas Gangguan)', 'Dalam Investigasi Lapangan', 'Perlu Tindakan Penertiban (PPNS)'], 'required' => true],
                ['name' => 'bap_number', 'label' => 'Nomor Berita Acara (BAP)', 'type' => 'text', 'placeholder' => 'Nomor BAP Penanganan / Pemeriksaan', 'required' => false],
                ['name' => 'equipment_used', 'label' => 'Instrumen / Peralatan Monitoring', 'type' => 'text', 'placeholder' => 'Contoh: R&S PR100, R&S ESMD DF, HE300, Laptop R&S Argus', 'required' => false],
                ['name' => 'team_leader', 'label' => 'Ketua Tim Pelaksana', 'type' => 'text', 'placeholder' => 'Nama & NIP Ketua Tim', 'required' => false],
                ['name' => 'team_members', 'label' => 'Anggota Tim Pelaksana', 'type' => 'text', 'placeholder' => 'Nama anggota tim', 'required' => false],
                ['name' => 'disposition', 'label' => 'Disposisi Kepala Balai', 'type' => 'textarea', 'placeholder' => 'Isi arahan / disposisi pimpinan', 'required' => false]
            ];

        case 11: // Kualitas Layanan (QoS)
            return [
                ['name' => 'operator_name', 'label' => 'Penyelenggara / Operator Jaringan', 'type' => 'select', 'options' => ['Telkomsel', 'Indosat Ooredoo Hutchison', 'XL Axiata', 'Smartfren', 'Internet Service Provider (ISP)', 'Lainnya'], 'required' => true],
                ['name' => 'network_technology', 'label' => 'Teknologi Akses Jaringan', 'type' => 'select', 'options' => ['4G LTE', '5G NR', 'Fiber Optic / FTTH', 'Wireless Broadband'], 'required' => true],
                ['name' => 'download_throughput', 'label' => 'Throughput Unduh / Download (Mbps)', 'type' => 'text', 'placeholder' => 'Contoh: 45.8 Mbps', 'required' => true],
                ['name' => 'upload_throughput', 'label' => 'Throughput Unggah / Upload (Mbps)', 'type' => 'text', 'placeholder' => 'Contoh: 18.2 Mbps', 'required' => true],
                ['name' => 'latency_rtt', 'label' => 'Latensi / Round Trip Time (ms)', 'type' => 'text', 'placeholder' => 'Contoh: 28 ms', 'required' => true],
                ['name' => 'packet_loss', 'label' => 'Packet Loss (%)', 'type' => 'text', 'placeholder' => 'Contoh: 0.2%', 'required' => true],
                ['name' => 'qos_compliance', 'label' => 'Evaluasi Kualitas Layanan (QoS)', 'type' => 'select', 'options' => ['Memenuhi Standar Kualitas (QoS Baik)', 'Cukup / Rata-rata', 'Di Bawah Standar Minimal (Degradasi)'], 'required' => true],
                ['name' => 'measurement_route', 'label' => 'Rute / Titik Pengukuran Drive Test', 'type' => 'text', 'placeholder' => 'Jalan Protokol / Titik Blankspot / Fasilitas Publik...', 'required' => true]
            ];

        default:
            return [];
    }
}

function getCategoryMaterialIcon($catId) {
    $icons = [
        1 => 'cell_tower',
        2 => 'settings_input_antenna',
        3 => 'radio',
        4 => 'live_tv',
        5 => 'waves',
        6 => 'devices',
        7 => 'gavel',
        8 => 'security',
        9 => 'stars',
        10 => 'report_problem',
        11 => 'speed'
    ];
    return $icons[(int)$catId] ?? 'sensors';
}

