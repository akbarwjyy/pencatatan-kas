<?php
// Sertakan header
require_once '../../layout/header.php';

// Pastikan hanya Admin yang bisa mengakses halaman ini
if (!has_permission('Admin')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

$id_customer_error = $nama_customer_error = $no_hp_error = $alamat_error = "";
$id_customer = $nama_customer = $no_hp = $alamat = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitasi input
    $id_customer = sanitize_input($_POST['id_customer']);
    $nama_customer = sanitize_input($_POST['nama_customer']);
    $no_hp = sanitize_input($_POST['no_hp']);
    $alamat = sanitize_input($_POST['alamat']);

    // Validasi input
    if (empty($id_customer)) {
        $id_customer_error = "ID Customer tidak boleh kosong.";
    } elseif (strlen($id_customer) > 8) {
        $id_customer_error = "ID Customer maksimal 8 karakter.";
    }

    if (empty($nama_customer)) {
        $nama_customer_error = "Nama Customer tidak boleh kosong.";
    } elseif (strlen($nama_customer) > 20) {
        $nama_customer_error = "Nama Customer maksimal 20 karakter.";
    }

    if (empty($no_hp)) {
        $no_hp_error = "Nomor HP tidak boleh kosong.";
    } elseif (!preg_match("/^[0-9]+$/", $no_hp)) { // Hanya angka
        $no_hp_error = "Nomor HP hanya boleh berisi angka.";
    } elseif (strlen($no_hp) > 13) {
        $no_hp_error = "Nomor HP maksimal 13 karakter.";
    }

    if (empty($alamat)) {
        $alamat_error = "Alamat tidak boleh kosong.";
    } elseif (strlen($alamat) > 20) {
        $alamat_error = "Alamat maksimal 20 karakter.";
    }

    // Jika tidak ada error validasi, coba simpan ke database
    if (empty($id_customer_error) && empty($nama_customer_error) && empty($no_hp_error) && empty($alamat_error)) {
        // Cek apakah id_customer sudah ada di database
        $check_sql = "SELECT id_customer FROM customer WHERE id_customer = ?";
        $stmt_check = $conn->prepare($check_sql);
        $stmt_check->bind_param("s", $id_customer);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $id_customer_error = "ID Customer sudah ada. Gunakan ID lain.";
            set_flash_message("Gagal menambahkan customer: ID Customer sudah ada.", "error");
        } else {
            $stmt_check->close();

            // Query untuk menambah data customer
            $sql = "INSERT INTO customer (id_customer, nama_customer, no_hp, alamat) VALUES (?, ?, ?, ?)";

            // Gunakan prepared statement untuk keamanan
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ssss", $id_customer, $nama_customer, $no_hp, $alamat);

                if ($stmt->execute()) {
                    set_flash_message("Customer berhasil ditambahkan!", "success");
                    redirect('index.php'); // Redirect ke halaman daftar customer
                } else {
                    set_flash_message("Gagal menambahkan customer: " . $stmt->error, "error");
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

<h1>Tambah Customer Baru</h1>
<p>Isi formulir di bawah ini untuk menambahkan data pelanggan baru.</p>

<form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
    <div class="form-group">
        <label for="id_customer">ID Customer:</label>
        <input type="text" id="id_customer" name="id_customer" value="<?php echo htmlspecialchars($id_customer); ?>" required maxlength="8">
        <span class="error" style="color: red; font-size: 0.9em;"><?php echo $id_customer_error; ?></span>
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
    <button type="submit" class="btn">Simpan</button>
    <a href="index.php" class="btn btn-secondary">Batal</a>
</form>

<?php
// Sertakan footer
require_once '../../layout/footer.php';
?>