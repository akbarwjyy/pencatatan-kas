<?php
// Sertakan header
require_once '../../layout/header.php';

// Pastikan hanya Admin atau Pegawai yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pegawai')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

// Inisialisasi variabel error
$id_pesan_error = $id_customer_error = $id_barang_error = $tgl_pesan_error = $tgl_kirim_error = $quantity_error = $uang_muka_error = $sub_total_error = $sisa_error = $harga_satuan_error = $id_akun_error = "";

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
$id_customer = $id_barang = $tgl_pesan = $tgl_kirim = $keterangan = '';
$status_pembayaran = 'Belum Lunas';
$quantity = 0;
$uang_muka = 0;
$sub_total = 0;
$sisa = 0;
$harga_satuan_input = 12000;

// Ambil daftar barang untuk dropdown
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
    // Sanitasi input (ID pesan sudah di-generate otomatis)
    $id_customer = sanitize_input($_POST['id_customer'] ?? '');
    $id_barang = sanitize_input($_POST['id_barang'] ?? '');
    $id_akun = sanitize_input($_POST['id_akun'] ?? '');
    $tgl_pesan = sanitize_input($_POST['tgl_pesan'] ?? '');
    $tgl_kirim = sanitize_input($_POST['tgl_kirim'] ?? '');
    $quantity = sanitize_input($_POST['quantity'] ?? 0);
    $harga_satuan_input = sanitize_input($_POST['harga_satuan'] ?? 0);
    $uang_muka = sanitize_input($_POST['uang_muka'] ?? 0);
    $status_pembayaran = sanitize_input($_POST['status_pembayaran'] ?? 'Belum Lunas');
    $keterangan = sanitize_input($_POST['keterangan'] ?? '');

    // Validasi field satu per satu (ID pesan tidak perlu validasi karena auto-generate)
    if (empty($id_customer)) {
        $id_customer_error = "Customer tidak boleh kosong.";
    }

    if (empty($id_akun)) {
        $id_akun_error = "Akun tidak boleh kosong.";
    } elseif (!preg_match('/^[1-5][0-9]{3}$/', $id_akun)) {
        $id_akun_error = "ID Akun harus 4 digit dengan format yang valid.";
    }
    if (empty($tgl_pesan)) {
        $tgl_pesan_error = "Tanggal Pesan tidak boleh kosong.";
    }
    if (empty($tgl_kirim)) {
        $tgl_kirim_error = "Tanggal Kirim tidak boleh kosong.";
    }
    if (!is_numeric($quantity) || $quantity <= 0) {
        $quantity_error = "Quantity harus angka positif.";
    } else {
        $quantity = (int)$quantity;
    }
    if (!is_numeric($harga_satuan_input) || $harga_satuan_input <= 0) {
        $harga_satuan_error = "Harga Satuan harus angka positif.";
    } else {
        $harga_satuan_input = (int)$harga_satuan_input;
    }
    if (!is_numeric($uang_muka) || $uang_muka < 0) {
        $uang_muka_error = "Uang Muka harus angka non-negatif.";
    } else {
        $uang_muka = (int)$uang_muka;
    }

    // Hanya lakukan perhitungan jika tidak ada error pada field numerik
    if (empty($quantity_error) && empty($harga_satuan_error) && empty($uang_muka_error)) {
        $sub_total = $quantity * $harga_satuan_input;

        // Jika status pembayaran Lunas, set uang muka = sub_total
        if ($status_pembayaran === 'Lunas') {
            $uang_muka = $sub_total;
        }

        if ($uang_muka > $sub_total) {
            $uang_muka_error = "Uang Muka tidak boleh melebihi Sub Total.";
        } else {
            $sisa = $sub_total - $uang_muka;

            // Jika status pembayaran Lunas, pastikan sisa = 0
            if ($status_pembayaran === 'Lunas') {
                $sisa = 0;
            }
        }
    } else {
        $sub_total = 0;
        $sisa = 0;
    }

    // Jika tidak ada error validasi, coba simpan ke database
    if (
        empty($id_customer_error) && empty($tgl_pesan_error) && empty($id_akun_error) &&
        empty($tgl_kirim_error) && empty($quantity_error) && empty($uang_muka_error) && empty($harga_satuan_error)
    ) {

        // Query untuk menambah data pemesanan (tanpa id_barang karena kolom belum ada)
        $sql = "INSERT INTO pemesanan (id_pesan, id_customer, id_barang, tgl_pesan, tgl_kirim, quantity, uang_muka, sub_total, sisa) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        // Mulai transaksi database untuk memastikan konsistensi
        $conn->begin_transaction();
        try {
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("sssssiiii", $id_pesan, $id_customer, $id_barang, $tgl_pesan, $tgl_kirim, $quantity, $uang_muka, $sub_total, $sisa);

                if (!$stmt->execute()) {
                    throw new Exception("Gagal menambahkan pemesanan: " . $stmt->error);
                }
                $stmt->close();
                $new_order_id = $id_pesan; // ID pesanan yang baru saja dimasukkan

                // Jika ada uang muka, buat transaksi dan tambahkan ke kas masuk
                if ($uang_muka > 0) {
                    // Generate ID transaksi untuk uang muka dengan format yang lebih unik
                    $latest_trx_sql = "SELECT MAX(CAST(SUBSTRING(id_transaksi, 4) AS UNSIGNED)) as last_num FROM transaksi WHERE id_transaksi LIKE 'TRX%'";
                    $latest_trx_result = $conn->query($latest_trx_sql);
                    $last_trx_num = 0;
                    if ($latest_trx_result && $row = $latest_trx_result->fetch_assoc()) {
                        $last_trx_num = intval($row['last_num']);
                    }
                    $new_trx_num = $last_trx_num + 1;
                    $id_transaksi_uang_muka = sprintf("TRX%05d", $new_trx_num); // Format: TRX00001, TRX00002, dst.

                    // Ambil id_akun dari form jika ada
                    $id_akun_default = sanitize_input($_POST['id_akun'] ?? '');

                    // Jika tidak ada, gunakan akun pendapatan (grup 4) sebagai default
                    if (empty($id_akun_default)) {
                        $id_akun_default = !empty($accounts['4']) ? $accounts['4'][0]['id_akun'] : '4001';
                    }

                    // Tambahkan transaksi untuk uang muka
                    $sql_transaksi = "INSERT INTO transaksi (id_transaksi, id_pesan, id_akun, id_customer, tgl_transaksi, jumlah_dibayar, metode_pembayaran, keterangan, total_tagihan, sisa_pembayaran) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $keterangan_transaksi = "Uang muka pemesanan " . $id_pesan;
                    $metode_pembayaran = "Cash";
                    $sisa_pembayaran_transaksi = $sub_total - $uang_muka;

                    if ($stmt_transaksi = $conn->prepare($sql_transaksi)) {
                        $stmt_transaksi->bind_param("sssssissii", $id_transaksi_uang_muka, $id_pesan, $id_akun, $id_customer, $tgl_pesan, $uang_muka, $metode_pembayaran, $keterangan_transaksi, $sub_total, $sisa_pembayaran_transaksi);
                        if (!$stmt_transaksi->execute()) {
                            throw new Exception("Gagal menambahkan transaksi uang muka: " . $stmt_transaksi->error);
                        }
                        $stmt_transaksi->close();
                    } else {
                        throw new Exception("Error prepared statement (transaksi): " . $conn->error);
                    }

                    // Generate ID kas masuk dengan format KMxxxxxx (8 karakter)
                    $latest_km_sql = "SELECT MAX(CAST(SUBSTRING(id_kas_masuk, 3) AS UNSIGNED)) as last_num FROM kas_masuk WHERE id_kas_masuk LIKE 'KM%'";
                    $latest_km_result = $conn->query($latest_km_sql);
                    $last_num = 0;
                    if ($latest_km_result && $row = $latest_km_result->fetch_assoc()) {
                        $last_num = intval($row['last_num']);
                    }
                    $new_num = $last_num + 1;
                    $id_kas_masuk = sprintf("KM%06d", $new_num); // Format: KM000001, KM000002, dst.

                    // Tambahkan entri ke tabel kas_masuk dengan id_transaksi
                    $sql_kas_masuk = "INSERT INTO kas_masuk (id_kas_masuk, id_transaksi, tgl_kas_masuk, jumlah, keterangan, harga, kuantitas) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $keterangan_kas_masuk = !empty($keterangan) ? $keterangan : "Uang muka pemesanan " . $id_pesan;
                    $harga_satuan = $harga_satuan_input;

                    if ($stmt_kas_masuk = $conn->prepare($sql_kas_masuk)) {
                        $stmt_kas_masuk->bind_param("sssissi", $id_kas_masuk, $id_transaksi_uang_muka, $tgl_pesan, $uang_muka, $keterangan_kas_masuk, $harga_satuan, $quantity);
                        if (!$stmt_kas_masuk->execute()) {
                            throw new Exception("Gagal menambahkan entri kas masuk: " . $stmt_kas_masuk->error);
                        }
                        $stmt_kas_masuk->close();
                    } else {
                        throw new Exception("Error prepared statement (kas_masuk): " . $conn->error);
                    }
                }

                // Commit transaksi jika semua berhasil
                $conn->commit();

                // Logika pengalihan berdasarkan tombol yang diklik
                if (isset($_POST['submit_action']) && $_POST['submit_action'] === 'bayar') {
                    set_flash_message("Pemesanan berhasil ditambahkan! Silakan lengkapi pembayaran.", "success");
                    // Redirect ke halaman transaksi baru dengan ID pesanan yang baru
                    redirect('../../modules/transaksi/add.php?id_pesan=' . htmlspecialchars($new_order_id));
                } else { // Jika tombol "TAMBAH" yang diklik
                    set_flash_message("Pemesanan berhasil ditambahkan! Uang muka telah dicatat di kas masuk.", "success");
                    redirect('index.php'); // Redirect ke halaman daftar pemesanan
                }
            } else {
                throw new Exception("Error prepared statement: " . $conn->error);
            }
        } catch (Exception $e) {
            // Rollback transaksi jika ada error
            $conn->rollback();
            set_flash_message("Error saat memproses pemesanan: " . $e->getMessage(), "error");
        }
    } else {
        // Tampilkan pesan error spesifik
        set_flash_message("Silakan perbaiki kesalahan pada formulir.", "error");
    }
}
?>

<div class="bg-white p-8 rounded-lg shadow-xl max-w-2xl mx-auto my-8">
    <h1 class="text-2xl font-bold text-gray-800 mb-4 text-center">Tambah Pemesanan</h1>
    <p class="text-gray-600 mb-6 text-center">Isi formulir di bawah ini untuk menambahkan pemesanan baru.</p>

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
                <div class="mb-4">
                    <label for="quantity" class="block text-gray-700 text-sm font-bold mb-2">Jumlah Barang:</label>
                    <input type="number" id="quantity" name="quantity" value="<?php echo htmlspecialchars($quantity); ?>" required min="1"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
                    <span class="text-red-500 text-xs italic mt-1 block"><?php echo $quantity_error; ?></span>
                </div>
                <div class="mb-4">
                    <label for="id_akun" class="block text-gray-700 text-sm font-bold mb-2">Akun Pendapatan:</label>
                    <select id="id_akun" name="id_akun" required
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
                <div class="mb-6">
                    <label for="keterangan" class="block text-gray-700 text-sm font-bold mb-2">Keterangan:</label>
                    <input id="keterangan" name="keterangan" rows="3"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500"><?php echo htmlspecialchars($keterangan); ?></input>
                </div>


            </div>

            <div>
                <div class="mb-4">
                    <label for="id_barang" class="block text-gray-700 text-sm font-bold mb-2">Nama Barang:</label>
                    <select id="id_barang" name="id_barang" required onchange="updateHargaSatuan()"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
                        <option value="">-- Pilih Barang --</option>
                        <?php foreach ($barang_list as $barang) : ?>
                            <option value="<?php echo htmlspecialchars($barang['id_barang']); ?>"
                                data-harga="<?php echo htmlspecialchars($barang['harga_satuan']); ?>"
                                <?php echo ($id_barang == $barang['id_barang']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($barang['nama_barang']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="text-red-500 text-xs italic mt-1 block"><?php echo $id_barang_error; ?></span>
                </div>
                <div class="mb-4">
                    <label for="harga_satuan" class="block text-gray-700 text-sm font-bold mb-2">Harga satuan:</label>
                    <input type="number" id="harga_satuan" name="harga_satuan" value="<?php echo htmlspecialchars($harga_satuan_input); ?>" readonly
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100 cursor-not-allowed">
                    <span class="text-red-500 text-xs italic mt-1 block"><?php echo $harga_satuan_error; ?></span>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Total Harga:</label>
                    <input type="text" id="total_harga" value="<?php echo format_rupiah($sub_total); ?>" disabled
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
                    <input type="text" id="sisa_pembayaran" value="<?php echo format_rupiah($sisa); ?>" disabled
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100 cursor-not-allowed">
                </div>
                <div class="mb-4">
                    <label for="status_pembayaran" class="block text-gray-700 text-sm font-bold mb-2">Status Pembayaran:</label>
                    <select id="status_pembayaran" name="status_pembayaran" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
                        <option value="Belum Lunas" <?php echo (($sisa > 0 || $sub_total == 0) ? 'selected' : ''); ?>>Belum Lunas</option>
                        <option value="Lunas" <?php echo (($sisa == 0 && $sub_total > 0) ? 'selected' : ''); ?>>Lunas</option>
                    </select>
                </div>

            </div>
        </div>

        <div class="flex items-center justify-center space-x-4 mt-6">
            <button type="submit" name="submit_action" value="tambah"
                class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                TAMBAH
            </button>
            <a href="index.php"
                class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                KELUAR
            </a>
            <button type="submit" name="submit_action" value="bayar"
                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                BAYAR
            </button>
        </div>
    </form>
</div>

<script>
    function formatRupiah(angka) {
        return 'Rp ' + angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function updateHargaSatuan() {
        const selectElement = document.getElementById('id_barang');
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        const hargaSatuan = selectedOption.dataset.harga || 0;

        document.getElementById('harga_satuan').value = hargaSatuan;
        hitungTotal();
    }

    function hitungTotal() {
        const quantity = parseInt(document.getElementById('quantity').value) || 0;
        const hargaSatuan = parseInt(document.getElementById('harga_satuan').value) || 0;
        const uangMuka = parseInt(document.getElementById('uang_muka').value) || 0;

        const totalHarga = quantity * hargaSatuan;
        const sisaPembayaran = totalHarga - uangMuka;

        document.getElementById('total_harga').value = formatRupiah(totalHarga);
        document.getElementById('sisa_pembayaran').value = formatRupiah(sisaPembayaran);

        const status = (sisaPembayaran <= 0 && totalHarga > 0) ? 'Lunas' : 'Belum Lunas';
        document.getElementById('status_pembayaran').value = status;
    }

    document.getElementById('quantity').addEventListener('input', hitungTotal);
    document.getElementById('uang_muka').addEventListener('input', hitungTotal);

    document.addEventListener('DOMContentLoaded', function() {
        updateHargaSatuan();
        hitungTotal();

        // Add event listener for status_pembayaran dropdown
        document.getElementById('status_pembayaran').addEventListener('change', function() {
            const statusPembayaran = this.value;
            const totalHarga = parseInt(document.getElementById('quantity').value || 0) * parseInt(document.getElementById('harga_satuan').value || 0);

            if (statusPembayaran === 'Lunas') {
                // If status is Lunas, set uang_muka = total harga
                document.getElementById('uang_muka').value = totalHarga;
                document.getElementById('sisa_pembayaran').value = formatRupiah(0);
            } else {
                // Otherwise, recalculate
                hitungTotal();
            }
        });
    });
</script>

<?php
?>