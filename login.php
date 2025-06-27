<?php
// Memulai sesi PHP
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/helpers.php';

if (is_logged_in()) {
    redirect('modules/dashboard/index.php');
}

$username_error = $password_error = "";
$username_input = ""; // Untuk menyimpan nilai username dari form jika ada error

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitasi input
    $username_input = sanitize_input($_POST['username']);
    $password_input = $_POST['password'];

    // Validasi input
    if (empty($username_input)) {
        $username_error = "Username tidak boleh kosong.";
    }
    if (empty($password_input)) {
        $password_error = "Password tidak boleh kosong.";
    }

    // Jika tidak ada error validasi input
    if (empty($username_error) && empty($password_error)) {
        // Query untuk mencari pengguna berdasarkan kolom 'username'
        $sql = "SELECT id_pengguna, username, password, jabatan FROM pengguna WHERE username = ?";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $username_input);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows == 1) {
                $stmt->bind_result($db_id_pengguna, $db_username, $db_hashed_password, $db_jabatan);
                $stmt->fetch();

                // Verifikasi password
                if (verify_password($password_input, $db_hashed_password)) {
                    // Password cocok, set session
                    $_SESSION['user_id'] = $db_id_pengguna;
                    $_SESSION['user_name'] = $db_username;
                    $_SESSION['user_role'] = $db_jabatan;

                    // Tampilkan pesan selamat datang, lalu arahkan ke dashboard
                    set_flash_message("Selamat datang, " . htmlspecialchars($db_username) . "!", "success");
                    redirect('modules/dashboard/index.php'); // Arahkan langsung ke dashboard modules
                } else {
                    // Password tidak cocok
                    set_flash_message("Username atau password salah.", "error");
                }
            } else {
                // Username tidak ditemukan
                set_flash_message("Username atau password salah.", "error");
            }
            $stmt->close();
        } else {
            set_flash_message("Error prepared statement: " . $conn->error, "error");
        }
    } else {
        set_flash_message("Silakan masukkan username dan password.", "error");
    }
}

require_once __DIR__ . '/layout/header.php'; //

?>

<div class="flex items-center justify-center min-h-screen bg-gray-50">
    <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-md text-center">
        <h2 class="text-3xl font-extrabold text-gray-900 mb-8 uppercase tracking-wide">LOGIN USER</h2>
        <?php echo display_flash_message(); ?>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="space-y-6">
            <div class="flex items-center">
                <label for="username" class="w-24 text-right pr-4 text-gray-700 text-sm font-medium lowercase">Username</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username_input); ?>" required
                    class="flex-1 w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                <span class="error text-red-600 text-sm ml-2"><?php echo $username_error; ?></span>
            </div>
            <div class="flex items-center">
                <label for="password" class="w-24 text-right pr-4 text-gray-700 text-sm font-medium lowercase">Password</label>
                <input type="password" id="password" name="password" required
                    class="flex-1 w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                <span class="error text-red-600 text-sm ml-2"><?php echo $password_error; ?></span>
            </div>
            <button type="submit"
                class="mt-8 w-32 py-2 px-4 bg-green-600 text-white font-semibold rounded-md shadow-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-75 block mx-auto">
                Masuk
            </button>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/layout/footer.php'; //
?>