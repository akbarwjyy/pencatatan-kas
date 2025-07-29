<?php
// Memulai sesi PHP
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Pastikan file ini dapat diakses langsung
define('DIRECT_ACCESS', true);

// Sertakan file konfigurasi dan fungsi-fungsi penting
require_once __DIR__ . '/config/db.php'; // Pastikan koneksi database tersedia
require_once __DIR__ . '/includes/functions.php'; // Sertakan fungsi-fungsi umum seperti sanitize_input, is_valid_password, dll.
require_once __DIR__ . '/includes/helpers.php'; // Sertakan helper seperti set_flash_message, display_flash_message, dll.

// Redirect jika sudah login
if (is_logged_in()) {
    redirect('modules/dashboard/index.php');
}

// Inisialisasi variabel untuk form
$username_input = $email_input = $new_password_input = "";
$username_error = $email_error = $new_password_error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitasi input
    $username_input = sanitize_input($_POST['username'] ?? '');
    $email_input = sanitize_input($_POST['email'] ?? '');
    $new_password_input = $_POST['new_password'] ?? ''; // Tidak disanitasi HTML karena akan di-hash

    // Validasi dasar
    if (empty($username_input)) {
        $username_error = "Username tidak boleh kosong.";
    }
    if (empty($email_input)) {
        $email_error = "Email tidak boleh kosong.";
    } elseif (!is_valid_email($email_input)) {
        $email_error = "Format email tidak valid.";
    }
    if (empty($new_password_input)) {
        $new_password_error = "Password baru tidak boleh kosong.";
    } elseif (!is_valid_password($new_password_input)) {
        $new_password_error = "Password minimal 8 karakter.";
    } elseif (strlen($new_password_input) > 8) { // Sesuai batasan VARCHAR(8) di DB
        $new_password_error = "Password maksimal 8 karakter.";
    }

    // Jika tidak ada error validasi form
    if (empty($username_error) && empty($email_error) && empty($new_password_error)) {
        // Cari pengguna berdasarkan username dan email
        $sql_find_user = "SELECT id_pengguna FROM pengguna WHERE username = ? AND email = ?";
        if ($stmt_find = $conn->prepare($sql_find_user)) {
            $stmt_find->bind_param("ss", $username_input, $email_input);
            $stmt_find->execute();
            $stmt_find->store_result();

            if ($stmt_find->num_rows == 1) {
                $stmt_find->bind_result($user_id_to_reset);
                $stmt_find->fetch();
                $stmt_find->close();

                // Hash password baru
                $hashed_new_password = hash_password($new_password_input);

                // Update password pengguna
                $sql_update_password = "UPDATE pengguna SET password = ? WHERE id_pengguna = ?";
                if ($stmt_update = $conn->prepare($sql_update_password)) {
                    $stmt_update->bind_param("ss", $hashed_new_password, $user_id_to_reset);
                    if ($stmt_update->execute()) {
                        set_flash_message("Kata sandi berhasil diganti! Silakan login.", "success");
                        redirect('login.php'); // Arahkan ke halaman login
                    } else {
                        set_flash_message("Gagal mengganti kata sandi: " . $stmt_update->error, "error");
                    }
                    $stmt_update->close();
                } else {
                    set_flash_message("Error menyiapkan update kata sandi: " . $conn->error, "error");
                }
            } else {
                set_flash_message("Username atau Email tidak ditemukan.", "error");
                $stmt_find->close();
            }
        } else {
            set_flash_message("Error menyiapkan pencarian pengguna: " . $conn->error, "error");
        }
    } else {
        set_flash_message("Silakan perbaiki kesalahan pada formulir.", "error");
    }
}

// Sertakan header dan footer untuk tampilan halaman
require_once __DIR__ . '/layout/header.php';
?>

<div class="flex items-center justify-center min-h-screen bg-gray-50">
    <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-md">
        <h2 class="text-3xl font-extrabold text-gray-900 mb-6 text-center">RESET PASSWORD</h2>
        <?php echo display_flash_message(); ?>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="space-y-6">
            <div class="mb-4">
                <label for="username" class="block text-gray-700 text-sm font-medium mb-2">Username</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username_input); ?>" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                <span class="text-red-500 text-xs italic mt-1 block"><?php echo $username_error; ?></span>
            </div>

            <div class="mb-4">
                <label for="email" class="block text-gray-700 text-sm font-medium mb-2">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email_input); ?>" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                <span class="text-red-500 text-xs italic mt-1 block"><?php echo $email_error; ?></span>
            </div>

            <div class="mb-4">
                <label for="new_password" class="block text-gray-700 text-sm font-medium mb-2">Password Baru</label>
                <input type="password" id="new_password" name="new_password" required maxlength="8"
                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                <span class="text-red-500 text-xs italic mt-1 block"><?php echo $new_password_error; ?></span>
            </div>

            <div class="text-center">
                <button type="submit"
                    class="mt-8 w-32 py-2 px-4 bg-green-600 text-white font-semibold rounded-md shadow-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-75">
                    Simpan
                </button>
            </div>
        </form>
        <div class="mt-4 text-center">
            <a href="login.php" class="text-sm text-green-600 hover:text-green-800">Kembali ke login</a>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/layout/footer.php';
?>