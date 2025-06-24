<?php
// Sertakan header
require_once '../../layout/header.php';

// Pastikan hanya Admin atau Pegawai yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pegawai')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

$id_pesan_error = $id_customer_error = $id_akun_error = $tgl_pesan_error = $tgl_kirim_error = $quantity_error = $uang_muka_error = $sub_total_error = $sisa_error = "";
$id_pesan = $id_customer = $id_akun = $tgl_pesan = $tgl_kirim = $quantity = $uang_muka = $sub_total = $sisa = "";
$harga_satuan_input = 0; // Ini akan dihitung balik dari sub_total dan quantity

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

// Cek apakah ada ID pemesanan yang dikirim melalui URL
if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $id_pesan_dari_url = sanitize_input(trim($_GET['id']));

    // Ambil data pemesanan berdasarkan ID
    $sql = "SELECT id_pesan, id_customer, id_akun, tgl_pesan, tgl_kirim, quantity, uang_muka, sub_total, sisa FROM pemesanan WHERE id_pesan = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $id_pesan_dari_url);
        if ($stmt->execute()) {
            $stmt->store_result();
            if ($stmt->num_rows == 1) {
                $stmt->bind_result($id_pesan, $id_customer, $id_akun, $tgl_pesan, $tgl_kirim, $quantity, $uang_muka, $sub_total, $sisa);
                $stmt->fetch();

                // Hitung balik harga_satuan untuk ditampilkan di form edit
                if ($quantity > 0) {
                    $harga_satuan_input = $sub_total / $quantity;
                }
            } else {
                set_flash_message("Pemesanan tidak ditemukan.", "error");
                redirect('index.php');
            }
        } else {
            set_flash_message("Error saat mengambil data pemesanan: " . $stmt->error, "error");
            redirect('index.php');
        }
        $stmt->close();
    } else {
        set_flash_message("Error prepared statement: " . $conn->error, "error");
        redirect('index.php');
    }
} else {
    set_flash_message("ID Pemesanan tidak ditemukan.", "error");
    redirect('index.php');
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitasi input
    $id_pesan_edit = sanitize_input($_POST['id_pesan_asal']); // ID yang diedit (hidden input)
    $id_customer_baru = sanitize_input($_POST['id_customer']);
    $id_akun_baru = sanitize_input($_POST['id_akun']);
    $tgl_pesan_baru = sanitize_input($_POST['tgl_pesan']);
    $tgl_kirim_baru = sanitize_input($_POST['tgl_kirim']);
    $quantity_baru = sanitize_input($_POST['quantity']);
    $harga_satuan_input_baru = sanitize_input($_POST['harga_satuan']); // Input sementara
    $uang_muka_baru = sanitize_input($_POST['uang_muka']);

    // Validasi input (mirip dengan add.php)
    if (empty($id_customer_baru)) {
        $id_customer_error = "Customer tidak boleh kosong.";
    }
    if (empty($id_akun_baru)) {
        $id_akun_error = "Akun tidak boleh kosong.";
    }

    if (empty($tgl_pesan_baru)) {
        $tgl_pesan_error = "Tanggal Pesan tidak boleh kosong.";
    }
    if (empty($tgl_kirim_baru)) {
        $tgl_kirim_error = "Tanggal Kirim tidak boleh kosong.";
    }

    if (empty($quantity_baru) || !is_numeric($quantity_baru) || $quantity_baru <= 0) {
        $quantity_error = "Quantity harus angka positif.";
    } else {
        $quantity_baru = (int)$quantity_baru;
    }

    if (empty($harga_satuan_input_baru) || !is_numeric($harga_satuan_input_baru) || $harga_satuan_input_baru <= 0) {
        $harga_satuan_error = "Harga Satuan harus angka positif.";
    } else {
        $harga_satuan_input_baru = (int)$harga_satuan_input_baru;
    }

    if (empty($uang_muka_baru) || !is_numeric($uang_muka_baru) || $uang_muka_baru < 0) {
        $uang_muka_error = "Uang Muka harus angka non-negatif.";
    } else {
        $uang_muka_baru = (int)$uang_muka_baru;
    }

    // Hitung ulang sub_total dan sisa
    if (empty($quantity_error) && empty($harga_satuan_error)) {
        $sub_total_baru = $quantity_baru * $harga_satuan_input_baru;
        if ($uang_muka_baru > $sub_total_baru) {
            $uang_muka_error = "Uang Muka tidak boleh melebihi Sub Total.";
        } else {
            $sisa_baru = $sub_total_baru - $uang_muka_baru;
        }
    } else {
        $sub_total_baru = 0;
        $sisa_baru = 0;
    }


    // Jika tidak ada error validasi, coba update ke database
    if (
        empty($id_customer_error) && empty($id_akun_error) && empty($tgl_pesan_error) &&
        empty($tgl_kirim_error) && empty($quantity_error) && empty($uang_muka_error) && empty($harga_satuan_error) && empty($sub_total_error)
    ) {

        // Query untuk update data pemesanan
        $sql = "UPDATE pemesanan SET id_customer = ?, id_akun = ?, tgl_pesan = ?, tgl_kirim = ?, quantity = ?, uang_muka = ?, sub_total = ?, sisa = ? WHERE id_pesan = ?";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("sssiisiii", $id_customer_baru, $id_akun_baru, $tgl_pesan_baru, $tgl_kirim_baru, $quantity_baru, $uang_muka_baru, $sub_total_baru, $sisa_baru, $id_pesan_edit);

            if ($stmt->execute()) {
                set_flash_message("Pemesanan berhasil diperbarui!", "success");
                redirect('index.php'); // Redirect ke halaman daftar pemesanan
            } else {
                set_flash_message("Gagal memperbarui pemesanan: " . $stmt->error, "error");
            }
            $stmt->close();
        } else {
            set_flash_message("Error prepared statement: " . $conn->error, "error");
        }
    } else {
        set_flash_message("Silakan perbaiki kesalahan pada formulir.", "error");
        // Reload data from current post if there were errors to keep form values
        $id_customer = $id_customer_baru;
        $id_akun = $id_akun_baru;
        $tgl_pesan = $tgl_pesan_baru;
        $tgl_kirim = $tgl_kirim_baru;
        $quantity = $quantity_baru;
        $uang_muka = $uang_muka_baru;
        $harga_satuan_input = $harga_satuan_input_baru;
        $sub_total = $sub_total_baru;
        $sisa = $sisa_baru;
    }
}
?>

<h1>Edit Pemesanan</h1>
<p>Ubah detail pemesanan di bawah ini.</p>

<form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?id=" . htmlspecialchars($id_pesan); ?>" method="post">
    <!-- ID Pemesanan ditampilkan sebagai non-editable, tapi tetap dikirimkan sebagai hidden input -->
    <div class="form-group">
        <label for="id_pesan_display">ID Pesan:</label>
        <input type="text" id="id_pesan_display" value="<?php echo htmlspecialchars($id_pesan); ?>" disabled>
        <input type="hidden" name="id_pesan_asal" value="<?php echo htmlspecialchars($id_pesan); ?>">
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
    <div class="form-group">
        <label>Sub Total (Otomatis):</label>
        <input type="text" value="<?php echo format_rupiah($sub_total); ?>" disabled>
    </div>
    <div class="form-group">
        <label>Sisa Pembayaran (Otomatis):</label>
        <input type="text" value="<?php echo format_rupiah($sisa); ?>" disabled>
    </div>
    <button type="submit" class="btn">Simpan Perubahan</button>
    <a href="index.php" class="btn btn-secondary">Batal</a>
</form>

<?php
// Sertakan footer
require_once '../../layout/footer.php';
?>