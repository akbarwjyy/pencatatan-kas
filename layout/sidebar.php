<?php
require_once __DIR__ . '/../includes/path_helper.php';
?>
<nav class="w-full bg-gray-900 text-white shadow-lg px-4 py-3 flex items-center justify-between relative" x-data="{ openMgmt: false, openReport: false, mobileMenu: false }">
    <div class="flex items-center gap-4">
        <a href="<?php echo to_url('modules/dashboard/index.php'); ?>" class="flex items-center gap-2 py-2 px-4 rounded-lg transition hover:bg-gray-700 hover:text-white font-semibold">
            Dashboard
        </a>
        <!-- Main menu (desktop) -->
        <div class="hidden md:flex items-center gap-4">
            <!-- Dropdown Manajemen -->
            <div class="relative">
                <button @click="openMgmt = !openMgmt" class="flex items-center gap-2 py-2 px-4 rounded-lg bg-gray-800 hover:bg-gray-700 focus:outline-none font-semibold">
                    Manajemen
                    <svg :class="{'rotate-180': openMgmt}" class="w-4 h-4 ml-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <ul x-show="openMgmt" x-transition class="absolute left-0 mt-2 w-56 bg-gray-800 rounded-lg shadow-lg py-2 z-50" @click.away="openMgmt = false">
                    <?php if (has_permission('Admin')) : ?>
                        <li><a href="<?php echo to_url('modules/pengguna/index.php'); ?>" class="block py-2 px-4 hover:bg-gray-700">Manajemen Pengguna</a></li>
                        <li><a href="<?php echo to_url('modules/akun/index.php'); ?>" class="block py-2 px-4 hover:bg-gray-700">Manajemen Akun</a></li>
                        <li><a href="<?php echo to_url('modules/customer/index.php'); ?>" class="block py-2 px-4 hover:bg-gray-700">Manajemen Customer</a></li>
                    <?php endif; ?>
                    <?php if (has_permission('Admin') || has_permission('Pegawai') || has_permission('Pemilik')) : ?>
                        <li>
                            <a href="<?php echo to_url('modules/pemesanan/index.php'); ?>" class="block py-2 px-4 hover:bg-gray-700">Manajemen Pemesanan</a>
                        </li>
                        <li>
                            <a href="<?php echo to_url('modules/beli_langsung/index.php'); ?>" class="block py-2 px-4 hover:bg-gray-700">Beli Langsung</a>
                        </li>
                    <?php endif; ?>
                    <?php if (has_permission('Admin') || has_permission('Pegawai')) : ?>
                        <li><a href="<?php echo to_url('modules/transaksi/index.php'); ?>" class="block py-2 px-4 hover:bg-gray-700">Manajemen Transaksi</a></li>
                        <li><a href="<?php echo to_url('modules/kas_masuk/index.php'); ?>" class="block py-2 px-4 hover:bg-gray-700">Manajemen Kas Masuk</a></li>
                    <?php endif; ?>
                    <?php if (has_permission('Admin')) : ?>
                        <li><a href="<?php echo to_url('modules/kas_keluar/index.php'); ?>" class="block py-2 px-4 hover:bg-gray-700">Manajemen Kas Keluar</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <!-- Dropdown Laporan -->
            <div class="relative">
                <button @click="openReport = !openReport" class="flex items-center gap-2 py-2 px-4 rounded-lg bg-gray-800 hover:bg-gray-700 focus:outline-none font-semibold">
                    Laporan
                    <svg :class="{'rotate-180': openReport}" class="w-4 h-4 ml-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <ul x-show="openReport" x-transition class="absolute left-0 mt-2 w-56 bg-gray-800 rounded-lg shadow-lg py-2 z-50" @click.away="openReport = false">
                    <?php if (has_permission('Admin') || has_permission('Pemilik') || has_permission('Pegawai')) : ?>
                        <li><a href="<?php echo to_url('reports/pemesanan.php'); ?>" class="block py-2 px-4 hover:bg-gray-700">Laporan Pemesanan</a></li>
                        <li><a href="<?php echo to_url('reports/kas_masuk.php'); ?>" class="block py-2 px-4 hover:bg-gray-700">Laporan Kas Masuk</a></li>
                        <li><a href="<?php echo to_url('reports/kas_keluar.php'); ?>" class="block py-2 px-4 hover:bg-gray-700">Laporan Kas Keluar</a></li>
                    <?php endif; ?>
                    <?php if (has_permission('Admin') || has_permission('Pemilik')) : ?>
                        <li><a href="<?php echo to_url('reports/laba_rugi.php'); ?>" class="block py-2 px-4 hover:bg-gray-700">Laporan Laba Rugi</a></li>
                        <li><a href="<?php echo to_url('reports/jurnal_umum.php'); ?>" class="block py-2 px-4 hover:bg-gray-700">Jurnal Umum</a></li>
                        <li><a href="<?php echo to_url('reports/buku_besar_kas.php'); ?>" class="block py-2 px-4 hover:bg-gray-700">Buku Besar Kas</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
    <!-- Hamburger for mobile (kanan) -->
    <div class="flex items-center md:hidden z-50">
        <button @click="mobileMenu = !mobileMenu" class="ml-2 focus:outline-none">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </div>
    <!-- Mobile menu (dropdown) -->
    <div x-show="mobileMenu" x-transition class="md:hidden absolute left-0 top-full w-full bg-gray-900 z-40 shadow-lg rounded-b-lg">
        <ul class="py-2">
            <li><a href="<?php echo to_url('modules/dashboard/index.php'); ?>" class="block py-2 px-4 hover:bg-gray-700">Dashboard</a></li>
            <?php if (has_permission('Admin')) : ?>
                <li><a href="<?php echo to_url('modules/pengguna/index.php'); ?>" class="block py-2 px-4 hover:bg-gray-700">Manajemen Pengguna</a></li>
                <li><a href="<?php echo to_url('modules/akun/index.php'); ?>" class="block py-2 px-4 hover:bg-gray-700">Manajemen Akun</a></li>
                <li><a href="<?php echo to_url('modules/customer/index.php'); ?>" class="block py-2 px-4 hover:bg-gray-700">Manajemen Customer</a></li>
            <?php endif; ?>
            <?php if (has_permission('Admin') || has_permission('Pegawai') || has_permission('Pemilik')) : ?>
                <li>
                    <a href="<?php echo to_url('modules/pemesanan/index.php'); ?>" class="block py-2 px-4 hover:bg-gray-700">Manajemen Pemesanan</a>
                </li>
                <li>
                    <a href="<?php echo to_url('modules/beli_langsung/index.php'); ?>" class="block py-2 px-4 hover:bg-gray-700">Beli Langsung</a>
                </li>
            <?php endif; ?>
            <?php if (has_permission('Admin') || has_permission('Pegawai')) : ?>
                <li><a href="<?php echo to_url('modules/transaksi/index.php'); ?>" class="block py-2 px-4 hover:bg-gray-700">Manajemen Transaksi</a></li>
                <li><a href="<?php echo to_url('modules/kas_masuk/index.php'); ?>" class="block py-2 px-4 hover:bg-gray-700">Manajemen Kas Masuk</a></li>
            <?php endif; ?>
            <?php if (has_permission('Admin')) : ?>
                <li><a href="<?php echo to_url('modules/kas_keluar/index.php'); ?>" class="block py-2 px-4 hover:bg-gray-700">Manajemen Kas Keluar</a></li>
            <?php endif; ?>
            <li class="border-t border-gray-700 my-2"></li>
            <?php if (has_permission('Admin') || has_permission('Pemilik') || has_permission('Pegawai')) : ?>
                <li><a href="<?php echo to_url('reports/pemesanan.php'); ?>" class="block py-2 px-4 hover:bg-gray-700">Laporan Pemesanan</a></li>
                <li><a href="<?php echo to_url('reports/kas_masuk.php'); ?>" class="block py-2 px-4 hover:bg-gray-700">Laporan Kas Masuk</a></li>
                <li><a href="<?php echo to_url('reports/kas_keluar.php'); ?>" class="block py-2 px-4 hover:bg-gray-700">Laporan Kas Keluar</a></li>
            <?php endif; ?>
            <?php if (has_permission('Admin') || has_permission('Pemilik')) : ?>
                <li><a href="<?php echo to_url('reports/laba_rugi.php'); ?>" class="block py-2 px-4 hover:bg-gray-700">Laporan Laba Rugi</a></li>
                <li><a href="<?php echo to_url('reports/jurnal_umum.php'); ?>" class="block py-2 px-4 hover:bg-gray-700">Jurnal Umum</a></li>
                <li><a href="<?php echo to_url('reports/buku_besar_kas.php'); ?>" class="block py-2 px-4 hover:bg-gray-700">Buku Besar Kas</a></li>
            <?php endif; ?>
            <li class="border-t border-gray-700 my-2"></li>
            <li><a href="<?php echo to_url('logout.php'); ?>" class="block py-2 px-4 rounded-lg bg-red-600 hover:bg-red-700 text-white font-semibold shadow transition">Logout</a></li>
        </ul>
    </div>
    <div class="hidden md:block">
        <a href="<?php echo to_url('logout.php'); ?>" class="py-2 px-4 rounded-lg bg-red-600 hover:bg-red-700 text-white font-semibold shadow transition">Logout</a>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</nav>