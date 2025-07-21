<?php
// Sertakan header
require_once '../../layout/header.php';

// Pastikan hanya Admin atau Pegawai yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pegawai')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

// Inisialisasi variabel error
$id_transaksi_error = $id_customer_error = $id_akun_error = $tgl_transaksi_error = $jumlah_dibayar_error = $metode_pembayaran_error = $keterangan_error = $id_barang_error = $quantity_error = $harga_satuan_error = "";
// Inisialisasi variabel data
$id_transaksi = ''; // Akan digenerate otomatis
$id_customer = '';
$id_akun = '';
$id_barang = '';
$tgl_transaksi = '';
$jumlah_dibayar = 0;
$metode_pembayaran = '';
$keterangan = '';
$quantity = 0;
$harga_satuan = 0;

// Ambil daftar customer untuk dropdown
$customers = [];
$customer_sql = "SELECT id_customer, nama_customer FROM customer ORDER BY nama_customer ASC";
$customer_result = $conn->query($customer_sql);
if ($customer_result->num_rows > 0) {
    while ($row = $customer_result->fetch_assoc()) {
        $customers[] = $row;
    }
}

// Ambil daftar barang untuk dropdown
$barang_list = [];
$barang_sql = "SELECT id_barang, nama_barang, harga_satuan FROM barang ORDER BY nama_barang ASC";
$barang_result = $conn->query($barang_sql);
if ($barang_result->num_rows > 0) {
    while ($row = $barang_result->fetch_assoc()) {
        $barang_list[] = $row;
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
    $id_customer = sanitize_input($_POST['id_customer'] ?? '');
    $id_akun = sanitize_input($_POST['id_akun'] ?? '');
    $id_barang = sanitize_input($_POST['id_barang'] ?? '');
    $tgl_transaksi = sanitize_input($_POST['tgl_transaksi'] ?? '');
    $quantity = sanitize_input($_POST['quantity'] ?? 1);
    $harga_satuan = sanitize_input($_POST['harga_satuan'] ?? 0);
    $jumlah_dibayar = sanitize_input($_POST['jumlah_dibayar'] ?? 0);
    $metode_pembayaran = "Tunai"; // Metode pembayaran selalu Tunai
    $keterangan = sanitize_input($_POST['keterangan'] ?? '');

    // Validasi input
    if (empty($id_customer)) {
        $id_customer_error = "Customer tidak boleh kosong.";
    }
    if (empty($id_akun)) {
        $id_akun_error = "Akun tidak boleh kosong.";
    }
    if (empty($id_barang)) {
        $id_barang_error = "Barang tidak boleh kosong.";
    }
    if (empty($tgl_transaksi)) {
        $tgl_transaksi_error = "Tanggal Transaksi tidak boleh kosong.";
    }

    if (!is_numeric($quantity) || $quantity <= 0) {
        $quantity_error = "Quantity harus angka positif.";
    } else {
        $quantity = (int)$quantity;
    }

    if (!is_numeric($harga_satuan) || $harga_satuan <= 0) {
        $harga_satuan_error = "Harga Satuan harus angka positif.";
    } else {
        $harga_satuan = (int)$harga_satuan;
        // Hitung jumlah dibayar berdasarkan quantity dan harga satuan
        $jumlah_dibayar = $quantity * $harga_satuan;
    }

    // Metode pembayaran selalu Tunai, tidak perlu validasi

    if (empty($keterangan)) {
        $keterangan_error = "Keterangan tidak boleh kosong.";
    } elseif (strlen($keterangan) > 30) {
        $keterangan_error = "Keterangan maksimal 30 karakter.";
    }

    // Jika tidak ada error validasi, coba simpan ke database
    if (
        empty($id_customer_error) && empty($id_akun_error) && empty($id_barang_error) && empty($tgl_transaksi_error) &&
        empty($quantity_error) && empty($harga_satuan_error) && empty($keterangan_error)
    ) {
        // Mulai transaksi database
        $conn->begin_transaction();
        try {
            // --- 1. Generate ID Transaksi Otomatis ---
            $generated_id_transaksi = 'TRX' . strtoupper(substr(uniqid(), 0, 5));

            // Periksa apakah ID yang digenerate sudah ada (pencegahan bentrok)
            $check_gen_id_sql = "SELECT id_transaksi FROM transaksi WHERE id_transaksi = ?";
            $stmt_check_gen_id = $conn->prepare($check_gen_id_sql);
            if ($stmt_check_gen_id === false) {
                throw new Exception("Error menyiapkan pengecekan ID transaksi: " . $conn->error);
            }
            $stmt_check_gen_id->bind_param("s", $generated_id_transaksi);
            $stmt_check_gen_id->execute();
            $stmt_check_gen_id->store_result();
            if ($stmt_check_gen_id->num_rows > 0) {
                $generated_id_transaksi = 'TRX' . strtoupper(substr(uniqid(rand(), true), 0, 5));
                set_flash_message("ID Transaksi otomatis bentrok, mencoba lagi. Mohon submit ulang jika error berlanjut.", "warning");
            }
            $stmt_check_gen_id->close();


            // --- 2. Masukkan data ke tabel `transaksi` (Pembelian Langsung) ---
            $sql_transaksi = "INSERT INTO transaksi (id_transaksi, id_pesan, id_akun, id_customer, tgl_transaksi, jumlah_dibayar, metode_pembayaran, keterangan, total_tagihan, sisa_pembayaran) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?)";
            if ($stmt_transaksi = $conn->prepare($sql_transaksi)) {
                // Deklarasikan variabel-variabel sebelum digunakan di bind_param
                $bind_id_akun = $id_akun;
                $bind_id_customer = $id_customer;
                $bind_tgl_transaksi = $tgl_transaksi;
                $bind_jumlah_dibayar = $jumlah_dibayar;
                $bind_metode_pembayaran = $metode_pembayaran;
                $bind_keterangan = $keterangan;
                $bind_total_tagihan_final = $jumlah_dibayar;
                $bind_sisa_pembayaran_final = 0;

                if (!$stmt_transaksi->bind_param(
                    "sssssssii",
                    $generated_id_transaksi,
                    $bind_id_akun,
                    $bind_id_customer,
                    $bind_tgl_transaksi,
                    $bind_jumlah_dibayar,
                    $bind_metode_pembayaran,
                    $bind_keterangan,
                    $bind_total_tagihan_final,
                    $bind_sisa_pembayaran_final
                )) {
                    throw new Exception("Error binding parameters: " . $stmt_transaksi->error);
                }

                if (!$stmt_transaksi->execute()) {
                    throw new Exception("Gagal menambahkan transaksi pembelian langsung: " . $stmt_transaksi->error);
                }
                $stmt_transaksi->close();
            } else {
                throw new Exception("Error prepared statement (transaksi beli_langsung): " . $conn->error);
            }

            // --- 3. Masukkan data ke tabel `kas_masuk` ---
            // Generate ID Kas Masuk
            $timestamp = date("YmdHis");
            $random = mt_rand(1000, 9999);
            $generated_id_kas_masuk = "KM" . $timestamp . $random;
            $generated_id_kas_masuk = substr($generated_id_kas_masuk, 0, 8); // Pastikan panjangnya sesuai dengan kolom database

            // Cek apakah ID kas masuk sudah ada
            $check_kas_masuk = "SELECT id_kas_masuk FROM kas_masuk WHERE id_kas_masuk = ?";
            if ($stmt_check = $conn->prepare($check_kas_masuk)) {
                $stmt_check->bind_param("s", $generated_id_kas_masuk);
                $stmt_check->execute();
                $result = $stmt_check->get_result();
                if ($result->num_rows > 0) {
                    // Jika ID sudah ada, generate ulang
                    $random = mt_rand(1000, 9999);
                    $generated_id_kas_masuk = "KM" . substr($timestamp, -4) . $random;
                    $generated_id_kas_masuk = substr($generated_id_kas_masuk, 0, 8);
                }
                $stmt_check->close();
            }

            // Insert ke tabel kas_masuk
            $sql_kas_masuk = "INSERT INTO kas_masuk (id_kas_masuk, id_transaksi, tgl_kas_masuk, jumlah, keterangan, harga, kuantitas) VALUES (?, ?, ?, ?, ?, ?, ?)";
            if ($stmt_kas_masuk = $conn->prepare($sql_kas_masuk)) {
                $bind_jumlah_masuk = $jumlah_dibayar;
                $bind_keterangan_kas = $keterangan;

                if (!$stmt_kas_masuk->bind_param(
                    "sssissi",
                    $generated_id_kas_masuk,
                    $generated_id_transaksi,
                    $tgl_transaksi,
                    $bind_jumlah_masuk,
                    $bind_keterangan_kas,
                    $harga_satuan,
                    $quantity
                )) {
                    throw new Exception("Error binding parameters for kas_masuk: " . $stmt_kas_masuk->error);
                }

                if (!$stmt_kas_masuk->execute()) {
                    throw new Exception("Gagal menambahkan entri kas masuk: " . $stmt_kas_masuk->error);
                }
                $stmt_kas_masuk->close();
            } else {
                throw new Exception("Error prepared statement (kas_masuk beli_langsung): " . $conn->error);
            }

            // Commit transaksi jika semua berhasil
            $conn->commit();
            set_flash_message("Pembelian Langsung berhasil dicatat!", "success");
            redirect('add.php'); // Tetap di halaman add.php untuk pembelian berikutnya

        } catch (Exception $e) {
            // Rollback transaksi jika ada error
            $conn->rollback();
            set_flash_message("Error saat memproses pembelian langsung: " . $e->getMessage(), "error");
        }
    } else {
        set_flash_message("Silakan perbaiki kesalahan pada formulir.", "error");
    }
}
?>

<div class="bg-white p-8 rounded-lg shadow-xl max-w-md mx-auto my-8">
    <h1 class="text-2xl font-bold text-gray-800 mb-4 text-center">Tambah Pembelian Langsung</h1>
    <p class="text-gray-600 mb-6 text-center">Catat penjualan tunai atau pembelian langsung di sini.</p>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
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
            <label for="id_akun" class="block text-gray-700 text-sm font-bold mb-2">Akun Pendapatan:</label>
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
            <input type="number" id="harga_satuan" name="harga_satuan" value="<?php echo htmlspecialchars($harga_satuan); ?>" readonly
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100 cursor-not-allowed">
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $harga_satuan_error; ?></span>
        </div>
        <div class="mb-4">
            <label for="quantity" class="block text-gray-700 text-sm font-bold mb-2">Jumlah Barang:</label>
            <input type="number" id="quantity" name="quantity" value="<?php echo htmlspecialchars($quantity); ?>" required min="1"
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $quantity_error; ?></span>
        </div>
        <div class="mb-4">
            <label for="tgl_transaksi" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Transaksi:</label>
            <input type="date" id="tgl_transaksi" name="tgl_transaksi" value="<?php echo htmlspecialchars($tgl_transaksi); ?>" required
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $tgl_transaksi_error; ?></span>
        </div>
        <div class="mb-4">
            <label for="jumlah_dibayar" class="block text-gray-700 text-sm font-bold mb-2">Jumlah Dibayar (Rp):</label>
            <input type="number" id="jumlah_dibayar" name="jumlah_dibayar" value="<?php echo htmlspecialchars($jumlah_dibayar); ?>" readonly
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100 cursor-not-allowed">
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $jumlah_dibayar_error; ?></span>
        </div>
        <!-- <div class="mb-4">
            <label for="metode_pembayaran" class="block text-gray-700 text-sm font-bold mb-2">Metode Pembayaran:</label>
            <input type="text" id="metode_pembayaran" value="Tunai" readonly
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100 cursor-not-allowed">
            <input type="hidden" name="metode_pembayaran" value="Tunai">
        </div> -->
        <div class="mb-6">
            <label for="keterangan" class="block text-gray-700 text-sm font-bold mb-2">Keterangan:</label>
            <input type="text" id="keterangan" name="keterangan" value="<?php echo htmlspecialchars($keterangan); ?>" required maxlength="30"
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $keterangan_error; ?></span>
        </div>
        <div class="flex items-center justify-between">
            <button type="submit" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Simpan
            </button>
            <a href="add.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Reset
            </a>
        </div>
    </form>
</div>

<script>
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

        const totalHarga = quantity * hargaSatuan;
        document.getElementById('jumlah_dibayar').value = totalHarga;
    }

    document.getElementById('quantity').addEventListener('input', hitungTotal);

    document.addEventListener('DOMContentLoaded', function() {
        updateHargaSatuan();
        hitungTotal();
    });
</script>

<?php
?>