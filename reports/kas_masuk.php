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

// Ambil tanggal dari GET, kalau kosong pakai default awal & akhir bulan ini
$start_date = isset($_GET['start_date']) && !empty($_GET['start_date'])
    ? sanitize_input($_GET['start_date'])
    : date('Y-m-01'); // awal bulan ini
$end_date = isset($_GET['end_date']) && !empty($_GET['end_date'])
    ? sanitize_input($_GET['end_date'])
    : date('Y-m-t'); // akhir bulan ini

// Pastikan kolom harga dan kuantitas ada di tabel kas_masuk
try {
    $columns_added = false;

    // Cek apakah kolom harga sudah ada
    $check_column_sql = "SHOW COLUMNS FROM kas_masuk LIKE 'harga'";
    $column_result = $conn->query($check_column_sql);
    if ($column_result->num_rows == 0) {
        $conn->query("ALTER TABLE kas_masuk ADD COLUMN harga DECIMAL(15,2) DEFAULT 0");
        $columns_added = true;
    }

    // Cek apakah kolom kuantitas sudah ada
    $check_column_sql = "SHOW COLUMNS FROM kas_masuk LIKE 'kuantitas'";
    $column_result = $conn->query($check_column_sql);
    if ($column_result->num_rows == 0) {
        $conn->query("ALTER TABLE kas_masuk ADD COLUMN kuantitas INT DEFAULT 0");
        $columns_added = true;
    }

    if ($columns_added) {
        // Jika perlu, isi kolom baru untuk data lama
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

// Ambil semua data kas masuk sesuai filter tanggal
$sql = "SELECT km.*, tr.id_pesan, tr.jumlah_dibayar AS jumlah_transaksi, tr.total_tagihan,
        (SELECT nama_akun FROM akun WHERE id_akun = tr.id_akun) AS nama_akun,
        -- Data untuk pemesanan
        p.total_quantity AS pemesanan_quantity,
        (SELECT dp_sub.harga_satuan_item
         FROM detail_pemesanan dp_sub
         WHERE dp_sub.id_pesan = p.id_pesan
         ORDER BY dp_sub.id_detail_pesan ASC LIMIT 1) AS first_item_unit_price,
        (SELECT b_sub.nama_barang
         FROM detail_pemesanan dp_sub
         JOIN barang b_sub ON dp_sub.id_barang = b_sub.id_barang
         WHERE dp_sub.id_pesan = p.id_pesan
         ORDER BY dp_sub.id_detail_pesan ASC LIMIT 1) AS first_item_name,
        -- Data untuk beli langsung
        (SELECT SUM(dbl_sub.quantity_item)
         FROM detail_beli_langsung dbl_sub
         WHERE dbl_sub.id_transaksi = tr.id_transaksi) AS beli_langsung_quantity,
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
        WHERE km.tgl_kas_masuk BETWEEN '$start_date' AND '$end_date'
        ORDER BY km.tgl_kas_masuk DESC";

$cash_incomes = [];
$total_jumlah = 0;

try {
    $result = $conn->query($sql);

    if ($result === false) {
        throw new Exception("Error executing query: " . $conn->error);
    }

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $cash_incomes[] = $row;
            $total_jumlah += (float)$row['jumlah'];
        }
    }
} catch (Exception $e) {
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
            .print-only {
                display: none;
                /* Sembunyikan saat tampilan normal */
            }

            @media print {

                /* Sembunyikan elemen yang tidak diperlukan */
                .print\:hidden,
                header,
                nav,
                .navbar,
                .sidebar,
                .breadcrumb,
                form,
                .form-container {
                    display: none !important;
                }

                /* Sembunyikan judul pertama bawaan halaman */
                h1:first-of-type {
                    display: none !important;
                }

                /* Tampilkan hanya saat print */
                .print-only {
                    display: block !important;
                }

                * {
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }

                body {
                    font-family: Arial, sans-serif !important;
                    font-size: 12px !important;
                    line-height: 1.4 !important;
                    color: black !important;
                    background: white !important;
                    margin: 0 !important;
                    padding: 10px !important;
                }

                .container {
                    max-width: none !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }

                h2 {
                    font-size: 18px !important;
                    font-weight: bold !important;
                    text-align: center !important;
                    margin-bottom: 3px !important;
                    color: black !important;
                }

                .periode {
                    font-size: 12px !important;
                    text-align: center !important;
                    margin-bottom: 15px !important;
                    color: black !important;
                }

                table {
                    width: 100% !important;
                    border-collapse: collapse !important;
                    margin-top: 10px !important;
                    font-size: 10px !important;
                    page-break-inside: avoid !important;
                }

                th,
                td {
                    border: 1px solid black !important;
                    padding: 4px 2px !important;
                    text-align: left !important;
                    vertical-align: top !important;
                    color: black !important;
                }

                th {
                    background-color: #f5f5f5 !important;
                    font-weight: bold !important;
                    text-align: center !important;
                    font-size: 9px !important;
                }

                /* Kolom angka rata kanan */
                td:nth-child(5),
                td:nth-child(6) {
                    text-align: right !important;
                    white-space: nowrap !important;
                    font-variant-numeric: tabular-nums;
                }

                tfoot tr {
                    background-color: #f0f0f0 !important;
                    font-weight: bold !important;
                }

                tfoot td {
                    border-top: 2px solid black !important;
                    font-weight: bold !important;
                }

                tr {
                    page-break-inside: avoid !important;
                    page-break-after: auto !important;
                }

                @page {
                    margin: 1cm !important;
                }
            }
        </style>

        <!-- Judul Laporan -->
        <h2 class="print-only" style="text-align:center; font-size:20px; font-weight:bold; margin-bottom:5px;">
            Laporan Pemasukan Kas
        </h2>

        <!-- Periode -->
        <div class="print-only" style="text-align:center; margin-bottom:15px; font-size:14px;">
            Periode
            <?= !empty($start_date) ? date('d-m-Y', strtotime($start_date)) : '-' ?>
            s/d
            <?= !empty($end_date) ? date('d-m-Y', strtotime($end_date)) : '-' ?>
        </div>

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
                                    $display_price_value = 0;

                                    // Jika ini adalah transaksi pemesanan (ada id_pesan)
                                    if (!empty($income['id_pesan']) && isset($income['first_item_unit_price']) && $income['first_item_unit_price'] > 0) {
                                        $display_price_value = $income['first_item_unit_price'];
                                    }
                                    // Jika ini adalah transaksi beli langsung (tidak ada id_pesan)
                                    elseif (empty($income['id_pesan'])) {
                                        // Prioritas 1: Gunakan harga dari detail_beli_langsung
                                        if (isset($income['beli_langsung_unit_price']) && $income['beli_langsung_unit_price'] > 0) {
                                            $display_price_value = $income['beli_langsung_unit_price'];
                                        }
                                        // Prioritas 2: Hitung dari kas masuk
                                        elseif (isset($income['kuantitas']) && $income['kuantitas'] > 0 && isset($income['harga']) && $income['harga'] > 0) {
                                            if (abs($income['harga'] - $income['jumlah']) < 0.01 && $income['kuantitas'] > 0) {
                                                $display_price_value = $income['jumlah'] / $income['kuantitas'];
                                            } else {
                                                $display_price_value = $income['harga'];
                                            }
                                        }
                                        // Prioritas 3: Gunakan harga default
                                        else {
                                            $display_price_value = $income['harga'] ?? 0;
                                        }
                                    } else {
                                        $display_price_value = $income['harga'] ?? 0;
                                    }

                                    echo format_rupiah($display_price_value);
                                    ?>
                                </td>
                                <td class="px-3 py-2 text-sm text-gray-900"><?php echo format_rupiah($income['jumlah'] ?? 0); ?></td>
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