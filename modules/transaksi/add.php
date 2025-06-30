<?php
// Sertakan header
require_once '../../layout/header.php';

// Pastikan hanya Admin atau Pegawai yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pegawai')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

$id_transaksi_error = $id_pesan_error = $id_akun_error = $tgl_transaksi_error = $jumlah_dibayar_error = $metode_pembayaran_error = $keterangan_error = $status_pelunasan_error = ""; // Tambah status_pelunasan_error
$id_transaksi = $id_pesan = $id_akun = $tgl_transaksi = $jumlah_dibayar = $metode_pembayaran = $keterangan = "";
$total_tagihan_display = 0;
$sisa_pembayaran_display = 0;
$status_pelunasan_input = "";

// Ambil daftar pemesanan yang belum lunas atau partially paid untuk dropdown
$pemesanan_options = [];
$pemesanan_sql = "SELECT p.id_pesan, p.sub_total, p.sisa, c.nama_customer 
                  FROM pemesanan p 
                  JOIN customer c ON p.id_customer = c.id_customer
                  WHERE p.sisa > 0 
                  ORDER BY p.tgl_pesan DESC";
$pemesanan_result = $conn->query($pemesanan_sql);
if ($pemesanan_result->num_rows > 0) {
    while ($row = $pemesanan_result->fetch_assoc()) {
        $pemesanan_options[] = $row;
    }
}

// Ambil daftar akun untuk dropdown (Ini masih diperlukan di PHP untuk proses transaksi, meski tidak di form)
$accounts = [];
$account_sql = "SELECT id_akun, nama_akun FROM akun ORDER BY nama_akun ASC";
$account_result = $conn->query($account_sql);
if ($account_result->num_rows > 0) {
    while ($row = $account_result->fetch_assoc()) {
        $accounts[] = $row;
    }
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // === Ambil input dari form, dengan operator ?? untuk mencegah Undefined index ===
    // id_transaksi akan digenerate, tidak dari POST
    $id_pesan = sanitize_input($_POST['id_pesan'] ?? '');
    // $id_akun = sanitize_input($_POST['id_akun'] ?? ''); // Dihapus dari form
    $tgl_transaksi = sanitize_input($_POST['tgl_transaksi'] ?? '');
    $jumlah_dibayar = sanitize_input($_POST['jumlah_dibayar'] ?? 0);
    $metode_pembayaran = sanitize_input($_POST['metode_pembayaran'] ?? '');
    $keterangan = sanitize_input($_POST['keterangan'] ?? '');
    $status_pelunasan_input = sanitize_input($_POST['status_pelunasan_input'] ?? ''); // Ambil status dari input baru

    // === Validasi Input ===
    // Validasi id_transaksi dihapus karena otomatis

    if (empty($id_pesan)) {
        $id_pesan_error = "ID Pesan tidak boleh kosong.";
    }
    // Asumsi id_akun akan diambil dari akun pertama yang tersedia jika tidak diinput manual
    $id_akun = !empty($accounts) ? $accounts[0]['id_akun'] : 'AK001'; // Default ke akun pertama atau 'AK001'

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

    if (strlen($keterangan) > 30) {
        $keterangan_error = "Keterangan maksimal 30 karakter.";
    }

    if (empty($status_pelunasan_input)) {
        $status_pelunasan_error = "Status Pembayaran tidak boleh kosong.";
    } elseif (!in_array($status_pelunasan_input, ['Lunas', 'Belum Lunas'])) {
        $status_pelunasan_error = "Status Pembayaran tidak valid.";
    }


    // Ambil detail pemesanan untuk validasi jumlah dibayar
    $current_sisa_pemesanan = 0;
    $total_tagihan_pemesanan = 0;
    $id_customer_related = '';
    $tgl_kirim_related = '';
    if (!empty($id_pesan)) {
        $pemesanan_detail_sql = "SELECT sisa, sub_total, id_customer, tgl_kirim FROM pemesanan WHERE id_pesan = ?";
        if ($stmt_pemesanan = $conn->prepare($pemesanan_detail_sql)) {
            $stmt_pemesanan->bind_param("s", $id_pesan);
            $stmt_pemesanan->execute();
            $stmt_pemesanan->bind_result($current_sisa_pemesanan, $total_tagihan_pemesanan, $id_customer_related, $tgl_kirim_related);
            $stmt_pemesanan->fetch();
            $stmt_pemesanan->close();

            if ($jumlah_dibayar > $current_sisa_pemesanan) {
                // Komen ini karena status bisa diubah manual, jadi validasi ini mungkin tidak relevan jika user ingin set 'Belum Lunas'
            }
            $total_tagihan_display = $total_tagihan_pemesanan;
            $sisa_pembayaran_display = $current_sisa_pemesanan - $jumlah_dibayar;
        } else {
            set_flash_message("Error saat mengambil detail pemesanan: " . $conn->error, "error");
            $id_pesan_error = "Gagal mengambil detail pemesanan.";
        }
    }


    // Jika tidak ada error validasi, coba simpan ke database
    if (
        empty($id_pesan_error) && empty($tgl_transaksi_error) &&
        empty($jumlah_dibayar_error) && empty($metode_pembayaran_error) && empty($keterangan_error) && empty($status_pelunasan_error)
    ) {
        // === Generate ID Transaksi Otomatis ===
        $generated_id_transaksi = 'TRX' . strtoupper(substr(uniqid(), 0, 5)); // Contoh: TRX6A8B1

        // Cek apakah ID yang digenerate sudah ada (pencegahan bentrok walau kecil)
        $check_gen_id_sql = "SELECT id_transaksi FROM transaksi WHERE id_transaksi = ?";
        $stmt_check_gen_id = $conn->prepare($check_gen_id_sql);
        if ($stmt_check_gen_id === false) {
            set_flash_message("Error menyiapkan pengecekan ID transaksi: " . $conn->error, "error");
        } else {
            $stmt_check_gen_id->bind_param("s", $generated_id_transaksi);
            $stmt_check_gen_id->execute();
            $stmt_check_gen_id->store_result();
            if ($stmt_check_gen_id->num_rows > 0) {
                $generated_id_transaksi = 'TRX' . strtoupper(substr(uniqid(rand(), true), 0, 5));
                set_flash_message("ID Transaksi otomatis bentrok, mencoba lagi. Mohon submit ulang jika error berlanjut.", "warning");
            }
            $stmt_check_gen_id->close();
        }

        // Mulai transaksi database untuk memastikan konsistensi
        $conn->begin_transaction();
        try {
            // Tentukan status pelunasan untuk transaksi ini (mengambil dari input)
            $status_pelunasan_final = $status_pelunasan_input; // Mengambil dari input form

            // Perbaikan: Hapus 'status_pelunasan' dari query INSERT transaksi
            $sql_transaksi = "INSERT INTO transaksi (id_transaksi, id_pesan, id_akun, id_customer, tgl_transaksi, jumlah_dibayar, metode_pembayaran, keterangan, total_tagihan, sisa_pembayaran) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            if ($stmt_transaksi = $conn->prepare($sql_transaksi)) {
                $stmt_transaksi->bind_param(
                    "sssssisssi", // Disesuaikan, 10 parameter
                    $generated_id_transaksi,
                    $id_pesan,
                    $id_akun,
                    $id_customer_related,
                    $tgl_transaksi,
                    $jumlah_dibayar,
                    $metode_pembayaran,
                    $keterangan,
                    $total_tagihan_pemesanan,
                    $sisa_pembayaran_display
                );

                if (!$stmt_transaksi->execute()) {
                    throw new Exception("Gagal menambahkan transaksi: " . $stmt_transaksi->error);
                }
                $stmt_transaksi->close();
            } else {
                throw new Exception("Error prepared statement (transaksi): " . $conn->error);
            }

            // Update sisa pembayaran di tabel pemesanan (berdasarkan sisa_pembayaran_display)
            // Perbaikan: Ubah 'status_pelunasan' menjadi 'status_pesanan' untuk tabel pemesanan
            $sql_update_pemesanan = "UPDATE pemesanan SET sisa = ?, status_pesanan = ? WHERE id_pesan = ?";
            if ($stmt_update_pemesanan = $conn->prepare($sql_update_pemesanan)) {
                $stmt_update_pemesanan->bind_param("iss", $sisa_pembayaran_display, $status_pelunasan_final, $id_pesan);
                if (!$stmt_update_pemesanan->execute()) {
                    throw new Exception("Gagal memperbarui pemesanan: " . $stmt_update_pemesanan->error);
                }
                $stmt_update_pemesanan->close();
            } else {
                throw new Exception("Error prepared statement (update pemesanan): " . $conn->error);
            }

            // Tambahkan entri ke tabel kas_masuk
            $sql_kas_masuk = "INSERT INTO kas_masuk (id_kas_masuk, id_transaksi, tgl_kas_masuk, jumlah, keterangan) VALUES (?, ?, ?, ?, ?)";

            // Generate ID kas masuk dengan format KMxxxxxx (8 karakter)
            $latest_km_sql = "SELECT MAX(CAST(SUBSTRING(id_kas_masuk, 3) AS UNSIGNED)) as last_num FROM kas_masuk WHERE id_kas_masuk LIKE 'KM%'";
            $latest_km_result = $conn->query($latest_km_sql);
            $last_num = 0;
            if ($latest_km_result && $row = $latest_km_result->fetch_assoc()) {
                $last_num = intval($row['last_num']);
            }
            $new_num = $last_num + 1;
            $id_kas_masuk = sprintf("KM%06d", $new_num); // Format: KM000001, KM000002, dst.

            $keterangan_kas_masuk = "Pembayaran " . $keterangan . " untuk Pesanan " . $id_pesan;

            if ($stmt_kas_masuk = $conn->prepare($sql_kas_masuk)) {
                $stmt_kas_masuk->bind_param("sssis", $id_kas_masuk, $generated_id_transaksi, $tgl_transaksi, $jumlah_dibayar, $keterangan_kas_masuk);
                if (!$stmt_kas_masuk->execute()) {
                    throw new Exception("Gagal menambahkan entri kas masuk: " . $stmt_kas_masuk->error);
                }
                $stmt_kas_masuk->close();
            } else {
                throw new Exception("Error prepared statement (kas_masuk): " . $conn->error);
            }

            // Commit transaksi jika semua berhasil
            $conn->commit();
            set_flash_message("Transaksi dan Kas Masuk berhasil ditambahkan! Status Pelunasan: " . $status_pelunasan_final, "success");
            redirect('index.php');
        } catch (Exception $e) {
            // Rollback transaksi jika ada error
            $conn->rollback();
            set_flash_message("Error saat memproses transaksi: " . $e->getMessage(), "error");
        }
    } else {
        set_flash_message("Silakan perbaiki kesalahan pada formulir.", "error");
    }
}
?>

<div class="bg-white p-8 rounded-lg shadow-xl max-w-lg mx-auto my-8">
    <h1 class="text-2xl font-bold text-gray-800 mb-4 text-center">Tambah Transaksi Baru</h1>
    <p class="text-gray-600 mb-6 text-center">Isi formulir di bawah ini untuk mencatat pembayaran dari pemesanan.</p>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
            <div>
                <div class="mb-4">
                    <label for="id_pesan" class="block text-gray-700 text-sm font-bold mb-2">ID Pesan:</label>
                    <select id="id_pesan" name="id_pesan" required onchange="updatePemesananInfo()"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
                        <option value="">-- Pilih Pemesanan --</option>
                        <?php foreach ($pemesanan_options as $option) : ?>
                            <option value="<?php echo htmlspecialchars($option['id_pesan']); ?>"
                                data-subtotal="<?php echo htmlspecialchars($option['sub_total']); ?>"
                                data-sisa="<?php echo htmlspecialchars($option['sisa']); ?>"
                                data-customername="<?php echo htmlspecialchars($option['nama_customer']); ?>"
                                <?php echo ($id_pesan == $option['id_pesan']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($option['nama_customer'] . " - " . format_rupiah($option['sub_total']) . " - Sisa: " . format_rupiah($option['sisa'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="text-red-500 text-xs italic mt-1 block"><?php echo $id_pesan_error; ?></span>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Nama Customer:</label>
                    <input type="text" id="nama_customer_display" value="" disabled
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100 cursor-not-allowed">
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
                <div class="mb-6">
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
            </div>

            <div>
                <div class="mb-6">
                    <label for="keterangan" class="block text-gray-700 text-sm font-bold mb-2">Keterangan:</label>
                    <input type="text" id="keterangan" name="keterangan" value="<?php echo htmlspecialchars($keterangan); ?>" maxlength="30"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
                    <span class="text-red-500 text-xs italic mt-1 block"><?php echo $keterangan_error; ?></span>
                </div>

                <div class="mb-4">
                    <label for="status_pelunasan_input" class="block text-gray-700 text-sm font-bold mb-2">Status Pembayaran:</label>
                    <select id="status_pelunasan_input" name="status_pelunasan_input" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
                        <option value="">-- Pilih Status --</option>
                        <option value="Lunas" <?php echo ($sisa_pembayaran_display == 0) ? 'selected' : (($status_pelunasan_input == 'Lunas') ? 'selected' : ''); ?>>Lunas</option>
                        <option value="Belum Lunas" <?php echo ($sisa_pembayaran_display != 0) ? 'selected' : (($status_pelunasan_input == 'Belum Lunas') ? 'selected' : ''); ?>>Belum Lunas</option>
                    </select>
                    <span class="text-red-500 text-xs italic mt-1 block"><?php echo $status_pelunasan_error; ?></span>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Total Tagihan:</label>
                    <input type="text" id="total_tagihan_display" value="<?php echo format_rupiah($total_tagihan_display); ?>" disabled
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100 cursor-not-allowed">
                </div>
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Sisa Pembayaran:</label>
                    <input type="text" id="sisa_pembayaran_display" value="<?php echo format_rupiah($sisa_pembayaran_display); ?>" disabled
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100 cursor-not-allowed">
                </div>
            </div>
        </div>

        <div class="flex items-center justify-center space-x-4 mt-6">
            <button type="submit" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Simpan
            </button>
            <a href="index.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                KELUAR
            </a>
            <button type="button" onclick="window.print()" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Cetak Bukti Pembayaran
            </button>
        </div>
    </form>
</div>

<script>
    // Fungsi untuk mengupdate info tagihan saat pemesanan dipilih
    function updatePemesananInfo() {
        const selectElement = document.getElementById('id_pesan');
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        // Pastikan nilai default 0 jika data-subtotal atau data-sisa tidak ada
        const subTotal = parseFloat(selectedOption.dataset.subtotal || 0);
        const sisaAwal = parseFloat(selectedOption.dataset.sisa || 0);
        const customerName = selectedOption.dataset.customername || ''; // Ambil nama customer

        document.getElementById('total_tagihan_display').value = formatRupiah(subTotal);
        document.getElementById('nama_customer_display').value = customerName; // Isi nama customer
        document.getElementById('jumlah_dibayar').max = sisaAwal; // Set max input jumlah_dibayar
        document.getElementById('jumlah_dibayar').value = sisaAwal; // Default ke sisa_awal
        updateSisaSetelahPembayaran();

        // Update default selection for Status Pembayaran dropdown based on sisaAwal
        const statusSelect = document.getElementById('status_pelunasan_input');
        if (sisaAwal === 0) {
            statusSelect.value = 'Lunas';
        } else {
            statusSelect.value = 'Belum Lunas';
        }
    }

    // Fungsi untuk mengupdate sisa pembayaran setelah input jumlah dibayar
    document.getElementById('jumlah_dibayar').addEventListener('input', updateSisaSetelahPembayaran);

    function updateSisaSetelahPembayaran() {
        const selectElement = document.getElementById('id_pesan');
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        const sisaAwal = parseFloat(selectedOption.dataset.sisa || 0);
        const jumlahDibayar = parseFloat(document.getElementById('jumlah_dibayar').value || 0);

        const sisaSetelahIni = sisaAwal - jumlahDibayar;
        document.getElementById('sisa_pembayaran_display').value = formatRupiah(sisaSetelahIni);

        // Update Status Pembayaran dropdown based on calculated sisaSetelahIni
        const statusSelect = document.getElementById('status_pelunasan_input');
        if (sisaSetelahIni === 0) {
            statusSelect.value = 'Lunas';
        } else {
            statusSelect.value = 'Belum Lunas';
        }
    }

    // Format Rupiah di sisi klien (JavaScript)
    function formatRupiah(angka) {
        var reverse = angka.toString().split('').reverse().join(''),
            ribuan = reverse.match(/\d{1,3}/g);
        ribuan = ribuan.join('.').split('').reverse().join('');
        return 'Rp ' + ribuan;
    }

    // Panggil saat halaman pertama kali dimuat untuk mengisi nilai awal jika ada selected option
    document.addEventListener('DOMContentLoaded', updatePemesananInfo);
</script>