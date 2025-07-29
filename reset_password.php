<?php
// Memulai sesi PHP
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Pastikan file ini dapat diakses langsung
define('DIRECT_ACCESS', true);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/helpers.php';

// Redirect jika sudah login
if (is_logged_in()) {
    redirect('modules/dashboard/index.php');
}

// Inisialisasi variabel untuk form
$username_input = $email_input = $new_password_input = "";
$username_error = $email_error = $new_password_error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitasi input (tetapi ingat, tanpa DB change, ini tidak aman untuk reset password)
    $username_input = sanitize_input($_POST['username'] ?? '');
    $email_input = sanitize_input($_POST['email'] ?? '');
    $new_password_input = $_POST['new_password'] ?? ''; // Tidak disanitasi HTML karena akan di-hash

    // Validasi dasar (tidak menjamin keamanan tanpa verifikasi token/email)
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

    // Jika tidak ada error validasi form, jelaskan keterbatasan
    if (empty($username_error) && empty($email_error) && empty($new_password_error)) {
        // --- PENTING: Penjelasan Keterbatasan Tanpa Perubahan Database ---
        set_flash_message(
            "Fitur 'Lupa Kata Sandi' yang aman (seperti mengirim tautan ke email) memerlukan penambahan kolom di database untuk menyimpan token reset. " .
                "Tanpa perubahan database, implementasi yang aman dan terverifikasi tidak dapat dilakukan. " .
                "Saat ini, untuk mereset kata sandi, Anda perlu menghubungi Administrator.",
            "warning"
        );
        // Logika di bawah ini (yang sebenarnya akan mereset password) TIDAK akan dieksekusi secara fungsional
        // jika Anda tidak ingin mengubah database atau logic yang sudah ada.
        // Ini hanyalah placeholder untuk menjelaskan.

        // Simulasikan pengecekan pengguna (ini tidak aman untuk reset tanpa token)
        $sql_check_user = "SELECT id_pengguna FROM pengguna WHERE username = ? AND email = ?";
        if ($stmt_check = $conn->prepare($sql_check_user)) {
            $stmt_check->bind_param("ss", $username_input, $email_input);
            $stmt_check->execute();
            $stmt_check->store_result();

            if ($stmt_check->num_rows == 1) {
                // Di sini Anda akan mengupdate password jika ini adalah sistem yang tidak aman
                // ATAU mengirim email dengan token jika Anda bisa mengubah DB.
                // Karena batasan, kita hanya menampilkan pesan peringatan.
            } else {
                set_flash_message("Username atau Email tidak ditemukan.", "error");
            }
            $stmt_check->close();
        } else {
            set_flash_message("Error database: " . $conn->error, "error");
        }
    } else {
        set_flash_message("Silakan perbaiki kesalahan pada formulir.", "error");
    }
}

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