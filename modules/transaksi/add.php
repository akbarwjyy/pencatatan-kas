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

// Ambil daftar pemesanan yang belum lunas atau partially paid untuk dropdown
$pemesanan_options = [];
$pemesanan_sql = "SELECT p.id_pesan, p.sub_total, p.sisa, c.nama_customer 
                  FROM pemesanan p 
                  JOIN customer c ON p.id_customer = c.id_customer
                  WHERE p.sisa > 0 
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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitasi input
    $id_transaksi = sanitize_input($_POST['id_transaksi']);
    $id_pesan = sanitize_input($_POST['id_pesan']);
    $id_akun = sanitize_input($_POST['id_akun']);
    $tgl_transaksi = sanitize_input($_POST['tgl_transaksi']);
    $jumlah_dibayar = sanitize_input($_POST['jumlah_dibayar']);
    $metode_pembayaran = sanitize_input($_POST['metode_pembayaran']);
    $keterangan = sanitize_input($_POST['keterangan']);

    // Validasi input
    if (empty($id_transaksi)) {
        $id_transaksi_error = "ID Transaksi tidak boleh kosong.";
    } elseif (strlen($id_transaksi) > 8) {
        $id_transaksi_error = "ID Transaksi maksimal 8 karakter.";
    }

    if (empty($id_pesan)) {
        $id_pesan_error = "Pemesanan tidak boleh kosong.";
    }
    if (empty($id_akun)) {
        $id_akun_error = "Akun tidak boleh kosong.";
    }
    if (empty($tgl_transaksi)) {
        $tgl_transaksi_error = "Tanggal Transaksi tidak boleh kosong.";
    }

    if (empty($jumlah_dibayar) || !is_numeric($jumlah_dibayar) || $jumlah_dibayar <= 0) {
        $jumlah_dibayar_error = "Jumlah Dibayar harus angka positif.";
    } else {
        $jumlah_dibayar = (int)$jumlah_dibayar;
    }

    if (empty($metode_pembayaran)) {
        $metode_pembayaran_error = "Metode Pembayaran tidak boleh kosong.";
    } elseif (strlen($metode_pembayaran) > 20) {
        $metode_pembayaran_error = "Metode Pembayaran maksimal 20 karakter.";
    }

    if (strlen($keterangan) > 30) {
        $keterangan_error = "Keterangan maksimal 30 karakter.";
    }

    // Ambil detail pemesanan untuk validasi jumlah dibayar
    $current_sisa_pemesanan = 0;
    $total_tagihan_pemesanan = 0;
    $id_customer_related = '';
    $tgl_kirim_related = '';
    if (!empty($id_pesan)) {
        $pemesanan_detail_sql = "SELECT sisa, sub_total, id_customer, tgl_kirim FROM pemesanan WHERE id_pesan = ?";
        if ($stmt_pemesanan = $conn->prepare($pemesanan_detail_sql)) {
            $stmt_pemesanan->bind_param("s", $id_pesan);
            $stmt_pemesanan->execute();
            $stmt_pemesanan->bind_result($current_sisa_pemesanan, $total_tagihan_pemesanan, $id_customer_related, $tgl_kirim_related);
            $stmt_pemesanan->fetch();
            $stmt_pemesanan->close();

            if ($jumlah_dibayar > $current_sisa_pemesanan) {
                $jumlah_dibayar_error = "Jumlah Dibayar tidak boleh melebihi sisa tagihan pemesanan (" . format_rupiah($current_sisa_pemesanan) . ").";
            }
            $total_tagihan_display = $total_tagihan_pemesanan;
            $sisa_pembayaran_display = $current_sisa_pemesanan - $jumlah_dibayar;
        } else {
            set_flash_message("Error saat mengambil detail pemesanan: " . $conn->error, "error");
            $id_pesan_error = "Gagal mengambil detail pemesanan.";
        }
    }


    // Jika tidak ada error validasi, coba simpan ke database
    if (
        empty($id_transaksi_error) && empty($id_pesan_error) && empty($id_akun_error) && empty($tgl_transaksi_error) &&
        empty($jumlah_dibayar_error) && empty($metode_pembayaran_error) && empty($keterangan_error)
    ) {

        // Cek apakah id_transaksi sudah ada di database
        $check_sql = "SELECT id_transaksi FROM transaksi WHERE id_transaksi = ?";
        $stmt_check = $conn->prepare($check_sql);
        $stmt_check->bind_param("s", $id_transaksi);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $id_transaksi_error = "ID Transaksi sudah ada. Gunakan ID lain.";
            set_flash_message("Gagal menambahkan transaksi: ID Transaksi sudah ada.", "error");
        } else {
            $stmt_check->close();

            // Mulai transaksi database untuk memastikan konsistensi
            $conn->begin_transaction();
            try {
                // Tentukan status pelunasan
                $status_pelunasan = ($sisa_pembayaran_display == 0) ? 'Lunas' : 'Belum Lunas';

                // Query untuk menambah data transaksi
                $sql_transaksi = "INSERT INTO transaksi (id_transaksi, id_pesan, id_akun, id_customer, tgl_transaksi, jumlah_dibayar, metode_pembayaran, keterangan, total_tagihan, sisa_pembayaran, status_pelunasan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                if ($stmt_transaksi = $conn->prepare($sql_transaksi)) {
                    $stmt_transaksi->bind_param(
                        "sssssisssis",
                        $id_transaksi,
                        $id_pesan,
                        $id_akun,
                        $id_customer_related,
                        $tgl_transaksi,
                        $jumlah_dibayar,
                        $metode_pembayaran,
                        $keterangan,
                        $total_tagihan_pemesanan,
                        $sisa_pembayaran_display,
                        $status_pelunasan
                    );

                    if (!$stmt_transaksi->execute()) {
                        throw new Exception("Gagal menambahkan transaksi: " . $stmt_transaksi->error);
                    }
                    $stmt_transaksi->close();
                } else {
                    throw new Exception("Error prepared statement (transaksi): " . $conn->error);
                }

                // Update sisa pembayaran di tabel pemesanan
                $sql_update_pemesanan = "UPDATE pemesanan SET sisa = ?, status_pelunasan = ? WHERE id_pesan = ?";
                if ($stmt_update_pemesanan = $conn->prepare($sql_update_pemesanan)) {
                    $stmt_update_pemesanan->bind_param("iss", $sisa_pembayaran_display, $status_pelunasan, $id_pesan);
                    if (!$stmt_update_pemesanan->execute()) {
                        throw new Exception("Gagal memperbarui pemesanan: " . $stmt_update_pemesanan->error);
                    }
                    $stmt_update_pemesanan->close();
                } else {
                    throw new Exception("Error prepared statement (update pemesanan): " . $conn->error);
                }

                // Tambahkan entri ke tabel kas_masuk
                $sql_kas_masuk = "INSERT INTO kas_masuk (id_kas_masuk, id_transaksi, tgl_kas_masuk, jumlah, keterangan) VALUES (?, ?, ?, ?, ?)";
                // Generate ID Kas Masuk (Anda mungkin punya format sendiri, ini contoh sederhana)
                $id_kas_masuk = "KM" . date("YmdHis");
                $keterangan_kas_masuk = "Pembayaran " . $keterangan . " untuk Pesanan " . $id_pesan;

                if ($stmt_kas_masuk = $conn->prepare($sql_kas_masuk)) {
                    $stmt_kas_masuk->bind_param("sssis", $id_kas_masuk, $id_transaksi, $tgl_transaksi, $jumlah_dibayar, $keterangan_kas_masuk);
                    if (!$stmt_kas_masuk->execute()) {
                        throw new Exception("Gagal menambahkan entri kas masuk: " . $stmt_kas_masuk->error);
                    }
                    $stmt_kas_masuk->close();
                } else {
                    throw new Exception("Error prepared statement (kas_masuk): " . $conn->error);
                }

                // Commit transaksi jika semua berhasil
                $conn->commit();
                set_flash_message("Transaksi dan Kas Masuk berhasil ditambahkan! Status Pelunasan: " . $status_pelunasan, "success");
                redirect('index.php');
            } catch (Exception $e) {
                // Rollback transaksi jika ada error
                $conn->rollback();
                set_flash_message("Error saat memproses transaksi: " . $e->getMessage(), "error");
            }
        }
    } else {
        set_flash_message("Silakan perbaiki kesalahan pada formulir.", "error");
        // Reload data from current post if there were errors to keep form values
        $tgl_transaksi_display = $tgl_transaksi;
        $jumlah_dibayar_display = $jumlah_dibayar;
        $metode_pembayaran_display = $metode_pembayaran;
        $keterangan_display = $keterangan;
    }
}
?>

<div class="bg-white p-8 rounded-lg shadow-xl max-w-lg mx-auto my-8">
    <h1 class="text-2xl font-bold text-gray-800 mb-4 text-center">Tambah Transaksi Baru</h1>
    <p class="text-gray-600 mb-6 text-center">Isi formulir di bawah ini untuk mencatat pembayaran dari pemesanan.</p>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="mb-4">
            <label for="id_transaksi" class="block text-gray-700 text-sm font-bold mb-2">ID Transaksi:</label>
            <input type="text" id="id_transaksi" name="id_transaksi" value="<?php echo htmlspecialchars($id_transaksi); ?>" required maxlength="8"
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $id_transaksi_error; ?></span>
        </div>
        <div class="mb-4">
            <label for="id_pesan" class="block text-gray-700 text-sm font-bold mb-2">Pemesanan (Customer - Total Tagihan - Sisa):</label>
            <select id="id_pesan" name="id_pesan" required onchange="updatePemesananInfo()"
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
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
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $id_pesan_error; ?></span>
        </div>
        <div class="mb-4">
            <label for="id_akun" class="block text-gray-700 text-sm font-bold mb-2">Akun Penerima Pembayaran:</label>
            <select id="id_akun" name="id_akun" required
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
                <option value="">-- Pilih Akun --</option>
                <?php foreach ($accounts as $account_option) : ?>
                    <option value="<?php echo htmlspecialchars($account_option['id_akun']); ?>"
                        <?php echo ($id_akun == $account_option['id_akun']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($account_option['nama_akun']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $id_akun_error; ?></span>
        </div>
        <div class="mb-4">
            <label for="tgl_transaksi" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Transaksi:</label>
            <input type="date" id="tgl_transaksi" name="tgl_transaksi" value="<?php echo htmlspecialchars($tgl_transaksi); ?>" required
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $tgl_transaksi_error; ?></span>
        </div>
        <div class="mb-4">
            <label for="jumlah_dibayar" class="block text-gray-700 text-sm font-bold mb-2">Jumlah Dibayar (Rp):</label>
            <input type="number" id="jumlah_dibayar" name="jumlah_dibayar" value="<?php echo htmlspecialchars($jumlah_dibayar); ?>" required min="1"
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $jumlah_dibayar_error; ?></span>
        </div>
        <div class="mb-4">
            <label for="metode_pembayaran" class="block text-gray-700 text-sm font-bold mb-2">Metode Pembayaran:</label>
            <input type="text" id="metode_pembayaran" name="metode_pembayaran" value="<?php echo htmlspecialchars($metode_pembayaran); ?>" required maxlength="20"
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $metode_pembayaran_error; ?></span>
        </div>
        <div class="mb-6">
            <label for="keterangan" class="block text-gray-700 text-sm font-bold mb-2">Keterangan:</label>
            <input type="text" id="keterangan" name="keterangan" value="<?php echo htmlspecialchars($keterangan); ?>" maxlength="30"
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $keterangan_error; ?></span>
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2">Total Tagihan Pemesanan:</label>
            <input type="text" id="total_tagihan_display" value="<?php echo format_rupiah($total_tagihan_display); ?>" disabled
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100 cursor-not-allowed">
        </div>
        <div class="mb-6">
            <label class="block text-gray-700 text-sm font-bold mb-2">Sisa Pembayaran Setelah Transaksi Ini:</label>
            <input type="text" id="sisa_pembayaran_display" value="<?php echo format_rupiah($sisa_pembayaran_display); ?>" disabled
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100 cursor-not-allowed">
        </div>

        <div class="flex items-center justify-between">
            <button type="submit"
                class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Simpan Transaksi
            </button>
            <a href="index.php"
                class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Batal
            </a>
        </div>
    </form>
</div>

<script>
    // Fungsi untuk mengupdate info tagihan saat pemesanan dipilih
    function updatePemesananInfo() {
        const selectElement = document.getElementById('id_pesan');
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        // Pastikan nilai default 0 jika data-subtotal atau data-sisa tidak ada
        const subTotal = parseFloat(selectedOption.dataset.subtotal || 0);
        const sisaAwal = parseFloat(selectedOption.dataset.sisa || 0);

        document.getElementById('total_tagihan_display').value = formatRupiah(subTotal);
        document.getElementById('jumlah_dibayar').max = sisaAwal; // Set max input jumlah_dibayar
        document.getElementById('jumlah_dibayar').value = sisaAwal; // Default ke sisa_awal
        updateSisaSetelahPembayaran();
    }

    // Fungsi untuk mengupdate sisa pembayaran setelah input jumlah dibayar
    document.getElementById('jumlah_dibayar').addEventListener('input', updateSisaSetelahPembayaran);

    function updateSisaSetelahPembayaran() {
        const selectElement = document.getElementById('id_pesan');
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        const sisaAwal = parseFloat(selectedOption.dataset.sisa || 0);
        const jumlahDibayar = parseFloat(document.getElementById('jumlah_dibayar').value || 0);

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
// Sertakan footer
require_once '../../layout/footer.php';
?>