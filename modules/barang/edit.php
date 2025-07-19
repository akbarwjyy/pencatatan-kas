<?php
// Sertakan header
require_once '../../layout/header.php';

// Pastikan hanya Admin yang bisa mengakses halaman ini
if (!has_permission('Admin')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

// Inisialisasi variabel
$id_barang = $nama_barang = "";
$harga_satuan = 0;
$nama_barang_error = $harga_satuan_error = "";

// Cek apakah ada parameter ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    set_flash_message("ID Barang tidak valid.", "error");
    redirect('index.php');
}

$id_barang = sanitize_input($_GET['id']);

// Ambil data barang berdasarkan ID
$sql = "SELECT * FROM barang WHERE id_barang = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("s", $id_barang);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $nama_barang = $row['nama_barang'];
        $harga_satuan = $row['harga_satuan'];
    } else {
        set_flash_message("Barang tidak ditemukan.", "error");
        redirect('index.php');
    }
    $stmt->close();
} else {
    set_flash_message("Error: " . $conn->error, "error");
    redirect('index.php');
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitasi input
    $nama_barang = sanitize_input($_POST['nama_barang']);
    $harga_satuan = sanitize_input($_POST['harga_satuan']);

    // Validasi input
    if (empty($nama_barang)) {
        $nama_barang_error = "Nama barang tidak boleh kosong.";
    } elseif (strlen($nama_barang) > 50) {
        $nama_barang_error = "Nama barang maksimal 50 karakter.";
    }

    if (!is_numeric($harga_satuan) || $harga_satuan <= 0) {
        $harga_satuan_error = "Harga satuan harus angka positif.";
    } else {
        $harga_satuan = (float)$harga_satuan;
    }

    // Jika tidak ada error validasi, coba update ke database
    if (empty($nama_barang_error) && empty($harga_satuan_error)) {
        // Query untuk update data barang
        $sql = "UPDATE barang SET nama_barang = ?, harga_satuan = ? WHERE id_barang = ?";

        // Gunakan prepared statement untuk keamanan
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("sds", $nama_barang, $harga_satuan, $id_barang);

            if ($stmt->execute()) {
                set_flash_message("Barang berhasil diperbarui!", "success");
                redirect('index.php'); // Redirect ke halaman daftar barang
            } else {
                set_flash_message("Gagal memperbarui barang: " . $stmt->error, "error");
            }
            $stmt->close();
        } else {
            set_flash_message("Error prepared statement: " . $conn->error, "error");
        }
    } else {
        set_flash_message("Silakan perbaiki kesalahan pada formulir.", "error");
    }
}
?>

<div class="min-h-screen flex items-center justify-center py-8">
    <div class="bg-white p-8 rounded-lg shadow-xl max-w-md w-full mx-4">
        <h1 class="text-2xl font-bold text-gray-800 mb-4 text-center">Edit Barang</h1>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . urlencode($id_barang)); ?>" method="post">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">ID Barang:</label>
                <input type="text" value="<?php echo htmlspecialchars($id_barang); ?>" disabled
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100 cursor-not-allowed">
            </div>
            <div class="mb-4">
                <label for="nama_barang" class="block text-gray-700 text-sm font-bold mb-2">Nama Barang:</label>
                <input type="text" id="nama_barang" name="nama_barang" value="<?php echo htmlspecialchars($nama_barang); ?>" required maxlength="50"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
                <span class="text-red-500 text-xs italic mt-1 block"><?php echo $nama_barang_error; ?></span>
            </div>
            <div class="mb-6">
                <label for="harga_satuan" class="block text-gray-700 text-sm font-bold mb-2">Harga Satuan:</label>
                <input type="number" id="harga_satuan" name="harga_satuan" value="<?php echo htmlspecialchars($harga_satuan); ?>" required min="1"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
                <span class="text-red-500 text-xs italic mt-1 block"><?php echo $harga_satuan_error; ?></span>
            </div>
            <div class="flex items-center justify-between">
                <button type="submit"
                    class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Simpan
                </button>
                <a href="index.php"
                    class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Batal
                </a>
            </div>
        </form>
    </div>
</div>

<?php
?>