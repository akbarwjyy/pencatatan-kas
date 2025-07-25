<?php
// Sertakan header
require_once '../../layout/header.php';

// Pastikan hanya Admin atau Pegawai yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pegawai')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

// Inisialisasi variabel error
$id_pesan_error = $id_customer_error = $tgl_pesan_error = $tgl_kirim_error = $uang_muka_error = $total_tagihan_keseluruhan_error = $sisa_error = $keterangan_error = "";

// Generate ID pesan otomatis
$latest_pesan_sql = "SELECT MAX(CAST(SUBSTRING(id_pesan, 4) AS UNSIGNED)) as last_num FROM pemesanan WHERE id_pesan LIKE 'ORD%'";
$latest_pesan_result = $conn->query($latest_pesan_sql);
$last_pesan_num = 0;
if ($latest_pesan_result && $row = $latest_pesan_result->fetch_assoc()) {
    $last_pesan_num = intval($row['last_num']);
}
$new_pesan_num = $last_pesan_num + 1;
$id_pesan = sprintf("ORD%05d", $new_pesan_num); // Format: ORD00001, ORD00002, dst.

// Inisialisasi variabel data. Variabel numerik diinisialisasi dengan 0.
$id_customer = $tgl_pesan = $tgl_kirim = $keterangan = '';
$status_pesanan = 'Belum Lunas';
$uang_muka = 0;
$total_tagihan_keseluruhan = 0;
$sisa = 0;
$total_quantity = 0; // Inisialisasi variabel baru untuk total kuantitas

// Ambil daftar barang untuk dropdown dinamis
$barang_list = [];
$barang_sql = "SELECT id_barang, nama_barang, harga_satuan FROM barang ORDER BY nama_barang ASC";
$barang_result = $conn->query($barang_sql);
if ($barang_result->num_rows > 0) {
    while ($row = $barang_result->fetch_assoc()) {
        $barang_list[] = $row;
    }
}

// Ambil daftar customer untuk dropdown
$customers = [];
$customer_sql = "SELECT id_customer, nama_customer FROM customer ORDER BY nama_customer ASC";
$customer_result = $conn->query($customer_sql);
if ($customer_result->num_rows > 0) {
    while ($row = $customer_result->fetch_assoc()) {
        $customers[] = $row;
    }
}

// Ambil daftar akun untuk dropdown, dikelompokkan berdasarkan jenis akun
$accounts = [];
$account_groups = [
    '1' => 'Aktiva',
    '2' => 'Kewajiban',
    '3' => 'Modal',
    '4' => 'Pendapatan',
    '5' => 'Beban'
];

// Ambil semua akun dan kelompokkan berdasarkan digit pertama dari id_akun
$account_sql = "SELECT id_akun, nama_akun FROM akun ORDER BY id_akun ASC";
$account_result = $conn->query($account_sql);
if ($account_result->num_rows > 0) {
    while ($row = $account_result->fetch_assoc()) {
        $group_key = substr($row['id_akun'], 0, 1);
        if (!isset($accounts[$group_key])) {
            $accounts[$group_key] = [];
        }
        $accounts[$group_key][] = $row;
    }
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitasi input utama pemesanan
    $id_customer = sanitize_input($_POST['id_customer'] ?? '');
    $tgl_pesan = sanitize_input($_POST['tgl_pesan'] ?? '');
    $tgl_kirim = sanitize_input($_POST['tgl_kirim'] ?? '');
    $uang_muka = sanitize_input($_POST['uang_muka'] ?? 0);
    $keterangan = sanitize_input($_POST['keterangan'] ?? '');
    $status_pesanan_input = sanitize_input($_POST['status_pesanan'] ?? 'Belum Lunas'); // Dari form status

    // Inisialisasi total tagihan keseluruhan dari sisi server
    $calculated_total_tagihan_keseluruhan = 0;
    $calculated_total_quantity = 0; // Inisialisasi untuk total kuantitas
    $items_data = []; // Untuk menyimpan detail item yang divalidasi

    // Validasi input utama
    if (empty($id_customer)) {
        $id_customer_error = "Customer tidak boleh kosong.";
    }
    if (empty($tgl_pesan)) {
        $tgl_pesan_error = "Tanggal Pesan tidak boleh kosong.";
    }
    if (empty($tgl_kirim)) {
        $tgl_kirim_error = "Tanggal Kirim tidak boleh kosong.";
    }
    if (!is_numeric($uang_muka) || $uang_muka < 0) {
        $uang_muka_error = "Uang Muka harus angka non-negatif.";
    } else {
        $uang_muka = (float)$uang_muka;
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
                goto end_post_processing; // Lompat ke akhir jika ada error item
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
            $calculated_total_tagihan_keseluruhan += $calculated_sub_total_item;
            $calculated_total_quantity += (int)$item_quantity; // Menambahkan ke total kuantitas

            $items_data[] = [
                'id_barang' => $item_id_barang,
                'quantity_item' => (int)$item_quantity,
                'harga_satuan_item' => (float)$item_harga_satuan,
                'sub_total_item' => $calculated_sub_total_item
            ];
        }
    } else {
        set_flash_message("Detail pesanan tidak boleh kosong. Tambahkan minimal satu item.", "error");
        goto end_post_processing;
    }

    if ($calculated_total_tagihan_keseluruhan <= 0) {
        set_flash_message("Total tagihan keseluruhan harus lebih dari 0.", "error");
        goto end_post_processing;
    }

    // Validasi uang muka terhadap total tagihan keseluruhan
    if ($uang_muka > $calculated_total_tagihan_keseluruhan) {
        $uang_muka_error = "Uang Muka tidak boleh melebihi Total Keseluruhan.";
        set_flash_message("Uang Muka tidak boleh melebihi Total Keseluruhan.", "error");
        goto end_post_processing;
    }

    // Hitung sisa pembayaran
    $sisa = $calculated_total_tagihan_keseluruhan - $uang_muka;

    // Sesuaikan status pesanan berdasarkan sisa
    if ($sisa <= 0) {
        $status_pesanan = 'Lunas';
        $sisa = 0; // Pastikan sisa 0 jika lunas
    } else {
        $status_pesanan = 'Belum Lunas';
    }

    // Jika tidak ada error validasi, coba simpan ke database
    if (
        empty($id_customer_error) && empty($tgl_pesan_error) && empty($tgl_kirim_error) &&
        empty($uang_muka_error) && empty($total_tagihan_keseluruhan_error) && empty($sisa_error) &&
        !empty($items_data) // Pastikan ada item yang valid
    ) {
        // --- START MODIFIKASI: Tambahkan calculated_total_quantity ke query INSERT pemesanan ---
        $sql_pemesanan = "INSERT INTO pemesanan (id_pesan, id_customer, tgl_pesan, tgl_kirim, uang_muka, total_tagihan_keseluruhan, sisa, status_pesanan, keterangan, total_quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        // --- END MODIFIKASI ---

        // Mulai transaksi database untuk memastikan konsistensi
        $conn->begin_transaction();
        try {
            if ($stmt_pemesanan = $conn->prepare($sql_pemesanan)) {
                // --- START MODIFIKASI: Tambahkan 'i' untuk total_quantity di bind_param ---
                $stmt_pemesanan->bind_param("ssssdddssi", $id_pesan, $id_customer, $tgl_pesan, $tgl_kirim, $uang_muka, $calculated_total_tagihan_keseluruhan, $sisa, $status_pesanan, $keterangan, $calculated_total_quantity);
                // --- END MODIFIKASI ---

                if (!$stmt_pemesanan->execute()) {
                    throw new Exception("Gagal menambahkan pemesanan utama: " . $stmt_pemesanan->error);
                }
                $stmt_pemesanan->close();

                // Simpan detail item ke tabel detail_pemesanan
                $sql_detail_pemesanan = "INSERT INTO detail_pemesanan (id_pesan, id_barang, quantity_item, harga_satuan_item, sub_total_item) VALUES (?, ?, ?, ?, ?)";
                if ($stmt_detail = $conn->prepare($sql_detail_pemesanan)) {
                    foreach ($items_data as $item_detail) {
                        $stmt_detail->bind_param(
                            "ssidd",
                            $id_pesan,
                            $item_detail['id_barang'],
                            $item_detail['quantity_item'],
                            $item_detail['harga_satuan_item'],
                            $item_detail['sub_total_item']
                        );
                        if (!$stmt_detail->execute()) {
                            throw new Exception("Gagal menambahkan detail pesanan untuk barang " . $item_detail['id_barang'] . ": " . $stmt_detail->error);
                        }
                    }
                    $stmt_detail->close();
                } else {
                    throw new Exception("Error prepared statement (detail_pemesanan): " . $conn->error);
                }

                // Jika ada uang muka, buat transaksi dan tambahkan ke kas masuk
                if ($uang_muka > 0) {
                    // Generate ID transaksi untuk uang muka
                    $latest_trx_sql = "SELECT MAX(CAST(SUBSTRING(id_transaksi, 4) AS UNSIGNED)) as last_num FROM transaksi WHERE id_transaksi LIKE 'TRX%'";
                    $latest_trx_result = $conn->query($latest_trx_sql);
                    $last_trx_num = 0;
                    if ($latest_trx_result && $row = $latest_trx_result->fetch_assoc()) {
                        $last_trx_num = intval($row['last_num']);
                    }
                    $new_trx_num = $last_trx_num + 1;
                    $id_transaksi_uang_muka = sprintf("TRX%05d", $new_trx_num);

                    // Ambil id_akun dari form jika ada
                    $id_akun_default = sanitize_input($_POST['id_akun'] ?? '');
                    if (empty($id_akun_default)) {
                        $id_akun_default = !empty($accounts['4']) ? $accounts['4'][0]['id_akun'] : '4001'; // Default ke akun pendapatan
                    }

                    // Tambahkan transaksi untuk uang muka
                    $sql_transaksi = "INSERT INTO transaksi (id_transaksi, id_pesan, id_akun, id_customer, tgl_transaksi, jumlah_dibayar, metode_pembayaran, keterangan, total_tagihan, sisa_pembayaran) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $keterangan_transaksi = "Uang muka pemesanan " . $id_pesan . (!empty($keterangan) ? " (" . $keterangan . ")" : "");
                    $metode_pembayaran_transaksi = "Cash"; // Default cash untuk uang muka

                    if ($stmt_transaksi = $conn->prepare($sql_transaksi)) {
                        $stmt_transaksi->bind_param("sssssissii", $id_transaksi_uang_muka, $id_pesan, $id_akun_default, $id_customer, $tgl_pesan, $uang_muka, $metode_pembayaran_transaksi, $keterangan_transaksi, $calculated_total_tagihan_keseluruhan, $sisa);
                        if (!$stmt_transaksi->execute()) {
                            throw new Exception("Gagal menambahkan transaksi uang muka: " . $stmt_transaksi->error);
                        }
                        $stmt_transaksi->close();
                    } else {
                        throw new Exception("Error prepared statement (transaksi uang muka): " . $conn->error);
                    }

                    // Generate ID kas masuk
                    $latest_km_sql = "SELECT MAX(CAST(SUBSTRING(id_kas_masuk, 3) AS UNSIGNED)) as last_num FROM kas_masuk WHERE id_kas_masuk LIKE 'KM%'";
                    $latest_km_result = $conn->query($latest_km_sql);
                    $last_num_km = 0;
                    if ($latest_km_result && $row_km = $latest_km_result->fetch_assoc()) {
                        $last_num_km = intval($row_km['last_num']);
                    }
                    $new_num_km = $last_num_km + 1;
                    $id_kas_masuk = sprintf("KM%06d", $new_num_km);

                    // Untuk kas_masuk, harga dan kuantitas bisa dihitung dari uang muka
                    // Atau bisa diambil dari item pertama jika uang muka hanya untuk satu item
                    // Untuk kesederhanaan, kita akan gunakan uang muka sebagai jumlah, dan harga/kuantitas sebagai 1 dan uang muka
                    $km_harga = $uang_muka; // Asumsi uang muka adalah harga jika kuantitas 1
                    $km_kuantitas = 1; // Asumsi kuantitas 1 untuk uang muka

                    if ($stmt_kas_masuk = $conn->prepare("INSERT INTO kas_masuk (id_kas_masuk, id_transaksi, tgl_kas_masuk, jumlah, keterangan, harga, kuantitas) VALUES (?, ?, ?, ?, ?, ?, ?)")) {
                        $stmt_kas_masuk->bind_param("sssisii", $id_kas_masuk, $id_transaksi_uang_muka, $tgl_pesan, $uang_muka, $keterangan_transaksi, $km_harga, $km_kuantitas);
                        if (!$stmt_kas_masuk->execute()) {
                            throw new Exception("Gagal menambahkan entri kas masuk uang muka: " . $stmt_kas_masuk->error);
                        }
                        $stmt_kas_masuk->close();
                    } else {
                        throw new Exception("Error prepared statement (kas_masuk uang muka): " . $conn->error);
                    }
                }

                // Commit transaksi jika semua berhasil
                $conn->commit();

                // Logika pengalihan berdasarkan tombol yang diklik
                if (isset($_POST['submit_action']) && $_POST['submit_action'] === 'bayar_sisa') {
                    set_flash_message("Pemesanan berhasil ditambahkan! Silakan lengkapi pembayaran sisa.", "success");
                    // Redirect ke halaman transaksi baru dengan ID pesanan yang baru
                    redirect('../../modules/transaksi/add.php?id_pesan=' . htmlspecialchars($id_pesan));
                } else { // Jika tombol "SIMPAN" yang diklik
                    set_flash_message("Pemesanan berhasil ditambahkan!", "success");
                    redirect('index.php'); // Redirect ke halaman daftar pemesanan
                }
            } else {
                throw new Exception("Error prepared statement (pemesanan utama): " . $conn->error);
            }
        } catch (Exception $e) {
            // Rollback transaksi jika ada error
            $conn->rollback();
            set_flash_message("Error saat memproses pemesanan: " . $e->getMessage(), "error");
        }
    } else {
        set_flash_message("Silakan perbaiki kesalahan pada formulir.", "error");
    }

    end_post_processing:; // Label untuk goto
}
?>

<div class="bg-white p-8 rounded-lg shadow-xl max-w-4xl mx-auto my-8">
    <h1 class="text-2xl font-bold text-gray-800 mb-4 text-center">Tambah Pemesanan</h1>
    <p class="text-gray-600 mb-6 text-center">Isi formulir di bawah ini untuk menambahkan pesanan baru dengan multi-item.</p>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
            <div>
                <div class="mb-4">
                    <label for="id_customer" class="block text-gray-700 text-sm font-bold mb-2">Nama Customer:</label>
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
                    <label for="tgl_pesan" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Pesan:</label>
                    <input type="date" id="tgl_pesan" name="tgl_pesan" value="<?php echo htmlspecialchars($tgl_pesan); ?>" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
                    <span class="text-red-500 text-xs italic mt-1 block"><?php echo $tgl_pesan_error; ?></span>
                </div>
                <div class="mb-4">
                    <label for="tgl_kirim" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Kirim:</label>
                    <input type="date" id="tgl_kirim" name="tgl_kirim" value="<?php echo htmlspecialchars($tgl_kirim); ?>" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
                    <span class="text-red-500 text-xs italic mt-1 block"><?php echo $tgl_kirim_error; ?></span>
                </div>
                <div class="mb-6">
                    <label for="keterangan" class="block text-gray-700 text-sm font-bold mb-2">Keterangan:</label>
                    <textarea id="keterangan" name="keterangan" rows="3"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500"><?php echo htmlspecialchars($keterangan); ?></textarea>
                    <span class="text-red-500 text-xs italic mt-1 block"><?php echo $keterangan_error; ?></span>
                </div>
            </div>

            <div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Total Keseluruhan:</label>
                    <input type="text" id="total_tagihan_keseluruhan_display" value="<?php echo format_rupiah($total_tagihan_keseluruhan); ?>" disabled
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100 cursor-not-allowed">
                </div>
                <div class="mb-4">
                    <label for="uang_muka" class="block text-gray-700 text-sm font-bold mb-2">Uang Muka:</label>
                    <input type="number" id="uang_muka" name="uang_muka" value="<?php echo htmlspecialchars($uang_muka); ?>" required min="0"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
                    <span class="text-red-500 text-xs italic mt-1 block"><?php echo $uang_muka_error; ?></span>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Sisa Pembayaran:</label>
                    <input type="text" id="sisa_pembayaran_display" value="<?php echo format_rupiah($sisa); ?>" disabled
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100 cursor-not-allowed">
                </div>
                <div class="mb-4">
                    <label for="status_pesanan" class="block text-gray-700 text-sm font-bold mb-2">Status Pembayaran:</label>
                    <select id="status_pesanan" name="status_pesanan" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
                        <option value="Belum Lunas" <?php echo ($status_pesanan == 'Belum Lunas') ? 'selected' : ''; ?>>Belum Lunas</option>
                        <option value="Lunas" <?php echo ($status_pesanan == 'Lunas') ? 'selected' : ''; ?>>Lunas</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label for="id_akun" class="block text-gray-700 text-sm font-bold mb-2">Akun Pendapatan (untuk Uang Muka):</label>
                    <select id="id_akun" name="id_akun"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
                        <option value="">-- Pilih Akun --</option>
                        <?php foreach ($account_groups as $group_key => $group_name) : ?>
                            <?php if ($group_key == '4') : // Hanya tampilkan akun pendapatan (grup 4) 
                            ?>
                                <optgroup label="<?php echo $group_name; ?>">
                                    <?php if (!empty($accounts[$group_key])) : ?>
                                        <?php foreach ($accounts[$group_key] as $account) : ?>
                                            <option value="<?php echo htmlspecialchars($account['id_akun']); ?>"
                                                <?php echo (isset($_POST['id_akun']) && $_POST['id_akun'] == $account['id_akun']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($account['id_akun'] . ' - ' . $account['nama_akun']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </optgroup>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    <span class="text-red-500 text-xs italic mt-1 block"><?php echo $id_akun_error ?? ''; ?></span>
                </div>
            </div>
        </div>

        <h2 class="text-xl font-bold text-gray-800 mt-8 mb-4 border-b pb-2">Detail Barang Pesanan</h2>
        <div id="item-list" class="space-y-4">
        </div>
        <button type="button" id="add-item-btn" class="mt-4 bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition">
            ➕ Tambah Item
        </button>

        <div class="flex items-center justify-center space-x-4 mt-8">
            <button type="submit" name="submit_action" value="simpan"
                class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                SIMPAN
            </button>
            <a href="index.php"
                class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                KELUAR
            </a>
            <button type="submit" name="submit_action" value="bayar_sisa"
                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                BAYAR SISA
            </button>
        </div>
    </form>
</div>

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
        }); // Tambahkan listener untuk harga satuan jika diisi manual

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

    // Fungsi untuk memperbarui total keseluruhan dan sisa pembayaran
    function updateGrandTotal() {
        let grandTotal = 0;
        document.querySelectorAll('[id^="item-subtotal-"]').forEach(input => {
            // Hapus "Rp " dan titik pemisah ribuan, lalu konversi ke float
            const value = input.value.replace('Rp', '').replace(/\./g, '').replace(',', '.').trim();
            grandTotal += parseFloat(value) || 0;
        });

        document.getElementById('total_tagihan_keseluruhan_display').value = formatRupiah(grandTotal);

        const uangMuka = parseFloat(document.getElementById('uang_muka').value) || 0;
        let sisaPembayaran = grandTotal - uangMuka;
        if (sisaPembayaran < 0) sisaPembayaran = 0; // Sisa tidak boleh negatif

        document.getElementById('sisa_pembayaran_display').value = formatRupiah(sisaPembayaran);

        // Update status pembayaran berdasarkan sisa
        const statusElement = document.getElementById('status_pesanan');
        if (sisaPembayaran === 0 && grandTotal > 0) { // Lunas jika sisa 0 dan ada total
            statusElement.value = 'Lunas';
        } else {
            statusElement.value = 'Belum Lunas';
        }
    }

    // Event listener untuk tombol "Tambah Item"
    document.getElementById('add-item-btn').addEventListener('click', addItemRow);

    // Event listener untuk perubahan pada uang muka
    document.getElementById('uang_muka').addEventListener('input', updateGrandTotal);

    // Inisialisasi saat halaman dimuat
    document.addEventListener('DOMContentLoaded', function() {
        // Set tanggal pesan dan tanggal kirim ke hari ini secara default jika kosong
        if (!document.getElementById('tgl_pesan').value) {
            document.getElementById('tgl_pesan').valueAsDate = new Date();
        }
        if (!document.getElementById('tgl_kirim').value) {
            document.getElementById('tgl_kirim').valueAsDate = new Date();
        }

        // Tambahkan satu baris item secara default saat halaman dimuat
        addItemRow();
        updateGrandTotal(); // Panggil sekali untuk inisialisasi total
    });
</script>