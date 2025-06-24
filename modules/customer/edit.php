<?php
// Sertakan header
require_once '../../layout/header.php';

// Pastikan hanya Admin yang bisa mengakses halaman ini
if (!has_permission('Admin')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

$id_customer_error = $nama_customer_error = $no_hp_error = $alamat_error = "";
$id_customer = $nama_customer = $no_hp = $alamat = ""; // Variabel untuk menyimpan data yang diambil dari DB

// Cek apakah ada ID customer yang dikirim melalui URL
if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $id_customer_dari_url = sanitize_input(trim($_GET['id']));

    // Ambil data customer berdasarkan ID
    $sql = "SELECT id_customer, nama_customer, no_hp, alamat FROM customer WHERE id_customer = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $id_customer_dari_url);
        if ($stmt->execute()) {
            $stmt->store_result();
            if ($stmt->num_rows == 1) {
                $stmt->bind_result($id_customer, $nama_customer, $no_hp, $alamat);
                $stmt->fetch();
            } else {
                set_flash_message("Customer tidak ditemukan.", "error");
                redirect('index.php');
            }
        } else {
            set_flash_message("Error saat mengambil data customer: " . $stmt->error, "error");
            redirect('index.php');
        }
        $stmt->close();
    } else {
        set_flash_message("Error prepared statement: " . $conn->error, "error");
        redirect('index.php');
    }
} else {
    set_flash_message("ID Customer tidak ditemukan.", "error");
    redirect('index.php');
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitasi input
    $id_customer_edit = sanitize_input($_POST['id_customer_asal']); // ID yang diedit (hidden input)
    $nama_customer_baru = sanitize_input($_POST['nama_customer']);
    $no_hp_baru = sanitize_input($_POST['no_hp']);
    $alamat_baru = sanitize_input($_POST['alamat']);

    // Validasi input
    if (empty($nama_customer_baru)) {
        $nama_customer_error = "Nama Customer tidak boleh kosong.";
    } elseif (strlen($nama_customer_baru) > 20) {
        $nama_customer_error = "Nama Customer maksimal 20 karakter.";
    }

    if (empty($no_hp_baru)) {
        $no_hp_error = "Nomor HP tidak boleh kosong.";
    } elseif (!preg_match("/^[0-9]+$/", $no_hp_baru)) {
        $no_hp_error = "Nomor HP hanya boleh berisi angka.";
    } elseif (strlen($no_hp_baru) > 13) {
        $no_hp_error = "Nomor HP maksimal 13 karakter.";
    }

    if (empty($alamat_baru)) {
        $alamat_error = "Alamat tidak boleh kosong.";
    } elseif (strlen($alamat_baru) > 20) {
        $alamat_error = "Alamat maksimal 20 karakter.";
    }

    // Jika tidak ada error validasi, coba update ke database
    if (empty($nama_customer_error) && empty($no_hp_error) && empty($alamat_error)) {
        // Query untuk update data customer
        $sql = "UPDATE customer SET nama_customer = ?, no_hp = ?, alamat = ? WHERE id_customer = ?";

        // Gunakan prepared statement untuk keamanan
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ssss", $nama_customer_baru, $no_hp_baru, $alamat_baru, $id_customer_edit);

            if ($stmt->execute()) {
                set_flash_message("Customer berhasil diperbarui!", "success");
                redirect('index.php'); // Redirect ke halaman daftar customer
            } else {
                set_flash_message("Gagal memperbarui customer: " . $stmt->error, "error");
            }
            $stmt->close();
        } else {
            set_flash_message("Error prepared statement: " . $conn->error, "error");
        }
    } else {
        set_flash_message("Silakan perbaiki kesalahan pada formulir.", "error");
        // Reload data from current post if there were errors to keep form values
        $nama_customer = $nama_customer_baru;
        $no_hp = $no_hp_baru;
        $alamat = $alamat_baru;
    }
}
?>

<h1>Edit Customer</h1>
<p>Ubah detail customer di bawah ini.</p>

<form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?id=" . htmlspecialchars($id_customer); ?>" method="post">
    <div class="form-group">
        <label for="id_customer_display">ID Customer:</label>
        <input type="text" id="id_customer_display" value="<?php echo htmlspecialchars($id_customer); ?>" disabled>
        <input type="hidden" name="id_customer_asal" value="<?php echo htmlspecialchars($id_customer); ?>">
    </div>
    <div class="form-group">
        <label for="nama_customer">Nama Customer:</label>
        <input type="text" id="nama_customer" name="nama_customer" value="<?php echo htmlspecialchars($nama_customer); ?>" required maxlength="20">
        <span class="error" style="color: red; font-size: 0.9em;"><?php echo $nama_customer_error; ?></span>
    </div>
    <div class="form-group">
        <label for="no_hp">No. HP:</label>
        <input type="text" id="no_hp" name="no_hp" value="<?php echo htmlspecialchars($no_hp); ?>" required maxlength="13">
        <span class="error" style="color: red; font-size: 0.9em;"><?php echo $no_hp_error; ?></span>
    </div>
    <div class="form-group">
        <label for="alamat">Alamat:</label>
        <input type="text" id="alamat" name="alamat" value="<?php echo htmlspecialchars($alamat); ?>" required maxlength="20">
        <span class="error" style="color: red; font-size: 0.9em;"><?php echo $alamat_error; ?></span>
    </div>
    <button type="submit" class="btn">Simpan Perubahan</button>
    <a href="index.php" class="btn btn-secondary">Batal</a>
</form>

<?php
// Sertakan footer
require_once '../../layout/footer.php';
?>