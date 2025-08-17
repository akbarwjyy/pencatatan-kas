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
// Perbaikan: Ganti 'tr.id_akun' menjadi 't.id_akun' di SELECT
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

// --- START MODIFIKASI: Ambil semua detail_pemesanan untuk JavaScript ---
$all_detail_pemesanan = [];
$detail_pemesanan_sql = "SELECT dp.*, b.nama_barang FROM detail_pemesanan dp JOIN barang b ON dp.id_barang = b.id_barang ORDER BY dp.id_pesan, dp.id_detail_pesan ASC";
$detail_result = $conn->query($detail_pemesanan_sql);

if ($detail_result === false) {
    set_flash_message("Error saat mengambil detail pesanan untuk nota: " . $conn->error . ". Pastikan tabel 'detail_pemesanan' dan 'barang' sudah benar.", "error");
} else if ($detail_result->num_rows > 0) {
    while ($row = $detail_result->fetch_assoc()) {
        $all_detail_pemesanan[$row['id_pesan']][] = $row; // Kelompokkan berdasarkan id_pesan
    }
}
// --- END MODIFIKASI ---


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_pesan = sanitize_input($_POST['id_pesan'] ?? '');
    $tgl_transaksi = sanitize_input($_POST['tgl_transaksi'] ?? '');
    $jumlah_dibayar = sanitize_input($_POST['jumlah_dibayar'] ?? ''); // Use sanitize_input to prevent XSS
    $metode_pembayaran = 'Tunai';
    $keterangan = sanitize_input($_POST['keterangan'] ?? '');
    $status_pelunasan_input = 'Lunas';

    // Validate if jumlah_dibayar is numeric and positive
    if (!is_numeric($jumlah_dibayar) || $jumlah_dibayar <= 0) {
        $jumlah_dibayar_error = "Jumlah Dibayar harus angka positif.";
    } else {
        $jumlah_dibayar = (float)$jumlah_dibayar; // Cast to float for calculations
    }

    if (empty($id_pesan)) {
        $id_pesan_error = "ID Pesan tidak boleh kosong.";
    }

    $id_akun = '';
    $quantity_pemesanan_db = 0; // Variabel untuk menyimpan total_quantity dari pemesanan
    $sub_total_pemesanan_db = 0; // Variabel untuk menyimpan total_tagihan_keseluruhan dari pemesanan

    foreach ($pemesanan_options as $option) {
        if ($option['id_pesan'] == $id_pesan) {
            if (!empty($option['id_akun'])) {
                $id_akun = $option['id_akun'];
            }
            $quantity_pemesanan_db = $option['quantity'] ?? 0;
            $sub_total_pemesanan_db = $option['sub_total'] ?? 0;
            break;
        }
    }

    if (empty($id_akun)) {
        $id_akun = '4001';
    }

    if (empty($tgl_transaksi)) {
        $tgl_transaksi_error = "Tanggal Transaksi tidak boleh kosong.";
    }

    $current_sisa_pemesanan = 0;
    $total_tagihan_pemesanan = 0;
    $id_customer_related = '';
    $tgl_kirim_related = '';
    if (!empty($id_pesan)) {
        $pemesanan_detail_sql = "SELECT sisa, total_tagihan_keseluruhan, id_customer, tgl_kirim, total_quantity, keterangan FROM pemesanan WHERE id_pesan = ?";
        if ($stmt_pemesanan = $conn->prepare($pemesanan_detail_sql)) {
            $stmt_pemesanan->bind_param("s", $id_pesan);
            $stmt_pemesanan->execute();
            $stmt_pemesanan->bind_result($current_sisa_pemesanan, $total_tagihan_pemesanan, $id_customer_related, $tgl_kirim_related, $quantity_pemesanan_db, $keterangan_pemesanan_db);
            $stmt_pemesanan->fetch();
            $stmt_pemesanan->close();

            $total_tagihan_display = $total_tagihan_pemesanan;
            // Hanya gunakan keterangan dari pemesanan jika user belum mengisi keterangan
            if (empty($keterangan)) {
                $keterangan = $keterangan_pemesanan_db; // Update keterangan dari pemesanan hanya jika form kosong
            }

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

            $sql_transaksi = "INSERT INTO transaksi (id_transaksi, id_pesan, id_akun, id_customer, tgl_transaksi, jumlah_dibayar, keterangan, total_tagihan, sisa_pembayaran) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            if ($stmt_transaksi = $conn->prepare($sql_transaksi)) {
                $stmt_transaksi->bind_param(
                    "sssssissi",
                    $generated_id_transaksi,
                    $id_pesan,
                    $id_akun,
                    $id_customer_related,
                    $tgl_transaksi,
                    $jumlah_dibayar,
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

            // Perbaikan: Hapus kolom kuantitas yang sudah tidak ada di tabel kas_masuk
            $sql_kas_masuk = "INSERT INTO kas_masuk (id_kas_masuk, id_transaksi, tgl_kas_masuk, jumlah, keterangan, harga) VALUES (?, ?, ?, ?, ?, ?)";

            $latest_km_sql = "SELECT MAX(CAST(SUBSTRING(id_kas_masuk, 3) AS UNSIGNED)) as last_num FROM kas_masuk WHERE id_kas_masuk LIKE 'KM%'";
            $latest_km_result = $conn->query($latest_km_sql);
            $last_num = 0;
            if ($latest_km_result && $row = $latest_km_result->fetch_assoc()) {
                $last_num = intval($row['last_num']);
            }
            $new_num = $last_num + 1;
            $id_kas_masuk = sprintf("KM%06d", $new_num);

            $keterangan_kas_masuk = $keterangan;

            // Hitung harga satuan dari detail_pemesanan untuk konsistensi
            $harga_satuan_item = 0;
            if ($quantity_pemesanan_db > 0 && $sub_total_pemesanan_db > 0) {
                $harga_satuan_item = $sub_total_pemesanan_db / $quantity_pemesanan_db;
            }
            if ($harga_satuan_item <= 0) {
                $harga_satuan_item = 12000; // Default harga satuan
            }

            if ($stmt_kas_masuk = $conn->prepare($sql_kas_masuk)) {
                $stmt_kas_masuk->bind_param("sssdsd", $id_kas_masuk, $generated_id_transaksi, $tgl_transaksi, $jumlah_dibayar, $keterangan_kas_masuk, $harga_satuan_item);
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
                <!-- 
                <div class="mb-6">
                    <label for="metode_pembayaran" class="block text-gray-700 text-sm font-bold mb-2">Metode Pembayaran:</label>
                    <input type="hidden" id="metode_pembayaran" name="metode_pembayaran" value="Tunai">
                    <input type="text" value="Tunai" disabled
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100 cursor-not-allowed">
                </div> -->

                <div class="mb-6">
                    <label for="keterangan" class="block text-gray-700 text-sm font-bold mb-2">Keterangan:</label>
                    <input type="text" id="keterangan" name="keterangan"
                        value="<?php echo htmlspecialchars($keterangan); ?>"
                        required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
                </div>

                <div class="mb-6">
                    <label for="tgl_transaksi" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Transaksi:</label>
                    <input type="date" id="tgl_transaksi" name="tgl_transaksi"
                        value="<?php echo htmlspecialchars($tgl_transaksi); ?>" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
                    <span class="text-red-500 text-xs italic mt-1 block"><?php echo $tgl_transaksi_error; ?></span>
                </div>
            </div>
            <div>

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

        <div class="flex items-center justify-center gap-4 mt-8"> <!-- justify start, end geser kanan, kiri-->
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
    <div style="text-align: center; font-size:1.2em; font-weight:bold;">Ampyang Cap Garuda</div>
    <div style="text-align: center; font-size: 0.9em;">Jl. Ngelosari, Srimulyo, Piyungan, Bantul, Yogyakarta</div>
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
            <td style="width:40%;">Total Pembelian</td>
            <td style="width:2%;">:</td>
            <td id="nota-total-tagihan"></td>
        </tr>
        <tr>
            <td>Dibayar (DP)</td>
            <td>:</td>
            <td id="nota-uang-muka">-</td>
        </tr>
        <tr>
            <td>Pelunasan</td>
            <td>:</td>
            <td id="nota-jumlah-dibayar"></td>
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
    <div style="text-align: center; font-size: 0.9em; margin-top: 10px;">
        Terima kasih telah berbelanja!<br>
        Ampyang Cap Garuda - Manisnya Tradisi Nusantara
    </div>
</div>

<script>
    // --- START MODIFIKASI: Deklarasi variabel global allPemesananDetails ---
    const allPemesananDetails = <?php echo json_encode($all_detail_pemesanan); ?>;
    // --- END MODIFIKASI ---

    function printNota() {
        // Ambil data dari form
        const selectElement = document.getElementById('id_pesan');
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        const namaCustomer = selectedOption.dataset.customername || '-';
        // --- START MODIFIKASI: Tambahkan .trim() pada idPemesanan ---
        const idPemesanan = selectElement.value.trim() || '-';
        // --- END MODIFIKASI ---
        const tglTransaksi = document.getElementById('tgl_transaksi').value || '-';
        const jumlahDibayar = document.getElementById('jumlah_dibayar').value || 0;
        const totalTagihanForm = document.getElementById('total_tagihan_display').value || 0;
        const sisaPembayaran = document.getElementById('sisa_pembayaran_display').value || 0;
        const statusPembayaran = document.getElementById('status_pembayaran_display').value || '-';
        const tglPemesanan = selectedOption.dataset.tglpesan || '-';
        const tglKirim = selectedOption.dataset.tglkirim || '-';
        const uangMuka = selectedOption.dataset.uangmuka || 0;
        const keterangan = document.getElementById('keterangan').value || '-';

        // --- START MODIFIKASI: Ambil detail item dari allPemesananDetails ---
        const itemsForNota = allPemesananDetails[idPemesanan] || []; // Ambil array detail item
        // --- END MODIFIKASI ---

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
        notaDetailBarang.innerHTML = ''; // Kosongkan baris item sebelumnya

        // --- START MODIFIKASI: Loop untuk mengisi detail barang di nota ---
        if (itemsForNota.length > 0) {
            itemsForNota.forEach(item => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td style="text-align:left;">${item.nama_barang}</td>
                    <td style="text-align:right;">${item.quantity_item}</td>
                    <td style="text-align:right;">${formatRupiah(item.harga_satuan_item)}</td>
                    <td style="text-align:right;">${formatRupiah(item.sub_total_item)}</td>
                `;
                notaDetailBarang.appendChild(row);
            });
        } else {
            const row = document.createElement('tr');
            row.innerHTML = `<td colspan="4" style="text-align:center; font-style:italic;">Tidak ada item untuk pesanan ini.</td>`;
            notaDetailBarang.appendChild(row);
        }
        // --- END MODIFIKASI ---

        document.getElementById('nota-total-tagihan').textContent = totalTagihanForm;

        document.getElementById('nota-jumlah-dibayar').textContent = formatRupiah(jumlahDibayar);

        document.getElementById('nota-status').textContent = statusPembayaran;
        document.getElementById('nota-uang-muka').textContent = formatRupiah(uangMuka);
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

        // Hanya set keterangan dari pesanan jika field keterangan masih kosong
        const keteranganField = document.getElementById('keterangan');
        if (!keteranganField.value.trim()) {
            keteranganField.value = keteranganPemesanan; // Set keterangan form dengan keterangan pesanan hanya jika kosong
        }

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

        const initialPesanId = document.getElementById('id_pesan').value;
        if (initialPesanId) {
            updatePemesananInfo();
            const selectedOption = document.getElementById('id_pesan').options[document.getElementById('id_pesan').selectedIndex];
            const sisaAwal = parseFloat(selectedOption.dataset.sisa || 0);
            document.getElementById('jumlah_dibayar').value = sisaAwal;
            updateSisaSetelahPembayaran();
        }
    });
</script>