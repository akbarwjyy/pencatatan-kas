<?php
// Sertakan header (ini akan melakukan session_start(), cek login, dan include koneksi/fungsi)
require_once '../../layout/header.php';

// Pastikan hanya Admin yang bisa mengakses halaman ini
if (!has_permission('Admin')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php'); // Redirect ke dashboard atau halaman lain
}

// Ambil semua data akun dari database
$sql = "SELECT * FROM akun";
$result = $conn->query($sql);

$accounts = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $accounts[] = $row;
    }
}
?>

<h1>Manajemen Akun</h1>
<p>Kelola daftar akun kas yang digunakan dalam sistem.</p>

<a href="add.php" class="btn">Tambah Akun Baru</a>

<?php if (empty($accounts)) : ?>
    <p>Belum ada data akun yang tersedia.</p>
<?php else : ?>
    <table>
        <thead>
            <tr>
                <th>ID Akun</th>
                <th>Nama Akun</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($accounts as $account) : ?>
                <tr>
                    <td><?php echo htmlspecialchars($account['id_akun']); ?></td>
                    <td><?php echo htmlspecialchars($account['nama_akun']); ?></td>
                    <td>
                        <a href="edit.php?id=<?php echo htmlspecialchars($account['id_akun']); ?>" class="btn">Edit</a>
                        <a href="delete.php?id=<?php echo htmlspecialchars($account['id_akun']); ?>" class="btn btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus akun ini?');">Hapus</a>
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