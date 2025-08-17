<?php
// Sertakan header
require_once '../../layout/header.php';

// Pastikan hanya Admin atau Pegawai yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pegawai')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

$id_kas_masuk_error = $tgl_kas_masuk_error = $jumlah_error = $keterangan_error = $id_akun_error = "";
$id_kas_masuk = $tgl_kas_masuk = $keterangan = $id_akun = "";
$jumlah = 0; // Jumlah akan diinput langsung

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
    $id_kas_masuk = sanitize_input($_POST['id_kas_masuk'] ?? '');
    $tgl_kas_masuk = sanitize_input($_POST['tgl_kas_masuk'] ?? '');
    $id_akun = sanitize_input($_POST['id_akun'] ?? '');
    $keterangan = sanitize_input($_POST['keterangan'] ?? '');
    $jumlah = sanitize_input($_POST['jumlah'] ?? 0);

    // Validasi input
    if (empty($id_kas_masuk)) {
        $id_kas_masuk_error = "ID Kas Masuk tidak boleh kosong.";
    } elseif (strlen($id_kas_masuk) > 8) {
        $id_kas_masuk_error = "ID Kas Masuk maksimal 8 karakter.";
    }

    if (empty($tgl_kas_masuk)) {
        $tgl_kas_masuk_error = "Tanggal Kas Masuk tidak boleh kosong.";
    }

    if (empty($id_akun)) {
        $id_akun_error = "Akun tidak boleh kosong.";
    }

    // Validasi Jumlah
    if (!is_numeric($jumlah) || $jumlah <= 0) {
        $jumlah_error = "Jumlah harus angka positif.";
    } else {
        $jumlah = (float)$jumlah;
    }

    if (empty($keterangan)) {
        $keterangan_error = "Keterangan tidak boleh kosong.";
    } elseif (strlen($keterangan) > 30) {
        $keterangan_error = "Keterangan maksimal 30 karakter.";
    }

    // Jika tidak ada error validasi, coba simpan ke database
    if (
        empty($id_kas_masuk_error) && empty($tgl_kas_masuk_error) && empty($id_akun_error) &&
        empty($jumlah_error) && empty($keterangan_error)
    ) {
        // Cek apakah id_kas_masuk sudah ada di database
        $check_sql = "SELECT id_kas_masuk FROM kas_masuk WHERE id_kas_masuk = ?";
        $stmt_check = $conn->prepare($check_sql);
        $stmt_check->bind_param("s", $id_kas_masuk);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $id_kas_masuk_error = "ID Kas Masuk sudah ada. Gunakan ID lain.";
            set_flash_message("Gagal menambahkan kas masuk: ID sudah ada.", "error");
        } else {
            $stmt_check->close();

            // --- MULAI PERBAIKAN UNTUK id_transaksi TIDAK BISA NULL dan bind_param ---
            $conn->begin_transaction(); // Mulai transaksi database
            try {
                // 1. Generate ID Transaksi untuk entri kas masuk "lainnya" ini
                // Tambahkan pengecekan untuk memastikan ID transaksi unik
                $is_unique = false;
                $max_attempts = 10;
                $attempt = 0;

                while (!$is_unique && $attempt < $max_attempts) {
                    $generated_id_transaksi = 'TRX' . strtoupper(substr(uniqid(mt_rand(), true), 0, 5));

                    // Cek apakah ID sudah ada di database
                    $check_trx_sql = "SELECT id_transaksi FROM transaksi WHERE id_transaksi = ? LIMIT 1";
                    $stmt_check_trx = $conn->prepare($check_trx_sql);
                    $stmt_check_trx->bind_param("s", $generated_id_transaksi);
                    $stmt_check_trx->execute();
                    $stmt_check_trx->store_result();

                    if ($stmt_check_trx->num_rows == 0) {
                        $is_unique = true;
                    }

                    $stmt_check_trx->close();
                    $attempt++;
                }

                if (!$is_unique) {
                    throw new Exception("Tidak dapat membuat ID transaksi unik setelah $max_attempts percobaan.");
                }
                $dummy_customer_id = 'CUST999'; // Default dummy
                // Pastikan dummy_customer_id ada di tabel customer
                $cek_dummy_sql = "SELECT id_customer FROM customer WHERE id_customer = ? LIMIT 1";
                $stmt_cek_dummy = $conn->prepare($cek_dummy_sql);
                $stmt_cek_dummy->bind_param("s", $dummy_customer_id);
                $stmt_cek_dummy->execute();
                $stmt_cek_dummy->store_result();
                if ($stmt_cek_dummy->num_rows == 0) {
                    // Jika tidak ada, ambil id_customer pertama dari tabel customer
                    $stmt_cek_dummy->close();
                    $sql_first_cust = "SELECT id_customer FROM customer LIMIT 1";
                    $result_first_cust = $conn->query($sql_first_cust);
                    if ($result_first_cust && $row_first = $result_first_cust->fetch_assoc()) {
                        $dummy_customer_id = $row_first['id_customer'];
                    } else {
                        throw new Exception("Tidak ada data customer di database. Tambahkan minimal 1 customer terlebih dahulu.");
                    }
                } else {
                    $stmt_cek_dummy->close();
                }

                // Declare variables for bind_param (FIXED: Only variables passed by reference)
                $metode_pembayaran_misc_var = 'Lain-lain';
                $sisa_pembayaran_misc_var = 0;
                $status_pelunasan_misc_var = 'Lunas';

                // 2. Buat entri "dummy" di tabel transaksi
                // Perbaikan: Hapus 'status_pelunasan' dari query INSERT transaksi dummy
                // Perbaikan: id_pesan adalah NULL di query, jadi tidak ada placeholder untuknya.
                // Jumlah parameter di query: 9
                $sql_dummy_transaksi = "INSERT INTO transaksi (id_transaksi, id_pesan, id_akun, id_customer, tgl_transaksi, jumlah_dibayar, metode_pembayaran, keterangan, total_tagihan, sisa_pembayaran) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?)";
                if ($stmt_dummy_transaksi = $conn->prepare($sql_dummy_transaksi)) {
                    $stmt_dummy_transaksi->bind_param(
                        "ssssissii", // 9 types: s (id_transaksi), s (id_akun), s (id_customer), s (tgl_transaksi), i (jumlah), s (metode), s (keterangan), i (total_tagihan), i (sisa_pembayaran)
                        $generated_id_transaksi,
                        $id_akun,
                        $dummy_customer_id,
                        $tgl_kas_masuk,
                        $jumlah,
                        $metode_pembayaran_misc_var, // Gunakan variabel
                        $keterangan,
                        $jumlah, // total_tagihan
                        $sisa_pembayaran_misc_var // Gunakan variabel (sisa_pembayaran)
                    );
                    if (!$stmt_dummy_transaksi->execute()) {
                        throw new Exception("Gagal membuat transaksi dummy: " . $stmt_dummy_transaksi->error);
                    }
                    $stmt_dummy_transaksi->close();
                } else {
                    throw new Exception("Error prepared statement (transaksi dummy): " . $conn->error);
                }

                // 3. Gunakan ID transaksi yang baru dibuat untuk INSERT ke kas_masuk
                // Tambahkan kolom harga jika belum ada, hapus kuantitas jika ada
                try {
                    // Cek apakah kolom harga sudah ada
                    $check_column_sql = "SHOW COLUMNS FROM kas_masuk LIKE 'harga'";
                    $column_result = $conn->query($check_column_sql);
                    if ($column_result->num_rows == 0) {
                        // Kolom harga belum ada, tambahkan
                        $conn->query("ALTER TABLE kas_masuk ADD COLUMN harga DECIMAL(15,2) DEFAULT 0");
                    }

                    // Hapus kolom kuantitas jika ada (tidak diperlukan lagi)
                    $check_kuantitas_sql = "SHOW COLUMNS FROM kas_masuk LIKE 'kuantitas'";
                    $kuantitas_result = $conn->query($check_kuantitas_sql);
                    if ($kuantitas_result->num_rows > 0) {
                        $conn->query("ALTER TABLE kas_masuk DROP COLUMN kuantitas");
                    }
                } catch (Exception $e) {
                    // Jika gagal mengubah kolom, lanjutkan saja
                }

                $sql = "INSERT INTO kas_masuk (id_kas_masuk, id_transaksi, tgl_kas_masuk, jumlah, keterangan, harga) VALUES (?, ?, ?, ?, ?, ?)";

                if ($stmt = $conn->prepare($sql)) {
                    $stmt->bind_param("sssisi", $id_kas_masuk, $generated_id_transaksi, $tgl_kas_masuk, $jumlah, $keterangan, $jumlah);

                    if ($stmt->execute()) {
                        $conn->commit(); // Commit transaksi jika semua berhasil
                        set_flash_message("Kas Masuk berhasil ditambahkan!", "success");
                        redirect('index.php'); // Redirect ke halaman daftar kas masuk
                    } else {
                        throw new Exception("Gagal menambahkan kas masuk: " . $stmt->error);
                    }
                    $stmt->close();
                } else {
                    throw new Exception("Error prepared statement: " . $conn->error);
                }
            } catch (Exception $e) {
                $conn->rollback(); // Rollback jika ada kesalahan
                set_flash_message("Error saat memproses kas masuk: " . $e->getMessage(), "error");
            }
            // --- AKHIR PERBAIKAN ---

        }
    } else {
        set_flash_message("Silakan perbaiki kesalahan pada formulir.", "error");
    }
}
?>

<div class="bg-white p-8 rounded-lg shadow-xl max-w-md mx-auto my-8">
    <h1 class="text-2xl font-bold text-gray-800 mb-4 text-center">Tambah Pemasukan Kas</h1>
    <p class="text-gray-600 mb-6 text-center">Isi formulir di bawah ini untuk mencatat pemasukan kas.</p>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="mb-4">
            <label for="id_kas_masuk" class="block text-gray-700 text-sm font-bold mb-2">ID Kas Masuk:</label>
            <input type="text" id="id_kas_masuk" name="id_kas_masuk" value="<?php echo htmlspecialchars($id_kas_masuk); ?>" required maxlength="8"
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $id_kas_masuk_error; ?></span>
        </div>
        <div class="mb-4">
            <label for="tgl_kas_masuk" class="block text-gray-700 text-sm font-bold mb-2">Tanggal:</label>
            <input type="date" id="tgl_kas_masuk" name="tgl_kas_masuk" value="<?php echo htmlspecialchars($tgl_kas_masuk); ?>" required
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $tgl_kas_masuk_error; ?></span>
        </div>
        <div class="mb-4">
            <label for="id_akun" class="block text-gray-700 text-sm font-bold mb-2">Nama Akun:</label>
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
            <label for="keterangan" class="block text-gray-700 text-sm font-bold mb-2">Keterangan:</label>
            <input type="text" id="keterangan" name="keterangan" value="<?php echo htmlspecialchars($keterangan); ?>" required maxlength="30"
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $keterangan_error; ?></span>
        </div>
        <div class="mb-6">
            <label for="jumlah" class="block text-gray-700 text-sm font-bold mb-2">Jumlah:</label>
            <input type="number" id="jumlah" name="jumlah" value="<?php echo htmlspecialchars($jumlah); ?>" required min="1" step="0.01"
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $jumlah_error; ?></span>
        </div>
        <div class="flex items-center justify-between">
            <button type="submit" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                TAMBAH
            </button>
            <a href="index.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                KELUAR
            </a>
        </div>
    </form>
</div>



<?php
// Sertakan footer
require_once '../../layout/footer.php';
?>