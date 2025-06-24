<?php
// Memulai sesi PHP
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Sertakan file koneksi database dan fungsi-fungsi umum
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/helpers.php';

// Jika pengguna sudah login, arahkan ke dashboard
if (is_logged_in()) {
    redirect('modules/dashboard/index.php');
}

$username_error = $password_error = "";
$username_input = ""; // Untuk menyimpan nilai username dari form jika ada error

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitasi input
    $username_input = sanitize_input($_POST['username']);
    $password_input = $_POST['password']; // Password tidak perlu disanitasi HTML karena akan di-hash/verifikasi

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
            $stmt->bind_param("s", $username_input); // Bind input username dari form
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows == 1) {
                $stmt->bind_result($db_id_pengguna, $db_username, $db_hashed_password, $db_jabatan);
                $stmt->fetch();

                // Verifikasi password
                if (verify_password($password_input, $db_hashed_password)) {
                    // Password cocok, set session
                    $_SESSION['user_id'] = $db_id_pengguna;
                    $_SESSION['user_name'] = $db_username; // MENGGUNAKAN USERNAME UNTUK DISPLAY
                    $_SESSION['user_role'] = $db_jabatan; // Simpan jabatan di session

                    // PERBAIKAN: Hapus baris flash message "Selamat datang" di sini
                    // set_flash_message("Selamat datang, " . htmlspecialchars($db_username) . "!", "success"); 

                    redirect('modules/dashboard/index.php'); // Arahkan ke dashboard
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
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login User</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f0f2f5;
        }

        .login-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        .login-container h2 {
            margin-bottom: 0px;
            color: #333;
            font-size: 1.8em;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .login-container .form-group {
            margin-top: 25px;
            margin-bottom: 20px;
            text-align: left;
            display: flex;
            align-items: center;
        }

        .login-container .form-group label {
            flex: 0 0 100px;
            font-weight: normal;
            text-align: right;
            margin-right: 15px;
            text-transform: lowercase;
        }

        .login-container .form-group input[type="text"],
        .login-container .form-group input[type="password"] {
            flex: 1;
            width: auto;
            padding: 8px;
        }

        .login-container .btn {
            width: 100px;
            padding: 8px 12px;
            font-size: 1em;
            margin-top: 30px;
            margin-left: auto;
            margin-right: auto;
            display: block;
        }

        .login-container .message {
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <h2>LOGIN USER</h2>
        <?php echo display_flash_message(); ?>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label for="username">username</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username_input); ?>" required>
                <span class="error" style="color: red; font-size: 0.9em;"><?php echo $username_error; ?></span>
            </div>
            <div class="form-group">
                <label for="password">password</label>
                <input type="password" id="password" name="password" required>
                <span class="error" style="color: red; font-size: 0.9em;"><?php echo $password_error; ?></span>
            </div>
            <button type="submit" class="btn">Masuk</button>
        </form>
    </div>
</body>

</html>