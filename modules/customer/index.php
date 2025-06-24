<?php
// Sertakan header (ini akan melakukan session_start(), cek login, dan include koneksi/fungsi)
require_once '../../layout/header.php';

// Pastikan hanya Admin yang bisa mengakses halaman ini
if (!has_permission('Admin')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php'); // Redirect ke dashboard atau halaman lain
}

// Ambil semua data customer dari database
$sql = "SELECT * FROM customer";
$result = $conn->query($sql);

$customers = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
}
?>

<h1>Manajemen Customer</h1>
<p>Kelola daftar data pelanggan Anda.</p>

<a href="add.php" class="btn">Tambah Customer Baru</a>

<?php if (empty($customers)) : ?>
    <p>Belum ada data customer yang tersedia.</p>
<?php else : ?>
    <table>
        <thead>
            <tr>
                <th>ID Customer</th>
                <th>Nama Customer</th>
                <th>No. HP</th>
                <th>Alamat</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($customers as $customer) : ?>
                <tr>
                    <td><?php echo htmlspecialchars($customer['id_customer']); ?></td>
                    <td><?php echo htmlspecialchars($customer['nama_customer']); ?></td>
                    <td><?php echo htmlspecialchars($customer['no_hp']); ?></td>
                    <td><?php echo htmlspecialchars($customer['alamat']); ?></td>
                    <td>
                        <a href="edit.php?id=<?php echo htmlspecialchars($customer['id_customer']); ?>" class="btn">Edit</a>
                        <a href="delete.php?id=<?php echo htmlspecialchars($customer['id_customer']); ?>" class="btn btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus customer ini? Semua pemesanan terkait akan terpengaruh!');">Hapus</a>
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