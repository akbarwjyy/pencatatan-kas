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

// Cek apakah ada ID akun yang dikirim melalui URL
if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $id_akun = sanitize_input(trim($_GET['id']));

    // Ambil data akun berdasarkan ID
    $sql = "SELECT id_akun, nama_akun FROM akun WHERE id_akun = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $id_akun);
        if ($stmt->execute()) {
            $stmt->store_result();
            if ($stmt->num_rows == 1) {
                $stmt->bind_result($id_akun, $nama_akun);
                $stmt->fetch();
            } else {
                set_flash_message("Akun tidak ditemukan.", "error");
                redirect('index.php');
            }
        } else {
            set_flash_message("Error saat mengambil data akun: " . $stmt->error, "error");
            redirect('index.php');
        }
        $stmt->close();
    } else {
        set_flash_message("Error prepared statement: " . $conn->error, "error");
        redirect('index.php');
    }
} else {
    set_flash_message("ID Akun tidak ditemukan.", "error");
    redirect('index.php');
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitasi input
    $nama_akun_baru = sanitize_input($_POST['nama_akun']);
    $id_akun_asal = sanitize_input($_POST['id_akun_asal']); // ID akun yang tidak berubah

    // Validasi input
    if (empty($nama_akun_baru)) {
        $nama_akun_error = "Nama Akun tidak boleh kosong.";
    } elseif (strlen($nama_akun_baru) > 20) {
        $nama_akun_error = "Nama Akun maksimal 20 karakter.";
    }

    // Jika tidak ada error validasi, coba update ke database
    if (empty($nama_akun_error)) {
        // Query untuk update data akun
        $sql = "UPDATE akun SET nama_akun = ? WHERE id_akun = ?";

        // Gunakan prepared statement untuk keamanan
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ss", $nama_akun_baru, $id_akun_asal); // 'ss' karena kedua parameter adalah string

            if ($stmt->execute()) {
                set_flash_message("Akun berhasil diperbarui!", "success");
                redirect('index.php'); // Redirect ke halaman daftar akun
            } else {
                set_flash_message("Gagal memperbarui akun: " . $stmt->error, "error");
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

<h1>Edit Akun</h1>
<p>Ubah detail akun di bawah ini.</p>

<form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?id=" . htmlspecialchars($id_akun); ?>" method="post">
    <div class="form-group">
        <label for="id_akun_display">ID Akun:</label>
        <input type="text" id="id_akun_display" value="<?php echo htmlspecialchars($id_akun); ?>" disabled>
        <input type="hidden" name="id_akun_asal" value="<?php echo htmlspecialchars($id_akun); ?>">
    </div>
    <div class="form-group">
        <label for="nama_akun">Nama Akun:</label>
        <input type="text" id="nama_akun" name="nama_akun" value="<?php echo htmlspecialchars($nama_akun); ?>" required maxlength="20">
        <span class="error" style="color: red; font-size: 0.9em;"><?php echo $nama_akun_error; ?></span>
    </div>
    <button type="submit" class="btn">Simpan Perubahan</button>
    <a href="index.php" class="btn btn-secondary">Batal</a>
</form>

<?php
?>