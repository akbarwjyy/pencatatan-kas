<?php
// Sertakan header
require_once '../../layout/header.php';

// Pastikan hanya Admin yang bisa mengakses halaman ini
if (!has_permission('Admin')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

// Generate ID customer otomatis
$latest_customer_sql = "SELECT MAX(CAST(SUBSTRING(id_customer, 4) AS UNSIGNED)) as last_num FROM customer WHERE id_customer LIKE 'CUS%'";
$latest_customer_result = $conn->query($latest_customer_sql);
$last_customer_num = 0;
if ($latest_customer_result && $row = $latest_customer_result->fetch_assoc()) {
    $last_customer_num = intval($row['last_num']);
}
$new_customer_num = $last_customer_num + 1;
$id_customer = sprintf("CUS%05d", $new_customer_num); // Format: CUS00001, CUS00002, dst.

$id_customer_error = $nama_customer_error = $no_hp_error = $alamat_error = "";
$nama_customer = $no_hp = $alamat = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitasi input (ID customer sudah di-generate otomatis)
    $nama_customer = sanitize_input($_POST['nama_customer']);
    $no_hp = sanitize_input($_POST['no_hp']);
    $alamat = sanitize_input($_POST['alamat']);

    // Validasi input (ID customer tidak perlu validasi karena auto-generate)
    if (empty($nama_customer)) {
        $nama_customer_error = "Nama Customer tidak boleh kosong.";
    } elseif (strlen($nama_customer) > 20) {
        $nama_customer_error = "Nama Customer maksimal 20 karakter.";
    }

    if (empty($no_hp)) {
        $no_hp_error = "Nomor HP tidak boleh kosong.";
    } elseif (!preg_match("/^[0-9]+$/", $no_hp)) { // Hanya angka
        $no_hp_error = "Nomor HP hanya boleh berisi angka.";
    } elseif (strlen($no_hp) > 13) {
        $no_hp_error = "Nomor HP maksimal 13 karakter.";
    }

    if (empty($alamat)) {
        $alamat_error = "Alamat tidak boleh kosong.";
    } elseif (strlen($alamat) > 20) {
        $alamat_error = "Alamat maksimal 20 karakter.";
    }

    // Jika tidak ada error validasi, coba simpan ke database
    if (empty($nama_customer_error) && empty($no_hp_error) && empty($alamat_error)) {

        // Query untuk menambah data customer
        $sql = "INSERT INTO customer (id_customer, nama_customer, no_hp, alamat) VALUES (?, ?, ?, ?)";

        // Gunakan prepared statement untuk keamanan
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ssss", $id_customer, $nama_customer, $no_hp, $alamat);

            if ($stmt->execute()) {
                set_flash_message("Customer berhasil ditambahkan!", "success");
                redirect('index.php'); // Redirect ke halaman daftar customer
            } else {
                set_flash_message("Gagal menambahkan customer: " . $stmt->error, "error");
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
        <h1 class="text-2xl font-bold text-gray-800 mb-4 text-center">Tambah Customer</h1>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">

            <div class="mb-4">
                <label for="nama_customer" class="block text-gray-700 text-sm font-bold mb-2">Nama Customer:</label>
                <input type="text" id="nama_customer" name="nama_customer" value="<?php echo htmlspecialchars($nama_customer); ?>" required maxlength="20"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
                <span class="text-red-500 text-xs italic mt-1 block"><?php echo $nama_customer_error; ?></span>
            </div>
            <div class="mb-4">
                <label for="no_hp" class="block text-gray-700 text-sm font-bold mb-2">No. HP:</label>
                <input type="text" id="no_hp" name="no_hp" value="<?php echo htmlspecialchars($no_hp); ?>" required maxlength="13"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
                <span class="text-red-500 text-xs italic mt-1 block"><?php echo $no_hp_error; ?></span>
            </div>
            <div class="mb-6">
                <label for="alamat" class="block text-gray-700 text-sm font-bold mb-2">Alamat:</label>
                <input type="text" id="alamat" name="alamat" value="<?php echo htmlspecialchars($alamat); ?>" required maxlength="20"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
                <span class="text-red-500 text-xs italic mt-1 block"><?php echo $alamat_error; ?></span>
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