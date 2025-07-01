<?php
// Sertakan header
require_once '../../layout/header.php';

// Pastikan hanya Admin atau Pegawai yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pegawai')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

// Inisialisasi variabel error
$id_pesan_error = $id_customer_error = $tgl_pesan_error = $tgl_kirim_error = $quantity_error = $uang_muka_error = $sub_total_error = $sisa_error = $harga_satuan_error = "";

// Inisialisasi variabel data. Variabel numerik diinisialisasi dengan 0.
$id_pesan = $id_customer = $tgl_pesan = $tgl_kirim = $keterangan = '';
$status_pembayaran = 'Belum Lunas';
$quantity = 0;
$uang_muka = 0;
$sub_total = 0;
$sisa = 0;
$harga_satuan_input = 12000;

// Ambil daftar customer untuk dropdown
$customers = [];
$customer_sql = "SELECT id_customer, nama_customer FROM customer ORDER BY nama_customer ASC";
$customer_result = $conn->query($customer_sql);
if ($customer_result->num_rows > 0) {
    while ($row = $customer_result->fetch_assoc()) {
        $customers[] = $row;
    }
}

// Ambil daftar akun untuk dropdown (tidak dipakai di proses insert)
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
    $id_pesan = sanitize_input($_POST['id_pesan'] ?? '');
    $id_customer = sanitize_input($_POST['id_customer'] ?? '');
    $tgl_pesan = sanitize_input($_POST['tgl_pesan'] ?? '');
    $tgl_kirim = sanitize_input($_POST['tgl_kirim'] ?? '');
    $quantity = sanitize_input($_POST['quantity'] ?? 0);
    $harga_satuan_input = sanitize_input($_POST['harga_satuan'] ?? 0);
    $uang_muka = sanitize_input($_POST['uang_muka'] ?? 0);
    $status_pembayaran = sanitize_input($_POST['status_pembayaran'] ?? 'Belum Lunas');
    $keterangan = sanitize_input($_POST['keterangan'] ?? '');

    // Validasi field satu per satu
    if (empty($id_pesan)) {
        $id_pesan_error = "ID Pesan tidak boleh kosong.";
    } elseif (strlen($id_pesan) > 8) {
        $id_pesan_error = "ID Pesan maksimal 8 karakter.";
    }
    if (empty($id_customer)) {
        $id_customer_error = "Customer tidak boleh kosong.";
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
        if ($uang_muka > $sub_total) {
            $uang_muka_error = "Uang Muka tidak boleh melebihi Sub Total.";
        } else {
            $sisa = $sub_total - $uang_muka;
        }
    } else {
        $sub_total = 0;
        $sisa = 0;
    }

    // Jika tidak ada error validasi, coba simpan ke database
    if (
        empty($id_pesan_error) && empty($id_customer_error) && empty($tgl_pesan_error) &&
        empty($tgl_kirim_error) && empty($quantity_error) && empty($uang_muka_error) && empty($harga_satuan_error)
    ) {
        // Cek apakah id_pesan sudah ada di database
        $check_sql = "SELECT id_pesan FROM pemesanan WHERE id_pesan = ?";
        $stmt_check = $conn->prepare($check_sql);
        $stmt_check->bind_param("s", $id_pesan);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $id_pesan_error = "ID Pesan sudah ada. Gunakan ID lain.";
            set_flash_message("Gagal menambahkan pemesanan: ID Pesan sudah ada.", "error");
        } else {
            $stmt_check->close();

            // Query untuk menambah data pemesanan (tanpa id_akun, tanpa harga_satuan)
            $sql = "INSERT INTO pemesanan (id_pesan, id_customer, tgl_pesan, tgl_kirim, quantity, uang_muka, sub_total, sisa) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ssssiiii", $id_pesan, $id_customer, $tgl_pesan, $tgl_kirim, $quantity, $uang_muka, $sub_total, $sisa);

                if ($stmt->execute()) {
                    $new_order_id = $id_pesan; // ID pesanan yang baru saja dimasukkan (karena ini input manual)

                    // Logika pengalihan berdasarkan tombol yang diklik
                    if (isset($_POST['submit_action']) && $_POST['submit_action'] === 'bayar') {
                        set_flash_message("Pemesanan berhasil ditambahkan! Silakan lengkapi pembayaran.", "success");
                        // Redirect ke halaman transaksi baru dengan ID pesanan yang baru
                        redirect('../../modules/transaksi/add.php?id_pesan=' . htmlspecialchars($new_order_id));
                    } else { // Jika tombol "TAMBAH" yang diklik
                        set_flash_message("Pemesanan berhasil ditambahkan!", "success");
                        redirect('index.php'); // Redirect ke halaman daftar pemesanan
                    }
                } else {
                    set_flash_message("Gagal menambahkan pemesanan: " . $stmt->error, "error");
                }
                $stmt->close();
            } else {
                set_flash_message("Error prepared statement: " . $conn->error, "error");
            }
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
                    <label for="id_pesan" class="block text-gray-700 text-sm font-bold mb-2">ID Pesan:</label>
                    <input type="text" id="id_pesan" name="id_pesan" value="<?php echo htmlspecialchars($id_pesan); ?>" required maxlength="8"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
                    <span class="text-red-500 text-xs italic mt-1 block"><?php echo $id_pesan_error; ?></span>
                </div>
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

            </div>

            <div>
                <div class="mb-4">
                    <label for="quantity" class="block text-gray-700 text-sm font-bold mb-2">Jumlah Ampyang:</label>
                    <input type="number" id="quantity" name="quantity" value="<?php echo htmlspecialchars($quantity); ?>" required min="1"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
                    <span class="text-red-500 text-xs italic mt-1 block"><?php echo $quantity_error; ?></span>
                </div>
                <div class="mb-4">
                    <label for="harga_satuan" class="block text-gray-700 text-sm font-bold mb-2">Harga satuan:</label>
                    <input type="number" id="harga_satuan" name="harga_satuan" value="<?php echo htmlspecialchars($harga_satuan_input); ?>" required min="1"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
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
                <div class="mb-6">
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
    document.getElementById('harga_satuan').addEventListener('input', hitungTotal);
    document.getElementById('uang_muka').addEventListener('input', hitungTotal);

    document.addEventListener('DOMContentLoaded', hitungTotal);
</script>

<?php
// Sertakan footer
require_once '../../layout/footer.php';
?>