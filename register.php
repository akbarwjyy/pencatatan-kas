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

$username_error = $password_error = $email_error = $jabatan_error = "";
$username = $email = $jabatan = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitasi input
    $username = sanitize_input($_POST['username']);
    $password_input = $_POST['password']; // Password tidak disanitasi HTML karena akan di-hash
    $email = sanitize_input($_POST['email']);
    $jabatan = sanitize_input($_POST['jabatan']);

    // Validasi input
    if (empty($username)) {
        $username_error = "Username tidak boleh kosong.";
    } elseif (strlen($username) > 30) {
        $username_error = "Username maksimal 30 karakter.";
    }

    if (empty($password_input)) {
        $password_error = "Password tidak boleh kosong.";
    } elseif (!is_valid_password($password_input)) {
        $password_error = "Password minimal 8 karakter.";
    } elseif (strlen($password_input) > 8) {
        $password_error = "Password maksimal 8 karakter.";
    }

    if (empty($email)) {
        $email_error = "Email tidak boleh kosong.";
    } elseif (!is_valid_email($email)) {
        $email_error = "Format email tidak valid.";
    } elseif (strlen($email) > 30) {
        $email_error = "Email maksimal 30 karakter.";
    }

    if (empty($jabatan)) {
        $jabatan_error = "Jabatan tidak boleh kosong.";
    } elseif (!in_array($jabatan, ['Pegawai', 'Admin', 'Pemilik'])) {
        $jabatan_error = "Jabatan tidak valid.";
    }

    // Jika tidak ada error validasi, coba simpan ke database
    if (empty($username_error) && empty($password_error) && empty($email_error) && empty($jabatan_error)) {
        // Cek apakah username sudah ada di database
        $check_sql = "SELECT username FROM pengguna WHERE username = ?";
        $stmt_check = $conn->prepare($check_sql);
        $stmt_check->bind_param("s", $username);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $username_error = "Username sudah digunakan. Silakan pilih username lain.";
            set_flash_message("Gagal mendaftar: Username sudah digunakan.", "error");
        } else {
            $stmt_check->close();

            // Generate ID pengguna alfanumerik otomatis
            function generateAlphanumericID()
            {
                // Generate 4 random characters (2 letters + 2 numbers)
                $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $numbers = '0123456789';

                $randomLetters = '';
                for ($i = 0; $i < 2; $i++) {
                    $randomLetters .= $letters[rand(0, strlen($letters) - 1)];
                }

                $randomNumbers = '';
                for ($i = 0; $i < 2; $i++) {
                    $randomNumbers .= $numbers[rand(0, strlen($numbers) - 1)];
                }

                return $randomLetters . $randomNumbers;
            }

            // Generate ID dan pastikan unik
            do {
                $id_pengguna = generateAlphanumericID();
                $check_id = "SELECT id_pengguna FROM pengguna WHERE id_pengguna = ?";
                $stmt_id = $conn->prepare($check_id);
                $stmt_id->bind_param("s", $id_pengguna);
                $stmt_id->execute();
                $stmt_id->store_result();
                $id_exists = $stmt_id->num_rows > 0;
                $stmt_id->close();
            } while ($id_exists);

            // Hash password sebelum menyimpan
            $hashed_password = hash_password($password_input);

            // Query untuk menambah data pengguna
            $sql = "INSERT INTO pengguna (id_pengguna, username, password, jabatan, email) VALUES (?, ?, ?, ?, ?)";

            // Gunakan prepared statement untuk keamanan
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("sssss", $id_pengguna, $username, $hashed_password, $jabatan, $email);

                if ($stmt->execute()) {
                    set_flash_message("Pendaftaran berhasil! Silakan login.", "success");
                    redirect('login.php');
                } else {
                    set_flash_message("Gagal mendaftar: " . $stmt->error, "error");
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

require_once __DIR__ . '/layout/header.php'; //
?>

<div class="flex items-center justify-center min-h-screen bg-gray-50">
    <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-md">
        <h2 class="text-3xl font-extrabold text-gray-900 mb-6 text-center">REGISTER</h2>
        <?php echo display_flash_message(); ?>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="space-y-6">
            <div class="mb-4">
                <label for="username" class="block text-gray-700 text-sm font-medium mb-2">Username</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required maxlength="30"
                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                <span class="text-red-500 text-xs italic mt-1 block"><?php echo $username_error; ?></span>
            </div>

            <div class="mb-4">
                <label for="email" class="block text-gray-700 text-sm font-medium mb-2">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required maxlength="30"
                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                <span class="text-red-500 text-xs italic mt-1 block"><?php echo $email_error; ?></span>
            </div>

            <div class="mb-4">
                <label for="password" class="block text-gray-700 text-sm font-medium mb-2">Password</label>
                <input type="password" id="password" name="password" required maxlength="8"
                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                <span class="text-red-500 text-xs italic mt-1 block"><?php echo $password_error; ?></span>
            </div>

            <div class="mb-4">
                <label for="jabatan" class="block text-gray-700 text-sm font-medium mb-2">Jabatan</label>
                <select id="jabatan" name="jabatan" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                    <option value="">-- Pilih Jabatan --</option>
                    <option value="Pegawai" <?php echo ($jabatan == 'Pegawai') ? 'selected' : ''; ?>>Pegawai</option>
                    <option value="Admin" <?php echo ($jabatan == 'Admin') ? 'selected' : ''; ?>>Admin</option>
                    <option value="Pemilik" <?php echo ($jabatan == 'Pemilik') ? 'selected' : ''; ?>>Pemilik</option>
                </select>
                <span class="text-red-500 text-xs italic mt-1 block"><?php echo $jabatan_error; ?></span>
            </div>

            <div class="text-center">
                <button type="submit"
                    class="mt-8 w-32 py-2 px-4 bg-green-600 text-white font-semibold rounded-md shadow-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-75">
                    Daftar
                </button>
            </div>
        </form>
        <div class="mt-4 text-center">
            <a href="login.php" class="text-sm text-green-600 hover:text-green-800">Sudah punya akun? Login</a>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/layout/footer.php'; //
?>