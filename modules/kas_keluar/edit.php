<?php
// Sertakan header
require_once '../../layout/header.php';

// Pastikan hanya Admin yang bisa mengakses halaman ini
if (!has_permission('Admin')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

$id_kas_keluar_error = $tgl_kas_keluar_error = $jumlah_error = $keterangan_error = $id_akun_error = "";
$id_kas_keluar = $tgl_kas_keluar = $jumlah = $keterangan = $id_akun = ""; // id_akun untuk menyimpan pilihan yang sudah ada

// Ambil daftar akun untuk dropdown
$accounts = [];
$account_sql = "SELECT id_akun, nama_akun FROM akun ORDER BY nama_akun ASC";
$account_result = $conn->query($account_sql);
if ($account_result->num_rows > 0) {
    while ($row = $account_result->fetch_assoc()) {
        $accounts[] = $row;
    }
}

// Cek apakah ada ID kas keluar yang dikirim melalui URL
if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $id_kas_keluar_dari_url = sanitize_input(trim($_GET['id']));

    // Ambil data kas keluar berdasarkan ID
    $sql = "SELECT id_kas_keluar, id_akun, tgl_kas_keluar, jumlah, keterangan FROM kas_keluar WHERE id_kas_keluar = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $id_kas_keluar_dari_url);
        if ($stmt->execute()) {
            $stmt->store_result();
            if ($stmt->num_rows == 1) {
                $stmt->bind_result($id_kas_keluar, $id_akun, $tgl_kas_keluar, $jumlah, $keterangan);
                $stmt->fetch();
            } else {
                set_flash_message("Kas Keluar tidak ditemukan.", "error");
                redirect('index.php');
            }
        } else {
            set_flash_message("Error saat mengambil data kas keluar: " . $stmt->error, "error");
            redirect('index.php');
        }
        $stmt->close();
    } else {
        set_flash_message("Error prepared statement: " . $conn->error, "error");
        redirect('index.php');
    }
} else {
    set_flash_message("ID Kas Keluar tidak ditemukan.", "error");
    redirect('index.php');
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitasi input
    $id_kas_keluar_edit = sanitize_input($_POST['id_kas_keluar_asal']); // ID yang diedit (hidden input)
    $tgl_kas_keluar_baru = sanitize_input($_POST['tgl_kas_keluar']);
    $id_akun_baru = sanitize_input($_POST['id_akun']);
    $jumlah_baru = sanitize_input($_POST['jumlah']);
    $keterangan_baru = sanitize_input($_POST['keterangan']);

    // Validasi input
    if (empty($tgl_kas_keluar_baru)) {
        $tgl_kas_keluar_error = "Tanggal Kas Keluar tidak boleh kosong.";
    }

    if (empty($id_akun_baru)) {
        $id_akun_error = "Akun tidak boleh kosong.";
    }

    if (empty($jumlah_baru) || !is_numeric($jumlah_baru) || $jumlah_baru <= 0) {
        $jumlah_error = "Jumlah harus angka positif.";
    } else {
        $jumlah_baru = (int)$jumlah_baru;
    }

    if (empty($keterangan_baru)) {
        $keterangan_error = "Keterangan tidak boleh kosong.";
    } elseif (strlen($keterangan_baru) > 30) {
        $keterangan_error = "Keterangan maksimal 30 karakter.";
    }

    // Jika tidak ada error validasi, coba update ke database
    if (empty($tgl_kas_keluar_error) && empty($id_akun_error) && empty($jumlah_error) && empty($keterangan_error)) {
        // Query untuk update data kas keluar
        $sql = "UPDATE kas_keluar SET id_akun = ?, tgl_kas_keluar = ?, jumlah = ?, keterangan = ? WHERE id_kas_keluar = ?";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("sssis", $id_akun_baru, $tgl_kas_keluar_baru, $jumlah_baru, $keterangan_baru, $id_kas_keluar_edit);

            if ($stmt->execute()) {
                set_flash_message("Kas Keluar berhasil diperbarui!", "success");
                redirect('index.php'); // Redirect ke halaman daftar kas keluar
            } else {
                set_flash_message("Gagal memperbarui kas keluar: " . $stmt->error, "error");
            }
            $stmt->close();
        } else {
            set_flash_message("Error prepared statement: " . $conn->error, "error");
        }
    } else {
        set_flash_message("Silakan perbaiki kesalahan pada formulir.", "error");
        // Reload data from current post if there were errors to keep form values
        $tgl_kas_keluar = $tgl_kas_keluar_baru;
        $id_akun = $id_akun_baru;
        $jumlah = $jumlah_baru;
        $keterangan = $keterangan_baru;
    }
}
?>

<h1>Edit Kas Keluar</h1>
<p>Ubah detail kas keluar di bawah ini.</p>

<form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?id=" . htmlspecialchars($id_kas_keluar); ?>" method="post">
    <!-- ID Kas Keluar ditampilkan sebagai non-editable, tapi tetap dikirimkan sebagai hidden input -->
    <div class="form-group">
        <label for="id_kas_keluar_display">ID Kas Keluar:</label>
        <input type="text" id="id_kas_keluar_display" value="<?php echo htmlspecialchars($id_kas_keluar); ?>" disabled>
        <input type="hidden" name="id_kas_keluar_asal" value="<?php echo htmlspecialchars($id_kas_keluar); ?>">
    </div>
    <div class="form-group">
        <label for="tgl_kas_keluar">Tanggal Kas Keluar:</label>
        <input type="date" id="tgl_kas_keluar" name="tgl_kas_keluar" value="<?php echo htmlspecialchars($tgl_kas_keluar); ?>" required>
        <span class="error" style="color: red; font-size: 0.9em;"><?php echo $tgl_kas_keluar_error; ?></span>
    </div>
    <div class="form-group">
        <label for="id_akun">Akun Pengeluaran:</label>
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
        <label for="keterangan">Keterangan:</label>
        <input type="text" id="keterangan" name="keterangan" value="<?php echo htmlspecialchars($keterangan); ?>" required maxlength="30">
        <span class="error" style="color: red; font-size: 0.9em;"><?php echo $keterangan_error; ?></span>
    </div>
    <button type="submit" class="btn">Simpan Perubahan</button>
    <a href="index.php" class="btn btn-secondary">Batal</a>
</form>

<?php
?>