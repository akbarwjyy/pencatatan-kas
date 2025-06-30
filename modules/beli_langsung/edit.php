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
$id_transaksi = ''; // Akan diambil dari URL atau POST
$id_customer = '';
$id_akun = '';
$tgl_transaksi = '';
$jumlah_dibayar = 0;
$metode_pembayaran = '';
$keterangan = '';
$old_jumlah_dibayar = 0; // Untuk menyimpan jumlah lama dari DB

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

// Cek apakah ada ID transaksi yang dikirim melalui URL
if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $id_transaksi_dari_url = sanitize_input(trim($_GET['id']));

    // Ambil data transaksi berdasarkan ID (harus id_pesan IS NULL untuk beli_langsung)
    $sql_fetch = "SELECT id_transaksi, id_customer, id_akun, tgl_transaksi, jumlah_dibayar, metode_pembayaran, keterangan FROM transaksi WHERE id_transaksi = ? AND id_pesan IS NULL";
    if ($stmt_fetch = $conn->prepare($sql_fetch)) {
        $stmt_fetch->bind_param("s", $id_transaksi_dari_url);
        if ($stmt_fetch->execute()) {
            $stmt_fetch->store_result();
            if ($stmt_fetch->num_rows == 1) {
                $stmt_fetch->bind_result($id_transaksi, $id_customer, $id_akun, $tgl_transaksi, $jumlah_dibayar, $metode_pembayaran, $keterangan);
                $stmt_fetch->fetch();

                // Simpan nilai lama untuk log atau referensi
                $old_jumlah_dibayar = $jumlah_dibayar;
            } else {
                set_flash_message("Transaksi pembelian langsung tidak ditemukan.", "error");
                redirect('index.php'); // Redirect ke daftar beli langsung
            }
        } else {
            set_flash_message("Error saat mengambil data transaksi: " . $stmt_fetch->error, "error");
            redirect('index.php');
        }
        $stmt_fetch->close();
    } else {
        set_flash_message("Error prepared statement: " . $conn->error, "error");
        redirect('index.php');
    }
} else {
    set_flash_message("ID Transaksi tidak ditemukan.", "error");
    redirect('index.php'); // Redirect ke daftar beli langsung
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitasi input
    $id_transaksi_edit = sanitize_input($_POST['id_transaksi_asal']); // ID dari hidden input
    $id_customer = sanitize_input($_POST['id_customer'] ?? '');
    $id_akun = sanitize_input($_POST['id_akun'] ?? '');
    $tgl_transaksi = sanitize_input($_POST['tgl_transaksi'] ?? '');
    $jumlah_dibayar = sanitize_input($_POST['jumlah_dibayar'] ?? 0);
    $metode_pembayaran = sanitize_input($_POST['metode_pembayaran'] ?? '');
    $keterangan = sanitize_input($_POST['keterangan'] ?? '');

    // Validasi input (serupa dengan add.php)
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


    // Jika tidak ada error validasi, coba update ke database
    if (
        empty($id_customer_error) && empty($id_akun_error) && empty($tgl_transaksi_error) &&
        empty($jumlah_dibayar_error) && empty($metode_pembayaran_error) && empty($keterangan_error)
    ) {

        $conn->begin_transaction(); // Mulai transaksi database
        try {
            // --- 1. Update data di tabel `transaksi` ---
            // id_pesan tidak diupdate karena ini beli langsung (selalu NULL)
            $sql_transaksi_update = "UPDATE transaksi SET id_customer = ?, id_akun = ?, tgl_transaksi = ?, jumlah_dibayar = ?, metode_pembayaran = ?, keterangan = ? WHERE id_transaksi = ? AND id_pesan IS NULL";
            if ($stmt_transaksi_update = $conn->prepare($sql_transaksi_update)) {
                $stmt_transaksi_update->bind_param(
                    "sssisss",
                    $id_customer,
                    $id_akun,
                    $tgl_transaksi,
                    $jumlah_dibayar,
                    $metode_pembayaran,
                    $keterangan,
                    $id_transaksi_edit
                );

                if (!$stmt_transaksi_update->execute()) {
                    throw new Exception("Gagal memperbarui transaksi: " . $stmt_transaksi_update->error);
                }
                $stmt_transaksi_update->close();
            } else {
                throw new Exception("Error prepared statement (update transaksi): " . $conn->error);
            }

            // --- 2. Update data di tabel `kas_masuk` yang terkait ---
            // Diasumsikan 1 transaksi memiliki 1 kas_masuk. Update jumlah, tgl, keterangan
            $sql_kas_masuk_update = "UPDATE kas_masuk SET tgl_kas_masuk = ?, jumlah = ?, keterangan = ? WHERE id_transaksi = ?";
            $keterangan_kas_masuk_final = "Pembelian Langsung: " . $keterangan;

            if ($stmt_kas_masuk_update = $conn->prepare($sql_kas_masuk_update)) {
                $stmt_kas_masuk_update->bind_param("siss", $tgl_transaksi, $jumlah_dibayar, $keterangan_kas_masuk_final, $id_transaksi_edit);
                if (!$stmt_kas_masuk_update->execute()) {
                    throw new Exception("Gagal memperbarui entri kas masuk: " . $stmt_kas_masuk_update->error);
                }
                $stmt_kas_masuk_update->close();
            } else {
                throw new Exception("Error prepared statement (update kas_masuk): " . $conn->error);
            }

            // Commit transaksi jika semua berhasil
            $conn->commit();
            set_flash_message("Pembelian Langsung berhasil diperbarui!", "success");
            redirect('index.php'); // Redirect ke daftar beli langsung

        } catch (Exception $e) {
            // Rollback transaksi jika ada error
            $conn->rollback();
            set_flash_message("Error saat memperbarui pembelian langsung: " . $e->getMessage(), "error");
        }
    } else {
        set_flash_message("Silakan perbaiki kesalahan pada formulir.", "error");
    }
}
?>

<div class="bg-white p-8 rounded-lg shadow-xl max-w-md mx-auto my-8">
    <h1 class="text-2xl font-bold text-gray-800 mb-4 text-center">Edit Pembelian Langsung</h1>
    <p class="text-gray-600 mb-6 text-center">Ubah detail transaksi pembelian langsung di bawah ini.</p>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?id=" . htmlspecialchars($id_transaksi); ?>" method="post">
        <div class="mb-4">
            <label for="id_transaksi_display" class="block text-gray-700 text-sm font-bold mb-2">ID Transaksi:</label>
            <input type="text" id="id_transaksi_display" value="<?php echo htmlspecialchars($id_transaksi); ?>" disabled
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100 cursor-not-allowed">
            <input type="hidden" name="id_transaksi_asal" value="<?php echo htmlspecialchars($id_transaksi); ?>">
        </div>

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
        <div class="mb-4">
            <label for="keterangan" class="block text-gray-700 text-sm font-bold mb-2">Keterangan (misal: "Penjualan Ampyang 5pcs"):</label>
            <input type="text" id="keterangan" name="keterangan" value="<?php echo htmlspecialchars($keterangan); ?>" required maxlength="30"
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $keterangan_error; ?></span>
        </div>
        <div class="flex items-center justify-between mt-6">
            <button type="submit" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Simpan Perubahan
            </button>
            <a href="index.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Batal
            </a>
        </div>
    </form>
</div>

<?php
// Sertakan footer
require_once '../../layout/footer.php';
?>