<?php
// Sertakan header (ini akan melakukan session_start(), cek login, dan include koneksi/fungsi)
require_once '../../layout/header.php'; // Pastikan jalur ini adalah ../../

// Pastikan hanya Admin atau Pegawai yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pegawai')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php'); // Redirect ke dashboard
}

// Mencegah cache browser
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Pastikan kolom harga dan kuantitas ada di tabel kas_masuk
try {
    $columns_added = false;

    // Cek apakah kolom harga sudah ada
    $check_column_sql = "SHOW COLUMNS FROM kas_masuk LIKE 'harga'";
    $column_result = $conn->query($check_column_sql);
    if ($column_result->num_rows == 0) {
        // Kolom harga belum ada, tambahkan
        $conn->query("ALTER TABLE kas_masuk ADD COLUMN harga INT DEFAULT 0");
        $columns_added = true;
    }

    // Cek apakah kolom kuantitas sudah ada
    $check_column_sql = "SHOW COLUMNS FROM kas_masuk LIKE 'kuantitas'";
    $column_result = $conn->query($check_column_sql);
    if ($column_result->num_rows == 0) {
        // Kolom kuantitas belum ada, tambahkan
        $conn->query("ALTER TABLE kas_masuk ADD COLUMN kuantitas INT DEFAULT 0");
        $columns_added = true;
    }

    // Jika kolom baru ditambahkan, update harga dan kuantitas untuk data lama
    if ($columns_added) {
        // Update data lama: set harga = 12000 untuk semua record yang harga = 0
        $conn->query("UPDATE kas_masuk SET harga = 12000 WHERE harga = 0 OR harga IS NULL");
        // Update kuantitas berdasarkan jumlah dan harga, menggunakan CEIL untuk membulatkan ke atas
        $conn->query("UPDATE kas_masuk SET kuantitas = CEIL(jumlah / harga) WHERE harga > 0");
    }

    // Tidak perlu update kuantitas secara otomatis agar nilai yang dimasukkan user tetap terjaga
} catch (Exception $e) {
    // Jika gagal menambahkan kolom, lanjutkan saja
}

// Update kuantitas untuk data yang sudah ada jika belum diisi
try {
    $update_sql = "UPDATE kas_masuk km 
        LEFT JOIN transaksi tr ON km.id_transaksi = tr.id_transaksi
        LEFT JOIN pemesanan p ON tr.id_pesan = p.id_pesan
        SET km.kuantitas = CASE 
            WHEN p.quantity IS NOT NULL AND p.quantity > 0 THEN p.quantity
            WHEN km.harga > 0 THEN CEIL(km.jumlah / km.harga)
            ELSE 1
        END
        WHERE km.kuantitas = 0";
    $conn->query($update_sql);
} catch (Exception $e) {
    // Jika ada error pada update, lanjutkan saja
}

// Ambil semua data kas masuk dari database, join dengan transaksi dan akun
// Perhatikan: kas_masuk selalu punya id_transaksi berdasarkan struktur database
$sql = "SELECT km.*, tr.id_pesan, tr.jumlah_dibayar AS jumlah_transaksi, tr.total_tagihan, tr.id_akun,
        (SELECT nama_akun FROM akun WHERE id_akun = tr.id_akun) AS nama_akun,
        p.quantity AS pemesanan_quantity
        FROM kas_masuk km
        LEFT JOIN transaksi tr ON km.id_transaksi = tr.id_transaksi
        LEFT JOIN pemesanan p ON tr.id_pesan = p.id_pesan
        ORDER BY km.tgl_kas_masuk DESC"; // Urutkan berdasarkan tanggal terbaru

$cash_incomes = [];
$total_jumlah = 0; // Inisialisasi variabel total_jumlah

// Eksekusi query dengan pengecekan error
try {
    $result = $conn->query($sql);

    if ($result === false) {
        throw new Exception("Error executing query: " . $conn->error);
    }

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $cash_incomes[] = $row;
            $total_jumlah += (float)$row['jumlah']; // Menambahkan jumlah kas masuk (uang muka) ke total
        }
    }
} catch (Exception $e) {
    // Tampilkan pesan error dan tetap lanjutkan dengan array kosong
    set_flash_message("Error: " . $e->getMessage(), "error");
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Laporan Kas Masuk</h1>
        <p class="text-gray-600 mb-6">Daftar pemasukan kas dari pemesanan dan transaksi.</p>

        <?php if (empty($cash_incomes)) : ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6">
                <p class="font-medium">Belum ada data kas masuk yang tersedia.</p>
            </div>
        <?php else : ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Nama Akun</th>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Keterangan</th>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Harga</th>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Kuantitas</th>
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Jumlah</th>
                            <th class="px-3 py-2 border-b text-center text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($cash_incomes as $income) : ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 text-sm text-gray-500"><?php echo htmlspecialchars($income['id_kas_masuk']); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo htmlspecialchars($income['tgl_kas_masuk']); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php
                                                                            // Gunakan nama akun tetap berdasarkan ID akun
                                                                            $id_akun = $income['id_akun'] ?? '';
                                                                            switch ($id_akun) {
                                                                                case '1101':
                                                                                    echo 'Kas';
                                                                                    break;
                                                                                case '1102':
                                                                                    echo 'Bank';
                                                                                    break;
                                                                                case '1103':
                                                                                    echo 'Piutang Usaha';
                                                                                    break;
                                                                                case '1104':
                                                                                    echo 'Persediaan';
                                                                                    break;
                                                                                case '1105':
                                                                                    echo 'Perlengkapan';
                                                                                    break;
                                                                                case '1201':
                                                                                    echo 'Peralatan';
                                                                                    break;
                                                                                case '1202':
                                                                                    echo 'Akumulasi Penyusutan Peralatan';
                                                                                    break;
                                                                                case '2101':
                                                                                    echo 'Utang Usaha';
                                                                                    break;
                                                                                case '2102':
                                                                                    echo 'Utang Bank';
                                                                                    break;
                                                                                case '3101':
                                                                                    echo 'Modal Pemilik';
                                                                                    break;
                                                                                case '3102':
                                                                                    echo 'Prive';
                                                                                    break;
                                                                                case '4001':
                                                                                    echo 'Pendapatan';
                                                                                    break;
                                                                                case '5101':
                                                                                    echo 'Beban Gaji';
                                                                                    break;
                                                                                case '5102':
                                                                                    echo 'Beban Sewa';
                                                                                    break;
                                                                                case '5103':
                                                                                    echo 'Beban Listrik dan Air';
                                                                                    break;
                                                                                case '5104':
                                                                                    echo 'Beban Perlengkapan';
                                                                                    break;
                                                                                case '5105':
                                                                                    echo 'Beban Penyusutan Peralatan';
                                                                                    break;
                                                                                case '5106':
                                                                                    echo 'Beban Lain-lain';
                                                                                    break;
                                                                                default:
                                                                                    echo htmlspecialchars($income['nama_akun'] ?? 'N/A');
                                                                                    break;
                                                                            }
                                                                            ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo htmlspecialchars($income['keterangan']); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo format_rupiah($income['harga'] > 0 ? $income['harga'] : 12000); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php
                                                                            if ($income['kuantitas'] > 0) {
                                                                                echo $income['kuantitas'];
                                                                            } elseif ($income['pemesanan_quantity'] > 0) {
                                                                                echo $income['pemesanan_quantity'];
                                                                            } else {
                                                                                echo ceil($income['jumlah'] / ($income['harga'] > 0 ? $income['harga'] : 12000));
                                                                            }
                                                                            ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo format_rupiah($income['jumlah'] ?? 0); ?></td>
                                <td class="px-3 py-2 text-sm text-center">
                                    <span class="text-gray-500 text-xs">Otomatis</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-100 font-bold">
                            <td colspan="6" class="px-3 py-2 border-t text-right text-xs uppercase text-gray-700">Total:</td>
                            <td class="px-3 py-2 border-t text-sm text-gray-900"><?php echo format_rupiah($total_jumlah); ?></td>
                            <td class="px-3 py-2 border-t"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
?>