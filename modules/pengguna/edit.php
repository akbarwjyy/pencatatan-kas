<?php
// Sertakan header
require_once '../../layout/header.php';

// Pastikan hanya Admin atau Pemilik yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pemilik')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

$id_pengguna_error = $nama_error = $password_error = $jabatan_error = $email_error = "";
$id_pengguna = $nama = $jabatan = $email = ""; // Variabel untuk menyimpan data yang diambil dari DB

// Cek apakah ada ID pengguna yang dikirim melalui URL
if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $id_pengguna_dari_url = sanitize_input(trim($_GET['id']));

    // Ambil data pengguna berdasarkan ID
    $sql = "SELECT id_pengguna, username, jabatan, email FROM pengguna WHERE id_pengguna = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $id_pengguna_dari_url);
        if ($stmt->execute()) {
            $stmt->store_result();
            if ($stmt->num_rows == 1) {
                $stmt->bind_result($id_pengguna, $nama, $jabatan, $email);
                $stmt->fetch();
            } else {
                set_flash_message("Pengguna tidak ditemukan.", "error");
                redirect('index.php');
            }
        } else {
            set_flash_message("Error saat mengambil data pengguna: " . $stmt->error, "error");
            redirect('index.php');
        }
        $stmt->close();
    } else {
        set_flash_message("Error prepared statement: " . $conn->error, "error");
        redirect('index.php');
    }
} else {
    set_flash_message("ID Pengguna tidak ditemukan.", "error");
    redirect('index.php');
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitasi input
    $id_pengguna_edit = sanitize_input($_POST['id_pengguna_asal']); // ID yang diedit (hidden input)
    $nama_baru = sanitize_input($_POST['nama']);
    $password_baru = $_POST['password']; // Password tidak disanitasi HTML karena akan di-hash
    $jabatan_baru = sanitize_input($_POST['jabatan']);
    $email_baru = sanitize_input($_POST['email']);

    // Validasi input
    if (empty($nama_baru)) {
        $nama_error = "Nama tidak boleh kosong.";
    } elseif (strlen($nama_baru) > 30) {
        $nama_error = "Nama maksimal 30 karakter.";
    }

    if (!empty($password_baru)) { // Password hanya divalidasi jika diisi
        if (!is_valid_password($password_baru)) {
            $password_error = "Password minimal 8 karakter.";
        } elseif (strlen($password_baru) > 8) { // Sesuai batasan VARCHAR(8) di DB
            $password_error = "Password maksimal 8 karakter.";
        }
    }

    if (empty($jabatan_baru)) {
        $jabatan_error = "Jabatan tidak boleh kosong.";
    } elseif (!in_array($jabatan_baru, ['Admin', 'Pemilik', 'Pegawai'])) {
        $jabatan_error = "Jabatan tidak valid.";
    } elseif ($user_role === 'Pegawai' && ($jabatan_baru === 'Admin' || $jabatan_baru === 'Pemilik')) {
        // Logika untuk mencegah pegawai mengubah jabatan menjadi admin/pemilik
        set_flash_message("Pegawai tidak diizinkan mengubah jabatan menjadi Admin atau Pemilik.", "error");
        redirect('index.php');
    }


    if (empty($email_baru)) {
        $email_error = "Email tidak boleh kosong.";
    } elseif (!is_valid_email($email_baru)) {
        $email_error = "Format email tidak valid.";
    } elseif (strlen($email_baru) > 30) {
        $email_error = "Email maksimal 30 karakter.";
    }

    // Jika tidak ada error validasi, coba update ke database
    if (empty($nama_error) && empty($password_error) && empty($jabatan_error) && empty($email_error)) {
        $update_password = false;
        $hashed_password = null;

        $sql = "UPDATE pengguna SET username = ?, jabatan = ?, email = ?";
        if (!empty($password_baru)) {
            $hashed_password = hash_password($password_baru);
            $sql .= ", password = ?";
            $update_password = true;
        }
        $sql .= " WHERE id_pengguna = ?";


        // Gunakan prepared statement untuk keamanan
        if ($stmt = $conn->prepare($sql)) {
            if ($update_password) {
                $stmt->bind_param("sssss", $nama_baru, $jabatan_baru, $email_baru, $hashed_password, $id_pengguna_edit);
            } else {
                $stmt->bind_param("ssss", $nama_baru, $jabatan_baru, $email_baru, $id_pengguna_edit);
            }

            if ($stmt->execute()) {
                // Jika pengguna yang sedang login yang diedit, update session
                if ($id_pengguna_edit === $_SESSION['user_id']) {
                    $_SESSION['user_name'] = $nama_baru;
                    $_SESSION['user_role'] = $jabatan_baru;
                }
                set_flash_message("Pengguna berhasil diperbarui!", "success");
                redirect('index.php');
            } else {
                set_flash_message("Gagal memperbarui pengguna: " . $stmt->error, "error");
            }
            $stmt->close();
        } else {
            set_flash_message("Error prepared statement: " . $conn->error, "error");
        }
    } else {
        set_flash_message("Silakan perbaiki kesalahan pada formulir.", "error");
        // Reload data from current post if there were errors to keep form values
        $nama = $nama_baru;
        $jabatan = $jabatan_baru;
        $email = $email_baru;
    }
}
?>

<div class="bg-white p-8 rounded-lg shadow-xl max-w-md mx-auto my-8">
    <h1 class="text-2xl font-bold text-gray-800 mb-4 text-center">Edit Pengguna</h1>
    <p class="text-gray-600 mb-6 text-center">Ubah detail pengguna di bawah ini.</p>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?id=" . htmlspecialchars($id_pengguna); ?>" method="post">
        <!-- ID Pengguna ditampilkan sebagai non-editable, tapi tetap dikirimkan sebagai hidden input -->
        <div class="mb-4">
            <label for="id_pengguna_display" class="block text-gray-700 text-sm font-bold mb-2">ID Pengguna:</label>
            <input type="text" id="id_pengguna_display" value="<?php echo htmlspecialchars($id_pengguna); ?>" disabled
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100">
            <input type="hidden" name="id_pengguna_asal" value="<?php echo htmlspecialchars($id_pengguna); ?>">
        </div>
        <div class="mb-4">
            <label for="nama" class="block text-gray-700 text-sm font-bold mb-2">Nama:</label>
            <input type="text" id="nama" name="nama" value="<?php echo htmlspecialchars($nama); ?>" required maxlength="30"
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $nama_error; ?></span>
        </div>
        <div class="mb-4">
            <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password (kosongkan jika tidak ingin diubah, maks 8 karakter):</label>
            <input type="password" id="password" name="password" maxlength="8"
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $password_error; ?></span>
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
        <div class="flex items-center justify-between">
            <button type="submit"
                class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Simpan Perubahan
            </button>
            <a href="index.php"
                class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Batal
            </a>
        </div>
    </form>
</div>

<?php
// Sertakan footer
require_once '../../layout/footer.php';
?>