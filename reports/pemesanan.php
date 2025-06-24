<?php
// Sertakan header (ini akan melakukan session_start(), cek login, dan include koneksi/fungsi)
require_once '../../layout/header.php';

// Pastikan hanya Admin, Pemilik, atau Pegawai yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pemilik') && !has_permission('Pegawai')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

$start_date = isset($_GET['start_date']) ? sanitize_input($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? sanitize_input($_GET['end_date']) : '';

$sql = "SELECT p.*, c.nama_customer, a.nama_akun
        FROM pemesanan p
        JOIN customer c ON p.id_customer = c.id_customer
        JOIN akun a ON p.id_akun = a.id_akun";

$where_clause = [];
$params = [];
$param_types = "";

if (!empty($start_date)) {
    $where_clause[] = "p.tgl_pesan >= ?";
    $params[] = $start_date;
    $param_types .= "s";
}
if (!empty($end_date)) {
    $where_clause[] = "p.tgl_pesan <= ?";
    $params[] = $end_date;
    $param_types .= "s";
}

if (!empty($where_clause)) {
    $sql .= " WHERE " . implode(" AND ", $where_clause);
}

$sql .= " ORDER BY p.tgl_pesan DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}
$stmt->close();
?>

<h1>Laporan Pemesanan</h1>
<p>Lihat daftar pemesanan berdasarkan periode tanggal.</p>

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
    <a href="pemesanan.php" class="btn btn-secondary">Reset Filter</a>
    <button type="button" class="btn" onclick="window.print()">Cetak Laporan</button>
</form>

<?php if (empty($orders)) : ?>
    <p>Tidak ada data pemesanan yang ditemukan untuk periode ini.</p>
<?php else : ?>
    <table>
        <thead>
            <tr>
                <th>ID Pesan</th>
                <th>Customer</th>
                <th>Akun</th>
                <th>Tgl Pesan</th>
                <th>Tgl Kirim</th>
                <th>Quantity</th>
                <th>Uang Muka</th>
                <th>Sub Total</th>
                <th>Sisa</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $total_quantity = 0;
            $total_uang_muka = 0;
            $total_sub_total = 0;
            $total_sisa = 0;
            foreach ($orders as $order) :
                $total_quantity += $order['quantity'];
                $total_uang_muka += $order['uang_muka'];
                $total_sub_total += $order['sub_total'];
                $total_sisa += $order['sisa'];
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($order['id_pesan']); ?></td>
                    <td><?php echo htmlspecialchars($order['nama_customer']); ?></td>
                    <td><?php echo htmlspecialchars($order['nama_akun']); ?></td>
                    <td><?php echo htmlspecialchars($order['tgl_pesan']); ?></td>
                    <td><?php echo htmlspecialchars($order['tgl_kirim']); ?></td>
                    <td><?php echo htmlspecialchars($order['quantity']); ?></td>
                    <td><?php echo format_rupiah($order['uang_muka']); ?></td>
                    <td><?php echo format_rupiah($order['sub_total']); ?></td>
                    <td><?php echo format_rupiah($order['sisa']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" style="text-align: right;"><strong>Total:</strong></td>
                <td><strong><?php echo htmlspecialchars($total_quantity); ?></strong></td>
                <td><strong><?php echo format_rupiah($total_uang_muka); ?></strong></td>
                <td><strong><?php echo format_rupiah($total_sub_total); ?></strong></td>
                <td><strong><?php echo format_rupiah($total_sisa); ?></strong></td>
            </tr>
        </tfoot>
    </table>
<?php endif; ?>

<?php
// Sertakan footer
require_once '../../layout/footer.php';
?>