<?php
// Sertakan header
require_once '../../layout/header.php';

// Pastikan hanya Admin atau Pegawai yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pegawai')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

$id_transaksi_error = $id_pesan_error = $id_akun_error = $tgl_transaksi_error = $jumlah_dibayar_error = $metode_pembayaran_error = $keterangan_error = "";
$id_transaksi = $id_pesan = $id_akun = $tgl_transaksi = $jumlah_dibayar = $metode_pembayaran = $keterangan = "";
$total_tagihan_display = 0;
$sisa_pembayaran_display = 0;
$old_id_pesan = ''; // Untuk menyimpan id_pesan lama jika berubah
$old_jumlah_dibayar = 0; // Untuk menghitung kembali sisa pemesanan jika diubah

// Ambil daftar pemesanan yang belum lunas atau partially paid untuk dropdown
$pemesanan_options = [];
// Termasuk pemesanan yang mungkin sudah lunas karena transaksi ini, agar bisa diedit
$pemesanan_sql = "SELECT p.id_pesan, p.sub_total, p.sisa, c.nama_customer 
                  FROM pemesanan p 
                  JOIN customer c ON p.id_customer = c.id_customer
                  ORDER BY p.tgl_pesan DESC";
$pemesanan_result = $conn->query($pemesanan_sql);
if ($pemesanan_result->num_rows > 0) {
    while ($row = $pemesanan_result->fetch_assoc()) {
        $pemesanan_options[] = $row;
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

// Cek apakah ada ID transaksi yang dikirim melalui URL
if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $id_transaksi_dari_url = sanitize_input(trim($_GET['id']));

    // Ambil data transaksi berdasarkan ID
    $sql = "SELECT id_transaksi, id_pesan, id_akun, tgl_transaksi, jumlah_dibayar, metode_pembayaran, keterangan, total_tagihan, sisa_pembayaran FROM transaksi WHERE id_transaksi = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $id_transaksi_dari_url);
        if ($stmt->execute()) {
            $stmt->store_result();
            if ($stmt->num_rows == 1) {
                $stmt->bind_result($id_transaksi, $id_pesan, $id_akun, $tgl_transaksi, $jumlah_dibayar, $metode_pembayaran, $keterangan, $total_tagihan_display, $sisa_pembayaran_display);
                $stmt->fetch();

                // Simpan nilai lama untuk perhitungan rollback jika perlu
                $old_id_pesan = $id_pesan;
                $old_jumlah_dibayar = $jumlah_dibayar;
            } else {
                set_flash_message("Transaksi tidak ditemukan.", "error");
                redirect('index.php');
            }
        } else {
            set_flash_message("Error saat mengambil data transaksi: " . $stmt->error, "error");
            redirect('index.php');
        }
        $stmt->close();
    } else {
        set_flash_message("Error prepared statement: " . $conn->error, "error");
        redirect('index.php');
    }
} else {
    set_flash_message("ID Transaksi tidak ditemukan.", "error");
    redirect('index.php');
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitasi input
    $id_transaksi_edit = sanitize_input($_POST['id_transaksi_asal']); // ID yang diedit (hidden input)
    $id_pesan_baru = sanitize_input($_POST['id_pesan']);
    $id_akun_baru = sanitize_input($_POST['id_akun']);
    $tgl_transaksi_baru = sanitize_input($_POST['tgl_transaksi']);
    $jumlah_dibayar_baru = sanitize_input($_POST['jumlah_dibayar']);
    $metode_pembayaran_baru = sanitize_input($_POST['metode_pembayaran']);
    $keterangan_baru = sanitize_input($_POST['keterangan']);

    $old_id_pesan_post = sanitize_input($_POST['old_id_pesan']);
    $old_jumlah_dibayar_post = sanitize_input($_POST['old_jumlah_dibayar']);


    // Validasi input
    if (empty($id_pesan_baru)) {
        $id_pesan_error = "Pemesanan tidak boleh kosong.";
    }
    if (empty($id_akun_baru)) {
        $id_akun_error = "Akun tidak boleh kosong.";
    }
    if (empty($tgl_transaksi_baru)) {
        $tgl_transaksi_error = "Tanggal Transaksi tidak boleh kosong.";
    }

    if (empty($jumlah_dibayar_baru) || !is_numeric($jumlah_dibayar_baru) || $jumlah_dibayar_baru <= 0) {
        $jumlah_dibayar_error = "Jumlah Dibayar harus angka positif.";
    } else {
        $jumlah_dibayar_baru = (int)$jumlah_dibayar_baru;
    }

    if (empty($metode_pembayaran_baru)) {
        $metode_pembayaran_error = "Metode Pembayaran tidak boleh kosong.";
    } elseif (strlen($metode_pembayaran_baru) > 20) {
        $metode_pembayaran_error = "Metode Pembayaran maksimal 20 karakter.";
    }

    if (strlen($keterangan_baru) > 30) {
        $keterangan_error = "Keterangan maksimal 30 karakter.";
    }

    // Ambil detail pemesanan untuk validasi jumlah dibayar yang baru
    $current_sisa_pemesanan_baru = 0;
    $total_tagihan_pemesanan_baru = 0;
    $id_customer_related_baru = '';
    $tgl_kirim_related_baru = '';

    if (!empty($id_pesan_baru)) {
        $pemesanan_detail_sql_baru = "SELECT sisa, sub_total, id_customer, tgl_kirim FROM pemesanan WHERE id_pesan = ?";
        if ($stmt_pemesanan_baru = $conn->prepare($pemesanan_detail_sql_baru)) {
            $stmt_pemesanan_baru->bind_param("s", $id_pesan_baru);
            $stmt_pemesanan_baru->execute();
            $stmt_pemesanan_baru->bind_result($current_sisa_pemesanan_baru, $total_tagihan_pemesanan_baru, $id_customer_related_baru, $tgl_kirim_related_baru);
            $stmt_pemesanan_baru->fetch();
            $stmt_pemesanan_baru->close();

            // Hitung sisa baru setelah transaksi ini
            // Jika pemesanan sama, sisa yang berlaku adalah sisa saat ini + jumlah dibayar lama - jumlah dibayar baru
            $sisa_untuk_validasi = $current_sisa_pemesanan_baru;
            if ($id_pesan_baru === $old_id_pesan_post) {
                $sisa_untuk_validasi += $old_jumlah_dibayar_post; // Tambahkan kembali jumlah lama
            }

            if ($jumlah_dibayar_baru > $sisa_untuk_validasi) {
                $jumlah_dibayar_error = "Jumlah Dibayar tidak boleh melebihi sisa tagihan pemesanan (" . format_rupiah($sisa_untuk_validasi) . ").";
            }
            $total_tagihan_display = $total_tagihan_pemesanan_baru;
            $sisa_pembayaran_display = $sisa_untuk_validasi - $jumlah_dibayar_baru;
        } else {
            set_flash_message("Error saat mengambil detail pemesanan baru: " . $conn->error, "error");
            $id_pesan_error = "Gagal mengambil detail pemesanan baru.";
        }
    }


    // Jika tidak ada error validasi, coba update ke database
    if (
        empty($id_transaksi_error) && empty($id_pesan_error) && empty($id_akun_error) && empty($tgl_transaksi_error) &&
        empty($jumlah_dibayar_error) && empty($metode_pembayaran_error) && empty($keterangan_error)
    ) {

        // Mulai transaksi database
        $conn->begin_transaction();
        try {
            // 1. Rollback perubahan pada pemesanan lama (jika ada dan jika id_pesan berubah atau jumlah dibayar berubah)
            if (!empty($old_id_pesan_post) && ($old_id_pesan_post != $id_pesan_baru || $old_jumlah_dibayar_post != $jumlah_dibayar_baru)) {
                // Tambahkan kembali jumlah yang dibayar sebelumnya ke sisa pemesanan lama
                $sql_rollback_old_pemesanan = "UPDATE pemesanan SET sisa = sisa + ?, status_pesanan = 'Belum Lunas' WHERE id_pesan = ?";
                if ($stmt_rollback = $conn->prepare($sql_rollback_old_pemesanan)) {
                    $stmt_rollback->bind_param("is", $old_jumlah_dibayar_post, $old_id_pesan_post);
                    if (!$stmt_rollback->execute()) {
                        throw new Exception("Gagal rollback pemesanan lama: " . $stmt_rollback->error);
                    }
                    $stmt_rollback->close();
                } else {
                    throw new Exception("Error prepared statement (rollback pemesanan): " . $conn->error);
                }
            }

            // Tentukan status pelunasan baru
            $status_pelunasan_baru = ($sisa_pembayaran_display == 0) ? 'Lunas' : 'Belum Lunas';

            // 2. Update data transaksi
            $sql_transaksi_update = "UPDATE transaksi SET id_pesan = ?, id_akun = ?, id_customer = ?, tgl_transaksi = ?, jumlah_dibayar = ?, metode_pembayaran = ?, keterangan = ?, total_tagihan = ?, sisa_pembayaran = ? WHERE id_transaksi = ?";
            if ($stmt_transaksi_update = $conn->prepare($sql_transaksi_update)) {
                $stmt_transaksi_update->bind_param(
                    "sssssissis",
                    $id_pesan_baru,
                    $id_akun_baru,
                    $id_customer_related_baru,
                    $tgl_transaksi_baru,
                    $jumlah_dibayar_baru,
                    $metode_pembayaran_baru,
                    $keterangan_baru,
                    $total_tagihan_pemesanan_baru,
                    $sisa_pembayaran_display,
                    $id_transaksi_edit
                );

                if (!$stmt_transaksi_update->execute()) {
                    throw new Exception("Gagal memperbarui transaksi: " . $stmt_transaksi_update->error);
                }
                $stmt_transaksi_update->close();
            } else {
                throw new Exception("Error prepared statement (update transaksi): " . $conn->error);
            }

            // 3. Update sisa pembayaran di tabel pemesanan baru (jika ada)
            if (!empty($id_pesan_baru)) {
                $sql_update_pemesanan_baru = "UPDATE pemesanan SET sisa = ?, status_pesanan = ? WHERE id_pesan = ?";
                if ($stmt_update_pemesanan_baru = $conn->prepare($sql_update_pemesanan_baru)) {
                    $stmt_update_pemesanan_baru->bind_param("iss", $sisa_pembayaran_display, $status_pelunasan_baru, $id_pesan_baru);
                    if (!$stmt_update_pemesanan_baru->execute()) {
                        throw new Exception("Gagal memperbarui pemesanan baru: " . $stmt_update_pemesanan_baru->error);
                    }
                    $stmt_update_pemesanan_baru->close();
                } else {
                    throw new Exception("Error prepared statement (update pemesanan baru): " . $conn->error);
                }
            }

            // 4. Update entri kas_masuk terkait transaksi ini
            $sql_kas_masuk_update = "UPDATE kas_masuk SET tgl_kas_masuk = ?, jumlah = ?, keterangan = ? WHERE id_transaksi = ?";
            $keterangan_kas_masuk_update = "Pembayaran " . $keterangan_baru . " untuk Pesanan " . $id_pesan_baru;

            if ($stmt_kas_masuk_update = $conn->prepare($sql_kas_masuk_update)) {
                $stmt_kas_masuk_update->bind_param("siss", $tgl_transaksi_baru, $jumlah_dibayar_baru, $keterangan_kas_masuk_update, $id_transaksi_edit);
                if (!$stmt_kas_masuk_update->execute()) {
                    throw new Exception("Gagal memperbarui entri kas masuk: " . $stmt_kas_masuk_update->error);
                }
                $stmt_kas_masuk_update->close();
            } else {
                throw new Exception("Error prepared statement (kas_masuk update): " . $conn->error);
            }


            // Commit transaksi jika semua berhasil
            $conn->commit();
            set_flash_message("Transaksi dan Kas Masuk berhasil diperbarui! Status Pelunasan: " . $status_pelunasan_baru, "success");
            redirect('index.php');
        } catch (Exception $e) {
            // Rollback transaksi jika ada error
            $conn->rollback();
            set_flash_message("Error saat memperbarui transaksi: " . $e->getMessage(), "error");
        }
    } else {
        set_flash_message("Silakan perbaiki kesalahan pada formulir.", "error");
        // Reload data from current post if there were errors to keep form values
        $id_pesan = $id_pesan_baru;
        $id_akun = $id_akun_baru;
        $tgl_transaksi = $tgl_transaksi_baru;
        $jumlah_dibayar = $jumlah_dibayar_baru;
        $metode_pembayaran = $metode_pembayaran_baru;
        $keterangan = $keterangan_baru;
    }
}
?>

<h1>Edit Transaksi</h1>
<p>Ubah detail transaksi pembayaran di bawah ini.</p>

<form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?id=" . htmlspecialchars($id_transaksi); ?>" method="post">
    <div class="form-group">
        <label for="id_transaksi_display">ID Transaksi:</label>
        <input type="text" id="id_transaksi_display" value="<?php echo htmlspecialchars($id_transaksi); ?>" disabled>
        <input type="hidden" name="id_transaksi_asal" value="<?php echo htmlspecialchars($id_transaksi); ?>">
        <input type="hidden" name="old_id_pesan" value="<?php echo htmlspecialchars($old_id_pesan); ?>">
        <input type="hidden" name="old_jumlah_dibayar" value="<?php echo htmlspecialchars($old_jumlah_dibayar); ?>">
    </div>
    <div class="form-group">
        <label for="id_pesan">Pemesanan (Customer - Total Tagihan - Sisa):</label>
        <select id="id_pesan" name="id_pesan" required onchange="updatePemesananInfo()">
            <option value="">-- Pilih Pemesanan --</option>
            <?php foreach ($pemesanan_options as $option) : ?>
                <option value="<?php echo htmlspecialchars($option['id_pesan']); ?>"
                    data-subtotal="<?php echo htmlspecialchars($option['sub_total']); ?>"
                    data-sisa="<?php echo htmlspecialchars($option['sisa']); ?>"
                    <?php echo ($id_pesan == $option['id_pesan']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($option['nama_customer'] . " - " . format_rupiah($option['sub_total']) . " - Sisa: " . format_rupiah($option['sisa'])); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <span class="error" style="color: red; font-size: 0.9em;"><?php echo $id_pesan_error; ?></span>
    </div>
    <div class="form-group">
        <label for="id_akun">Akun Penerima Pembayaran:</label>
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
        <label for="tgl_transaksi">Tanggal Transaksi:</label>
        <input type="date" id="tgl_transaksi" name="tgl_transaksi" value="<?php echo htmlspecialchars($tgl_transaksi); ?>" required>
        <span class="error" style="color: red; font-size: 0.9em;"><?php echo $tgl_transaksi_error; ?></span>
    </div>
    <div class="form-group">
        <label for="jumlah_dibayar">Jumlah Dibayar (Rp):</label>
        <input type="number" id="jumlah_dibayar" name="jumlah_dibayar" value="<?php echo htmlspecialchars($jumlah_dibayar); ?>" required min="1">
        <span class="error" style="color: red; font-size: 0.9em;"><?php echo $jumlah_dibayar_error; ?></span>
    </div>
    <div class="form-group">
        <label for="metode_pembayaran">Metode Pembayaran:</label>
        <input type="text" id="metode_pembayaran" name="metode_pembayaran" value="<?php echo htmlspecialchars($metode_pembayaran); ?>" required maxlength="20">
        <span class="error" style="color: red; font-size: 0.9em;"><?php echo $metode_pembayaran_error; ?></span>
    </div>
    <div class="form-group">
        <label for="keterangan">Keterangan:</label>
        <input type="text" id="keterangan" name="keterangan" value="<?php echo htmlspecialchars($keterangan); ?>" maxlength="30">
        <span class="error" style="color: red; font-size: 0.9em;"><?php echo $keterangan_error; ?></span>
    </div>
    <div class="form-group">
        <label>Total Tagihan Pemesanan:</label>
        <input type="text" id="total_tagihan_display" value="<?php echo format_rupiah($total_tagihan_display); ?>" disabled>
    </div>
    <div class="form-group">
        <label>Sisa Pembayaran Setelah Transaksi Ini:</label>
        <input type="text" id="sisa_pembayaran_display" value="<?php echo format_rupiah($sisa_pembayaran_display); ?>" disabled>
    </div>
    <button type="submit" class="btn">Simpan Perubahan</button>
    <a href="index.php" class="btn btn-secondary">Batal</a>
</form>

<script>
    // Fungsi untuk mengupdate info tagihan saat pemesanan dipilih
    function updatePemesananInfo() {
        const selectElement = document.getElementById('id_pesan');
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        const subTotal = parseFloat(selectedOption.dataset.subtotal || 0);
        let sisaAwal = parseFloat(selectedOption.dataset.sisa || 0);

        // Jika ID pemesanan yang dipilih sama dengan ID pemesanan lama (saat pertama load halaman)
        // maka sisa awal harus ditambahkan kembali dengan jumlah_dibayar lama untuk perhitungan yang benar
        const oldIdPesan = document.querySelector('input[name="old_id_pesan"]').value;
        const oldJumlahDibayar = parseFloat(document.querySelector('input[name="old_jumlah_dibayar"]').value || 0);

        if (selectedOption.value === oldIdPesan) {
            sisaAwal += oldJumlahDibayar;
        }

        document.getElementById('total_tagihan_display').value = formatRupiah(subTotal);
        document.getElementById('jumlah_dibayar').max = sisaAwal; // Set max input jumlah_dibayar
        // Tidak otomatis mengisi jumlah_dibayar di edit, biarkan user yang mengisi

        updateSisaSetelahPembayaran();
    }

    // Fungsi untuk mengupdate sisa pembayaran setelah input jumlah dibayar
    document.getElementById('jumlah_dibayar').addEventListener('input', updateSisaSetelahPembayaran);

    function updateSisaSetelahPembayaran() {
        const selectElement = document.getElementById('id_pesan');
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        let sisaAwal = parseFloat(selectedOption.dataset.sisa || 0);
        const jumlahDibayar = parseFloat(document.getElementById('jumlah_dibayar').value || 0);

        const oldIdPesan = document.querySelector('input[name="old_id_pesan"]').value;
        const oldJumlahDibayar = parseFloat(document.querySelector('input[name="old_jumlah_dibayar"]').value || 0);

        if (selectedOption.value === oldIdPesan) {
            sisaAwal += oldJumlahDibayar;
        }

        const sisaSetelahIni = sisaAwal - jumlahDibayar;
        document.getElementById('sisa_pembayaran_display').value = formatRupiah(sisaSetelahIni);
    }

    // Format Rupiah di sisi klien (JavaScript)
    function formatRupiah(angka) {
        var reverse = angka.toString().split('').reverse().join(''),
            ribuan = reverse.match(/\d{1,3}/g);
        ribuan = ribuan.join('.').split('').reverse().join('');
        return 'Rp ' + ribuan;
    }

    // Panggil saat halaman pertama kali dimuat untuk mengisi nilai awal jika ada selected option
    document.addEventListener('DOMContentLoaded', updatePemesananInfo);
</script>

<?php
?>