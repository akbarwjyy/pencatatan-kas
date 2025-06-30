<?php
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
$jumlah_dibayar = 0;
$metode_pembayaran = '';
$keterangan = '';

// Ambil daftar customer untuk dropdown
$customers = [];
$customer_sql = "SELECT id_customer, nama_customer FROM customer ORDER BY nama_customer ASC";
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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitasi input
    $id_customer = sanitize_input($_POST['id_customer'] ?? '');
    $id_akun = sanitize_input($_POST['id_akun'] ?? '');
    $tgl_transaksi = sanitize_input($_POST['tgl_transaksi'] ?? '');
    $jumlah_dibayar = sanitize_input($_POST['jumlah_dibayar'] ?? 0);
    $metode_pembayaran = sanitize_input($_POST['metode_pembayaran'] ?? '');
    $keterangan = sanitize_input($_POST['keterangan'] ?? '');

    // Validasi input
    if (empty($id_customer)) {
        $id_customer_error = "Customer tidak boleh kosong.";
    }
    if (empty($id_akun)) {
        $id_akun_error = "Akun tidak boleh kosong.";
    }
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

    if (empty($keterangan)) {
        $keterangan_error = "Keterangan tidak boleh kosong.";
    } elseif (strlen($keterangan) > 30) {
        $keterangan_error = "Keterangan maksimal 30 karakter.";
    }

    // Jika tidak ada error validasi, coba simpan ke database
    if (
        empty($id_customer_error) && empty($id_akun_error) && empty($tgl_transaksi_error) &&
        empty($jumlah_dibayar_error) && empty($metode_pembayaran_error) && empty($keterangan_error)
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
            $sql_kas_masuk = "INSERT INTO kas_masuk (id_kas_masuk, id_transaksi, tgl_kas_masuk, jumlah, keterangan) VALUES (?, ?, ?, ?, ?)";
            if ($stmt_kas_masuk = $conn->prepare($sql_kas_masuk)) {
                $bind_jumlah_masuk = $jumlah_dibayar;
                $bind_keterangan_kas = "Pembelian langsung: " . $keterangan;

                if (!$stmt_kas_masuk->bind_param(
                    "sssis",
                    $generated_id_kas_masuk,
                    $generated_id_transaksi,
                    $tgl_transaksi,
                    $bind_jumlah_masuk,
                    $bind_keterangan_kas
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
        <div class="mb-4">
            <label for="jumlah_dibayar" class="block text-gray-700 text-sm font-bold mb-2">Jumlah Dibayar (Rp):</label>
            <input type="number" id="jumlah_dibayar" name="jumlah_dibayar" value="<?php echo htmlspecialchars($jumlah_dibayar); ?>" required min="1"
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $jumlah_dibayar_error; ?></span>
        </div>
        <div class="mb-4">
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
            <label for="keterangan" class="block text-gray-700 text-sm font-bold mb-2">Keterangan (misal: "Penjualan Ampyang 5pcs"):</label>
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

<?php
// Sertakan footer
require_once '../../layout/footer.php';
?>