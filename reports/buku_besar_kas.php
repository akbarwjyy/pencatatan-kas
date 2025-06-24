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
$selected_akun = isset($_GET['id_akun']) ? sanitize_input($_GET['id_akun']) : '';

// Jika tanggal belum diset, gunakan bulan ini sebagai default
if (empty($start_date) && empty($end_date)) {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');
}

// Ambil daftar akun untuk dropdown filter
$accounts_filter = [];
$account_sql_filter = "SELECT id_akun, nama_akun FROM akun ORDER BY nama_akun ASC";
$account_result_filter = $conn->query($account_sql_filter);
if ($account_result_filter->num_rows > 0) {
    while ($row = $account_result_filter->fetch_assoc()) {
        $accounts_filter[] = $row;
    }
}

$account_ledger_entries = [];
$account_name = "Semua Akun";
$saldo_awal = 0; // Saldo awal periode yang dipilih

if (!empty($selected_akun)) {
    // Dapatkan nama akun terpilih
    foreach ($accounts_filter as $acc) {
        if ($acc['id_akun'] == $selected_akun) {
            $account_name = $acc['nama_akun'];
            break;
        }
    }

    // Hitung saldo awal untuk akun yang dipilih (total kas masuk sebelum start_date - total kas keluar sebelum start_date)
    $sql_saldo_awal_masuk = "SELECT SUM(jumlah) FROM kas_masuk km 
                             LEFT JOIN transaksi tr ON km.id_transaksi = tr.id_transaksi
                             WHERE km.tgl_kas_masuk < ? AND (tr.id_akun = ? OR km.id_transaksi IS NULL)"; // Perlu penyesuaian jika kas_masuk manual tidak punya id_akun
    $stmt_saldo_awal_masuk = $conn->prepare($sql_saldo_awal_masuk);
    $stmt_saldo_awal_masuk->bind_param("ss", $start_date, $selected_akun);
    $stmt_saldo_awal_masuk->execute();
    $stmt_saldo_awal_masuk->bind_result($saldo_masuk_awal);
    $stmt_saldo_awal_masuk->fetch();
    $stmt_saldo_awal_masuk->close();
    $saldo_masuk_awal = $saldo_masuk_awal ?: 0;

    $sql_saldo_awal_keluar = "SELECT SUM(jumlah) FROM kas_keluar WHERE tgl_kas_keluar < ? AND id_akun = ?";
    $stmt_saldo_awal_keluar = $conn->prepare($sql_saldo_awal_keluar);
    $stmt_saldo_awal_keluar->bind_param("ss", $start_date, $selected_akun);
    $stmt_saldo_awal_keluar->execute();
    $stmt_saldo_awal_keluar->bind_result($saldo_keluar_awal);
    $stmt_saldo_awal_keluar->fetch();
    $stmt_saldo_awal_keluar->close();
    $saldo_keluar_awal = $saldo_keluar_awal ?: 0;

    $saldo_awal = $saldo_masuk_awal - $saldo_keluar_awal;


    // Ambil entri untuk akun yang dipilih dalam periode
    // Kas Masuk
    $sql_km_akun = "SELECT km.tgl_kas_masuk AS tanggal, km.keterangan, km.jumlah, 'Debit' AS tipe_saldo, tr.id_transaksi
                    FROM kas_masuk km 
                    LEFT JOIN transaksi tr ON km.id_transaksi = tr.id_transaksi
                    WHERE km.tgl_kas_masuk BETWEEN ? AND ? 
                    AND (tr.id_akun = ? OR km.id_transaksi IS NULL)"; // Menarik dari akun yang sama atau jika manual
    $stmt_km_akun = $conn->prepare($sql_km_akun);
    $stmt_km_akun->bind_param("sss", $start_date, $end_date, $selected_akun);
    $stmt_km_akun->execute();
    $result_km_akun = $stmt_km_akun->get_result();
    while ($row = $result_km_akun->fetch_assoc()) {
        $account_ledger_entries[] = $row;
    }
    $stmt_km_akun->close();

    // Kas Keluar
    $sql_kk_akun = "SELECT kk.tgl_kas_keluar AS tanggal, kk.keterangan, kk.jumlah, 'Kredit' AS tipe_saldo, NULL AS id_transaksi
                    FROM kas_keluar kk 
                    WHERE kk.tgl_kas_keluar BETWEEN ? AND ? AND kk.id_akun = ?";
    $stmt_kk_akun = $conn->prepare($sql_kk_akun);
    $stmt_kk_akun->bind_param("sss", $start_date, $end_date, $selected_akun);
    $stmt_kk_akun->execute();
    $result_kk_akun = $stmt_kk_akun->get_result();
    while ($row = $result_kk_akun->fetch_assoc()) {
        $account_ledger_entries[] = $row;
    }
    $stmt_kk_akun->close();

    // Urutkan semua entri berdasarkan tanggal
    usort($account_ledger_entries, function ($a, $b) {
        return strtotime($a['tanggal']) - strtotime($b['tanggal']);
    });
}
?>

<h1>Laporan Buku Besar Kas</h1>
<p>Lihat pergerakan saldo untuk akun kas tertentu.</p>

<form action="" method="get" class="form-filter">
    <div class="form-group">
        <label for="id_akun">Pilih Akun:</label>
        <select id="id_akun" name="id_akun" required>
            <option value="">-- Pilih Akun --</option>
            <?php foreach ($accounts_filter as $akun_option) : ?>
                <option value="<?php echo htmlspecialchars($akun_option['id_akun']); ?>"
                    <?php echo ($selected_akun == $akun_option['id_akun']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($akun_option['nama_akun']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="start_date">Dari Tanggal:</label>
        <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
    </div>
    <div class="form-group">
        <label for="end_date">Sampai Tanggal:</label>
        <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
    </div>
    <button type="submit" class="btn">Filter</button>
    <a href="buku_besar_kas.php" class="btn btn-secondary">Reset Filter</a>
    <button type="button" class="btn" onclick="window.print()">Cetak Laporan</button>
</form>

<?php if (empty($selected_akun)) : ?>
    <p>Silakan pilih akun dan periode untuk melihat buku besar.</p>
<?php elseif (empty($account_ledger_entries) && $saldo_awal == 0) : ?>
    <p>Tidak ada pergerakan kas ditemukan untuk akun "<?php echo htmlspecialchars($account_name); ?>" pada periode ini.</p>
<?php else : ?>
    <h2>Buku Besar Akun: <?php echo htmlspecialchars($account_name); ?></h2>
    <p>Periode: <?php echo htmlspecialchars(date('d F Y', strtotime($start_date))); ?> s/d <?php echo htmlspecialchars(date('d F Y', strtotime($end_date))); ?></p>
    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Keterangan</th>
                <th>Debit</th>
                <th>Kredit</th>
                <th>Saldo</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?php echo htmlspecialchars(date('d F Y', strtotime($start_date))); ?></td>
                <td>Saldo Awal</td>
                <td>-</td>
                <td>-</td>
                <td><?php echo format_rupiah($saldo_awal); ?></td>
            </tr>
            <?php
            $current_saldo = $saldo_awal;
            foreach ($account_ledger_entries as $entry) :
                $debit = 0;
                $kredit = 0;

                if ($entry['tipe_saldo'] == 'Debit') {
                    $debit = $entry['jumlah'];
                    $current_saldo += $entry['jumlah'];
                } else { // Kredit
                    $kredit = $entry['jumlah'];
                    $current_saldo -= $entry['jumlah'];
                }
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($entry['tanggal']); ?></td>
                    <td><?php echo htmlspecialchars($entry['keterangan']); ?></td>
                    <td><?php echo ($debit > 0) ? format_rupiah($debit) : '-'; ?></td>
                    <td><?php echo ($kredit > 0) ? format_rupiah($kredit) : '-'; ?></td>
                    <td><?php echo format_rupiah($current_saldo); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" style="text-align: right;"><strong>Saldo Akhir:</strong></td>
                <td><strong><?php echo format_rupiah($current_saldo); ?></strong></td>
            </tr>
        </tfoot>
    </table>
<?php endif; ?>

<?php
// Sertakan footer
require_once '../../layout/footer.php';
?>