// lib/screens/verification_list_screen.dart
import 'package:flutter/material.dart';
import '../config/app_theme.dart';
import '../models/report_model.dart';
import '../services/api_service.dart';
import 'report_detail_screen.dart';

class VerificationListScreen extends StatefulWidget {
  const VerificationListScreen({super.key});

  @override
  State<VerificationListScreen> createState() => _VerificationListScreenState();
}

class _VerificationListScreenState extends State<VerificationListScreen> {
  String _selectedStatus = 'submitted';
  bool _isLoading = true;
  List<ReportModel> _reports = [];

  @override
  void initState() {
    super.initState();
    _loadReports();
  }

  Future<void> _loadReports() async {
    setState(() => _isLoading = true);
    final list = await ApiService.fetchReports(status: _selectedStatus);
    setState(() {
      _reports = list;
      _isLoading = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Verifikasi Laporan'),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  ChoiceChip(
                    label: const Text('Menunggu Verifikasi'),
                    selected: _selectedStatus == 'submitted',
                    onSelected: (val) {
                      if (val) {
                        setState(() => _selectedStatus = 'submitted');
                        _loadReports();
                      }
                    },
                  ),
                  const SizedBox(width: 8),
                  ChoiceChip(
                    label: const Text('Perlu Revisi'),
                    selected: _selectedStatus == 'revision',
                    onSelected: (val) {
                      if (val) {
                        setState(() => _selectedStatus = 'revision');
                        _loadReports();
                      }
                    },
                  ),
                  const SizedBox(width: 8),
                  ChoiceChip(
                    label: const Text('Terverifikasi (Disetujui)'),
                    selected: _selectedStatus == 'verified',
                    onSelected: (val) {
                      if (val) {
                        setState(() => _selectedStatus = 'verified');
                        _loadReports();
                      }
                    },
                  ),
                ],
              ),
            ),
          ),
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator(color: AppTheme.primary))
                : RefreshIndicator(
                    onRefresh: _loadReports,
                    color: AppTheme.primary,
                    child: _reports.isEmpty
                        ? const Center(child: Text('Tidak ada laporan dengan status ini.'))
                        : ListView.separated(
                            padding: const EdgeInsets.all(16),
                            itemCount: _reports.length,
                            separatorBuilder: (_, __) => const SizedBox(height: 10),
                            itemBuilder: (ctx, idx) {
                              final r = _reports[idx];
                              return Card(
                                child: ListTile(
                                  onTap: () async {
                                    await Navigator.push(
                                      context,
                                      MaterialPageRoute(
                                        builder: (_) => ReportDetailScreen(reportId: r.id),
                                      ),
                                    );
                                    _loadReports();
                                  },
                                  title: Text(
                                    r.title,
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
                                  ),
                                  subtitle: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        '${r.reportCode} • ${r.officerName}',
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                        style: TextStyle(fontSize: 11, color: Colors.grey.shade600),
                                      ),
                                      Text(
                                        'Lokasi: ${r.location}',
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                        style: TextStyle(fontSize: 11, color: Colors.grey.shade500),
                                      ),
                                    ],
                                  ),
                                  trailing: const Icon(Icons.arrow_forward_ios, size: 14),
                                ),
                              );
                            },
                          ),
                  ),
          ),
        ],
      ),
    );
  }
}
