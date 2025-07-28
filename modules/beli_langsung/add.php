<?php
// --- START MODIFIKASI: Paksa tampilkan semua error PHP ---
ini_set('display_errors', 1);
error_reporting(E_ALL);
// --- END MODIFIKASI ---

// Sertakan header
require_once '../../layout/header.php';

// Pastikan hanya Admin atau Pegawai yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pegawai')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

// Inisialisasi variabel error
$id_transaksi_error = $id_customer_error = $id_akun_error = $tgl_transaksi_error = $jumlah_dibayar_error = $metode_pembayaran_error = $keterangan_error = "";
// Inisialisasi variabel data
$id_transaksi = ''; // Akan digenerate otomatis
$id_customer = '';
$id_akun = '';
$tgl_transaksi = '';
$jumlah_dibayar = 0; // Akan diisi dari total item
$metode_pembayaran = '';
$keterangan = '';
$total_tagihan = 0; // Akan diisi dari total item
$total_quantity = 0; // Akan diisi dari total item quantity

// Ambil daftar customer untuk dropdown
$customers = [];
$customer_sql = "SELECT id_customer, nama_customer FROM customer ORDER BY nama_customer ASC"; // Mengoreksi nama_akun menjadi nama_customer
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

// Ambil daftar barang untuk dropdown dinamis
$barang_list = [];
$barang_sql = "SELECT id_barang, nama_barang, harga_satuan FROM barang ORDER BY nama_barang ASC";
$barang_result = $conn->query($barang_sql);
if ($barang_result->num_rows > 0) {
    while ($row = $barang_result->fetch_assoc()) {
        $barang_list[] = $row;
    }
}

// === MODIFIKASI: Variabel untuk menyimpan data nota ===
$print_nota = false;
$nota_data = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitasi input utama transaksi
    $id_customer = sanitize_input($_POST['id_customer'] ?? '');
    $id_akun = sanitize_input($_POST['id_akun'] ?? '');
    $tgl_transaksi = sanitize_input($_POST['tgl_transaksi'] ?? '');
    $metode_pembayaran = 'Tunai'; // Selalu Tunai untuk pembelian langsung
    $keterangan = sanitize_input($_POST['keterangan'] ?? '');

    // Inisialisasi total dari sisi server
    $calculated_total_tagihan = 0;
    $calculated_total_quantity = 0;
    $items_data = []; // Untuk menyimpan detail item yang divalidasi

    // Validasi input utama
    if (empty($id_customer)) {
        $id_customer_error = "Customer tidak boleh kosong.";
    }
    if (empty($id_akun)) {
        $id_akun_error = "Akun tidak boleh kosong.";
    }
    // --- START PERBAIKAN: Validasi format tanggal yang lebih ketat dan debugging ---
    if (empty($tgl_transaksi)) {
        $tgl_transaksi_error = "Tanggal Transaksi tidak boleh kosong.";
    } else {
        // Debug: Tampilkan nilai tanggal yang diterima
        error_log("Raw tgl_transaksi received: " . var_export($tgl_transaksi, true));

        // Pastikan format tanggal benar dan bersihkan whitespace
        $tgl_transaksi = trim($tgl_transaksi);

        // Validasi format tanggal (YYYY-MM-DD)
        $date_obj = DateTime::createFromFormat('Y-m-d', $tgl_transaksi);
        if ($date_obj === false || $date_obj->format('Y-m-d') !== $tgl_transaksi) {
            $tgl_transaksi_error = "Format Tanggal Transaksi tidak valid (YYYY-MM-DD). Diterima: " . $tgl_transaksi;
            error_log("Date validation failed for: " . $tgl_transaksi);
        } else {
            // Pastikan tanggal tidak lebih dari hari ini
            $today = new DateTime();
            if ($date_obj > $today) {
                $tgl_transaksi_error = "Tanggal transaksi tidak boleh lebih dari hari ini.";
            }
            error_log("Date validation passed for: " . $tgl_transaksi);
        }
    }
    // --- END PERBAIKAN ---

    if (empty($metode_pembayaran)) {
        $metode_pembayaran_error = "Metode Pembayaran tidak boleh kosong.";
    }
    if (empty($keterangan)) {
        $keterangan_error = "Keterangan tidak boleh kosong.";
    } elseif (strlen($keterangan) > 30) {
        $keterangan_error = "Keterangan maksimal 30 karakter.";
    }

    // Validasi dan proses item-item
    if (isset($_POST['items']) && is_array($_POST['items'])) {
        foreach ($_POST['items'] as $index => $item) {
            $item_id_barang = sanitize_input($item['id_barang'] ?? '');
            $item_quantity = sanitize_input($item['quantity_item'] ?? 0);
            $item_harga_satuan = sanitize_input($item['harga_satuan_item'] ?? 0);

            // Validasi setiap item
            if (empty($item_id_barang)) {
                set_flash_message("Item ke-" . ($index + 1) . ": Barang tidak boleh kosong.", "error");
                goto end_post_processing;
            }
            if (!is_numeric($item_quantity) || $item_quantity <= 0) {
                set_flash_message("Item ke-" . ($index + 1) . ": Kuantitas harus angka positif.", "error");
                goto end_post_processing;
            }
            if (!is_numeric($item_harga_satuan) || $item_harga_satuan <= 0) {
                set_flash_message("Item ke-" . ($index + 1) . ": Harga satuan harus angka positif.", "error");
                goto end_post_processing;
            }

            $calculated_sub_total_item = (float)$item_quantity * (float)$item_harga_satuan;
            $calculated_total_tagihan += $calculated_sub_total_item;
            $calculated_total_quantity += (int)$item_quantity;

            $items_data[] = [
                'id_barang' => $item_id_barang,
                'quantity_item' => (int)$item_quantity,
                'harga_satuan_item' => (float)$item_harga_satuan,
                'sub_total_item' => $calculated_sub_total_item
            ];
        }
    } else {
        set_flash_message("Detail pembelian tidak boleh kosong. Tambahkan minimal satu item.", "error");
        goto end_post_processing;
    }

    if ($calculated_total_tagihan <= 0) {
        set_flash_message("Total tagihan harus lebih dari 0.", "error");
        goto end_post_processing;
    }

    // Jumlah dibayar untuk pembelian langsung biasanya sama dengan total tagihan
    $jumlah_dibayar = $calculated_total_tagihan;
    $sisa_pembayaran_for_db = 0; // Pembelian langsung dianggap lunas

    // Jika tidak ada error validasi, coba simpan ke database
    if (
        empty($id_customer_error) && empty($id_akun_error) && empty($tgl_transaksi_error) &&
        empty($metode_pembayaran_error) && empty($keterangan_error) && !empty($items_data)
    ) {
        // Mulai transaksi database
        $conn->begin_transaction();
        try {
            // --- Implementasi Alur Pembelian Langsung sebagai Pesanan Lunas ---

            // 1. Generate ID Pesanan untuk "dummy" pemesanan
            $latest_pesan_sql = "SELECT MAX(CAST(SUBSTRING(id_pesan, 4) AS UNSIGNED)) as last_num FROM pemesanan WHERE id_pesan LIKE 'ORD%'";
            $latest_pesan_result = $conn->query($latest_pesan_sql);
            $last_pesan_num = 0;
            if ($latest_pesan_result && $row = $latest_pesan_result->fetch_assoc()) {
                $last_pesan_num = intval($row['last_num']);
            }
            $new_pesan_num = $last_pesan_num + 1;
            $id_pesan_dummy = sprintf("ORD%05d", $new_pesan_num); // Format: ORD00001, dst.

            // 2. Masukkan data ke tabel `pemesanan` (sebagai pesanan langsung lunas)
            // Kolom uang_muka, total_tagihan_keseluruhan, sisa, status_pesanan, keterangan, total_quantity
            $sql_pemesanan_dummy = "INSERT INTO pemesanan (id_pesan, id_customer, tgl_pesan, tgl_kirim, uang_muka, total_tagihan_keseluruhan, sisa, status_pesanan, keterangan, total_quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            if ($stmt_pemesanan_dummy = $conn->prepare($sql_pemesanan_dummy)) {
                // Gunakan variabel untuk nilai literal di bind_param
                $sisa_pemesanan_dummy_var = 0;
                $status_pesanan_dummy_var = 'Lunas';
                $stmt_pemesanan_dummy->bind_param(
                    "ssssdddssi",
                    $id_pesan_dummy,
                    $id_customer,
                    $tgl_transaksi, // tgl_pesan sama dengan tgl_transaksi
                    $tgl_transaksi, // tgl_kirim sama dengan tgl_transaksi
                    $calculated_total_tagihan, // uang_muka adalah total tagihan
                    $calculated_total_tagihan, // total_tagihan_keseluruhan
                    $sisa_pemesanan_dummy_var, // Gunakan variabel
                    $status_pesanan_dummy_var, // Gunakan variabel
                    $keterangan,
                    $calculated_total_quantity // total_quantity
                );

                if (!$stmt_pemesanan_dummy->execute()) {
                    throw new Exception("Gagal menambahkan dummy pemesanan: " . $stmt_pemesanan_dummy->error);
                }
                $stmt_pemesanan_dummy->close();
            } else {
                throw new Exception("Error prepared statement (dummy pemesanan): " . $conn->error);
            }

            // 3. Masukkan data ke tabel `detail_pemesanan`
            $sql_detail_pemesanan = "INSERT INTO detail_pemesanan (id_pesan, id_barang, quantity_item, harga_satuan_item, sub_total_item) VALUES (?, ?, ?, ?, ?)";
            if ($stmt_detail = $conn->prepare($sql_detail_pemesanan)) {
                foreach ($items_data as $item_detail) {
                    $stmt_detail->bind_param(
                        "ssidd",
                        $id_pesan_dummy, // Menggunakan id_pesan dari dummy pemesanan
                        $item_detail['id_barang'],
                        $item_detail['quantity_item'],
                        $item_detail['harga_satuan_item'],
                        $item_detail['sub_total_item']
                    );
                    if (!$stmt_detail->execute()) {
                        throw new Exception("Gagal menambahkan detail pembelian langsung untuk barang " . $item_detail['id_barang'] . ": " . $stmt_detail->error);
                    }
                }
                $stmt_detail->close();
            } else {
                throw new Exception("Error prepared statement (detail_pemesanan beli_langsung): " . $conn->error);
            }

            // 4. Generate ID Transaksi Otomatis
            $generated_id_transaksi = 'TRX' . strtoupper(substr(uniqid(), 0, 5));
            $check_gen_id_sql = "SELECT id_transaksi FROM transaksi WHERE id_transaksi = ?";
            $stmt_check_gen_id = $conn->prepare($check_gen_id_sql);
            $stmt_check_gen_id->bind_param("s", $generated_id_transaksi);
            $stmt_check_gen_id->execute();
            $stmt_check_gen_id->store_result();
            if ($stmt_check_gen_id->num_rows > 0) {
                $generated_id_transaksi = 'TRX' . strtoupper(substr(uniqid(rand(), true), 0, 5));
            }
            $stmt_check_gen_id->close();

            // 5. Masukkan data ke tabel `transaksi` (Pembelian Langsung)
            // --- PERBAIKAN UTAMA: Pastikan parameter binding dan tipe data benar ---
            $sql_transaksi = "INSERT INTO transaksi (id_transaksi, id_pesan, id_akun, id_customer, tgl_transaksi, jumlah_dibayar, metode_pembayaran, keterangan, total_tagihan, sisa_pembayaran) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            if ($stmt_transaksi = $conn->prepare($sql_transaksi)) {
                // Debug: Log nilai sebelum binding
                error_log("Before binding - tgl_transaksi: " . $tgl_transaksi);
                error_log("Before binding - jumlah_dibayar: " . $jumlah_dibayar);
                error_log("Before binding - calculated_total_tagihan: " . $calculated_total_tagihan);

                // Pastikan semua parameter adalah variabel eksplisit dan tipe data benar
                $bind_id_transaksi = $generated_id_transaksi;
                $bind_id_pesan_dummy = $id_pesan_dummy;
                $bind_id_akun = $id_akun;
                $bind_id_customer = $id_customer;
                $bind_tgl_transaksi = $tgl_transaksi; // Pastikan ini format DATE yang benar
                $bind_jumlah_dibayar = (float)$jumlah_dibayar; // Cast ke float
                $bind_metode_pembayaran = $metode_pembayaran;
                $bind_keterangan = $keterangan;
                $bind_calculated_total_tagihan = (float)$calculated_total_tagihan; // Cast ke float
                $bind_sisa_pembayaran_var = 0.0; // Float 0

                // Perbaikan tipe data binding: s=string, d=double/float
                $stmt_transaksi->bind_param(
                    "sssssdssdd", // Perbaikan: tgl_transaksi tetap string (s), jumlah decimal menggunakan d
                    $bind_id_transaksi,
                    $bind_id_pesan_dummy,
                    $bind_id_akun,
                    $bind_id_customer,
                    $bind_tgl_transaksi,
                    $bind_jumlah_dibayar,
                    $bind_metode_pembayaran,
                    $bind_keterangan,
                    $bind_calculated_total_tagihan,
                    $bind_sisa_pembayaran_var
                );

                if (!$stmt_transaksi->execute()) {
                    throw new Exception("Gagal menambahkan transaksi pembelian langsung: " . $stmt_transaksi->error);
                }
                $stmt_transaksi->close();
            } else {
                throw new Exception("Error prepared statement (transaksi beli_langsung): " . $conn->error);
            }

            // 6. Masukkan data ke tabel `kas_masuk`
            $timestamp = date("YmdHis");
            $random = mt_rand(1000, 9999);
            $generated_id_kas_masuk = "KM" . $timestamp . $random;
            $generated_id_kas_masuk = substr($generated_id_kas_masuk, 0, 8);

            $check_kas_masuk = "SELECT id_kas_masuk FROM kas_masuk WHERE id_kas_masuk = ?";
            if ($stmt_check = $conn->prepare($check_kas_masuk)) {
                $stmt_check->bind_param("s", $generated_id_kas_masuk);
                $stmt_check->execute();
                $result = $stmt_check->get_result();
                if ($result->num_rows > 0) {
                    $random = mt_rand(1000, 9999);
                    $generated_id_kas_masuk = "KM" . substr($timestamp, -4) . $random;
                    $generated_id_kas_masuk = substr($generated_id_kas_masuk, 0, 8);
                }
                $stmt_check->close();
            }

            $sql_kas_masuk = "INSERT INTO kas_masuk (id_kas_masuk, id_transaksi, tgl_kas_masuk, jumlah, keterangan, harga, kuantitas) VALUES (?, ?, ?, ?, ?, ?, ?)";
            if ($stmt_kas_masuk = $conn->prepare($sql_kas_masuk)) {
                $km_harga = (float)$calculated_total_tagihan; // Cast ke float
                $km_kuantitas = (int)$calculated_total_quantity; // Cast ke int

                $bind_keterangan_kas_masuk_text = $keterangan; // Variabel untuk string literal
                $bind_jumlah_kas_masuk = (float)$calculated_total_tagihan; // Cast ke float

                $stmt_kas_masuk->bind_param(
                    "sssdsdi", // Perbaikan tipe data binding
                    $generated_id_kas_masuk,
                    $generated_id_transaksi,
                    $tgl_transaksi, // Pastikan tanggal benar
                    $bind_jumlah_kas_masuk, // Jumlah kas masuk (float/decimal -> d)
                    $bind_keterangan_kas_masuk_text,
                    $km_harga, // (float/decimal -> d)
                    $km_kuantitas // (int -> i)
                );

                if (!$stmt_kas_masuk->execute()) {
                    throw new Exception("Gagal menambahkan entri kas masuk: " . $stmt_kas_masuk->error);
                }
                $stmt_kas_masuk->close();
            } else {
                throw new Exception("Error prepared statement (kas_masuk): " . $conn->error);
            }

            // === MODIFIKASI: Siapkan data untuk nota cetak ===
            // Ambil nama customer
            $customer_name = '';
            foreach ($customers as $customer_option) {
                if ($customer_option['id_customer'] == $id_customer) {
                    $customer_name = $customer_option['nama_customer'];
                    break;
                }
            }

            // Siapkan data item dengan nama barang
            $nota_items = [];
            foreach ($items_data as $item_detail) {
                $nama_barang = '';
                foreach ($barang_list as $barang) {
                    if ($barang['id_barang'] == $item_detail['id_barang']) {
                        $nama_barang = $barang['nama_barang'];
                        break;
                    }
                }
                $nota_items[] = [
                    'nama_barang' => $nama_barang,
                    'quantity_item' => $item_detail['quantity_item'],
                    'harga_satuan_item' => $item_detail['harga_satuan_item'],
                    'sub_total_item' => $item_detail['sub_total_item']
                ];
            }

            // Set data nota untuk JavaScript
            $nota_data = [
                'id_transaksi' => $generated_id_transaksi,
                'tanggal' => $tgl_transaksi,
                'customer_name' => $customer_name,
                'metode_pembayaran' => $metode_pembayaran,
                'keterangan' => $keterangan,
                'total_tagihan' => $calculated_total_tagihan,
                'items' => $nota_items
            ];
            $print_nota = true;

            // Commit transaksi jika semua berhasil
            $conn->commit();
            set_flash_message("Pembelian Langsung berhasil dicatat!", "success");

            // Reset form data untuk pembelian berikutnya
            $id_customer = '';
            $id_akun = '';
            $tgl_transaksi = '';
            $keterangan = '';
            $total_tagihan = 0;
            $jumlah_dibayar = 0;
        } catch (Exception $e) {
            // Rollback transaksi jika ada error
            $conn->rollback();
            set_flash_message("Error saat memproses pembelian langsung: " . $e->getMessage(), "error");
            // Log error untuk debugging
            error_log("Transaction error: " . $e->getMessage());
        }
    } else {
        set_flash_message("Silakan perbaiki kesalahan pada formulir.", "error");
    }

    end_post_processing:; // Label untuk goto
}
?>

<div class="bg-white p-8 rounded-lg shadow-xl max-w-4xl mx-auto my-8">
    <h1 class="text-2xl font-bold text-gray-800 mb-4 text-center">Tambah Pembelian Langsung</h1>
    <p class="text-gray-600 mb-6 text-center">Catat penjualan tunai atau pembelian langsung dengan multi-item di sini.</p>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
            <div>
                <div class="mb-4">
                    <label for="id_customer" class="block text-gray-700 text-sm font-bold mb-2">Customer:</label>
                    <select id="id_customer" name="id_customer" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
                        <option value="">-- Pilih Customer --</option>
                        <?php foreach ($customers as $customer_option) : ?>
                            <option value="<?php echo htmlspecialchars($customer_option['id_customer']); ?>"
                                <?php echo ($id_customer == $customer_option['id_customer']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($customer_option['nama_customer']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="text-red-500 text-xs italic mt-1 block"><?php echo $id_customer_error; ?></span>
                </div>
                <div class="mb-4">
                    <label for="id_akun" class="block text-gray-700 text-sm font-bold mb-2">Akun Penerima:</label>
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

            </div>

            <div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Total Tagihan:</label>
                    <input type="text" id="total_tagihan_display" value="<?php echo format_rupiah($total_tagihan); ?>" disabled
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100 cursor-not-allowed">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Jumlah Dibayar:</label>
                    <input type="text" id="jumlah_dibayar_display" value="<?php echo format_rupiah($jumlah_dibayar); ?>" disabled
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100 cursor-not-allowed">
                </div>
                <!-- <div class="mb-4">
                    <label for="metode_pembayaran" class="block text-gray-700 text-sm font-bold mb-2">Metode Pembayaran:</label>
                    <input type="hidden" id="metode_pembayaran" name="metode_pembayaran" value="Tunai">
                    <input type="text" value="Tunai" disabled
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100 cursor-not-allowed">
                </div> -->
                <div class="mb-6">
                    <label for="keterangan" class="block text-gray-700 text-sm font-bold mb-2">Keterangan :</label>
                    <input type="text" id="keterangan" name="keterangan" value="<?php echo htmlspecialchars($keterangan); ?>" required maxlength="30"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
                    <span class="text-red-500 text-xs italic mt-1 block"><?php echo $keterangan_error; ?></span>
                </div>
            </div>
        </div>

        <h2 class="text-xl font-bold text-gray-800 mt-8 mb-4 border-b pb-2">Detail Barang Pembelian</h2>
        <div id="item-list" class="space-y-4">
        </div>
        <button type="button" id="add-item-btn" class="mt-4 bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition">
            ➕ Tambah Item
        </button>

        <div class="flex items-center justify-center space-x-4 mt-8">
            <button type="submit" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                SIMPAN
            </button>
            <a href="add.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                RESET
            </a>
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
            <td style="width:20%;">Metode Pembayaran</td>
            <td style="width:2%;">:</td>
            <td id="nota-metode-pembayaran"></td>
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
            <td id="nota-total-pembelian"></td>
        </tr>
        <tr>
            <td>Keterangan</td>
            <td>:</td>
            <td id="nota-keterangan"></td>
        </tr>
    </table>
    <div style="text-align: center; font-size: 0.9em; margin-top: 10px;">
        Terima kasih telah berbelanja!<br>
        Ampyang Cap Garuda - Manisnya Tradisi Nusantara
    </div>
</div>

<?php if ($print_nota): ?>
    <script>
        // Data nota dari PHP
        const notaData = <?php echo json_encode($nota_data); ?>;

        // Auto print nota setelah halaman load
        document.addEventListener('DOMContentLoaded', function() {
            if (notaData) {
                setTimeout(function() {
                    printNotaFromData(notaData);
                }, 1000); // Delay 1 detik untuk memastikan halaman sudah load
            }
        });
    </script>
<?php endif; ?>

<script>
    // Data barang dari PHP untuk digunakan di JavaScript
    const barangList = <?php echo json_encode($barang_list); ?>;
    let itemCounter = 0; // Untuk ID unik setiap baris item

    // Fungsi untuk memformat angka menjadi Rupiah
    function formatRupiah(angka) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(angka);
    }

    // Fungsi untuk menambahkan baris item baru
    function addItemRow() {
        const itemList = document.getElementById('item-list');
        const newItemRow = document.createElement('div');
        newItemRow.classList.add('flex', 'flex-wrap', 'items-end', 'gap-4', 'p-4', 'border', 'border-gray-200', 'rounded-lg', 'bg-gray-50');
        newItemRow.id = `item-row-${itemCounter}`;

        // Buat dropdown barang
        let barangOptions = '<option value="">-- Pilih Barang --</option>';
        barangList.forEach(barang => {
            barangOptions += `<option value="${barang.id_barang}" data-harga="${barang.harga_satuan}">${barang.nama_barang}</option>`;
        });

        newItemRow.innerHTML = `
            <div class="flex-1 min-w-[150px]">
                <label for="items[${itemCounter}][id_barang]" class="block text-gray-700 text-sm font-bold mb-1">Barang:</label>
                <select name="items[${itemCounter}][id_barang]" id="item-barang-${itemCounter}" required
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-blue-500">
                    ${barangOptions}
                </select>
            </div>
            <div class="flex-1 min-w-[80px]">
                <label for="items[${itemCounter}][quantity_item]" class="block text-gray-700 text-sm font-bold mb-1">Qty:</label>
                <input type="number" name="items[${itemCounter}][quantity_item]" id="item-quantity-${itemCounter}" value="1" min="1" required
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="flex-1 min-w-[100px]">
                <label for="item-harga-${itemCounter}" class="block text-gray-700 text-sm font-bold mb-1">Harga Satuan:</label>
                <input type="text" name="items[${itemCounter}][harga_satuan_item]" id="item-harga-${itemCounter}" value="0" readonly
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100 cursor-not-allowed">
            </div>
            <div class="flex-1 min-w-[120px]">
                <label for="item-subtotal-${itemCounter}" class="block text-gray-700 text-sm font-bold mb-1">Subtotal Item:</label>
                <input type="text" id="item-subtotal-${itemCounter}" value="0" readonly
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100 cursor-not-allowed">
            </div>
            <div>
                <button type="button" onclick="removeItemRow(${itemCounter})"
                    class="bg-red-500 text-white px-3 py-2 rounded-lg hover:bg-red-600 transition">
                    Hapus
                </button>
            </div>
        `;
        itemList.appendChild(newItemRow);

        // Tambahkan event listener untuk perubahan pada dropdown barang dan input kuantitas
        document.getElementById(`item-barang-${itemCounter}`).addEventListener('change', function() {
            const selectedHarga = this.options[this.selectedIndex].dataset.harga || 0;
            document.getElementById(`item-harga-${this.id.split('-')[2]}`).value = selectedHarga;
            calculateItemSubtotal(this.id.split('-')[2]);
        });
        document.getElementById(`item-quantity-${itemCounter}`).addEventListener('input', function() {
            calculateItemSubtotal(this.id.split('-')[2]);
        });
        document.getElementById(`item-harga-${itemCounter}`).addEventListener('input', function() {
            calculateItemSubtotal(this.id.split('-')[2]);
        });

        itemCounter++;
        updateGrandTotal(); // Perbarui total setelah menambah item
    }

    // Fungsi untuk menghitung subtotal satu item
    function calculateItemSubtotal(rowId) {
        const quantity = parseFloat(document.getElementById(`item-quantity-${rowId}`).value) || 0;
        const hargaSatuan = parseFloat(document.getElementById(`item-harga-${rowId}`).value) || 0;
        const subtotal = quantity * hargaSatuan;
        document.getElementById(`item-subtotal-${rowId}`).value = formatRupiah(subtotal);
        updateGrandTotal(); // Perbarui total keseluruhan setelah subtotal item berubah
    }

    // Fungsi untuk menghapus baris item
    function removeItemRow(rowId) {
        document.getElementById(`item-row-${rowId}`).remove();
        updateGrandTotal(); // Perbarui total setelah menghapus item
    }

    // Fungsi untuk memperbarui total keseluruhan dan jumlah dibayar
    function updateGrandTotal() {
        let grandTotal = 0;
        document.querySelectorAll('[id^="item-subtotal-"]').forEach(input => {
            const value = input.value.replace('Rp', '').replace(/\./g, '').replace(',', '.').trim();
            grandTotal += parseFloat(value) || 0;
        });

        document.getElementById('total_tagihan_display').value = formatRupiah(grandTotal);
        document.getElementById('jumlah_dibayar_display').value = formatRupiah(grandTotal); // Jumlah dibayar sama dengan total tagihan
    }

    // === MODIFIKASI: Fungsi untuk mencetak nota menggunakan data dari database ===
    function printNotaFromData(data) {
        // Format tanggal untuk tampilan
        const tanggalFormatted = new Date(data.tanggal).toLocaleDateString('id-ID', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });

        // Isi data umum nota
        document.getElementById('nota-tanggal').textContent = tanggalFormatted;
        document.getElementById('nota-no-transaksi').textContent = data.id_transaksi;
        document.getElementById('nota-nama-customer').textContent = data.customer_name;
        document.getElementById('nota-metode-pembayaran').textContent = data.metode_pembayaran;
        document.getElementById('nota-total-pembelian').textContent = formatRupiah(data.total_tagihan);
        document.getElementById('nota-keterangan').textContent = data.keterangan;

        // Isi detail barang di nota
        const notaDetailBarang = document.getElementById('nota-detail-barang');
        notaDetailBarang.innerHTML = ''; // Kosongkan baris item sebelumnya

        if (data.items && data.items.length > 0) {
            data.items.forEach(item => {
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
            row.innerHTML = `<td colspan="4" style="text-align:center; font-style:italic;">Tidak ada item pembelian.</td>`;
            notaDetailBarang.appendChild(row);
        }

        // Tampilkan nota dan print
        document.getElementById('nota-cetak').style.display = 'block';
        window.print();
        setTimeout(function() {
            document.getElementById('nota-cetak').style.display = 'none';
        }, 500);
    }

    // Fungsi untuk mencetak nota manual (dari form)
    function printNota() {
        const customerSelect = document.getElementById('id_customer');
        const customerName = customerSelect.options[customerSelect.selectedIndex].textContent || '-';
        const tglTransaksi = document.getElementById('tgl_transaksi').value || '-';
        const metodePembayaran = document.getElementById('metode_pembayaran').value || '-';
        const totalPembelian = document.getElementById('total_tagihan_display').value || 'Rp 0';
        const keterangan = document.getElementById('keterangan').value || '-';

        let noTransaksi = 'TRX' + Math.random().toString(36).substr(2, 5).toUpperCase(); // Generate simple ID for receipt

        // Format tanggal untuk tampilan
        const tanggalFormatted = new Date(tglTransaksi).toLocaleDateString('id-ID', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });

        // Isi data umum nota
        document.getElementById('nota-tanggal').textContent = tanggalFormatted;
        document.getElementById('nota-no-transaksi').textContent = noTransaksi;
        document.getElementById('nota-nama-customer').textContent = customerName;
        document.getElementById('nota-metode-pembayaran').textContent = metodePembayaran;
        document.getElementById('nota-total-pembelian').textContent = totalPembelian;
        document.getElementById('nota-keterangan').textContent = keterangan;

        // Isi detail barang di nota
        const notaDetailBarang = document.getElementById('nota-detail-barang');
        notaDetailBarang.innerHTML = ''; // Kosongkan baris item sebelumnya

        const itemRows = document.querySelectorAll('[id^="item-row-"]');
        if (itemRows.length > 0) {
            itemRows.forEach(rowElement => {
                const rowId = rowElement.id.split('-')[2];
                const barangSelect = document.getElementById(`item-barang-${rowId}`);
                const namaBarang = barangSelect.options[barangSelect.selectedIndex].textContent || 'N/A';
                const quantityItem = document.getElementById(`item-quantity-${rowId}`).value || 0;
                const hargaSatuanItem = parseFloat(document.getElementById(`item-harga-${rowId}`).value) || 0;
                const subTotalItem = parseFloat(document.getElementById(`item-subtotal-${rowId}`).value.replace('Rp', '').replace(/\./g, '').replace(',', '.').trim()) || 0;

                const row = document.createElement('tr');
                row.innerHTML = `
                    <td style="text-align:left;">${namaBarang}</td>
                    <td style="text-align:right;">${quantityItem}</td>
                    <td style="text-align:right;">${formatRupiah(hargaSatuanItem)}</td>
                    <td style="text-align:right;">${formatRupiah(subTotalItem)}</td>
                `;
                notaDetailBarang.appendChild(row);
            });
        } else {
            const row = document.createElement('tr');
            row.innerHTML = `<td colspan="4" style="text-align:center; font-style:italic;">Tidak ada item pembelian.</td>`;
            notaDetailBarang.appendChild(row);
        }

        // Tampilkan nota dan print
        document.getElementById('nota-cetak').style.display = 'block';
        window.print();
        setTimeout(function() {
            document.getElementById('nota-cetak').style.display = 'none';
        }, 500);
    }

    // Event listener untuk tombol "Tambah Item"
    document.getElementById('add-item-btn').addEventListener('click', addItemRow);

    // Inisialisasi saat halaman dimuat
    document.addEventListener('DOMContentLoaded', function() {
        // Set tanggal transaksi ke hari ini secara default
        if (!document.getElementById('tgl_transaksi').value) {
            document.getElementById('tgl_transaksi').valueAsDate = new Date();
        }

        // Tambahkan satu baris item secara default saat halaman dimuat
        addItemRow();
        updateGrandTotal(); // Panggil sekali untuk inisialisasi total
    });
</script>