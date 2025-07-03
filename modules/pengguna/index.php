<?php
// Sertakan header (ini akan melakukan session_start(), cek login, dan include koneksi/fungsi)
require_once '../../layout/header.php';

// Pastikan hanya Admin atau Pemilik yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pemilik')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php'); // Redirect ke dashboard
}

// Ambil semua data pengguna dari database
$users = [];
$sql = "SELECT id_pengguna, username, jabatan, email FROM pengguna"; // Jangan ambil password

// Cek koneksi database
if (!$conn) {
    set_flash_message("Koneksi database gagal: " . mysqli_connect_error(), "error");
} else {
    $result = $conn->query($sql);

    // Cek apakah query berhasil
    if ($result === false) {
        set_flash_message("Error query database: " . $conn->error, "error");
    } else if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
}

?>

<div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Manajemen Pengguna</h1>
        <p class="text-gray-600 mb-6">Kelola daftar pengguna aplikasi, termasuk hak akses (jabatan).</p>

        <a href="add.php" class="inline-block bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition mb-6">
            <span class="flex items-center gap-2">
                <span>➕</span> Tambah Pengguna Baru
            </span>
        </a>

        <?php if (empty($users)) : ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6">
                <p class="font-medium">Belum ada data pengguna yang tersedia.</p>
            </div>
        <?php else : ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">ID Pengguna</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">Jabatan</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                            <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($users as $user) : ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm text-gray-500"><?php echo htmlspecialchars($user['id_pengguna']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($user['username']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($user['jabatan']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($user['email']); ?></td>
                                <td class="px-6 py-4 text-sm space-x-2">
                                    <a href="edit.php?id=<?php echo htmlspecialchars($user['id_pengguna']); ?>"
                                        class="inline-block bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600 transition">
                                        Edit
                                    </a>
                                    <?php if ($user['id_pengguna'] !== $_SESSION['user_id']) : ?>
                                        <a href="delete.php?id=<?php echo htmlspecialchars($user['id_pengguna']); ?>"
                                            class="inline-block bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600 transition"
                                            onclick="return confirm('Apakah Anda yakin ingin menghapus pengguna ini?');">
                                            Hapus
                                        </a>
                                    <?php else : ?>
                                        <span class="inline-block bg-gray-300 text-gray-500 px-3 py-1 rounded cursor-not-allowed">
                                            Hapus
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
?>