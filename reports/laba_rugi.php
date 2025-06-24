<?php
// Sertakan header
require_once '../../layout/header.php';

// Pastikan hanya Admin atau Pemilik yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pemilik')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

$start_date = isset($_GET['start_date']) ? sanitize_input($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? sanitize_input($_GET['end_date']) : '';

// Jika tanggal belum diset, gunakan bulan ini sebagai default
if (empty($start_date) && empty($end_date)) {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');
}

$total_kas_masuk = 0;
$total_kas_keluar = 0;
$laba_rugi_bersih = 0;

// Query untuk total kas masuk
$sql_kas_masuk = "SELECT SUM(jumlah) AS total FROM kas_masuk WHERE tgl_kas_masuk BETWEEN ? AND ?";
if ($stmt = $conn->prepare($sql_kas_masuk)) {
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $stmt->bind_result($total_kas_masuk);
    $stmt->fetch();
    $stmt->close();
}

// Query untuk total kas keluar
$sql_kas_keluar = "SELECT SUM(jumlah) AS total FROM kas_keluar WHERE tgl_kas_keluar BETWEEN ? AND ?";
if ($stmt = $conn->prepare($sql_kas_keluar)) {
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $stmt->bind_result($total_kas_keluar);
    $stmt->fetch();
    $stmt->close();
}

$laba_rugi_bersih = $total_kas_masuk - $total_kas_keluar;

?>

<h1>Laporan Laba Rugi</h1>
<p>Lihat ringkasan laba atau rugi bersih berdasarkan periode tanggal.</p>

<form action="" method="get" class="form-filter">
    <div class="form-group">
        <label for="start_date">Dari Tanggal:</label>
        <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
    </div>
    <div class="form-group">
        <label for="end_date">Sampai Tanggal:</label>
        <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
    </div>
    <button type="submit" class="btn">Filter</button>
    <a href="laba_rugi.php" class="btn btn-secondary">Reset Filter</a>
    <button type="button" class="btn" onclick="window.print()">Cetak Laporan</button>
</form>

<div class="report-summary-card">
    <h2>Ringkasan Laba Rugi Periode:</h2>
    <p><strong><?php echo htmlspecialchars(date('d F Y', strtotime($start_date))); ?></strong> sampai <strong><?php echo htmlspecialchars(date('d F Y', strtotime($end_date))); ?></strong></p>

    <div class="summary-item">
        <span>Total Kas Masuk:</span>
        <span class="amount-positive"><?php echo format_rupiah($total_kas_masuk); ?></span>
    </div>
    <div class="summary-item">
        <span>Total Kas Keluar:</span>
        <span class="amount-negative"><?php echo format_rupiah($total_kas_keluar); ?></span>
    </div>
    <hr>
    <div class="summary-item total">
        <span>Laba/Rugi Bersih:</span>
        <span class="<?php echo ($laba_rugi_bersih >= 0) ? 'amount-positive' : 'amount-negative'; ?>">
            <?php echo format_rupiah($laba_rugi_bersih); ?>
        </span>
    </div>
</div>

<style>
    .report-summary-card {
        background-color: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        margin-top: 30px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        max-width: 500px;
        margin-left: auto;
        margin-right: auto;
    }

    .report-summary-card h2 {
        text-align: center;
        color: #4CAF50;
        margin-top: 0;
        margin-bottom: 20px;
    }

    .summary-item {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px dashed #eee;
    }

    .summary-item:last-child {
        border-bottom: none;
    }

    .summary-item.total {
        font-weight: bold;
        font-size: 1.1em;
        padding-top: 15px;
    }

    .amount-positive {
        color: #28a745;
        /* Green */
    }

    .amount-negative {
        color: #dc3545;
        /* Red */
    }
</style>

<?php
// Sertakan footer
require_once '../../layout/footer.php';
?>