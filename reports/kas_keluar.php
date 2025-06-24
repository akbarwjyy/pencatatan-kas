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

$sql = "SELECT kk.*, a.nama_akun
        FROM kas_keluar kk
        JOIN akun a ON kk.id_akun = a.id_akun";

$where_clause = [];
$params = [];
$param_types = "";

if (!empty($start_date)) {
    $where_clause[] = "kk.tgl_kas_keluar >= ?";
    $params[] = $start_date;
    $param_types .= "s";
}
if (!empty($end_date)) {
    $where_clause[] = "kk.tgl_kas_keluar <= ?";
    $params[] = $end_date;
    $param_types .= "s";
}

if (!empty($where_clause)) {
    $sql .= " WHERE " . implode(" AND ", $where_clause);
}

$sql .= " ORDER BY kk.tgl_kas_keluar DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$cash_expenses = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $cash_expenses[] = $row;
    }
}
$stmt->close();
?>

<h1>Laporan Kas Keluar</h1>
<p>Lihat daftar pengeluaran kas berdasarkan periode tanggal.</p>

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
    <a href="kas_keluar.php" class="btn btn-secondary">Reset Filter</a>
    <button type="button" class="btn" onclick="window.print()">Cetak Laporan</button>
</form>

<?php if (empty($cash_expenses)) : ?>
    <p>Tidak ada data kas keluar yang ditemukan untuk periode ini.</p>
<?php else : ?>
    <table>
        <thead>
            <tr>
                <th>ID Kas Keluar</th>
                <th>Tgl Kas Keluar</th>
                <th>Akun</th>
                <th>Jumlah</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $total_jumlah = 0;
            foreach ($cash_expenses as $expense) :
                $total_jumlah += $expense['jumlah'];
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($expense['id_kas_keluar']); ?></td>
                    <td><?php echo htmlspecialchars($expense['tgl_kas_keluar']); ?></td>
                    <td><?php echo htmlspecialchars($expense['nama_akun']); ?></td>
                    <td><?php echo format_rupiah($expense['jumlah']); ?></td>
                    <td><?php echo htmlspecialchars($expense['keterangan']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" style="text-align: right;"><strong>Total Kas Keluar:</strong></td>
                <td><strong><?php echo format_rupiah($total_jumlah); ?></strong></td>
                <td></td>
            </tr>
        </tfoot>
    </table>
<?php endif; ?>

<?php
// Sertakan footer
require_once '../../layout/footer.php';
?>