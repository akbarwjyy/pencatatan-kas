<?php
// Sertakan header
require_once '../../layout/header.php';

// Pastikan hanya Admin yang bisa mengakses halaman ini
if (!has_permission('Admin')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

$id_akun_error = $nama_akun_error = "";
$id_akun = $nama_akun = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitasi input
    $id_akun = sanitize_input($_POST['id_akun']);
    $nama_akun = sanitize_input($_POST['nama_akun']);

    // Validasi input
    if (empty($id_akun)) {
        $id_akun_error = "ID Akun tidak boleh kosong.";
    } elseif (strlen($id_akun) > 8) {
        $id_akun_error = "ID Akun maksimal 8 karakter.";
    }

    if (empty($nama_akun)) {
        $nama_akun_error = "Nama Akun tidak boleh kosong.";
    } elseif (strlen($nama_akun) > 20) {
        $nama_akun_error = "Nama Akun maksimal 20 karakter.";
    }

    // Jika tidak ada error validasi, coba simpan ke database
    if (empty($id_akun_error) && empty($nama_akun_error)) {
        // Cek apakah id_akun sudah ada di database
        $check_sql = "SELECT id_akun FROM akun WHERE id_akun = ?";
        $stmt_check = $conn->prepare($check_sql);
        $stmt_check->bind_param("s", $id_akun);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $id_akun_error = "ID Akun sudah ada. Gunakan ID lain.";
            set_flash_message("Gagal menambahkan akun: ID Akun sudah ada.", "error");
        } else {
            $stmt_check->close();

            // Query untuk menambah data akun
            $sql = "INSERT INTO akun (id_akun, nama_akun) VALUES (?, ?)";

            // Gunakan prepared statement untuk keamanan
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ss", $id_akun, $nama_akun); // 'ss' karena kedua parameter adalah string

                if ($stmt->execute()) {
                    set_flash_message("Akun berhasil ditambahkan!", "success");
                    redirect('index.php'); // Redirect ke halaman daftar akun
                } else {
                    set_flash_message("Gagal menambahkan akun: " . $stmt->error, "error");
                }
                $stmt->close();
            } else {
                set_flash_message("Error prepared statement: " . $conn->error, "error");
            }
        }
    } else {
        set_flash_message("Silakan perbaiki kesalahan pada formulir.", "error");
    }
}
?>

<div class="bg-white p-8 rounded-lg shadow-xl max-w-md mx-auto my-8">
    <h1 class="text-2xl font-bold text-gray-800 mb-4 text-center">Tambah Akun</h1>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="mb-4">
            <label for="id_akun" class="block text-gray-700 text-sm font-bold mb-2">ID Akun:</label>
            <input type="text" id="id_akun" name="id_akun" value="<?php echo htmlspecialchars($id_akun); ?>" required maxlength="8"
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $id_akun_error; ?></span>
        </div>
        <div class="mb-6">
            <label for="nama_akun" class="block text-gray-700 text-sm font-bold mb-2">Nama Akun:</label>
            <input type="text" id="nama_akun" name="nama_akun" value="<?php echo htmlspecialchars($nama_akun); ?>" required maxlength="20"
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $nama_akun_error; ?></span>
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

<?php
?>