<?php
// Sertakan header
require_once '../../layout/header.php';

// Pastikan hanya Admin atau Pemilik yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pemilik')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

$start_date = isset($_GET['start_date']) ? sanitize_input($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? sanitize_input($_GET['end_date']) : '';

// Jika tanggal belum diset, gunakan bulan ini sebagai default
if (empty($start_date) && empty($end_date)) {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');
}

$entries = [];

// Query untuk Kas Masuk
$sql_kas_masuk = "SELECT km.tgl_kas_masuk AS tanggal, 'Kas Masuk' AS tipe, km.jumlah, km.keterangan, 
                         a.nama_akun AS akun_asal, NULL AS akun_tujuan
                  FROM kas_masuk km
                  LEFT JOIN transaksi tr ON km.id_transaksi = tr.id_transaksi
                  LEFT JOIN akun a ON tr.id_akun = a.id_akun -- Ambil akun dari transaksi terkait
                  WHERE km.tgl_kas_masuk BETWEEN ? AND ?";
$stmt_km = $conn->prepare($sql_kas_masuk);
$stmt_km->bind_param("ss", $start_date, $end_date);
$stmt_km->execute();
$result_km = $stmt_km->get_result();
while ($row = $result_km->fetch_assoc()) {
    // Untuk kas masuk manual (id_transaksi IS NULL), keterangan sudah cukup.
    // Untuk kas masuk dari transaksi, kita bisa ambil nama akun dari transaksi.
    // Jika kolom id_akun ditambahkan ke kas_masuk, logika ini perlu diubah.
    $row['akun_transaksi'] = $row['akun_asal'] ?: 'Tidak Terkait Transaksi';
    $entries[] = $row;
}
$stmt_km->close();

// Query untuk Kas Keluar
$sql_kas_keluar = "SELECT kk.tgl_kas_keluar AS tanggal, 'Kas Keluar' AS tipe, kk.jumlah, kk.keterangan,
                           NULL AS akun_asal, a.nama_akun AS akun_tujuan
                   FROM kas_keluar kk
                   JOIN akun a ON kk.id_akun = a.id_akun
                   WHERE kk.tgl_kas_keluar BETWEEN ? AND ?";
$stmt_kk = $conn->prepare($sql_kas_keluar);
$stmt_kk->bind_param("ss", $start_date, $end_date);
$stmt_kk->execute();
$result_kk = $stmt_kk->get_result();
while ($row = $result_kk->fetch_assoc()) {
    $entries[] = $row;
}
$stmt_kk->close();

// Urutkan semua entri berdasarkan tanggal
usort($entries, function ($a, $b) {
    return strtotime($a['tanggal']) - strtotime($b['tanggal']);
});

?>

<h1>Laporan Jurnal Umum Kas</h1>
<p>Menampilkan semua pergerakan kas (masuk dan keluar) secara kronologis.</p>

<form action="" method="get" class="form-filter">
    <div class="form-group">
        <label for="start_date">Dari Tanggal:</label>
        <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
    </div>
    <div class="form-group">
        <label for="end_date">Sampai Tanggal:</label>
        <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
    </div>
    <button type="submit" class="btn">Filter</button>
    <a href="jurnal_umum.php" class="btn btn-secondary">Reset Filter</a>
    <button type="button" class="btn" onclick="window.print()">Cetak Laporan</button>
</form>

<?php if (empty($entries)) : ?>
    <p>Tidak ada entri jurnal ditemukan untuk periode ini.</p>
<?php else : ?>
    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Tipe</th>
                <th>Keterangan</th>
                <th>Debit (Kas Masuk)</th>
                <th>Kredit (Kas Keluar)</th>
                <th>Akun Terkait</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $total_debit = 0;
            $total_kredit = 0;
            foreach ($entries as $entry) :
                $debit = ($entry['tipe'] == 'Kas Masuk') ? $entry['jumlah'] : 0;
                $kredit = ($entry['tipe'] == 'Kas Keluar') ? $entry['jumlah'] : 0;
                $total_debit += $debit;
                $total_kredit += $kredit;
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($entry['tanggal']); ?></td>
                    <td><?php echo htmlspecialchars($entry['tipe']); ?></td>
                    <td><?php echo htmlspecialchars($entry['keterangan']); ?></td>
                    <td><?php echo ($debit > 0) ? format_rupiah($debit) : '-'; ?></td>
                    <td><?php echo ($kredit > 0) ? format_rupiah($kredit) : '-'; ?></td>
                    <td>
                        <?php
                        if ($entry['tipe'] == 'Kas Masuk') {
                            echo htmlspecialchars($entry['akun_transaksi'] ?? 'N/A');
                        } else { // Kas Keluar
                            echo htmlspecialchars($entry['akun_tujuan'] ?? 'N/A');
                        }
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" style="text-align: right;"><strong>Total Jurnal:</strong></td>
                <td><strong><?php echo format_rupiah($total_debit); ?></strong></td>
                <td><strong><?php echo format_rupiah($total_kredit); ?></strong></td>
                <td></td>
            </tr>
        </tfoot>
    </table>
<?php endif; ?>

<?php
// Sertakan footer
require_once '../../layout/footer.php';
?>