<?php
// Sertakan header
require_once '../../layout/header.php';

// Pastikan hanya Admin yang bisa mengakses halaman ini
if (!has_permission('Admin')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

$id_kas_keluar_error = $tgl_kas_keluar_error = $jumlah_error = $keterangan_error = $id_akun_error = "";
$harga_error = $kuantitas_error = ""; // Tambahan variabel error baru
$id_kas_keluar = $tgl_kas_keluar = $keterangan = $id_akun = "";
$harga = 0; // Inisialisasi variabel baru
$kuantitas = 0; // Inisialisasi variabel baru
$jumlah = 0; // Jumlah akan dihitung

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
    $id_kas_keluar = sanitize_input($_POST['id_kas_keluar'] ?? '');
    $tgl_kas_keluar = sanitize_input($_POST['tgl_kas_keluar'] ?? '');
    $id_akun = sanitize_input($_POST['id_akun'] ?? '');
    $keterangan = sanitize_input($_POST['keterangan'] ?? '');
    $harga = sanitize_input($_POST['harga'] ?? 0); // Input baru
    $kuantitas = sanitize_input($_POST['kuantitas'] ?? 0); // Input baru

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

    // Validasi Harga dan Kuantitas
    if (!is_numeric($harga) || $harga <= 0) {
        $harga_error = "Harga harus angka positif.";
    } else {
        $harga = (int)$harga;
    }

    if (!is_numeric($kuantitas) || $kuantitas <= 0) {
        $kuantitas_error = "Kuantitas harus angka positif.";
    } else {
        $kuantitas = (int)$kuantitas;
    }

    // Hitung jumlah setelah validasi harga dan kuantitas
    $jumlah = $harga * $kuantitas;

    if (empty($keterangan)) {
        $keterangan_error = "Keterangan tidak boleh kosong.";
    } elseif (strlen($keterangan) > 30) {
        $keterangan_error = "Keterangan maksimal 30 karakter.";
    }

    // Validasi jumlah akhir (setelah dihitung)
    if ($jumlah <= 0) {
        $jumlah_error = "Jumlah harus lebih dari 0.";
    }

    // Jika tidak ada error validasi, coba simpan ke database
    if (
        empty($id_kas_keluar_error) && empty($tgl_kas_keluar_error) && empty($id_akun_error) &&
        empty($harga_error) && empty($kuantitas_error) && empty($jumlah_error) && empty($keterangan_error)
    ) {
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
            // $sql = "INSERT INTO kas_keluar (id_kas_keluar, id_akun, tgl_kas_keluar, jumlah, keterangan) VALUES (?, ?, ?, ?, ?)";
            // Di sini, kita sudah punya $jumlah yang dihitung dari harga * kuantitas

            if ($stmt = $conn->prepare("INSERT INTO kas_keluar (id_kas_keluar, id_akun, tgl_kas_keluar, jumlah, keterangan) VALUES (?, ?, ?, ?, ?)")) {
                $stmt->bind_param("sssis", $id_kas_keluar, $id_akun, $tgl_kas_keluar, $jumlah, $keterangan); // $jumlah sudah dihitung

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
    <h1 class="text-2xl font-bold text-gray-800 mb-4 text-center">Tambah Pengeluaran Kas</h1>
    <p class="text-gray-600 mb-6 text-center">Isi formulir di bawah ini untuk mencatat pengeluaran kas.</p>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="mb-4">
            <label for="id_kas_keluar" class="block text-gray-700 text-sm font-bold mb-2">ID Kas Keluar:</label>
            <input type="text" id="id_kas_keluar" name="id_kas_keluar" value="<?php echo htmlspecialchars($id_kas_keluar); ?>" required maxlength="8"
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $id_kas_keluar_error; ?></span>
        </div>
        <div class="mb-4">
            <label for="tgl_kas_keluar" class="block text-gray-700 text-sm font-bold mb-2">Tanggal:</label>
            <input type="date" id="tgl_kas_keluar" name="tgl_kas_keluar" value="<?php echo htmlspecialchars($tgl_kas_keluar); ?>" required
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $tgl_kas_keluar_error; ?></span>
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
        <div class="mb-4">
            <label for="harga" class="block text-gray-700 text-sm font-bold mb-2">Harga:</label>
            <input type="number" id="harga" name="harga" value="<?php echo htmlspecialchars($harga); ?>" required min="1"
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $harga_error; ?></span>
        </div>
        <div class="mb-4">
            <label for="kuantitas" class="block text-gray-700 text-sm font-bold mb-2">Kuantitas:</label>
            <input type="number" id="kuantitas" name="kuantitas" value="<?php echo htmlspecialchars($kuantitas); ?>" required min="1"
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $kuantitas_error; ?></span>
        </div>
        <div class="mb-6">
            <label for="jumlah_display" class="block text-gray-700 text-sm font-bold mb-2">Jumlah:</label>
            <input type="text" id="jumlah_display" value="<?php echo format_rupiah($jumlah); ?>" disabled
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100 cursor-not-allowed">
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

<script>
    // Fungsi untuk menghitung jumlah otomatis
    function calculateJumlah() {
        const hargaInput = document.getElementById('harga');
        const kuantitasInput = document.getElementById('kuantitas');
        const jumlahDisplay = document.getElementById('jumlah_display');

        const harga = parseFloat(hargaInput.value) || 0;
        const kuantitas = parseFloat(kuantitasInput.value) || 0;

        const calculatedJumlah = harga * kuantitas;
        jumlahDisplay.value = formatRupiah(calculatedJumlah);
    }

    // Tambahkan event listener untuk memanggil fungsi perhitungan saat input berubah
    document.getElementById('harga').addEventListener('input', calculateJumlah);
    document.getElementById('kuantitas').addEventListener('input', calculateJumlah);

    // Panggil fungsi perhitungan saat halaman dimuat untuk nilai awal
    document.addEventListener('DOMContentLoaded', calculateJumlah);

    // Format Rupiah di sisi klien (JavaScript) (fungsi ini sudah ada di transaksi/add.php, bisa dipindahkan ke script.js jika umum)
    function formatRupiah(angka) {
        var reverse = angka.toString().split('').reverse().join(''),
            ribuan = reverse.match(/\d{1,3}/g);
        ribuan = ribuan.join('.').split('').reverse().join('');
        return 'Rp ' + ribuan;
    }
</script>

<?php
?>