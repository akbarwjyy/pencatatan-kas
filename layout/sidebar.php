<?php
// Pastikan file ini di-include setelah header.php yang sudah memulai session
// dan menyediakan $user_role
?>
<div class="sidebar">
    <h2>Menu Navigasi</h2>
    <ul>
        <li><a href="../modules/dashboard/index.php">Dashboard</a></li>

        <?php if (has_permission('Admin')) : // Hanya Admin yang bisa mengelola Pengguna 
        ?>
            <li><a href="../modules/pengguna/index.php">Manajemen Pengguna</a></li>
            <li><a href="../modules/akun/index.php">Manajemen Akun</a></li>
            <li><a href="../modules/customer/index.php">Manajemen Customer</a></li>
        <?php endif; ?>

        <?php if (has_permission('Admin') || has_permission('Pegawai') || has_permission('Pemilik')) : // Admin, Pegawai, Pemilik bisa lihat Pemesanan 
        ?>
            <li><a href="../modules/pemesanan/index.php">Manajemen Pemesanan</a></li>
        <?php endif; ?>

        <?php if (has_permission('Admin') || has_permission('Pegawai')) : // Admin dan Pegawai bisa mengelola Transaksi & Kas Masuk 
        ?>
            <li><a href="../modules/transaksi/index.php">Manajemen Transaksi</a></li>
            <li><a href="../modules/kas_masuk/index.php">Manajemen Kas Masuk</a></li>
        <?php endif; ?>

        <?php if (has_permission('Admin')) : // Hanya Admin yang bisa mengelola Kas Keluar 
        ?>
            <li><a href="../modules/kas_keluar/index.php">Manajemen Kas Keluar</a></li>
        <?php endif; ?>

        <li class="menu-separator">--- Laporan ---</li>

        <?php if (has_permission('Admin') || has_permission('Pemilik') || has_permission('Pegawai')) : // Semua bisa melihat laporan dasar 
        ?>
            <li><a href="../reports/pemesanan.php">Laporan Pemesanan</a></li>
            <li><a href="../reports/kas_masuk.php">Laporan Kas Masuk</a></li>
            <li><a href="../reports/kas_keluar.php">Laporan Kas Keluar</a></li>
        <?php endif; ?>

        <?php if (has_permission('Admin') || has_permission('Pemilik')) : // Admin & Pemilik bisa melihat Laba Rugi, Jurnal, Buku Besar 
        ?>
            <li><a href="../reports/laba_rugi.php">Laporan Laba Rugi</a></li>
            <li><a href="../reports/jurnal_umum.php">Jurnal Umum</a></li>
            <li><a href="../reports/buku_besar_kas.php">Buku Besar Kas</a></li>
        <?php endif; ?>

        <li><a href="../logout.php">Logout</a></li>
    </ul>
</div>