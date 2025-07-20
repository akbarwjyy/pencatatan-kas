<?php
// Sertakan header
require_once '../../layout/header.php';

// Pastikan hanya Admin atau Pemilik yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pemilik')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

$id_pengguna_error = $nama_error = $password_error = $jabatan_error = $email_error = "";
$username = $jabatan = $email = "";

// Generate ID pengguna otomatis
$query = "SELECT MAX(CAST(id_pengguna AS UNSIGNED)) as max_id FROM pengguna";
$result = $conn->query($query);
$row = $result->fetch_assoc();
$id_pengguna = ($row['max_id'] ? $row['max_id'] + 1 : 1);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // ID pengguna sudah di-generate otomatis
    $username = sanitize_input($_POST['username']);
    $password_input = $_POST['password']; // Password tidak disanitasi HTML karena akan di-hash
    $jabatan = sanitize_input($_POST['jabatan']);
    $email = sanitize_input($_POST['email']);

    // ID pengguna sudah di-generate otomatis dan divalidasi

    if (empty($username)) {
        $nama_error = "Nama tidak boleh kosong.";
    } elseif (strlen($username) > 30) {
        $nama_error = "Nama maksimal 30 karakter.";
    }

    if (empty($password_input)) {
        $password_error = "Password tidak boleh kosong.";
    } elseif (!is_valid_password($password_input)) {
        $password_error = "Password minimal 8 karakter.";
    } elseif (strlen($password_input) > 8) { // Sesuai batasan VARCHAR(8) di DB
        $password_error = "Password maksimal 8 karakter.";
    }


    if (empty($jabatan)) {
        $jabatan_error = "Jabatan tidak boleh kosong.";
    } elseif (!in_array($jabatan, ['Admin', 'Pemilik', 'Pegawai'])) {
        $jabatan_error = "Jabatan tidak valid.";
    } elseif ($user_role === 'Pegawai' && $jabatan === 'Admin' && $jabatan === 'Pemilik') {
        // Logika untuk mencegah pegawai membuat admin/pemilik
        set_flash_message("Pegawai tidak diizinkan membuat akun dengan jabatan Admin atau Pemilik.", "error");
        redirect('index.php');
    }

    if (empty($email)) {
        $email_error = "Email tidak boleh kosong.";
    } elseif (!is_valid_email($email)) {
        $email_error = "Format email tidak valid.";
    } elseif (strlen($email) > 30) {
        $email_error = "Email maksimal 30 karakter.";
    }

    // Jika tidak ada error validasi, coba simpan ke database
    if (empty($nama_error) && empty($password_error) && empty($jabatan_error) && empty($email_error)) { {

            // Hash password sebelum menyimpan
            $hashed_password = hash_password($password_input);

            // Query untuk menambah data pengguna
            $sql = "INSERT INTO pengguna (id_pengguna, username, password, jabatan, email) VALUES (?, ?, ?, ?, ?)";

            // Gunakan prepared statement untuk keamanan
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("sssss", $id_pengguna, $username, $hashed_password, $jabatan, $email);

                if ($stmt->execute()) {
                    set_flash_message("Pengguna berhasil ditambahkan!", "success");
                    redirect('index.php');
                } else {
                    set_flash_message("Gagal menambahkan pengguna: " . $stmt->error, "error");
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
    <h1 class="text-2xl font-bold text-gray-800 mb-4 text-center">Tambah Pengguna</h1>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <!-- <div class="mb-4">
            <label for="id_pengguna" class="block text-gray-700 text-sm font-bold mb-2">ID Pengguna:</label>
            <input type="text" id="id_pengguna" name="id_pengguna" value="<?php echo htmlspecialchars($id_pengguna); ?>" readonly
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 bg-gray-100 leading-tight">
            <p class="text-gray-600 text-xs italic mt-1">ID Pengguna akan digenerate otomatis</p>
        </div> -->
        <div class="mb-4">
            <label for="username" class="block text-gray-700 text-sm font-bold mb-2">Nama:</label>
            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required maxlength="30"
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $nama_error; ?></span>
        </div>
        <div class="mb-4">
            <label for="jabatan" class="block text-gray-700 text-sm font-bold mb-2">Jabatan:</label>
            <select id="jabatan" name="jabatan" required
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
                <option value="">-- Pilih Jabatan --</option>
                <option value="Admin" <?php echo ($jabatan == 'Admin') ? 'selected' : ''; ?>>Admin</option>
                <option value="Pemilik" <?php echo ($jabatan == 'Pemilik') ? 'selected' : ''; ?>>Pemilik</option>
                <option value="Pegawai" <?php echo ($jabatan == 'Pegawai') ? 'selected' : ''; ?>>Pegawai</option>
            </select>
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $jabatan_error; ?></span>
        </div>
        <div class="mb-6">
            <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email:</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required maxlength="30"
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $email_error; ?></span>
        </div>
        <div class="mb-4">
            <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password (minimal 8 karakter, maksimal 8 karakter sesuai DB):</label>
            <input type="password" id="password" name="password" required maxlength="8"
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $password_error; ?></span>
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