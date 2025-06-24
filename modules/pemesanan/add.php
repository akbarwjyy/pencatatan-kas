<?php
// Sertakan header
require_once '../../layout/header.php';

// Pastikan hanya Admin atau Pegawai yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pegawai')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

$id_pesan_error = $id_customer_error = $id_akun_error = $tgl_pesan_error = $tgl_kirim_error = $quantity_error = $uang_muka_error = $sub_total_error = $sisa_error = "";
$id_pesan = $id_customer = $id_akun = $tgl_pesan = $tgl_kirim = $quantity = $uang_muka = $sub_total = $sisa = $harga_satuan_input = "";

// Ambil daftar customer untuk dropdown
$customers = [];
$customer_sql = "SELECT id_customer, nama_customer FROM customer ORDER BY nama_customer ASC";
$customer_result = $conn->query($customer_sql);
if ($customer_result->num_rows > 0) {
    while ($row = $customer_result->fetch_assoc()) {
        $customers[] = $row;
    }
}

// Ambil daftar akun untuk dropdown
$accounts = [];
$account_sql = "SELECT id_akun, nama_akun FROM akun ORDER BY nama_akun ASC";
$account_result = $conn->query($account_sql);
if ($account_result->num_rows > 0) {
    while ($row = $account_result->fetch_assoc()) {
        $accounts[] = $row;
    }
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitasi input
    $id_pesan = sanitize_input($_POST['id_pesan']);
    $id_customer = sanitize_input($_POST['id_customer']);
    $id_akun = sanitize_input($_POST['id_akun']);
    $tgl_pesan = sanitize_input($_POST['tgl_pesan']);
    $tgl_kirim = sanitize_input($_POST['tgl_kirim']);
    $quantity = sanitize_input($_POST['quantity']);
    $harga_satuan_input = sanitize_input($_POST['harga_satuan']); // Input sementara
    $uang_muka = sanitize_input($_POST['uang_muka']);

    // Validasi dan Konversi Tipe Data
    if (empty($id_pesan)) {
        $id_pesan_error = "ID Pesan tidak boleh kosong.";
    } elseif (strlen($id_pesan) > 8) {
        $id_pesan_error = "ID Pesan maksimal 8 karakter.";
    }

    if (empty($id_customer)) {
        $id_customer_error = "Customer tidak boleh kosong.";
    }
    if (empty($id_akun)) {
        $id_akun_error = "Akun tidak boleh kosong.";
    }

    if (empty($tgl_pesan)) {
        $tgl_pesan_error = "Tanggal Pesan tidak boleh kosong.";
    }
    if (empty($tgl_kirim)) {
        $tgl_kirim_error = "Tanggal Kirim tidak boleh kosong.";
    }

    if (empty($quantity) || !is_numeric($quantity) || $quantity <= 0) {
        $quantity_error = "Quantity harus angka positif.";
    } else {
        $quantity = (int)$quantity;
    }

    if (empty($harga_satuan_input) || !is_numeric($harga_satuan_input) || $harga_satuan_input <= 0) {
        $harga_satuan_error = "Harga Satuan harus angka positif.";
    } else {
        $harga_satuan_input = (int)$harga_satuan_input;
    }

    if (empty($uang_muka) || !is_numeric($uang_muka) || $uang_muka < 0) {
        $uang_muka_error = "Uang Muka harus angka non-negatif.";
    } else {
        $uang_muka = (int)$uang_muka;
    }

    // Hitung sub_total dan sisa
    if (empty($quantity_error) && empty($harga_satuan_error)) {
        $sub_total = $quantity * $harga_satuan_input;
        if ($uang_muka > $sub_total) {
            $uang_muka_error = "Uang Muka tidak boleh melebihi Sub Total.";
        } else {
            $sisa = $sub_total - $uang_muka;
        }
    } else {
        $sub_total = 0; // Set to 0 if quantity or harga_satuan are invalid
        $sisa = 0;
    }


    // Jika tidak ada error validasi, coba simpan ke database
    if (
        empty($id_pesan_error) && empty($id_customer_error) && empty($id_akun_error) && empty($tgl_pesan_error) &&
        empty($tgl_kirim_error) && empty($quantity_error) && empty($uang_muka_error) && empty($harga_satuan_error) && empty($sub_total_error)
    ) {

        // Cek apakah id_pesan sudah ada di database
        $check_sql = "SELECT id_pesan FROM pemesanan WHERE id_pesan = ?";
        $stmt_check = $conn->prepare($check_sql);
        $stmt_check->bind_param("s", $id_pesan);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $id_pesan_error = "ID Pesan sudah ada. Gunakan ID lain.";
            set_flash_message("Gagal menambahkan pemesanan: ID Pesan sudah ada.", "error");
        } else {
            $stmt_check->close();

            // Query untuk menambah data pemesanan
            $sql = "INSERT INTO pemesanan (id_pesan, id_customer, id_akun, tgl_pesan, tgl_kirim, quantity, uang_muka, sub_total, sisa) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("sssssiiii", $id_pesan, $id_customer, $id_akun, $tgl_pesan, $tgl_kirim, $quantity, $uang_muka, $sub_total, $sisa);

                if ($stmt->execute()) {
                    set_flash_message("Pemesanan berhasil ditambahkan!", "success");
                    redirect('index.php'); // Redirect ke halaman daftar pemesanan
                } else {
                    set_flash_message("Gagal menambahkan pemesanan: " . $stmt->error, "error");
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

<h1>Tambah Pemesanan Baru</h1>
<p>Isi formulir di bawah ini untuk menambahkan pemesanan baru.</p>

<form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
    <div class="form-group">
        <label for="id_pesan">ID Pesan:</label>
        <input type="text" id="id_pesan" name="id_pesan" value="<?php echo htmlspecialchars($id_pesan); ?>" required maxlength="8">
        <span class="error" style="color: red; font-size: 0.9em;"><?php echo $id_pesan_error; ?></span>
    </div>
    <div class="form-group">
        <label for="id_customer">Customer:</label>
        <select id="id_customer" name="id_customer" required>
            <option value="">-- Pilih Customer --</option>
            <?php foreach ($customers as $customer_option) : ?>
                <option value="<?php echo htmlspecialchars($customer_option['id_customer']); ?>"
                    <?php echo ($id_customer == $customer_option['id_customer']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($customer_option['nama_customer']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <span class="error" style="color: red; font-size: 0.9em;"><?php echo $id_customer_error; ?></span>
    </div>
    <div class="form-group">
        <label for="id_akun">Akun Tujuan Pembayaran:</label>
        <select id="id_akun" name="id_akun" required>
            <option value="">-- Pilih Akun --</option>
            <?php foreach ($accounts as $account_option) : ?>
                <option value="<?php echo htmlspecialchars($account_option['id_akun']); ?>"
                    <?php echo ($id_akun == $account_option['id_akun']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($account_option['nama_akun']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <span class="error" style="color: red; font-size: 0.9em;"><?php echo $id_akun_error; ?></span>
    </div>
    <div class="form-group">
        <label for="tgl_pesan">Tanggal Pesan:</label>
        <input type="date" id="tgl_pesan" name="tgl_pesan" value="<?php echo htmlspecialchars($tgl_pesan); ?>" required>
        <span class="error" style="color: red; font-size: 0.9em;"><?php echo $tgl_pesan_error; ?></span>
    </div>
    <div class="form-group">
        <label for="tgl_kirim">Tanggal Kirim:</label>
        <input type="date" id="tgl_kirim" name="tgl_kirim" value="<?php echo htmlspecialchars($tgl_kirim); ?>" required>
        <span class="error" style="color: red; font-size: 0.9em;"><?php echo $tgl_kirim_error; ?></span>
    </div>
    <div class="form-group">
        <label for="quantity">Quantity (Jumlah Ampyang):</label>
        <input type="number" id="quantity" name="quantity" value="<?php echo htmlspecialchars($quantity); ?>" required min="1">
        <span class="error" style="color: red; font-size: 0.9em;"><?php echo $quantity_error; ?></span>
    </div>
    <div class="form-group">
        <label for="harga_satuan">Harga Satuan (Rp):</label>
        <input type="number" id="harga_satuan" name="harga_satuan" value="<?php echo htmlspecialchars($harga_satuan_input); ?>" required min="1">
        <span class="error" style="color: red; font-size: 0.9em;"><?php echo $harga_satuan_error; ?></span>
    </div>
    <div class="form-group">
        <label for="uang_muka">Uang Muka (Rp):</label>
        <input type="number" id="uang_muka" name="uang_muka" value="<?php echo htmlspecialchars($uang_muka); ?>" required min="0">
        <span class="error" style="color: red; font-size: 0.9em;"><?php echo $uang_muka_error; ?></span>
    </div>
    <!-- Sub Total dan Sisa akan dihitung dan tidak langsung diinput -->
    <button type="submit" class="btn">Simpan</button>
    <a href="index.php" class="btn btn-secondary">Batal</a>
</form>

<?php
// Sertakan footer
require_once '../../layout/footer.php';
?>