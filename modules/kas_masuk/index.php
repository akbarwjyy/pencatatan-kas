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

// Pastikan kolom harga ada di tabel kas_masuk dan hapus kolom kuantitas yang tidak diperlukan
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

    // Hapus kolom kuantitas yang tidak diperlukan
    $check_kuantitas_sql = "SHOW COLUMNS FROM kas_masuk LIKE 'kuantitas'";
    $kuantitas_result = $conn->query($check_kuantitas_sql);
    if ($kuantitas_result->num_rows > 0) {
        // Kolom kuantitas ada, hapus karena tidak diperlukan
        $conn->query("ALTER TABLE kas_masuk DROP COLUMN kuantitas");
    }

    // Jika kolom baru ditambahkan, update harga untuk data lama
    if ($columns_added) {
        // Logika update otomatis untuk data lama (jika perlu)
    }
} catch (Exception $e) {
    // Jika gagal mengubah kolom, lanjutkan saja
}

// Update harga untuk data yang sudah ada - perbaiki dengan harga satuan yang benar
try {
    // Update harga dengan prioritas: 1. Harga satuan dari detail_pemesanan, 2. Harga satuan dari detail_beli_langsung, 3. Default 12000
    $update_sql = "UPDATE kas_masuk km
        LEFT JOIN transaksi tr ON km.id_transaksi = tr.id_transaksi
        LEFT JOIN pemesanan p ON tr.id_pesan = p.id_pesan
        LEFT JOIN (
            SELECT dp.id_pesan, dp.harga_satuan_item
            FROM detail_pemesanan dp
            WHERE dp.id_detail_pesan = (
                SELECT MIN(dp2.id_detail_pesan) 
                FROM detail_pemesanan dp2 
                WHERE dp2.id_pesan = dp.id_pesan
            )
        ) dp_first ON p.id_pesan = dp_first.id_pesan
        LEFT JOIN (
            SELECT dbl.id_transaksi, dbl.harga_satuan_item
            FROM detail_beli_langsung dbl
            WHERE dbl.id_detail_beli = (
                SELECT MIN(dbl2.id_detail_beli)
                FROM detail_beli_langsung dbl2
                WHERE dbl2.id_transaksi = dbl.id_transaksi
            )
        ) dbl_first ON tr.id_transaksi = dbl_first.id_transaksi
        SET km.harga = CASE
            WHEN dp_first.harga_satuan_item IS NOT NULL AND dp_first.harga_satuan_item > 0
                THEN dp_first.harga_satuan_item
            WHEN dbl_first.harga_satuan_item IS NOT NULL AND dbl_first.harga_satuan_item > 0
                THEN dbl_first.harga_satuan_item
            ELSE 12000
        END
        WHERE km.harga IS NULL OR km.harga = 0 OR km.harga = km.jumlah"; // Termasuk yang harga = jumlah (bermasalah)
    $conn->query($update_sql);

    // Log untuk debugging
    $affected_rows = $conn->affected_rows;
    if ($affected_rows > 0) {
        error_log("Updated $affected_rows records in kas_masuk with correct unit prices");
    }
} catch (Exception $e) {
    // Jika ada error pada update, lanjutkan saja
    error_log("Error updating kas_masuk prices: " . $e->getMessage());
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
         ORDER BY dp_sub.id_detail_pesan ASC LIMIT 1) AS first_item_name,
        -- Subquery untuk mendapatkan harga satuan dari detail_beli_langsung
        (SELECT dbl_sub.harga_satuan_item
         FROM detail_beli_langsung dbl_sub
         WHERE dbl_sub.id_transaksi = tr.id_transaksi
         ORDER BY dbl_sub.id_detail_beli ASC LIMIT 1) AS beli_langsung_unit_price,
        (SELECT b_sub.nama_barang
         FROM detail_beli_langsung dbl_sub
         JOIN barang b_sub ON dbl_sub.id_barang = b_sub.id_barang
         WHERE dbl_sub.id_transaksi = tr.id_transaksi
         ORDER BY dbl_sub.id_detail_beli ASC LIMIT 1) AS beli_langsung_item_name
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
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Daftar Kas Masuk</h1>
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
                            <th class="px-3 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($cash_incomes as $income) : ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 text-sm text-gray-500"><?php echo htmlspecialchars($income['id_kas_masuk']); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo date('d/m/Y', strtotime($income['tgl_kas_masuk'])); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo htmlspecialchars($income['nama_akun'] ?? 'N/A'); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo htmlspecialchars($income['keterangan']); ?></td>
                                <td class="px-3 py-2 text-sm text-gray-900">
                                    <?php
                                    $display_price_label = "";
                                    $display_price_value = 0;

                                    // Prioritas: 1. Harga satuan dari pemesanan, 2. Harga satuan dari beli langsung, 3. Harga total
                                    if (!empty($income['first_item_unit_price']) && $income['first_item_unit_price'] > 0) {
                                        $display_price_label = "Satuan";
                                        $display_price_value = $income['first_item_unit_price'];
                                    } elseif (!empty($income['beli_langsung_unit_price']) && $income['beli_langsung_unit_price'] > 0) {
                                        $display_price_label = "Satuan";
                                        $display_price_value = $income['beli_langsung_unit_price'];
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
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo format_rupiah($income['jumlah'] ?? 0); ?></td>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-100 font-bold">
                            <td colspan="5" class="px-3 py-2 border-t text-right text-xs uppercase text-gray-700">Total:</td>
                            <td class="px-3 py-2 border-t text-sm text-gray-900"><?php echo format_rupiah($total_jumlah); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
?>