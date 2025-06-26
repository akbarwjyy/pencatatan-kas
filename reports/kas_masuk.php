<?php
// Sertakan header
require_once '../layout/header.php';

// Pastikan hanya Admin, Pemilik, atau Pegawai yang bisa mengakses halaman ini
if (!has_permission('Admin') && !has_permission('Pemilik') && !has_permission('Pegawai')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

$start_date = isset($_GET['start_date']) ? sanitize_input($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? sanitize_input($_GET['end_date']) : '';

$sql = "SELECT km.*, tr.id_pesan, tr.metode_pembayaran, c.nama_customer, a.nama_akun
        FROM kas_masuk km
        LEFT JOIN transaksi tr ON km.id_transaksi = tr.id_transaksi
        LEFT JOIN pemesanan p ON tr.id_pesan = p.id_pesan
        LEFT JOIN customer c ON p.id_customer = c.id_customer
        LEFT JOIN akun a ON tr.id_akun = a.id_akun"; // Mengambil nama akun dari transaksi jika terkait

$where_clause = [];
$params = [];
$param_types = "";

if (!empty($start_date)) {
    $where_clause[] = "km.tgl_kas_masuk >= ?";
    $params[] = $start_date;
    $param_types .= "s";
}
if (!empty($end_date)) {
    $where_clause[] = "km.tgl_kas_masuk <= ?";
    $params[] = $end_date;
    $param_types .= "s";
}

if (!empty($where_clause)) {
    $sql .= " WHERE " . implode(" AND ", $where_clause);
}

$sql .= " ORDER BY km.tgl_kas_masuk DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$cash_incomes = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $cash_incomes[] = $row;
    }
}
$stmt->close();
?>

<h1>Laporan Kas Masuk</h1>
<p>Lihat daftar pemasukan kas berdasarkan periode tanggal.</p>

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
    <a href="kas_masuk.php" class="btn btn-secondary">Reset Filter</a>
    <button type="button" class="btn" onclick="window.print()">Cetak Laporan</button>
</form>

<?php if (empty($cash_incomes)) : ?>
    <p>Tidak ada data kas masuk yang ditemukan untuk periode ini.</p>
<?php else : ?>
    <table>
        <thead>
            <tr>
                <th>ID Kas Masuk</th>
                <th>Tgl Kas Masuk</th>
                <th>Jumlah</th>
                <th>Keterangan</th>
                <th>Asal Transaksi</th>
                <th>Metode Pembayaran</th>
                <th>Customer</th>
                <th>Akun Penerima (dari Transaksi)</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $total_jumlah = 0;
            foreach ($cash_incomes as $income) :
                $total_jumlah += $income['jumlah'];
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($income['id_kas_masuk']); ?></td>
                    <td><?php echo htmlspecialchars($income['tgl_kas_masuk']); ?></td>
                    <td><?php echo format_rupiah($income['jumlah']); ?></td>
                    <td><?php echo htmlspecialchars($income['keterangan']); ?></td>
                    <td><?php echo !empty($income['id_transaksi']) ? 'ID Transaksi: ' . htmlspecialchars($income['id_transaksi']) . ' (Pemesanan: ' . htmlspecialchars($income['id_pesan'] ?? '-') . ')' : '-'; ?></td>
                    <td><?php echo htmlspecialchars($income['metode_pembayaran'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($income['nama_customer'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($income['nama_akun'] ?? '-'); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" style="text-align: right;"><strong>Total Kas Masuk:</strong></td>
                <td><strong><?php echo format_rupiah($total_jumlah); ?></strong></td>
                <td colspan="4"></td>
            </tr>
        </tfoot>
    </table>
<?php endif; ?>

<?php
// Sertakan footer
require_once '../layout/footer.php';
?>