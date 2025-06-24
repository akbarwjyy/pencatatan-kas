<?php
// Sertakan header (ini akan melakukan session_start(), cek login, dan include koneksi/fungsi)
require_once '../../layout/header.php';

// Pastikan hanya Admin atau Pegawai yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pegawai')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php'); // Redirect ke dashboard
}

// Ambil semua data kas masuk dari database, join dengan transaksi dan akun
// Perhatikan: kas_masuk mungkin tidak selalu punya id_transaksi (jika diinput manual)
$sql = "SELECT km.*, tr.id_pesan, tr.jumlah_dibayar AS jumlah_transaksi, a.nama_akun
        FROM kas_masuk km
        LEFT JOIN transaksi tr ON km.id_transaksi = tr.id_transaksi
        LEFT JOIN akun a ON tr.id_akun = a.id_akun  -- Jika akun di transaksi yang diacu
        ORDER BY km.tgl_kas_masuk DESC"; // Urutkan berdasarkan tanggal terbaru
$result = $conn->query($sql);

$cash_incomes = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $cash_incomes[] = $row;
    }
}
?>

<h1>Manajemen Kas Masuk</h1>
<p>Kelola daftar pemasukan kas di luar transaksi pemesanan (misalnya modal, dll).</p>

<a href="add.php" class="btn">Tambah Kas Masuk Lainnya</a>

<?php if (empty($cash_incomes)) : ?>
    <p>Belum ada data kas masuk yang tersedia.</p>
<?php else : ?>
    <table>
        <thead>
            <tr>
                <th>ID Kas Masuk</th>
                <th>ID Transaksi (jika ada)</th>
                <th>Tgl Kas Masuk</th>
                <th>Jumlah</th>
                <th>Keterangan</th>
                <th>Akun Penerima (dari Transaksi)</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cash_incomes as $income) : ?>
                <tr>
                    <td><?php echo htmlspecialchars($income['id_kas_masuk']); ?></td>
                    <td><?php echo htmlspecialchars($income['id_transaksi'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($income['tgl_kas_masuk']); ?></td>
                    <td><?php echo format_rupiah($income['jumlah']); ?></td>
                    <td><?php echo htmlspecialchars($income['keterangan']); ?></td>
                    <td><?php echo htmlspecialchars($income['nama_akun'] ?? 'N/A'); ?></td>
                    <td>
                        <?php
                        // Aksi Edit/Delete hanya untuk kas masuk yang tidak terkait transaksi
                        // Kas masuk dari transaksi diedit/dihapus via modul Transaksi
                        if (empty($income['id_transaksi'])) :
                        ?>
                            <a href="edit.php?id=<?php echo htmlspecialchars($income['id_kas_masuk']); ?>" class="btn">Edit</a>
                            <a href="delete.php?id=<?php echo htmlspecialchars($income['id_kas_masuk']); ?>" class="btn btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus entri kas masuk ini?');">Hapus</a>
                        <?php else : ?>
                            <span class="btn" style="background-color: #ccc; cursor: not-allowed;">Dikelola oleh Transaksi</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php
// Sertakan footer
require_once '../../layout/footer.php';
?>