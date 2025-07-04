<?php
// Sertakan header
require_once '../../layout/header.php';

// Pastikan hanya Admin atau Pegawai yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pegawai')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

$id_transaksi_error = $id_pesan_error = $id_akun_error = $tgl_transaksi_error = $jumlah_dibayar_error = $metode_pembayaran_error = $keterangan_error = $status_pelunasan_error = ""; // Tambah status_pelunasan_error
$id_transaksi = $id_pesan = $id_akun = $tgl_transaksi = $jumlah_dibayar = $metode_pembayaran = $keterangan = "";
$total_tagihan_display = 0;
$sisa_pembayaran_display = 0;
$status_pelunasan_input = "";

// Ambil daftar pemesanan yang belum lunas atau partially paid untuk dropdown
$pemesanan_options = [];
$pemesanan_sql = "SELECT p.id_pesan, p.sub_total, p.sisa, p.tgl_pesan, p.tgl_kirim, p.uang_muka, c.nama_customer 
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

// Ambil daftar akun untuk dropdown (Ini masih diperlukan di PHP untuk proses transaksi, meski tidak di form)
$accounts = [];
$account_sql = "SELECT id_akun, nama_akun FROM akun ORDER BY nama_akun ASC";
$account_result = $conn->query($account_sql);
if ($account_result->num_rows > 0) {
    while ($row = $account_result->fetch_assoc()) {
        $accounts[] = $row;
    }
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // === Ambil input dari form, dengan operator ?? untuk mencegah Undefined index ===
    // id_transaksi akan digenerate, tidak dari POST
    $id_pesan = sanitize_input($_POST['id_pesan'] ?? '');
    // $id_akun = sanitize_input($_POST['id_akun'] ?? ''); // Dihapus dari form
    $tgl_transaksi = sanitize_input($_POST['tgl_transaksi'] ?? '');
    $jumlah_dibayar = sanitize_input($_POST['jumlah_dibayar'] ?? 0);
    $metode_pembayaran = sanitize_input($_POST['metode_pembayaran'] ?? '');
    // $keterangan = sanitize_input($_POST['keterangan'] ?? '');
    // $status_pelunasan_input = sanitize_input($_POST['status_pelunasan_input'] ?? ''); // Ambil status dari input baru
    $keterangan = 'Pembayaran';
    $status_pelunasan_input = 'Lunas'; // Default status

    // === Validasi Input ===
    // Validasi id_transaksi dihapus karena otomatis

    if (empty($id_pesan)) {
        $id_pesan_error = "ID Pesan tidak boleh kosong.";
    }
    // Asumsi id_akun akan diambil dari akun pertama yang tersedia jika tidak diinput manual
    $id_akun = !empty($accounts) ? $accounts[0]['id_akun'] : 'AK001'; // Default ke akun pertama atau 'AK001'

    if (empty($tgl_transaksi)) {
        $tgl_transaksi_error = "Tanggal Transaksi tidak boleh kosong.";
    }

    if (!is_numeric($jumlah_dibayar) || $jumlah_dibayar <= 0) {
        $jumlah_dibayar_error = "Jumlah Dibayar harus angka positif.";
    } else {
        $jumlah_dibayar = (int)$jumlah_dibayar;
    }

    if (empty($metode_pembayaran)) {
        $metode_pembayaran_error = "Metode Pembayaran tidak boleh kosong.";
    }

    // Validasi keterangan dan status dihapus karena sudah default


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
                // Komen ini karena status bisa diubah manual, jadi validasi ini mungkin tidak relevan jika user ingin set 'Belum Lunas'
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
        empty($id_pesan_error) && empty($tgl_transaksi_error) &&
        empty($jumlah_dibayar_error) && empty($metode_pembayaran_error)
    ) {
        // === Generate ID Transaksi Otomatis ===
        $generated_id_transaksi = 'TRX' . strtoupper(substr(uniqid(), 0, 5)); // Contoh: TRX6A8B1

        // Cek apakah ID yang digenerate sudah ada (pencegahan bentrok walau kecil)
        $check_gen_id_sql = "SELECT id_transaksi FROM transaksi WHERE id_transaksi = ?";
        $stmt_check_gen_id = $conn->prepare($check_gen_id_sql);
        if ($stmt_check_gen_id === false) {
            set_flash_message("Error menyiapkan pengecekan ID transaksi: " . $conn->error, "error");
        } else {
            $stmt_check_gen_id->bind_param("s", $generated_id_transaksi);
            $stmt_check_gen_id->execute();
            $stmt_check_gen_id->store_result();
            if ($stmt_check_gen_id->num_rows > 0) {
                $generated_id_transaksi = 'TRX' . strtoupper(substr(uniqid(rand(), true), 0, 5));
                set_flash_message("ID Transaksi otomatis bentrok, mencoba lagi. Mohon submit ulang jika error berlanjut.", "warning");
            }
            $stmt_check_gen_id->close();
        }

        // Mulai transaksi database untuk memastikan konsistensi
        $conn->begin_transaction();
        try {
            // Tentukan status pelunasan untuk transaksi ini (mengambil dari input)
            $status_pelunasan_final = $status_pelunasan_input; // Mengambil dari input form

            // Perbaikan: Hapus 'status_pelunasan' dari query INSERT transaksi
            $sql_transaksi = "INSERT INTO transaksi (id_transaksi, id_pesan, id_akun, id_customer, tgl_transaksi, jumlah_dibayar, metode_pembayaran, keterangan, total_tagihan, sisa_pembayaran) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            if ($stmt_transaksi = $conn->prepare($sql_transaksi)) {
                $stmt_transaksi->bind_param(
                    "sssssisssi", // Disesuaikan, 10 parameter
                    $generated_id_transaksi,
                    $id_pesan,
                    $id_akun,
                    $id_customer_related,
                    $tgl_transaksi,
                    $jumlah_dibayar,
                    $metode_pembayaran,
                    $keterangan,
                    $total_tagihan_pemesanan,
                    $sisa_pembayaran_display
                );

                if (!$stmt_transaksi->execute()) {
                    throw new Exception("Gagal menambahkan transaksi: " . $stmt_transaksi->error);
                }
                $stmt_transaksi->close();
            } else {
                throw new Exception("Error prepared statement (transaksi): " . $conn->error);
            }

            // Update sisa pembayaran di tabel pemesanan (berdasarkan sisa_pembayaran_display)
            // Perbaikan: Ubah 'status_pelunasan' menjadi 'status_pesanan' untuk tabel pemesanan
            $sql_update_pemesanan = "UPDATE pemesanan SET sisa = ?, status_pesanan = ? WHERE id_pesan = ?";
            if ($stmt_update_pemesanan = $conn->prepare($sql_update_pemesanan)) {
                $stmt_update_pemesanan->bind_param("iss", $sisa_pembayaran_display, $status_pelunasan_final, $id_pesan);
                if (!$stmt_update_pemesanan->execute()) {
                    throw new Exception("Gagal memperbarui pemesanan: " . $stmt_update_pemesanan->error);
                }
                $stmt_update_pemesanan->close();
            } else {
                throw new Exception("Error prepared statement (update pemesanan): " . $conn->error);
            }

            // Tambahkan entri ke tabel kas_masuk
            $sql_kas_masuk = "INSERT INTO kas_masuk (id_kas_masuk, id_transaksi, tgl_kas_masuk, jumlah, keterangan) VALUES (?, ?, ?, ?, ?)";

            // Generate ID kas masuk dengan format KMxxxxxx (8 karakter)
            $latest_km_sql = "SELECT MAX(CAST(SUBSTRING(id_kas_masuk, 3) AS UNSIGNED)) as last_num FROM kas_masuk WHERE id_kas_masuk LIKE 'KM%'";
            $latest_km_result = $conn->query($latest_km_sql);
            $last_num = 0;
            if ($latest_km_result && $row = $latest_km_result->fetch_assoc()) {
                $last_num = intval($row['last_num']);
            }
            $new_num = $last_num + 1;
            $id_kas_masuk = sprintf("KM%06d", $new_num); // Format: KM000001, KM000002, dst.

            $keterangan_kas_masuk = "Pembayaran " . $keterangan . " untuk Pesanan " . $id_pesan;

            if ($stmt_kas_masuk = $conn->prepare($sql_kas_masuk)) {
                $stmt_kas_masuk->bind_param("sssis", $id_kas_masuk, $generated_id_transaksi, $tgl_transaksi, $jumlah_dibayar, $keterangan_kas_masuk);
                if (!$stmt_kas_masuk->execute()) {
                    throw new Exception("Gagal menambahkan entri kas masuk: " . $stmt_kas_masuk->error);
                }
                $stmt_kas_masuk->close();
            } else {
                throw new Exception("Error prepared statement (kas_masuk): " . $conn->error);
            }

            // Commit transaksi jika semua berhasil
            $conn->commit();
            set_flash_message("Transaksi dan Kas Masuk berhasil ditambahkan! Status Pelunasan: " . $status_pelunasan_final, "success");
            redirect('index.php');
        } catch (Exception $e) {
            // Rollback transaksi jika ada error
            $conn->rollback();
            set_flash_message("Error saat memproses transaksi: " . $e->getMessage(), "error");
        }
    } else {
        set_flash_message("Silakan perbaiki kesalahan pada formulir.", "error");
    }
}
?>

<div class="bg-white p-8 rounded-lg shadow-xl max-w-4xl mx-auto my-8">
    <h1 class="text-2xl font-bold text-gray-800 mb-6 text-center">TAMBAH TRANSAKSI PEMBAYARAN</h1>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="grid grid-cols-2 gap-8">
            <!-- Kolom Kiri -->
            <div>
                <div class="mb-6">
                    <label for="id_pesan" class="block text-gray-700 text-sm font-bold mb-2">Nama Customer / ID Pesanan:</label>
                    <select id="id_pesan" name="id_pesan" required onchange="updatePemesananInfo()"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
                        <option value="">-- Pilih Customer / Pesanan --</option>
                        <?php foreach ($pemesanan_options as $option) : ?>
                            <option value="<?php echo htmlspecialchars($option['id_pesan']); ?>"
                                data-subtotal="<?php echo htmlspecialchars($option['sub_total']); ?>"
                                data-sisa="<?php echo htmlspecialchars($option['sisa']); ?>"
                                data-customername="<?php echo htmlspecialchars($option['nama_customer']); ?>"
                                data-tglpesan="<?php echo htmlspecialchars($option['tgl_pesan']); ?>"
                                data-tglkirim="<?php echo htmlspecialchars($option['tgl_kirim']); ?>"
                                data-uangmuka="<?php echo htmlspecialchars($option['uang_muka']); ?>"
                                <?php echo ($id_pesan == $option['id_pesan']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($option['nama_customer'] . " - " . $option['id_pesan']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="text-red-500 text-xs italic mt-1 block"><?php echo $id_pesan_error; ?></span>
                </div>

                <div class="mb-6">
                    <label for="jumlah_dibayar" class="block text-gray-700 text-sm font-bold mb-2">Jumlah Dibayar:</label>
                    <input type="number" id="jumlah_dibayar" name="jumlah_dibayar"
                        value="<?php echo htmlspecialchars($jumlah_dibayar); ?>" required min="1"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500"
                        onchange="updateSisaSetelahPembayaran()">
                    <span class="text-red-500 text-xs italic mt-1 block"><?php echo $jumlah_dibayar_error; ?></span>
                </div>

                <div class="mb-6">
                    <label for="metode_pembayaran" class="block text-gray-700 text-sm font-bold mb-2">Metode Pembayaran:</label>
                    <select id="metode_pembayaran" name="metode_pembayaran" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
                        <option value="">-- Pilih Metode --</option>
                        <option value="Cash" <?php echo ($metode_pembayaran == 'Cash') ? 'selected' : ''; ?>>Cash</option>
                        <option value="Transfer Bank" <?php echo ($metode_pembayaran == 'Transfer Bank') ? 'selected' : ''; ?>>Transfer Bank</option>
                        <option value="QRIS" <?php echo ($metode_pembayaran == 'QRIS') ? 'selected' : ''; ?>>QRIS</option>
                        <option value="Lainnya" <?php echo ($metode_pembayaran == 'Lainnya') ? 'selected' : ''; ?>>Lainnya</option>
                    </select>
                    <span class="text-red-500 text-xs italic mt-1 block"><?php echo $metode_pembayaran_error; ?></span>
                </div>

                <div class="mb-6">
                    <label for="keterangan" class="block text-gray-700 text-sm font-bold mb-2">Keterangan:</label>
                    <input type="text" id="keterangan" name="keterangan" value="<?php echo htmlspecialchars($keterangan); ?>"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
                </div>
            </div>

            <!-- Kolom Kanan -->
            <div>
                <div class="mb-6">
                    <label for="tgl_transaksi" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Transaksi:</label>
                    <input type="date" id="tgl_transaksi" name="tgl_transaksi"
                        value="<?php echo htmlspecialchars($tgl_transaksi); ?>" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
                    <span class="text-red-500 text-xs italic mt-1 block"><?php echo $tgl_transaksi_error; ?></span>
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Total Tagihan:</label>
                    <input type="text" id="total_tagihan_display"
                        value="<?php echo format_rupiah($total_tagihan_display); ?>" disabled
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100">
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Sisa Pembayaran:</label>
                    <input type="text" id="sisa_pembayaran_display" disabled
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100">
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Status Pembayaran:</label>
                    <input type="text" id="status_pembayaran_display" value="<?php echo $status_pelunasan_input; ?>" disabled
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100">
                </div>
            </div>
        </div>

        <div class="flex items-center justify-center gap-4 mt-8">
            <button type="submit" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-6 rounded focus:outline-none focus:shadow-outline">
                SIMPAN
            </button>
            <button type="button" onclick="printNota()"
                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded focus:outline-none focus:shadow-outline">
                CETAK BUKTI PEMBAYARAN
            </button>
        </div>
    </form>
</div>

<style>
    @media print {
        body * {
            visibility: hidden !important;
        }

        #nota-cetak,
        #nota-cetak * {
            visibility: visible !important;
        }

        #nota-cetak {
            position: absolute;
            left: 0;
            top: 0;
            width: 100vw;
            background: white;
        }
    }

    .nota-hr {
        border: none;
        border-top: 3px dashed #000;
        margin: 12px 0;
    }

    .nota-hr2 {
        border: none;
        border-top: 3px dashed #000;
        border-style: dashed;
        border-width: 3px 0 0 0;
        border-top-style: dashed;
        border-top-color: #000;
        margin: 12px 0;
    }

    .nota-hr3 {
        border: none;
        border-top: 3px dashed #000;
        border-style: dashed;
        border-width: 3px 0 0 0;
        border-top-style: dashed;
        border-top-color: #000;
        margin: 12px 0;
        border-top: 3px dash-dot-dot #000;
    }
</style>

<div id="nota-cetak" class="max-w-lg mx-auto my-8 p-8 bg-white border border-black text-black text-base" style="font-family: 'Times New Roman', Times, serif; display:none;">
    <div style="font-size:1.2em; font-weight:bold;">Ampyang Cap Garuda</div>
    <br>
    <table style="width:100%;">
        <tr>
            <td style="width:30%;">Tanggal</td>
            <td style="width:2%;">:</td>
            <td id="nota-tanggal"></td>
        </tr>
        <tr>
            <td>No Transaksi</td>
            <td>:</td>
            <td id="nota-no-transaksi"></td>
        </tr>
    </table>
    <hr class="nota-hr">
    <table style="width:100%;">
        <tr>
            <td style="width:40%;">Nama Customer</td>
            <td style="width:2%;">:</td>
            <td style="width:28%;" id="nota-nama-customer"></td>
            <td style="width:20%;">Tanggal Pemesanan</td>
            <td style="width:2%;">:</td>
            <td id="nota-tgl-pemesanan"></td>
        </tr>
        <tr>
            <td>ID Pemesanan</td>
            <td>:</td>
            <td id="nota-id-pemesanan"></td>
            <td>Tanggal Kirim</td>
            <td>:</td>
            <td id="nota-tgl-kirim"></td>
        </tr>
    </table>
    <hr class="nota-hr2">
    <table style="width:100%;">
        <tr>
            <td style="width:40%;">Total Tagihan</td>
            <td style="width:2%;">:</td>
            <td id="nota-total-tagihan"></td>
        </tr>
        <tr>
            <td>Uang Muka</td>
            <td>:</td>
            <td id="nota-uang-muka">-</td>
        </tr>
        <tr>
            <td>Sisa Pembayaran</td>
            <td>:</td>
            <td id="nota-sisa-pembayaran"></td>
        </tr>
        <tr>
            <td>Jumlah Dibayar</td>
            <td>:</td>
            <td id="nota-jumlah-dibayar"></td>
        </tr>
        <tr>
            <td><b>Status</b></td>
            <td>:</td>
            <td><b id="nota-status"></b></td>
        </tr>
    </table>
</div>

<script>
    function printNota() {
        // Ambil data dari form
        const selectElement = document.getElementById('id_pesan');
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        const namaCustomer = selectedOption.dataset.customername || '-';
        const idPemesanan = selectElement.value || '-';
        const tglTransaksi = document.getElementById('tgl_transaksi').value || '-';
        const jumlahDibayar = document.getElementById('jumlah_dibayar').value || 0;
        const totalTagihan = document.getElementById('total_tagihan_display').value || 0;
        const sisaPembayaran = document.getElementById('sisa_pembayaran_display').value || 0;
        const statusPembayaran = document.getElementById('status_pembayaran_display').value || '-';
        // Data tambahan dari dataset
        const tglPemesanan = selectedOption.dataset.tglpesan || '-';
        const tglKirim = selectedOption.dataset.tglkirim || '-';
        const uangMuka = selectedOption.dataset.uangmuka || 0;
        // No Transaksi: generate random jika belum ada
        let noTransaksi = document.getElementById('nota-no-transaksi').textContent;
        if (!noTransaksi || noTransaksi === '-') {
            noTransaksi = 'TRX' + Math.random().toString(36).substr(2, 5).toUpperCase();
        }

        // Isi nota
        document.getElementById('nota-tanggal').textContent = tglTransaksi;
        document.getElementById('nota-no-transaksi').textContent = noTransaksi;
        document.getElementById('nota-nama-customer').textContent = namaCustomer;
        document.getElementById('nota-id-pemesanan').textContent = idPemesanan;
        document.getElementById('nota-tgl-pemesanan').textContent = tglPemesanan;
        document.getElementById('nota-tgl-kirim').textContent = tglKirim;
        document.getElementById('nota-total-tagihan').textContent = totalTagihan;
        document.getElementById('nota-sisa-pembayaran').textContent = document.getElementById('sisa_pembayaran_display').value;
        document.getElementById('nota-jumlah-dibayar').textContent = formatRupiah(jumlahDibayar);
        document.getElementById('nota-status').textContent = statusPembayaran;
        // Tampilkan uang muka
        document.getElementById('nota-uang-muka').textContent = formatRupiah(uangMuka);

        // Tampilkan nota dan print
        document.getElementById('nota-cetak').style.display = 'block';
        window.print();
        setTimeout(function() {
            document.getElementById('nota-cetak').style.display = 'none';
        }, 500);
    }
</script>

<script>
    function updatePemesananInfo() {
        const selectElement = document.getElementById('id_pesan');
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        const subTotal = parseFloat(selectedOption.dataset.subtotal || 0);
        const sisaAwal = parseFloat(selectedOption.dataset.sisa || 0);

        document.getElementById('total_tagihan_display').value = formatRupiah(subTotal);
        document.getElementById('jumlah_dibayar').max = sisaAwal;

        // Set jumlah dibayar ke sisa pembayaran secara default
        document.getElementById('jumlah_dibayar').value = sisaAwal;

        updateSisaSetelahPembayaran();
    }

    function updateSisaSetelahPembayaran() {
        const selectElement = document.getElementById('id_pesan');
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        const sisaAwal = parseFloat(selectedOption.dataset.sisa || 0);
        const jumlahDibayar = parseFloat(document.getElementById('jumlah_dibayar').value || 0);

        const sisaSetelahIni = Math.max(0, sisaAwal - jumlahDibayar);
        document.getElementById('sisa_pembayaran_display').value = formatRupiah(sisaSetelahIni);

        // Update status pembayaran
        const statusPembayaran = sisaSetelahIni === 0 ? 'Lunas' : 'Belum Lunas';
        document.getElementById('status_pembayaran_display').value = statusPembayaran;
    }

    function formatRupiah(angka) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(angka);
    }

    // Inisialisasi saat halaman dimuat
    document.addEventListener('DOMContentLoaded', function() {
        // Set tanggal transaksi ke hari ini secara default
        if (!document.getElementById('tgl_transaksi').value) {
            document.getElementById('tgl_transaksi').valueAsDate = new Date();
        }

        // Update info pemesanan jika sudah ada yang dipilih
        if (document.getElementById('id_pesan').value) {
            updatePemesananInfo();

            // Set jumlah dibayar ke sisa pembayaran secara default
            const selectedOption = document.getElementById('id_pesan').options[document.getElementById('id_pesan').selectedIndex];
            const sisaAwal = parseFloat(selectedOption.dataset.sisa || 0);
            document.getElementById('jumlah_dibayar').value = sisaAwal;
            updateSisaSetelahPembayaran();
        }
    });
</script>