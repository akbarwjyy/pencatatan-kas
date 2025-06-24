<?php
// Pastikan file ini di-include setelah header.php yang sudah memulai session
// dan menyediakan $user_role
?>
<div class="w-64 bg-gray-900 text-white p-5 shadow-lg flex-shrink-0">
    <h2 class="text-2xl font-semibold mb-6 text-center">Menu Navigasi</h2>
    <ul class="list-none p-0 space-y-2">
        <li><a href="../dashboard/index.php" class="block py-2 px-3 rounded-md transition duration-200 hover:bg-gray-700 hover:text-white">Dashboard</a></li>

        <?php if (has_permission('Admin')) : // Hanya Admin yang bisa mengelola Pengguna
        ?>
            <li><a href="../pengguna/index.php" class="block py-2 px-3 rounded-md transition duration-200 hover:bg-gray-700 hover:text-white">Manajemen Pengguna</a></li>
            <li><a href="../akun/index.php" class="block py-2 px-3 rounded-md transition duration-200 hover:bg-gray-700 hover:text-white">Manajemen Akun</a></li>
            <li><a href="../customer/index.php" class="block py-2 px-3 rounded-md transition duration-200 hover:bg-gray-700 hover:text-white">Manajemen Customer</a></li>
        <?php endif; ?>

        <?php if (has_permission('Admin') || has_permission('Pegawai') || has_permission('Pemilik')) : // Admin, Pegawai, Pemilik bisa lihat Pemesanan
        ?>
            <li><a href="../pemesanan/index.php" class="block py-2 px-3 rounded-md transition duration-200 hover:bg-gray-700 hover:text-white">Manajemen Pemesanan</a></li>
        <?php endif; ?>

        <?php if (has_permission('Admin') || has_permission('Pegawai')) : // Admin dan Pegawai bisa mengelola Transaksi & Kas Masuk
        ?>
            <li><a href="../transaksi/index.php" class="block py-2 px-3 rounded-md transition duration-200 hover:bg-gray-700 hover:text-white">Manajemen Transaksi</a></li>
            <li><a href="../kas_masuk/index.php" class="block py-2 px-3 rounded-md transition duration-200 hover:bg-gray-700 hover:text-white">Manajemen Kas Masuk</a></li>
        <?php endif; ?>

        <?php if (has_permission('Admin')) : // Hanya Admin yang bisa mengelola Kas Keluar
        ?>
            <li><a href="../kas_keluar/index.php" class="block py-2 px-3 rounded-md transition duration-200 hover:bg-gray-700 hover:text-white">Manajemen Kas Keluar</a></li>
        <?php endif; ?>

        <li class="mt-4 pt-2 border-t border-gray-700 text-gray-400 text-sm text-center uppercase">--- Laporan ---</li>

        <?php if (has_permission('Admin') || has_permission('Pemilik') || has_permission('Pegawai')) : // Semua bisa melihat laporan dasar
        ?>
            <li><a href="../../reports/pemesanan.php" class="block py-2 px-3 rounded-md transition duration-200 hover:bg-gray-700 hover:text-white">Laporan Pemesanan</a></li>
            <li><a href="../../reports/kas_masuk.php" class="block py-2 px-3 rounded-md transition duration-200 hover:bg-gray-700 hover:text-white">Laporan Kas Masuk</a></li>
            <li><a href="../../reports/kas_keluar.php" class="block py-2 px-3 rounded-md transition duration-200 hover:bg-gray-700 hover:text-white">Laporan Kas Keluar</a></li>
        <?php endif; ?>

        <?php if (has_permission('Admin') || has_permission('Pemilik')) : // Admin & Pemilik bisa melihat Laba Rugi, Jurnal, Buku Besar
        ?>
            <li><a href="../../reports/laba_rugi.php" class="block py-2 px-3 rounded-md transition duration-200 hover:bg-gray-700 hover:text-white">Laporan Laba Rugi</a></li>
            <li><a href="../../reports/jurnal_umum.php" class="block py-2 px-3 rounded-md transition duration-200 hover:bg-gray-700 hover:text-white">Jurnal Umum</a></li>
            <li><a href="../../reports/buku_besar_kas.php" class="block py-2 px-3 rounded-md transition duration-200 hover:bg-gray-700 hover:text-white">Buku Besar Kas</a></li>
        <?php endif; ?>

        <li><a href="../../logout.php" class="block py-2 px-3 rounded-md transition duration-200 hover:bg-red-700 hover:text-white">Logout</a></li>
    </ul>
</div>