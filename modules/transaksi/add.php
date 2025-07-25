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

// Ambil daftar pemesanan untuk dropdown (termasuk yang sudah lunas)
$pemesanan_options = [];
// --- START MODIFIKASI: Sesuaikan query SQL untuk dropdown dengan struktur tabel pemesanan yang baru ---
$pemesanan_sql = "SELECT p.id_pesan, p.total_tagihan_keseluruhan AS sub_total, p.sisa, p.tgl_pesan, p.tgl_kirim, p.uang_muka, p.total_quantity AS quantity, p.keterangan, c.nama_customer,
                  t.id_akun, a.nama_akun
                  FROM pemesanan p
                  JOIN customer c ON p.id_customer = c.id_customer
                  LEFT JOIN transaksi t ON p.id_pesan = t.id_pesan
                  LEFT JOIN akun a ON t.id_akun = a.id_akun
                  ORDER BY p.tgl_pesan DESC";
// --- END MODIFIKASI ---
$pemesanan_result = $conn->query($pemesanan_sql);

// --- START MODIFIKASI: Tambahkan penanganan error untuk query SQL dropdown ---
if ($pemesanan_result === false) {
    set_flash_message("Error saat mengambil daftar pesanan: " . $conn->error . ". Pastikan struktur database 'pemesanan' sudah diperbarui.", "error");
    $pemesanan_options = [];
} else if ($pemesanan_result->num_rows > 0) {
    while ($row = $pemesanan_result->fetch_assoc()) {
        $pemesanan_options[] = $row;
    }
}
// --- END MODIFIKASI ---

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
    $id_pesan = sanitize_input($_POST['id_pesan'] ?? '');
    $tgl_transaksi = sanitize_input($_POST['tgl_transaksi'] ?? '');
    $jumlah_dibayar = sanitize_input($_POST['jumlah_dibayar'] ?? 0);
    $metode_pembayaran = 'Tunai';
    $keterangan = sanitize_input($_POST['keterangan'] ?? '');
    $status_pelunasan_input = 'Lunas';

    if (empty($id_pesan)) {
        $id_pesan_error = "ID Pesan tidak boleh kosong.";
    }

    $id_akun = '';
    $quantity_pemesanan_db = 0; // Variabel untuk menyimpan quantity dari pemesanan
    $sub_total_pemesanan_db = 0; // Variabel untuk menyimpan sub_total dari pemesanan

    foreach ($pemesanan_options as $option) {
        if ($option['id_pesan'] == $id_pesan) {
            if (!empty($option['id_akun'])) {
                $id_akun = $option['id_akun'];
            }
            $quantity_pemesanan_db = $option['quantity'] ?? 0;
            $sub_total_pemesanan_db = $option['sub_total'] ?? 0; // Ambil sub_total (alias total_tagihan_keseluruhan)
            break;
        }
    }

    if (empty($id_akun)) {
        $id_akun = '4001';
    }

    if (empty($tgl_transaksi)) {
        $tgl_transaksi_error = "Tanggal Transaksi tidak boleh kosong.";
    }

    if (!is_numeric($jumlah_dibayar) || $jumlah_dibayar <= 0) {
        $jumlah_dibayar_error = "Jumlah Dibayar harus angka positif.";
    } else {
        $jumlah_dibayar = (int)$jumlah_dibayar;
    }

    $current_sisa_pemesanan = 0;
    $total_tagihan_pemesanan = 0;
    $id_customer_related = '';
    $tgl_kirim_related = '';
    if (!empty($id_pesan)) {
        // --- START MODIFIKASI: Query detail pemesanan hanya mengambil kolom yang ada di tabel 'pemesanan' ---
        $pemesanan_detail_sql = "SELECT sisa, total_tagihan_keseluruhan, id_customer, tgl_kirim, total_quantity, keterangan FROM pemesanan WHERE id_pesan = ?"; // Menggunakan nama kolom baru
        // --- END MODIFIKASI ---
        if ($stmt_pemesanan = $conn->prepare($pemesanan_detail_sql)) {
            $stmt_pemesanan->bind_param("s", $id_pesan);
            $stmt_pemesanan->execute();
            // --- START MODIFIKASI: bind_result menggunakan total_tagihan_keseluruhan dan total_quantity ---
            $stmt_pemesanan->bind_result($current_sisa_pemesanan, $total_tagihan_pemesanan, $id_customer_related, $tgl_kirim_related, $quantity_pemesanan_db, $keterangan_pemesanan_db); // Menambahkan $keterangan_pemesanan_db
            // --- END MODIFIKASI ---
            $stmt_pemesanan->fetch();
            $stmt_pemesanan->close();

            $total_tagihan_display = $total_tagihan_pemesanan;
            $keterangan = $keterangan_pemesanan_db; // Update keterangan dari pemesanan

            if ($current_sisa_pemesanan == 0) {
                $sisa_pembayaran_display = 0;
                $status_pelunasan_input = 'Lunas';
            } else {
                $sisa_pembayaran_display = $current_sisa_pemesanan - $jumlah_dibayar;
            }
        } else {
            set_flash_message("Error saat mengambil detail pemesanan: " . $conn->error, "error");
            $id_pesan_error = "Gagal mengambil detail pemesanan.";
        }
    }

    if (
        empty($id_pesan_error) && empty($tgl_transaksi_error) &&
        empty($jumlah_dibayar_error)
    ) {
        $generated_id_transaksi = 'TRX' . strtoupper(substr(uniqid(), 0, 5));

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

        $conn->begin_transaction();
        try {
            $status_pelunasan_final = $sisa_pembayaran_display == 0 ? 'Lunas' : 'Belum Lunas';

            $sql_transaksi = "INSERT INTO transaksi (id_transaksi, id_pesan, id_akun, id_customer, tgl_transaksi, jumlah_dibayar, metode_pembayaran, keterangan, total_tagihan, sisa_pembayaran) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            if ($stmt_transaksi = $conn->prepare($sql_transaksi)) {
                $stmt_transaksi->bind_param(
                    "sssssisssi",
                    $generated_id_transaksi,
                    $id_pesan,
                    $id_akun,
                    $id_customer_related,
                    $tgl_transaksi,
                    $jumlah_dibayar,
                    $metode_pembayaran,
                    $keterangan, // Menggunakan keterangan dari pemesanan/form
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

            if ($current_sisa_pemesanan != $sisa_pembayaran_display || $status_pelunasan_final == 'Lunas') {
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
            }

            $sql_kas_masuk = "INSERT INTO kas_masuk (id_kas_masuk, id_transaksi, tgl_kas_masuk, jumlah, keterangan, harga, kuantitas) VALUES (?, ?, ?, ?, ?, ?, ?)";

            $latest_km_sql = "SELECT MAX(CAST(SUBSTRING(id_kas_masuk, 3) AS UNSIGNED)) as last_num FROM kas_masuk WHERE id_kas_masuk LIKE 'KM%'";
            $latest_km_result = $conn->query($latest_km_sql);
            $last_num = 0;
            if ($latest_km_result && $row = $latest_km_result->fetch_assoc()) {
                $last_num = intval($row['last_num']);
            }
            $new_num = $last_num + 1;
            $id_kas_masuk = sprintf("KM%06d", $new_num);

            $keterangan_kas_masuk = $keterangan;

            $harga_satuan_item = 0; // Inisialisasi
            if ($quantity_pemesanan_db > 0) { // Gunakan total_quantity dari pemesanan
                $harga_satuan_item = $total_tagihan_pemesanan / $quantity_pemesanan_db;
            }
            if ($harga_satuan_item == 0) {
                $harga_satuan_item = 12000;
            }

            $kuantitas = $quantity_pemesanan_db > 0 ? $quantity_pemesanan_db : ceil($jumlah_dibayar / $harga_satuan_item);

            if ($stmt_kas_masuk = $conn->prepare($sql_kas_masuk)) {
                $stmt_kas_masuk->bind_param("sssisii", $id_kas_masuk, $generated_id_transaksi, $tgl_transaksi, $jumlah_dibayar, $keterangan_kas_masuk, $harga_satuan_item, $kuantitas);
                if (!$stmt_kas_masuk->execute()) {
                    throw new Exception("Gagal menambahkan entri kas masuk: " . $stmt_kas_masuk->error);
                }
                $stmt_kas_masuk->close();
            } else {
                throw new Exception("Error prepared statement (kas_masuk): " . $conn->error);
            }

            $conn->commit();
            set_flash_message("Transaksi dan Kas Masuk berhasil ditambahkan! Status Pelunasan: " . $status_pelunasan_final, "success");
            redirect('index.php');
        } catch (Exception $e) {
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
                                data-idakun="<?php echo htmlspecialchars($option['id_akun'] ?? ''); ?>"
                                data-namaakun="<?php echo htmlspecialchars($option['nama_akun'] ?? ''); ?>"
                                data-quantity="<?php echo htmlspecialchars($option['quantity'] ?? 0); ?>"
                                data-keterangan="<?php echo htmlspecialchars($option['keterangan'] ?? ''); ?>"
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
                    <input type="hidden" id="metode_pembayaran" name="metode_pembayaran" value="Tunai">
                    <input type="text" value="Tunai" disabled
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100 cursor-not-allowed">
                </div>

                <div class="mb-6">
                    <label for="keterangan" class="block text-gray-700 text-sm font-bold mb-2">Keterangan:</label>
                    <input type="text" id="keterangan" name="keterangan"
                        value="<?php echo htmlspecialchars($keterangan); ?>"
                        required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">

                </div>
            </div>

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

                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Akun Pendapatan:</label>
                    <input type="text" id="akun_display" disabled
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

    <table style="width:100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 10px;">
        <thead>
            <tr>
                <th style="text-align:left; border-bottom: 1px dashed black; padding-bottom: 5px;">Barang</th>
                <th style="text-align:right; border-bottom: 1px dashed black; padding-bottom: 5px;">Qty</th>
                <th style="text-align:right; border-bottom: 1px dashed black; padding-bottom: 5px;">Harga Satuan</th>
                <th style="text-align:right; border-bottom: 1px dashed black; padding-bottom: 5px;">Subtotal Barang</th>
            </tr>
        </thead>
        <tbody id="nota-detail-barang">
        </tbody>
    </table>
    <hr class="nota-hr3">

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
            <td colspan="2" style="padding-left: 20px; font-style: italic;">(Jumlah yang Diterima)</td>
            <td id="nota-jumlah-dibayar-konfirmasi"></td>
        </tr>
        <tr>
            <td>Keterangan</td>
            <td>:</td>
            <td id="nota-keterangan"></td>
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
        const totalTagihanForm = document.getElementById('total_tagihan_display').value || 0;
        const sisaPembayaran = document.getElementById('sisa_pembayaran_display').value || 0;
        const statusPembayaran = document.getElementById('status_pembayaran_display').value || '-';
        const tglPemesanan = selectedOption.dataset.tglpesan || '-';
        const tglKirim = selectedOption.dataset.tglkirim || '-';
        const uangMuka = selectedOption.dataset.uangmuka || 0;
        const keterangan = document.getElementById('keterangan').value || '-';

        // --- START MODIFIKASI: Ambil data untuk perhitungan harga satuan dari dataset ---
        // Kita perlu mendapatkan nama barang dan harga satuan dari detail pesanan yang terkait.
        // Karena di halaman add.php ini tidak ada detail_pemesanan, kita akan menggunakan
        // pendekatan dari pemesanan/index.php yang menunjukkan total_quantity
        // dan menghitung harga satuan rata-rata atau menggunakan default.
        // Jika ingin menampilkan detail multi-item di nota transaksi/add.php,
        // maka data detail_pemesanan harus diambil di PHP dan dilewatkan ke JS.
        // Untuk saat ini, kita akan menggunakan pendekatan yang paling aman tanpa mengubah lebih lanjut.
        // Nama barang akan di-hardcode "Ampyang" dan harga satuan akan dihitung dari total_tagihan_pemesanan / total_quantity.
        const itemQuantity = parseFloat(selectedOption.dataset.quantity || 0); // Ini adalah total_quantity dari pemesanan
        const totalTagihanPemesananUntukNota = parseFloat(selectedOption.dataset.subtotal || 0); // Ini adalah total_tagihan_keseluruhan dari pemesanan

        let itemHargaSatuan = 0;
        if (itemQuantity > 0) {
            itemHargaSatuan = totalTagihanPemesananUntukNota / itemQuantity;
        }
        if (itemHargaSatuan === 0) {
            itemHargaSatuan = 12000; // Fallback jika perhitungan gagal atau total_quantity 0
        }
        const namaBarang = "Ampyang"; // Hardcode nama barang
        // --- END MODIFIKASI ---

        const itemSubtotal = itemQuantity * itemHargaSatuan; // Ini akan menjadi (total_quantity * harga_satuan_rata2)

        let noTransaksi = document.getElementById('nota-no-transaksi').textContent;
        if (!noTransaksi || noTransaksi.startsWith('TRX') && noTransaksi.endsWith('-TUNAI')) {
            noTransaksi = 'TRX' + Math.random().toString(36).substr(2, 5).toUpperCase();
        }

        // Isi nota
        document.getElementById('nota-tanggal').textContent = tglTransaksi;
        document.getElementById('nota-no-transaksi').textContent = noTransaksi;
        document.getElementById('nota-nama-customer').textContent = namaCustomer;
        document.getElementById('nota-id-pemesanan').textContent = idPemesanan;
        document.getElementById('nota-tgl-pemesanan').textContent = tglPemesanan;
        document.getElementById('nota-tgl-kirim').textContent = tglKirim;

        const notaDetailBarang = document.getElementById('nota-detail-barang');
        notaDetailBarang.innerHTML = `
            <tr>
                <td style="text-align:left;">${namaBarang}</td>
                <td style="text-align:right;">${itemQuantity}</td>
                <td style="text-align:right;">${formatRupiah(itemHargaSatuan)}</td>
                <td style="text-align:right;">${formatRupiah(itemSubtotal)}</td>
            </tr>
        `;

        document.getElementById('nota-total-tagihan').textContent = totalTagihanForm;
        document.getElementById('nota-sisa-pembayaran').textContent = document.getElementById('sisa_pembayaran_display').value;
        document.getElementById('nota-jumlah-dibayar').textContent = formatRupiah(jumlahDibayar);
        document.getElementById('nota-jumlah-dibayar-konfirmasi').textContent = formatRupiah(jumlahDibayar);
        document.getElementById('nota-status').textContent = statusPembayaran;
        document.getElementById('nota-uang_muka').textContent = formatRupiah(uangMuka);
        document.getElementById('nota-keterangan').textContent = keterangan;

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
        const subTotal = parseFloat(selectedOption.dataset.subtotal || 0); // Ini adalah total_tagihan_keseluruhan
        const sisaAwal = parseFloat(selectedOption.dataset.sisa || 0);
        const idAkun = selectedOption.dataset.idakun || '';
        const namaAkun = selectedOption.dataset.namaakun || '';
        const keteranganPemesanan = selectedOption.dataset.keterangan || ''; // Ambil keterangan dari pesanan

        const accountInfo = idAkun && namaAkun ? `${idAkun} - ${namaAkun}` : 'Akun Pendapatan Default (4001)';
        document.getElementById('akun_display').value = accountInfo;

        document.getElementById('total_tagihan_display').value = formatRupiah(subTotal);
        document.getElementById('keterangan').value = keteranganPemesanan; // Set keterangan form dengan keterangan pesanan

        if (sisaAwal > 0) {
            document.getElementById('jumlah_dibayar').max = sisaAwal;
            document.getElementById('jumlah_dibayar').value = sisaAwal;
        } else {
            document.getElementById('jumlah_dibayar').value = subTotal;
        }

        updateSisaSetelahPembayaran();
    }

    function updateSisaSetelahPembayaran() {
        const selectElement = document.getElementById('id_pesan');
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        const sisaAwal = parseFloat(selectedOption.dataset.sisa || 0);
        const jumlahDibayar = parseFloat(document.getElementById('jumlah_dibayar').value || 0);

        if (sisaAwal === 0) {
            document.getElementById('sisa_pembayaran_display').value = formatRupiah(0);
            document.getElementById('status_pembayaran_display').value = 'Lunas';
            return;
        }

        const sisaSetelahIni = Math.max(0, sisaAwal - jumlahDibayar);
        document.getElementById('sisa_pembayaran_display').value = formatRupiah(sisaSetelahIni);

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

    document.addEventListener('DOMContentLoaded', function() {
        if (!document.getElementById('tgl_transaksi').value) {
            document.getElementById('tgl_transaksi').valueAsDate = new Date();
        }

        if (document.getElementById('id_pesan').value) {
            updatePemesananInfo();

            const selectedOption = document.getElementById('id_pesan').options[document.getElementById('id_pesan').selectedIndex];
            const sisaAwal = parseFloat(selectedOption.dataset.sisa || 0);
            document.getElementById('jumlah_dibayar').value = sisaAwal;
            updateSisaSetelahPembayaran();
        }
    });
</script>