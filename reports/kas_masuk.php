<?php
// Sertakan header (ini akan melakukan session_start(), cek login, dan include koneksi/fungsi)
require_once '../layout/header.php';

// Pastikan hanya Admin, Pemilik, atau Pegawai yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pemilik') && !has_permission('Pegawai')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
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

<div class="container mx-auto px-4 py-8" id="report-container">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Laporan Pemasukan Kas Per Periode</h1>
        <p class="text-gray-600 mb-6">Lihat daftar pemasukan kas berdasarkan periode tanggal.</p>

        <form action="" method="get" class="mb-6 p-4 bg-gray-50 rounded-lg shadow-sm flex flex-wrap items-end gap-4 print-hidden">
            <div class="flex-1 min-w-[200px]">
                <label for="start_date" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Awal:</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="flex-1 min-w-[200px]">
                <label for="end_date" class="block text-gray-700 text-sm font-bold mb-2">Tanggal Akhir:</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <button type="submit"
                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Filter
                </button>
                <a href="kas_masuk.php"
                    class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline ml-2">
                    Reset Filter
                </a>
                <button type="button" onclick="window.print()"
                    class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline ml-2 print-hidden">
                    Cetak Laporan
                </button>
            </div>
        </form>

        <style>
            @media print {

                /* Hide everything except the report container */
                body * {
                    visibility: hidden !important;
                }

                #report-container,
                #report-container * {
                    visibility: visible !important;
                }

                #report-container {
                    position: absolute;
                    left: 0;
                    top: 0;
                    width: 100%;
                    margin: 0 !important;
                    padding: 10px !important;
                }

                /* Explicitly hide header, nav, and common layout elements */
                header,
                nav,
                .navbar,
                .header,
                .topbar,
                .sidebar,
                footer,
                .print-hidden {
                    display: none !important;
                }

                /* Remove unnecessary styling for print */
                .bg-white {
                    background: white !important;
                }

                .bg-gray-50,
                .bg-gray-100,
                .bg-yellow-100 {
                    background: white !important;
                    border: none !important;
                }

                .shadow-md,
                .shadow-sm {
                    box-shadow: none !important;
                }

                .rounded-lg {
                    border-radius: 0 !important;
                }

                .border {
                    border: 1px solid black !important;
                }

                .px-4,
                .py-8,
                .p-6 {
                    padding: 4px !important;
                }

                .mb-6,
                .mb-4 {
                    margin-bottom: 4px !important;
                }

                .text-gray-600,
                .text-gray-800,
                .text-gray-500,
                .text-gray-900,
                .text-yellow-700 {
                    color: black !important;
                }

                .text-xs {
                    font-size: 10px !important;
                }

                .text-sm {
                    font-size: 11px !important;
                }

                .text-xl,
                .text-2xl {
                    font-size: 14px !important;
                }

                .overflow-x-auto {
                    overflow: visible !important;
                }

                .min-w-full {
                    width: 100% !important;
                }

                table {
                    border-collapse: collapse !important;
                }

                th,
                td {
                    border: 1px solid black !important;
                    padding: 4px !important;
                }

                thead {
                    display: table-header-group !important;
                    /* Ensure table header is printed */
                }
            }
        </style>

        <?php if (empty($cash_incomes)) : ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6">
                <p class="font-medium">Tidak ada data kas masuk yang ditemukan untuk periode ini.</p>
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
                                    $display_price_value = 0;

                                    // Prioritas 1: Jika ada harga satuan dari detail_pemesanan (untuk transaksi pemesanan)
                                    if (isset($income['first_item_unit_price']) && $income['first_item_unit_price'] > 0) {
                                        $display_price_value = $income['first_item_unit_price'];
                                    }
                                    // Prioritas 2: Jika id_pesan kosong (transaksi beli langsung atau kas masuk manual)
                                    elseif (empty($income['id_pesan'])) {
                                        if (isset($income['kuantitas']) && $income['kuantitas'] > 0 && isset($income['harga']) && $income['harga'] > 0) {
                                            // Heuristik: Jika 'harga' yang tercatat sama dengan 'jumlah' total pembayaran,
                                            // dan kuantitas lebih dari 0, ini kemungkinan adalah transaksi pembelian langsung
                                            // di mana 'harga' menyimpan total, dan kita perlu menghitung harga per unit.
                                            if (abs($income['harga'] - $income['jumlah']) < 0.01 && $income['kuantitas'] > 0) {
                                                $display_price_value = $income['jumlah'] / $income['kuantitas'];
                                            } else {
                                                // Jika tidak, asumsikan 'harga' sudah merupakan harga satuan (untuk kas masuk manual)
                                                $display_price_value = $income['harga'];
                                            }
                                        } else {
                                            // Fallback jika kuantitas atau harga tidak valid, tampilkan harga saja jika ada
                                            $display_price_value = $income['harga'] ?? 0;
                                        }
                                    }
                                    // Prioritas 3: Fallback jika tidak ada kondisi spesifik yang cocok, gunakan harga dari kas_masuk langsung
                                    else {
                                        $display_price_value = $income['harga'] ?? 0;
                                    }

                                    echo format_rupiah($display_price_value);
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