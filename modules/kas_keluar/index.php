<?php
// Sertakan header (ini akan melakukan session_start(), cek login, dan include koneksi/fungsi)
require_once '../../layout/header.php';

// Pastikan hanya Admin yang bisa mengakses halaman ini
if (!has_permission('Admin')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php'); // Redirect ke dashboard
}

// Ambil semua data kas keluar dari database, join dengan akun untuk menampilkan nama akun
$sql = "SELECT kk.*, a.nama_akun
        FROM kas_keluar kk
        JOIN akun a ON kk.id_akun = a.id_akun
        ORDER BY kk.tgl_kas_keluar DESC"; // Urutkan berdasarkan tanggal terbaru
$result = $conn->query($sql);

$cash_expenses = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $cash_expenses[] = $row;
    }
}
?>

<h1>Manajemen Kas Keluar</h1>
<p>Kelola daftar pengeluaran kas operasional dan lainnya.</p>

<a href="add.php" class="btn">Tambah Kas Keluar Baru</a>

<?php if (empty($cash_expenses)) : ?>
    <p>Belum ada data kas keluar yang tersedia.</p>
<?php else : ?>
    <table>
        <thead>
            <tr>
                <th>ID Kas Keluar</th>
                <th>Tgl Kas Keluar</th>
                <th>Akun</th>
                <th>Jumlah</th>
                <th>Keterangan</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cash_expenses as $expense) : ?>
                <tr>
                    <td><?php echo htmlspecialchars($expense['id_kas_keluar']); ?></td>
                    <td><?php echo htmlspecialchars($expense['tgl_kas_keluar']); ?></td>
                    <td><?php echo htmlspecialchars($expense['nama_akun']); ?></td>
                    <td><?php echo format_rupiah($expense['jumlah']); ?></td>
                    <td><?php echo htmlspecialchars($expense['keterangan']); ?></td>
                    <td>
                        <a href="edit.php?id=<?php echo htmlspecialchars($expense['id_kas_keluar']); ?>" class="btn">Edit</a>
                        <a href="delete.php?id=<?php echo htmlspecialchars($expense['id_kas_keluar']); ?>" class="btn btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus entri kas keluar ini?');">Hapus</a>
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