<?php
// Sertakan header
require_once '../../layout/header.php';

// Pastikan hanya Admin yang bisa mengakses halaman ini
if (!has_permission('Admin')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

$id_kas_keluar_error = $tgl_kas_keluar_error = $jumlah_error = $keterangan_error = $id_akun_error = "";
$id_kas_keluar = $tgl_kas_keluar = $jumlah = $keterangan = $id_akun = "";

// Ambil daftar akun untuk dropdown (ini penting karena id_akun adalah FK di kas_keluar)
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
    $id_kas_keluar = sanitize_input($_POST['id_kas_keluar']);
    $tgl_kas_keluar = sanitize_input($_POST['tgl_kas_keluar']);
    $id_akun = sanitize_input($_POST['id_akun']);
    $jumlah = sanitize_input($_POST['jumlah']);
    $keterangan = sanitize_input($_POST['keterangan']);

    // Validasi input
    if (empty($id_kas_keluar)) {
        $id_kas_keluar_error = "ID Kas Keluar tidak boleh kosong.";
    } elseif (strlen($id_kas_keluar) > 8) {
        $id_kas_keluar_error = "ID Kas Keluar maksimal 8 karakter.";
    }

    if (empty($tgl_kas_keluar)) {
        $tgl_kas_keluar_error = "Tanggal Kas Keluar tidak boleh kosong.";
    }

    if (empty($id_akun)) {
        $id_akun_error = "Akun tidak boleh kosong.";
    }

    if (empty($jumlah) || !is_numeric($jumlah) || $jumlah <= 0) {
        $jumlah_error = "Jumlah harus angka positif.";
    } else {
        $jumlah = (int)$jumlah;
    }

    if (empty($keterangan)) {
        $keterangan_error = "Keterangan tidak boleh kosong.";
    } elseif (strlen($keterangan) > 30) {
        $keterangan_error = "Keterangan maksimal 30 karakter.";
    }

    // Jika tidak ada error validasi, coba simpan ke database
    if (empty($id_kas_keluar_error) && empty($tgl_kas_keluar_error) && empty($id_akun_error) && empty($jumlah_error) && empty($keterangan_error)) {
        // Cek apakah id_kas_keluar sudah ada di database
        $check_sql = "SELECT id_kas_keluar FROM kas_keluar WHERE id_kas_keluar = ?";
        $stmt_check = $conn->prepare($check_sql);
        $stmt_check->bind_param("s", $id_kas_keluar);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $id_kas_keluar_error = "ID Kas Keluar sudah ada. Gunakan ID lain.";
            set_flash_message("Gagal menambahkan kas keluar: ID sudah ada.", "error");
        } else {
            $stmt_check->close();

            // Query untuk menambah data kas keluar
            $sql = "INSERT INTO kas_keluar (id_kas_keluar, id_akun, tgl_kas_keluar, jumlah, keterangan) VALUES (?, ?, ?, ?, ?)";

            // Gunakan prepared statement untuk keamanan
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("sssis", $id_kas_keluar, $id_akun, $tgl_kas_keluar, $jumlah, $keterangan);

                if ($stmt->execute()) {
                    set_flash_message("Kas Keluar berhasil ditambahkan!", "success");
                    redirect('index.php'); // Redirect ke halaman daftar kas keluar
                } else {
                    set_flash_message("Gagal menambahkan kas keluar: " . $stmt->error, "error");
                }
                $stmt->close();
            } else {
                set_flash_message("Error prepared statement: " . $conn->error, "error");
            }
        }
    } else {
        set_flash_message("Silakan perbaiki kesalahan pada formulir.", "error");
    }
}
?>

<div class="bg-white p-8 rounded-lg shadow-xl max-w-md mx-auto my-8">
    <h1 class="text-2xl font-bold text-gray-800 mb-4 text-center">Tambah Kas Keluar Baru</h1>
    <p class="text-gray-600 mb-6 text-center">Isi formulir di bawah ini untuk mencatat pengeluaran kas.</p>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="mb-4">
            <label for="id_kas_keluar" class="block text-gray-700 text-sm font-bold mb-2">ID Kas Keluar:</label>
            <input type="text" id="id_kas_keluar" name="id_kas_keluar" value="<?php echo htmlspecialchars($id_kas_keluar); ?>" required maxlength="8"
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $id_kas_keluar_error; ?></span>
        </div>
        <div class="mb-4">
            <label for="tgl_kas_keluar" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Kas Keluar:</label>
            <input type="date" id="tgl_kas_keluar" name="tgl_kas_keluar" value="<?php echo htmlspecialchars($tgl_kas_keluar); ?>" required
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $tgl_kas_keluar_error; ?></span>
        </div>
        <div class="mb-4">
            <label for="id_akun" class="block text-gray-700 text-sm font-bold mb-2">Akun Pengeluaran:</label>
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
            <label for="jumlah" class="block text-gray-700 text-sm font-bold mb-2">Jumlah (Rp):</label>
            <input type="number" id="jumlah" name="jumlah" value="<?php echo htmlspecialchars($jumlah); ?>" required min="1"
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $jumlah_error; ?></span>
        </div>
        <div class="mb-6">
            <label for="keterangan" class="block text-gray-700 text-sm font-bold mb-2">Keterangan (misal: "Gaji Karyawan", "Pembelian Bahan Baku"):</label>
            <input type="text" id="keterangan" name="keterangan" value="<?php echo htmlspecialchars($keterangan); ?>" required maxlength="30"
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $keterangan_error; ?></span>
        </div>
        <div class="flex items-center justify-between">
            <button type="submit"
                class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Simpan
            </button>
            <a href="index.php"
                class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Batal
            </a>
        </div>
    </form>
</div>

<?php
// Sertakan footer
require_once '../../layout/footer.php';
?>