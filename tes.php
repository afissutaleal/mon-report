<?php
// tes.php - Diagnostik Server Hosting
echo "<div style='font-family:sans-serif;padding:30px;max-width:600px;margin:40px auto;border:1px solid #0070E0;border-radius:12px;background:#f8fafc;'>";
echo "<h2 style='color:#0070E0;margin-top:0;'>Tes Koneksi Server Monreport</h2>";
echo "<p>✅ <strong>PHP Berjalan Normal!</strong> Versi: " . phpversion() . "</p>";
echo "<p><strong>Driver Database yang Tersedia:</strong> " . (implode(', ', PDO::getAvailableDrivers()) ?: 'Tidak ada') . "</p>";
echo "<p><strong>Lokasi Folder Saat Ini:</strong> " . __DIR__ . "</p>";
echo "<hr style='margin:20px 0;border:0;border-top:1px solid #e2e8f0;'>";
echo "<a href='login.php' style='display:inline-block;padding:10px 18px;background:#0070E0;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;'>Buka Halaman Login</a>";
echo "</div>";
