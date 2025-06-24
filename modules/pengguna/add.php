<?php
// Sertakan header
require_once '../../layout/header.php';

// Pastikan hanya Admin atau Pemilik yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pemilik')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

$id_pengguna_error = $nama_error = $password_error = $jabatan_error = $email_error = "";
$id_pengguna = $nama = $jabatan = $email = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitasi input
    $id_pengguna = sanitize_input($_POST['id_pengguna']);
    $nama = sanitize_input($_POST['nama']);
    $password_input = $_POST['password']; // Password tidak disanitasi HTML karena akan di-hash
    $jabatan = sanitize_input($_POST['jabatan']);
    $email = sanitize_input($_POST['email']);

    // Validasi input
    if (empty($id_pengguna)) {
        $id_pengguna_error = "ID Pengguna tidak boleh kosong.";
    } elseif (strlen($id_pengguna) > 11) {
        $id_pengguna_error = "ID Pengguna maksimal 11 karakter.";
    }

    if (empty($nama)) {
        $nama_error = "Nama tidak boleh kosong.";
    } elseif (strlen($nama) > 30) {
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
    if (empty($id_pengguna_error) && empty($nama_error) && empty($password_error) && empty($jabatan_error) && empty($email_error)) {
        // Cek apakah id_pengguna sudah ada di database
        $check_sql = "SELECT id_pengguna FROM pengguna WHERE id_pengguna = ?";
        $stmt_check = $conn->prepare($check_sql);
        $stmt_check->bind_param("s", $id_pengguna);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $id_pengguna_error = "ID Pengguna sudah ada. Gunakan ID lain.";
            set_flash_message("Gagal menambahkan pengguna: ID Pengguna sudah ada.", "error");
        } else {
            $stmt_check->close();

            // Hash password sebelum menyimpan
            $hashed_password = hash_password($password_input);

            // Query untuk menambah data pengguna
            $sql = "INSERT INTO pengguna (id_pengguna, nama, password, jabatan, email) VALUES (?, ?, ?, ?, ?)";

            // Gunakan prepared statement untuk keamanan
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("sssss", $id_pengguna, $nama, $hashed_password, $jabatan, $email);

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

<h1>Tambah Pengguna Baru</h1>
<p>Isi formulir di bawah ini untuk menambahkan pengguna baru.</p>

<form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
    <div class="form-group">
        <label for="id_pengguna">ID Pengguna:</label>
        <input type="text" id="id_pengguna" name="id_pengguna" value="<?php echo htmlspecialchars($id_pengguna); ?>" required maxlength="11">
        <span class="error" style="color: red; font-size: 0.9em;"><?php echo $id_pengguna_error; ?></span>
    </div>
    <div class="form-group">
        <label for="nama">Nama:</label>
        <input type="text" id="nama" name="nama" value="<?php echo htmlspecialchars($nama); ?>" required maxlength="30">
        <span class="error" style="color: red; font-size: 0.9em;"><?php echo $nama_error; ?></span>
    </div>
    <div class="form-group">
        <label for="password">Password (minimal 8 karakter, maksimal 8 karakter sesuai DB):</label>
        <input type="password" id="password" name="password" required maxlength="8">
        <span class="error" style="color: red; font-size: 0.9em;"><?php echo $password_error; ?></span>
    </div>
    <div class="form-group">
        <label for="jabatan">Jabatan:</label>
        <select id="jabatan" name="jabatan" required>
            <option value="">-- Pilih Jabatan --</option>
            <option value="Admin" <?php echo ($jabatan == 'Admin') ? 'selected' : ''; ?>>Admin</option>
            <option value="Pemilik" <?php echo ($jabatan == 'Pemilik') ? 'selected' : ''; ?>>Pemilik</option>
            <option value="Pegawai" <?php echo ($jabatan == 'Pegawai') ? 'selected' : ''; ?>>Pegawai</option>
        </select>
        <span class="error" style="color: red; font-size: 0.9em;"><?php echo $jabatan_error; ?></span>
    </div>
    <div class="form-group">
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required maxlength="30">
        <span class="error" style="color: red; font-size: 0.9em;"><?php echo $email_error; ?></span>
    </div>
    <button type="submit" class="btn">Simpan</button>
    <a href="index.php" class="btn btn-secondary">Batal</a>
</form>

<?php
// Sertakan footer
require_once '../../layout/footer.php';
?>