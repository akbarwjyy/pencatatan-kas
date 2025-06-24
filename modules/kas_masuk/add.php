<?php
// Sertakan header
require_once '../../layout/header.php';

// Pastikan hanya Admin atau Pegawai yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pegawai')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

$id_kas_masuk_error = $tgl_kas_masuk_error = $jumlah_error = $keterangan_error = $id_akun_error = "";
$id_kas_masuk = $tgl_kas_masuk = $jumlah = $keterangan = $id_akun = "";

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
    $id_kas_masuk = sanitize_input($_POST['id_kas_masuk']);
    $tgl_kas_masuk = sanitize_input($_POST['tgl_kas_masuk']);
    $jumlah = sanitize_input($_POST['jumlah']);
    $keterangan = sanitize_input($_POST['keterangan']);
    $id_akun = sanitize_input($_POST['id_akun']); // Akun tujuan kas masuk ini

    // Validasi input
    if (empty($id_kas_masuk)) {
        $id_kas_masuk_error = "ID Kas Masuk tidak boleh kosong.";
    } elseif (strlen($id_kas_masuk) > 8) {
        $id_kas_masuk_error = "ID Kas Masuk maksimal 8 karakter.";
    }

    if (empty($tgl_kas_masuk)) {
        $tgl_kas_masuk_error = "Tanggal Kas Masuk tidak boleh kosong.";
    }

    if (empty($jumlah) || !is_numeric($jumlah) || $jumlah <= 0) {
        $jumlah_error = "Jumlah harus angka positif.";
    } else {
        $jumlah = (int)$jumlah;
    }

    if (empty($keterangan)) {
        $keterangan_error = "Keterangan tidak boleh kosong.";
    } elseif (strlen($keterangan) > 30) {
        $keterangan_error = "Keterangan maksimal 30 karakter.";
    }

    if (empty($id_akun)) {
        $id_akun_error = "Akun tidak boleh kosong.";
    }

    // Jika tidak ada error validasi, coba simpan ke database
    if (empty($id_kas_masuk_error) && empty($tgl_kas_masuk_error) && empty($jumlah_error) && empty($keterangan_error) && empty($id_akun_error)) {
        // Cek apakah id_kas_masuk sudah ada di database
        $check_sql = "SELECT id_kas_masuk FROM kas_masuk WHERE id_kas_masuk = ?";
        $stmt_check = $conn->prepare($check_sql);
        $stmt_check->bind_param("s", $id_kas_masuk);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $id_kas_masuk_error = "ID Kas Masuk sudah ada. Gunakan ID lain.";
            set_flash_message("Gagal menambahkan kas masuk: ID sudah ada.", "error");
        } else {
            $stmt_check->close();

            // Query untuk menambah data kas masuk (id_transaksi diisi NULL)
            $sql = "INSERT INTO kas_masuk (id_kas_masuk, id_transaksi, tgl_kas_masuk, jumlah, keterangan) VALUES (?, NULL, ?, ?, ?)";

            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ssis", $id_kas_masuk, $tgl_kas_masuk, $jumlah, $keterangan);

                if ($stmt->execute()) {
                    set_flash_message("Kas Masuk berhasil ditambahkan!", "success");
                    redirect('index.php'); // Redirect ke halaman daftar kas masuk
                } else {
                    set_flash_message("Gagal menambahkan kas masuk: " . $stmt->error, "error");
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

<h1>Tambah Kas Masuk Lainnya</h1>
<p>Isi formulir di bawah ini untuk mencatat kas masuk yang tidak terkait dengan transaksi pemesanan.</p>

<form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
    <div class="form-group">
        <label for="id_kas_masuk">ID Kas Masuk:</label>
        <input type="text" id="id_kas_masuk" name="id_kas_masuk" value="<?php echo htmlspecialchars($id_kas_masuk); ?>" required maxlength="8">
        <span class="error" style="color: red; font-size: 0.9em;"><?php echo $id_kas_masuk_error; ?></span>
    </div>
    <div class="form-group">
        <label for="tgl_kas_masuk">Tanggal Kas Masuk:</label>
        <input type="date" id="tgl_kas_masuk" name="tgl_kas_masuk" value="<?php echo htmlspecialchars($tgl_kas_masuk); ?>" required>
        <span class="error" style="color: red; font-size: 0.9em;"><?php echo $tgl_kas_masuk_error; ?></span>
    </div>
    <div class="form-group">
        <label for="id_akun">Akun Tujuan (contoh: Modal, Kas, Bank):</label>
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
        <label for="jumlah">Jumlah (Rp):</label>
        <input type="number" id="jumlah" name="jumlah" value="<?php echo htmlspecialchars($jumlah); ?>" required min="1">
        <span class="error" style="color: red; font-size: 0.9em;"><?php echo $jumlah_error; ?></span>
    </div>
    <div class="form-group">
        <label for="keterangan">Keterangan (misal: "Modal Awal", "Pengembalian Dana"):</label>
        <input type="text" id="keterangan" name="keterangan" value="<?php echo htmlspecialchars($keterangan); ?>" required maxlength="30">
        <span class="error" style="color: red; font-size: 0.9em;"><?php echo $keterangan_error; ?></span>
    </div>
    <button type="submit" class="btn">Simpan</button>
    <a href="index.php" class="btn btn-secondary">Batal</a>
</form>

<?php
// Sertakan footer
require_once '../../layout/footer.php';
?>