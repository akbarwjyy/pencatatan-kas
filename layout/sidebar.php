<?php
require_once __DIR__ . '/../includes/path_helper.php';
?>
<nav class="fixed left-0 top-0 w-64 h-full bg-gray-900 text-white shadow-lg overflow-y-auto z-40 md:block hidden" id="sidebar">
    <div class="p-4">
        <!-- Mobile Close Button -->
        <div class="md:hidden flex justify-end mb-4">
            <button onclick="document.getElementById('sidebar').classList.add('hidden')" class="text-white hover:text-gray-300">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <!-- Dashboard -->
        <div class="mb-4">
            <a href="<?php echo to_url('modules/dashboard/index.php'); ?>" class="flex items-center gap-3 py-3 px-4 rounded-lg transition hover:bg-gray-700 hover:text-white font-semibold">
                <span>📊</span>
                Dashboard
            </a>
        </div>

        <!-- Manajemen Section -->
        <?php if (!($_SESSION['user_role'] === 'Pemilik')) : ?>
            <div class="mb-4">
                <div class="mb-2">
                    <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wide px-4 py-2">Data Utama</h3>
                </div>
                <div class="space-y-1">
                    <?php if (has_permission('Admin')) : ?>
                        <!-- Data Master Dropdown -->
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="flex items-center justify-between w-full py-2 px-4 rounded-lg hover:bg-gray-700 text-sm">
                                <div class="flex items-center gap-3">
                                    <span>📁</span> Data Master
                                </div>
                                <svg class="w-4 h-4 transition-transform" :class="{'rotate-180': open}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div x-show="open" @click.away="open = false" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="transform opacity-100 scale-100" x-transition:leave-end="transform opacity-0 scale-95" class="pl-4 mt-1 space-y-1">
                                <a href="<?php echo to_url('modules/pengguna/index.php'); ?>" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-gray-700 text-sm">👥 Data Pengguna</a>
                                <a href="<?php echo to_url('modules/akun/index.php'); ?>" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-gray-700 text-sm">💳 Data Akun</a>
                                <a href="<?php echo to_url('modules/customer/index.php'); ?>" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-gray-700 text-sm">👤 Data Customer</a>
                                <a href="<?php echo to_url('modules/barang/index.php'); ?>" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-gray-700 text-sm">📦 Data Barang</a>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (has_permission('Admin') || has_permission('Pegawai')) : ?>
                        <a href="<?php echo to_url('modules/pemesanan/index.php'); ?>" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-gray-700 text-sm">📝 Daftar Pemesanan</a>
                        <a href="<?php echo to_url('modules/beli_langsung/index.php'); ?>" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-gray-700 text-sm">🛒 Beli Langsung</a>
                    <?php endif; ?>
                    <?php if (has_permission('Admin') || has_permission('Pegawai')) : ?>
                        <a href="<?php echo to_url('modules/transaksi/index.php'); ?>" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-gray-700 text-sm">💰 Transaksi</a>
                        <a href="<?php echo to_url('modules/kas_masuk/index.php'); ?>" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-gray-700 text-sm">💵 Daftar Kas Masuk</a>
                    <?php endif; ?>
                    <?php if (has_permission('Admin')) : ?>
                        <a href="<?php echo to_url('modules/kas_keluar/index.php'); ?>" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-gray-700 text-sm">💸 Daftar Kas Keluar</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Laporan Section -->
        <div class="mb-4">
            <div class="mb-2">
                <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wide px-4 py-2">Laporan</h3>
            </div>
            <div class="space-y-1">
                <?php if (has_permission('Admin') || has_permission('Pemilik') || has_permission('Pegawai')) : ?>
                    <a href="<?php echo to_url('reports/pemesanan.php'); ?>" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-gray-700 text-sm">📋 Laporan Pemesanan</a>
                    <a href="<?php echo to_url('reports/kas_masuk.php'); ?>" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-gray-700 text-sm">📈 Laporan Kas Masuk</a>
                <?php endif; ?>
                <?php if (has_permission('Admin') || has_permission('Pemilik')) : ?>
                    <a href="<?php echo to_url('reports/kas_keluar.php'); ?>" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-gray-700 text-sm">📉 Laporan Kas Keluar</a>
                <?php endif; ?>
                <?php if (has_permission('Admin') || has_permission('Pemilik')) : ?>
                    <a href="<?php echo to_url('reports/laba_rugi.php'); ?>" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-gray-700 text-sm">💹 Laporan Laba Rugi</a>
                    <a href="<?php echo to_url('reports/jurnal_umum.php'); ?>" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-gray-700 text-sm">📖 Jurnal Umum</a>
                    <a href="<?php echo to_url('reports/buku_besar_kas.php'); ?>" class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-gray-700 text-sm">📚 Buku Besar Kas</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>