<?php
// Sertakan header (ini akan melakukan session_start(), cek login, dan include koneksi/fungsi)
require_once '../../layout/header.php';

// Pastikan hanya Admin atau Pemilik yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pemilik')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php'); // Redirect ke dashboard
}

// Ambil semua data pengguna dari database
$sql = "SELECT id_pengguna, nama, jabatan, email FROM pengguna"; // Jangan ambil password
$result = $conn->query($sql);

$users = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
?>

<h1>Manajemen Pengguna</h1>
<p>Kelola daftar pengguna aplikasi, termasuk hak akses (jabatan).</p>

<a href="add.php" class="btn">Tambah Pengguna Baru</a>

<?php if (empty($users)) : ?>
    <p>Belum ada data pengguna yang tersedia.</p>
<?php else : ?>
    <table>
        <thead>
            <tr>
                <th>ID Pengguna</th>
                <th>Nama</th>
                <th>Jabatan</th>
                <th>Email</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user) : ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['id_pengguna']); ?></td>
                    <td><?php echo htmlspecialchars($user['nama']); ?></td>
                    <td><?php echo htmlspecialchars($user['jabatan']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td>
                        <a href="edit.php?id=<?php echo htmlspecialchars($user['id_pengguna']); ?>" class="btn">Edit</a>
                        <?php if ($user['id_pengguna'] !== $_SESSION['user_id']) : // Tidak bisa menghapus diri sendiri 
                        ?>
                            <a href="delete.php?id=<?php echo htmlspecialchars($user['id_pengguna']); ?>" class="btn btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus pengguna ini?');">Hapus</a>
                        <?php else : ?>
                            <span class="btn" style="background-color: #ccc; cursor: not-allowed;">Hapus</span>
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