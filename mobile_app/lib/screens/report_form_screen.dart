// lib/screens/report_form_screen.dart
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../config/app_theme.dart';
import '../models/category_model.dart';
import '../services/api_service.dart';
import '../services/auth_service.dart';

class ReportFormScreen extends StatefulWidget {
  final CategoryModel category;

  const ReportFormScreen({super.key, required this.category});

  @override
  State<ReportFormScreen> createState() => _ReportFormScreenState();
}

class _ReportFormScreenState extends State<ReportFormScreen> {
  final _formKey = GlobalKey<FormState>();

  final _titleCtrl = TextEditingController();
  final _targetCtrl = TextEditingController();
  final _freqCtrl = TextEditingController();
  final _locationCtrl = TextEditingController();
  final _latCtrl = TextEditingController(text: '-0.502106');
  final _lngCtrl = TextEditingController(text: '117.153709');
  DateTime _selectedDate = DateTime.now();

  final Map<String, dynamic> _dynamicFields = {};
  bool _isSubmitting = false;

  @override
  void initState() {
    super.initState();
    _initDefaultValues();
  }

  void _initDefaultValues() {
    switch (widget.category.id) {
      case 1:
        _dynamicFields['bandwidth'] = '12.5 kHz';
        _dynamicFields['emission_mode'] = '11K0F3E';
        _dynamicFields['signal_level'] = '-65 dBm';
        _dynamicFields['isr_number'] = '';
        _dynamicFields['compliance_status'] = 'Sesuai ISR';
        break;
      case 2:
        _dynamicFields['link_name'] = '';
        _dynamicFields['antenna_polarization'] = 'Vertical';
        _dynamicFields['tx_power'] = '+24 dBm';
        _dynamicFields['rsl'] = '-42.0 dBm';
        _dynamicFields['isr_number'] = '';
        _dynamicFields['device_condition'] = 'Baik / Prima';
        _dynamicFields['link_status'] = 'Aktif Beroperasi Normal';
        break;
      case 3:
        _dynamicFields['erp_power'] = '3000 Watt';
        _dynamicFields['freq_deviation'] = '72.0 kHz';
        _dynamicFields['field_strength'] = '80.0 dBuV/m';
        _dynamicFields['harmonic_spurious'] = '-65 dBc';
        _dynamicFields['compliance_status'] = 'Memenuhi Standar Teknis';
        break;
      case 4:
        _dynamicFields['channel_uhf'] = 'CH 30 UHF';
        _dynamicFields['mer_value'] = '32.0 dB';
        _dynamicFields['ber_value'] = '< 1.0E-8';
        _dynamicFields['received_level'] = '-48 dBm';
        _dynamicFields['mux_operator'] = 'TVRI Kaltim';
        _dynamicFields['video_quality'] = 'HD 1080i (Lancar / Tanpa Glitch)';
        break;
      case 5:
        _dynamicFields['emission_mode'] = 'J3E (USB - Upper Side Band)';
        _dynamicFields['direction_finding_azimuth'] = '115°';
        _dynamicFields['utc_time'] = '03:00 UTC';
        _dynamicFields['interference_detected'] = 'Nihil / Bersih';
        break;
      case 6:
        _dynamicFields['device_type'] = 'Handy Talkie (HT)';
        _dynamicFields['brand_model'] = '';
        _dynamicFields['certification_sdppi'] = 'Bersertifikat Resmi & Berlabel';
        _dynamicFields['store_type'] = 'Toko / Distributor Elektronik';
        _dynamicFields['units_observed'] = '10';
        _dynamicFields['compliance_status'] = 'Memenuhi Ketentuan';
        break;
      case 7:
        _dynamicFields['warrant_number'] = '';
        _dynamicFields['violation_type'] = 'Tanpa ISR (Stasiun Radio Ilegal)';
        _dynamicFields['violator_identity'] = '';
        _dynamicFields['action_type'] = 'Peringatan & Teguran Tertulis';
        _dynamicFields['bap_number'] = '';
        _dynamicFields['legal_followup'] = 'Surat Peringatan 1 (SP1)';
        break;
      case 8:
        _dynamicFields['warrant_number'] = '';
        _dynamicFields['device_confiscated'] = 'Cellular Signal Repeater';
        _dynamicFields['quantity'] = '2 unit';
        _dynamicFields['bap_number'] = '';
        _dynamicFields['sanction'] = 'Pembongkaran & Penyegelan Perangkat';
        break;
      case 9:
        _dynamicFields['mission_name'] = '';
        _dynamicFields['coverage_area'] = 'Kawasan Inti IKN Sepaku';
        _dynamicFields['equipment_deployed'] = 'Mobil Unit Monitoring (MUMF)';
        _dynamicFields['team_members'] = '';
        _dynamicFields['spectrum_health'] = 'Bebas Gangguan (Green Status - Kondusif)';
        break;
    }
  }

  Future<void> _handleSubmit() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isSubmitting = true);

    final payload = {
      'user_id': AuthService.currentUser?.id ?? 1,
      'category_id': widget.category.id,
      'title': _titleCtrl.text.trim(),
      'target_name': _targetCtrl.text.trim(),
      'frequency': _freqCtrl.text.trim(),
      'location': _locationCtrl.text.trim(),
      'latitude': double.tryParse(_latCtrl.text.trim()) ?? -0.502106,
      'longitude': double.tryParse(_lngCtrl.text.trim()) ?? 117.153709,
      'date_monitored': DateFormat('yyyy-MM-dd').format(_selectedDate),
      'status': 'submitted',
      'technical_data': _dynamicFields,
    };

    final res = await ApiService.submitReport(payload);

    setState(() => _isSubmitting = false);

    if (res['success'] == true) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(res['message'] ?? 'Laporan berhasil disimpan.'),
          backgroundColor: AppTheme.success,
        ),
      );
      Navigator.pop(context, true);
    } else {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(res['message'] ?? 'Gagal menyimpan laporan.'),
          backgroundColor: AppTheme.danger,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Input: ${widget.category.shortTitle}'),
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            // Category Card Header
            Card(
              color: AppTheme.primary.withOpacity(0.08),
              elevation: 0,
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Row(
                  children: [
                    const CircleAvatar(
                      backgroundColor: AppTheme.primary,
                      child: Icon(Icons.edit_note, color: Colors.white),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            widget.category.name,
                            style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13),
                          ),
                          const Text(
                            'Formulir Pelaporan Lapangan Balmon Samarinda',
                            style: TextStyle(fontSize: 11, color: Colors.grey),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),

            const Text('Informasi Umum', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14)),
            const SizedBox(height: 10),

            TextFormField(
              controller: _titleCtrl,
              decoration: const InputDecoration(
                labelText: 'Judul Laporan Monitoring *',
                hintText: 'Contoh: Pengukuran Stasiun Radio...',
              ),
              validator: (v) => v!.isEmpty ? 'Wajib diisi' : null,
            ),
            const SizedBox(height: 12),

            TextFormField(
              controller: _targetCtrl,
              decoration: const InputDecoration(
                labelText: 'Nama Target / Stasiun / Penyelenggara *',
                hintText: 'Contoh: PT Radio Suara Mahakam / PT Telkomsel',
              ),
              validator: (v) => v!.isEmpty ? 'Wajib diisi' : null,
            ),
            const SizedBox(height: 12),

            TextFormField(
              controller: _freqCtrl,
              decoration: const InputDecoration(
                labelText: 'Frekuensi Kerja (MHz / GHz / Band)',
                hintText: 'Contoh: 98.40 MHz / 15 GHz',
              ),
            ),
            const SizedBox(height: 12),

            TextFormField(
              controller: _locationCtrl,
              decoration: const InputDecoration(
                labelText: 'Lokasi Pelaksanaan *',
                hintText: 'Contoh: Jl. Pahlawan, Kota Samarinda',
              ),
              validator: (v) => v!.isEmpty ? 'Wajib diisi' : null,
            ),
            const SizedBox(height: 12),

            ListTile(
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
                side: BorderSide(color: Colors.grey.shade300),
              ),
              leading: const Icon(Icons.calendar_today, color: AppTheme.primary, size: 20),
              title: const Text('Tanggal Monitoring', style: TextStyle(fontSize: 12)),
              subtitle: Text(
                DateFormat('dd MMMM yyyy').format(_selectedDate),
                style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
              ),
              trailing: const Icon(Icons.arrow_drop_down),
              onTap: () async {
                final pick = await showDatePicker(
                  context: context,
                  initialDate: _selectedDate,
                  firstDate: DateTime(2025),
                  lastDate: DateTime(2028),
                );
                if (pick != null) setState(() => _selectedDate = pick);
              },
            ),
            const SizedBox(height: 12),

            Row(
              children: [
                Expanded(
                  child: TextFormField(
                    controller: _latCtrl,
                    decoration: const InputDecoration(labelText: 'Latitude GPS'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: TextFormField(
                    controller: _lngCtrl,
                    decoration: const InputDecoration(labelText: 'Longitude GPS'),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 24),

            // Dynamic Category Specific Technical Fields
            const Text(
              'Parameter Teknis Spesifik',
              style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: AppTheme.primary),
            ),
            const SizedBox(height: 10),

            ..._buildCategoryFields(),

            const SizedBox(height: 28),
            ElevatedButton(
              onPressed: _isSubmitting ? null : _handleSubmit,
              style: ElevatedButton.styleFrom(
                padding: const EdgeInsets.symmetric(vertical: 14),
              ),
              child: _isSubmitting
                  ? const SizedBox(
                      width: 22,
                      height: 22,
                      child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                    )
                  : const Text('Simpan & Ajukan Laporan', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
            ),
            const SizedBox(height: 30),
          ],
        ),
      ),
    );
  }

  List<Widget> _buildCategoryFields() {
    switch (widget.category.id) {
      case 1: // Spektrum
        return [
          _buildTextField('bandwidth', 'Bandwidth (kHz / MHz)'),
          _buildTextField('emission_mode', 'Mode Emisi (Contoh: 11K0F3E)'),
          _buildTextField('signal_level', 'Kekuatan Sinyal (dBm / dBuV)'),
          _buildTextField('isr_number', 'Nomor Izin Stasiun Radio (ISR)'),
          _buildDropdown('compliance_status', 'Kesesuaian Penggunaan', [
            'Sesuai ISR',
            'Tidak Sesuai Parameter ISR',
            'Tanpa ISR (Ilegal)'
          ]),
        ];
      case 2: // Microwavelink
        return [
          _buildTextField('link_name', 'Nama Hop Link (Site A ke Site B)'),
          _buildDropdown('antenna_polarization', 'Polarisasi Antena', ['Vertical', 'Horizontal']),
          _buildTextField('tx_power', 'Daya Pancar Tx Power (dBm/Watt)'),
          _buildTextField('rsl', 'Received Signal Level RSL (dBm)'),
          _buildTextField('isr_number', 'Nomor ISR Microwavelink'),
          _buildDropdown('device_condition', 'Kondisi Fisik Perangkat', ['Baik / Prima', 'Perlu Pemeliharaan', 'Rusak']),
        ];
      case 3: // Radio FM
        return [
          _buildTextField('erp_power', 'Daya Pancar ERP (Watt/kW)'),
          _buildTextField('freq_deviation', 'Deviasi Frekuensi Maksimum (kHz)'),
          _buildTextField('field_strength', 'Kuat Medan (dBuV/m)'),
          _buildTextField('harmonic_spurious', 'Emisi Harmonisa (dBc)'),
          _buildDropdown('compliance_status', 'Kesesuaian Standar Teknis', [
            'Memenuhi Standar Teknis',
            'Deviasi Berlebih (>75 kHz)',
            'Harmonisa Melebihi Batas'
          ]),
        ];
      case 4: // TV Digital
        return [
          _buildTextField('channel_uhf', 'Kanal UHF (CH 22 - 48)'),
          _buildTextField('mer_value', 'Modulation Error Ratio MER (dB)'),
          _buildTextField('ber_value', 'Bit Error Rate (BER)'),
          _buildTextField('received_level', 'Level Sinyal Penerimaan (dBm)'),
          _buildTextField('mux_operator', 'Penyelenggara MUX Siaran'),
          _buildDropdown('video_quality', 'Kualitas Layanan Siaran', [
            'HD 1080i (Lancar / Tanpa Glitch)',
            'Terkadang Glitch / Mosaik',
            'Sinyal Putus / No Signal'
          ]),
        ];
      case 5: // HF
        return [
          _buildDropdown('emission_mode', 'Mode Emisi', ['J3E (USB - Upper Side Band)', 'J3E (LSB)', 'A3E (AM)', 'CW Morse']),
          _buildTextField('direction_finding_azimuth', 'Arah Kedatangan LoB Azimuth (°)'),
          _buildTextField('utc_time', 'Waktu Deteksi (UTC / WITA)'),
          _buildDropdown('interference_detected', 'Deteksi Interferensi', ['Nihil / Bersih', 'Interferensi Co-channel', 'Jamming']),
        ];
      case 6: // Alat Telekomunikasi
        return [
          _buildDropdown('device_type', 'Kategori Perangkat', [
            'Handy Talkie (HT)',
            'Access Point WiFi 2.4/5.8 GHz',
            'Cellular Signal Repeater/Booster',
            'Radar / Drone'
          ]),
          _buildTextField('brand_model', 'Merek & Tipe Perangkat'),
          _buildTextField('units_observed', 'Jumlah Unit Diperiksa'),
          _buildDropdown('certification_sdppi', 'Status Sertifikasi SDPPI', [
            'Bersertifikat Resmi & Berlabel',
            'Label SDPPI Palsu / Meragukan',
            'Tidak Bersertifikat (Non-certified)'
          ]),
        ];
      case 7: // Penertiban Frekuensi
        return [
          _buildTextField('warrant_number', 'Nomor Surat Perintah Tugas Penertiban'),
          _buildDropdown('violation_type', 'Jenis Pelanggaran', [
            'Tanpa ISR (Stasiun Radio Ilegal)',
            'Mengganggu Frekuensi Penerbangan / Maritim',
            'Parameter Tidak Sesuai ISR'
          ]),
          _buildTextField('violator_identity', 'Identitas Pemilik / Pelanggar'),
          _buildDropdown('action_type', 'Tindakan Penertiban', [
            'Peringatan & Teguran Tertulis',
            'Penghentian Pancaran Secara Sukarela',
            'Penyegelan Perangkat Pemancar',
            'Penyitaan Barang Bukti oleh PPNS'
          ]),
          _buildTextField('bap_number', 'Nomor Berita Acara Penertiban (BAP)'),
        ];
      case 8: // Penertiban Alat
        return [
          _buildTextField('warrant_number', 'Nomor Surat Tugas Operasi'),
          _buildTextField('device_confiscated', 'Perangkat Yang Ditertibkan'),
          _buildTextField('quantity', 'Jumlah Barang Bukti'),
          _buildTextField('bap_number', 'Nomor Berita Acara Pemeriksaan'),
          _buildDropdown('sanction', 'Sanksi / Tindakan Pengamanan', [
            'Pembongkaran & Penyegelan Perangkat',
            'Penyitaan Sementara untuk Pemeriksaan',
            'Penyerahan Sukarela'
          ]),
        ];
      case 9: // Penugasan Lainnya
        return [
          _buildTextField('mission_name', 'Nama Agenda Penugasan Khusus'),
          _buildTextField('coverage_area', 'Wilayah Cakupan Operasi (IKN/Samarinda)'),
          _buildTextField('equipment_deployed', 'Peralatan / MMU Yang Dikerahkan'),
          _buildTextField('team_members', 'Personel Tim Penugasan'),
          _buildDropdown('spectrum_health', 'Kondisi Spektrum Selama Operasi', [
            'Bebas Gangguan (Green Status - Kondusif)',
            'Terdeteksi Interferensi Minor',
            'Gangguan Signifikan'
          ]),
        ];
      default:
        return [];
    }
  }

  Widget _buildTextField(String key, String label) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextFormField(
        initialValue: _dynamicFields[key]?.toString() ?? '',
        decoration: InputDecoration(labelText: label),
        onChanged: (v) => _dynamicFields[key] = v,
      ),
    );
  }

  Widget _buildDropdown(String key, String label, List<String> options) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: DropdownButtonFormField<String>(
        value: options.contains(_dynamicFields[key]) ? _dynamicFields[key] : options.first,
        decoration: InputDecoration(labelText: label),
        items: options
            .map((opt) => DropdownMenuItem(value: opt, child: Text(opt, style: const TextStyle(fontSize: 13))))
            .toList(),
        onChanged: (v) => setState(() => _dynamicFields[key] = v),
      ),
    );
  }
}
