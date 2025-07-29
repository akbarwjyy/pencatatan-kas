<?php
// Sertakan header (ini akan melakukan session_start(), cek login, dan include koneksi/fungsi)
require_once '../../layout/header.php';

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
        $conn->query("ALTER TABLE kas_masuk ADD COLUMN harga DECIMAL(15,2) DEFAULT 0"); // Pastikan DECIMAL
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
        // Logika update otomatis untuk data lama (jika perlu)
    }
} catch (Exception $e) {
    // Jika gagal menambahkan kolom, lanjutkan saja
}

// Update kuantitas untuk data yang sudah ada jika belum diisi
try {
    $update_sql = "UPDATE kas_masuk km
        LEFT JOIN transaksi tr ON km.id_transaksi = tr.id_transaksi
        LEFT JOIN pemesanan p ON tr.id_pesan = p.id_pesan
        SET km.kuantitas = CASE
            WHEN p.total_quantity IS NOT NULL AND p.total_quantity > 0 THEN p.total_quantity
            WHEN km.harga > 0 THEN CEIL(km.jumlah / km.harga)
            ELSE 1
        END
        WHERE km.kuantitas = 0";
    $conn->query($update_sql);
} catch (Exception $e) {
    // Jika ada error pada update, lanjutkan saja
}

// Ambil semua data kas masuk dari database, join dengan transaksi dan akun
$sql = "SELECT km.*, tr.id_pesan, tr.jumlah_dibayar AS jumlah_transaksi, tr.total_tagihan,
        (SELECT nama_akun FROM akun WHERE id_akun = tr.id_akun) AS nama_akun,
        p.total_quantity AS pemesanan_quantity,
        -- Subquery untuk mendapatkan harga satuan dan nama barang dari item pertama di pesanan
        (SELECT dp_sub.harga_satuan_item
         FROM detail_pemesanan dp_sub
         WHERE dp_sub.id_pesan = p.id_pesan
         ORDER BY dp_sub.id_detail_pesan ASC LIMIT 1) AS first_item_unit_price,
        (SELECT b_sub.nama_barang
         FROM detail_pemesanan dp_sub
         JOIN barang b_sub ON dp_sub.id_barang = b_sub.id_barang
         WHERE dp_sub.id_pesan = p.id_pesan
         ORDER BY dp_sub.id_detail_pesan ASC LIMIT 1) AS first_item_name
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
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo htmlspecialchars($income['nama_akun'] ?? 'N/A'); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo htmlspecialchars($income['keterangan']); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900">
                                    <?php
                                    $display_price_label = "";
                                    $display_price_value = 0;

                                    if (!empty($income['first_item_unit_price']) && $income['first_item_unit_price'] > 0) {
                                        $display_price_label = "Satuan";
                                        $display_price_value = $income['first_item_unit_price'];
                                    } elseif (!empty($income['harga']) && $income['harga'] > 0) {
                                        $display_price_label = "Total";
                                        $display_price_value = $income['harga'];
                                    } else {
                                        $display_price_label = "Default"; // Jika tidak ada harga yang relevan
                                        $display_price_value = 0; // Default ke 0 jika tidak ada harga
                                    }
                                    echo htmlspecialchars($display_price_label . ": ") . format_rupiah($display_price_value);
                                    ?>
                                </td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php
                                                                            if ($income['kuantitas'] > 0) {
                                                                                echo $income['kuantitas'];
                                                                            } elseif ($income['pemesanan_quantity'] > 0) {
                                                                                echo $income['pemesanan_quantity'];
                                                                            } else {
                                                                                // Fallback jika tidak ada kuantitas spesifik atau dari pesanan
                                                                                $calc_qty_denom = ($display_price_value > 0) ? $display_price_value : (($income['jumlah'] > 0) ? $income['jumlah'] : 1);
                                                                                echo ceil(($income['jumlah'] ?? 0) / $calc_qty_denom);
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