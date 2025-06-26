<?php

?>
<nav class="w-full bg-gray-900 text-white shadow-lg px-8 py-3 flex items-center justify-between" x-data="{ openMgmt: false, openReport: false }">
    <div class="flex items-center gap-4">
        <a href="../dashboard/index.php" class="flex items-center gap-2 py-2 px-4 rounded-lg transition hover:bg-gray-700 hover:text-white font-semibold">
            <!-- <span class="text-lg">🏠</span> -->
            Dashboard
        </a>
        <!-- Dropdown Manajemen -->
        <div class="relative" @mouseleave="openMgmt = false">
            <button @click="openMgmt = !openMgmt" class="flex items-center gap-2 py-2 px-4 rounded-lg bg-gray-800 hover:bg-gray-700 focus:outline-none font-semibold">
                <!-- <span class="text-lg">🗂️</span> -->
                Manajemen
                <svg :class="{'rotate-180': openMgmt}" class="w-4 h-4 ml-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <ul x-show="openMgmt" x-transition class="absolute left-0 mt-2 w-56 bg-gray-800 rounded-lg shadow-lg py-2 z-50" @click.away="openMgmt = false">
                <?php if (has_permission('Admin')) : ?>
                    <li><a href="../pengguna/index.php" class="block py-2 px-4 hover:bg-gray-700">Manajemen Pengguna</a></li>
                    <li><a href="../akun/index.php" class="block py-2 px-4 hover:bg-gray-700">Manajemen Akun</a></li>
                    <li><a href="../customer/index.php" class="block py-2 px-4 hover:bg-gray-700">Manajemen Customer</a></li>
                <?php endif; ?>
                <?php if (has_permission('Admin') || has_permission('Pegawai') || has_permission('Pemilik')) : ?>
                    <li><a href="../pemesanan/index.php" class="block py-2 px-4 hover:bg-gray-700">Manajemen Pemesanan</a></li>
                <?php endif; ?>
                <?php if (has_permission('Admin') || has_permission('Pegawai')) : ?>
                    <li><a href="../transaksi/index.php" class="block py-2 px-4 hover:bg-gray-700">Manajemen Transaksi</a></li>
                    <li><a href="../kas_masuk/index.php" class="block py-2 px-4 hover:bg-gray-700">Manajemen Kas Masuk</a></li>
                <?php endif; ?>
                <?php if (has_permission('Admin')) : ?>
                    <li><a href="../kas_keluar/index.php" class="block py-2 px-4 hover:bg-gray-700">Manajemen Kas Keluar</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <!-- Dropdown Laporan -->
        <div class="relative" @mouseleave="openReport = false">
            <button @click="openReport = !openReport" class="flex items-center gap-2 py-2 px-4 rounded-lg bg-gray-800 hover:bg-gray-700 focus:outline-none font-semibold">
                <!-- <span class="text-lg">📊</span> -->
                Laporan
                <svg :class="{'rotate-180': openReport}" class="w-4 h-4 ml-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <ul x-show="openReport" x-transition class="absolute left-0 mt-2 w-56 bg-gray-800 rounded-lg shadow-lg py-2 z-50" @click.away="openReport = false">
                <?php if (has_permission('Admin') || has_permission('Pemilik') || has_permission('Pegawai')) : ?>
                    <li><a href="../../reports/pemesanan.php" class="block py-2 px-4 hover:bg-gray-700">Laporan Pemesanan</a></li>
                    <li><a href="../../reports/kas_masuk.php" class="block py-2 px-4 hover:bg-gray-700">Laporan Kas Masuk</a></li>
                    <li><a href="../../reports/kas_keluar.php" class="block py-2 px-4 hover:bg-gray-700">Laporan Kas Keluar</a></li>
                <?php endif; ?>
                <?php if (has_permission('Admin') || has_permission('Pemilik')) : ?>
                    <li><a href="../../reports/laba_rugi.php" class="block py-2 px-4 hover:bg-gray-700">Laporan Laba Rugi</a></li>
                    <li><a href="../../reports/jurnal_umum.php" class="block py-2 px-4 hover:bg-gray-700">Jurnal Umum</a></li>
                    <li><a href="../../reports/buku_besar_kas.php" class="block py-2 px-4 hover:bg-gray-700">Buku Besar Kas</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <div>
        <a href="../../logout.php" class="py-2 px-4 rounded-lg bg-red-600 hover:bg-red-700 text-white font-semibold shadow transition">Logout</a>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</nav>