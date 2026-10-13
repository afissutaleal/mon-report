// lib/screens/report_detail_screen.dart
import 'package:flutter/material.dart';
import '../config/app_theme.dart';
import '../models/report_model.dart';
import '../services/api_service.dart';
import '../services/auth_service.dart';

class ReportDetailScreen extends StatefulWidget {
  final int reportId;

  const ReportDetailScreen({super.key, required this.reportId});

  @override
  State<ReportDetailScreen> createState() => _ReportDetailScreenState();
}

class _ReportDetailScreenState extends State<ReportDetailScreen> {
  bool _isLoading = true;
  ReportModel? _report;

  @override
  void initState() {
    super.initState();
    _loadReport();
  }

  Future<void> _loadReport() async {
    setState(() => _isLoading = true);
    final rep = await ApiService.fetchReportDetail(widget.reportId);
    setState(() {
      _report = rep;
      _isLoading = false;
    });
  }

  Future<void> _showVerificationDialog() async {
    if (_report == null) return;
    String selectedStatus = _report!.status == 'submitted' ? 'verified' : _report!.status;
    final notesCtrl = TextEditingController(text: _report!.verificationNotes);

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setDialogState) => AlertDialog(
          title: const Text('Proses Verifikasi Laporan', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Kode: ${_report!.reportCode}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: AppTheme.primary)),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                value: selectedStatus,
                decoration: const InputDecoration(labelText: 'Keputusan Verifikasi'),
                items: const [
                  DropdownMenuItem(value: 'verified', child: Text('Setujui (Terverifikasi)')),
                  DropdownMenuItem(value: 'revision', child: Text('Kembalikan (Perlu Revisi)')),
                  DropdownMenuItem(value: 'rejected', child: Text('Tolak Laporan')),
                ],
                onChanged: (val) {
                  if (val != null) setDialogState(() => selectedStatus = val);
                },
              ),
              const SizedBox(height: 12),
              TextField(
                controller: notesCtrl,
                maxLines: 3,
                decoration: const InputDecoration(
                  labelText: 'Catatan / Instruksi Perbaikan',
                  hintText: 'Tuliskan catatan teknis...',
                ),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: const Text('Batal'),
            ),
            ElevatedButton(
              onPressed: () async {
                Navigator.pop(ctx);
                final ok = await ApiService.submitVerification(
                  reportId: _report!.id,
                  status: selectedStatus,
                  notes: notesCtrl.text.trim(),
                  verifierId: AuthService.currentUser?.id ?? 1,
                );
                if (mounted) {
                  if (ok) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('Status verifikasi berhasil disimpan!'), backgroundColor: AppTheme.success),
                    );
                    _loadReport();
                  } else {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('Gagal memperbarui verifikasi.'), backgroundColor: AppTheme.danger),
                    );
                  }
                }
              },
              child: const Text('Simpan'),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final user = AuthService.currentUser;
    final canVerify = user != null && ['admin', 'koordinator', 'kepala_balai'].contains(user.role);

    return Scaffold(
      appBar: AppBar(
        title: Text(_report?.reportCode ?? 'Detail Laporan'),
        actions: [
          if (canVerify && _report != null)
            IconButton(
              icon: const Icon(Icons.verified_outlined),
              tooltip: 'Verifikasi Laporan',
              onPressed: _showVerificationDialog,
            ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.primary))
          : _report == null
              ? const Center(child: Text('Laporan tidak ditemukan.'))
              : SingleChildScrollView(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      // Status card
                      Card(
                        child: Padding(
                          padding: const EdgeInsets.all(16),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Text(
                                    _report!.categoryShort,
                                    style: const TextStyle(fontWeight: FontWeight.w800, color: AppTheme.primary, fontSize: 13),
                                  ),
                                  _buildStatusBadge(_report!.status),
                                ],
                              ),
                              const SizedBox(height: 8),
                              Text(
                                _report!.title,
                                style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                'Target: ${_report!.targetName}',
                                style: TextStyle(fontSize: 13, color: Colors.grey.shade600),
                              ),
                              const SizedBox(height: 10),
                              const Divider(),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Text(
                                    'Pelapor: ${_report!.officerName}',
                                    style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
                                  ),
                                  Text(
                                    _report!.dateMonitored,
                                    style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 16),

                      // Location & Coordinates
                      Card(
                        child: Padding(
                          padding: const EdgeInsets.all(16),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text('Lokasi & Koordinat', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14)),
                              const SizedBox(height: 8),
                              Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const Icon(Icons.location_on, color: Colors.red, size: 20),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(_report!.location, style: const TextStyle(fontSize: 13)),
                                        const SizedBox(height: 4),
                                        Text(
                                          'Lat: ${_report!.latitude}, Lng: ${_report!.longitude}',
                                          style: TextStyle(fontSize: 11, color: Colors.grey.shade600),
                                        ),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 16),

                      // Technical Parameters Table
                      Card(
                        child: Padding(
                          padding: const EdgeInsets.all(16),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text('Parameter Teknis Monitoring', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14)),
                              const SizedBox(height: 12),
                              if (_report!.technicalData.isEmpty)
                                const Text('Tidak ada parameter teknis tambahan.', style: TextStyle(fontSize: 12, color: Colors.grey))
                              else
                                ..._report!.technicalData.entries.map((entry) {
                                  return Padding(
                                    padding: const EdgeInsets.symmetric(vertical: 6),
                                    child: Row(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Expanded(
                                          flex: 4,
                                          child: Text(
                                            _formatKey(entry.key),
                                            style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
                                          ),
                                        ),
                                        Expanded(
                                          flex: 5,
                                          child: Text(
                                            entry.value?.toString() ?? '-',
                                            style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
                                          ),
                                        ),
                                      ],
                                    ),
                                  );
                                }),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 16),

                      // Verification Notes
                      if (_report!.verificationNotes.isNotEmpty)
                        Card(
                          color: Colors.amber.shade50,
                          elevation: 0,
                          child: Padding(
                            padding: const EdgeInsets.all(14),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Row(
                                  children: [
                                    Icon(Icons.notes, size: 18, color: Colors.amber),
                                    SizedBox(width: 6),
                                    Text('Catatan Verifikasi / Evaluasi', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: Colors.brown)),
                                  ],
                                ),
                                const SizedBox(height: 6),
                                Text(
                                  _report!.verificationNotes,
                                  style: const TextStyle(fontSize: 12, color: Colors.brown),
                                ),
                              ],
                            ),
                          ),
                        ),

                      const SizedBox(height: 20),
                      if (canVerify)
                        ElevatedButton.icon(
                          onPressed: _showVerificationDialog,
                          icon: const Icon(Icons.check_circle_outline),
                          label: const Text('Proses Verifikasi Laporan Ini'),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppTheme.success,
                            padding: const EdgeInsets.symmetric(vertical: 14),
                          ),
                        ),
                    ],
                  ),
                ),
    );
  }

  String _formatKey(String key) {
    return key
        .replaceAll('_', ' ')
        .split(' ')
        .map((w) => w.isNotEmpty ? w[0].toUpperCase() + w.substring(1) : '')
        .join(' ');
  }

  Widget _buildStatusBadge(String status) {
    Color bg;
    Color fg;
    String label;
    if (status == 'verified') {
      bg = Colors.green.shade50;
      fg = Colors.green.shade700;
      label = 'Terverifikasi';
    } else if (status == 'submitted') {
      bg = Colors.orange.shade50;
      fg = Colors.orange.shade800;
      label = 'Menunggu';
    } else if (status == 'revision') {
      bg = Colors.red.shade50;
      fg = Colors.red.shade700;
      label = 'Revisi';
    } else {
      bg = Colors.grey.shade100;
      fg = Colors.grey.shade700;
      label = status.toUpperCase();
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(6)),
      child: Text(
        label,
        style: TextStyle(color: fg, fontSize: 11, fontWeight: FontWeight.w700),
      ),
    );
  }
}
