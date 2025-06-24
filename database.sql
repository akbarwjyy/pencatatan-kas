
-- Tabel pengguna
CREATE TABLE pengguna (
    id_pengguna VARCHAR(8) PRIMARY KEY, 
    username VARCHAR(50) NOT NULL, 
    password VARCHAR(255) NOT NULL, 
    jabatan VARCHAR(20) NOT NULL,
    email VARCHAR(50) UNIQUE NOT NULL 
);

-- Tabel akun
CREATE TABLE akun (
    id_akun VARCHAR(8) PRIMARY KEY,
    nama_akun VARCHAR(50) NOT NULL UNIQUE 
);

-- Tabel customer
CREATE TABLE customer (
    id_customer VARCHAR(8) PRIMARY KEY,
    nama_customer VARCHAR(50) NOT NULL,
    no_hp VARCHAR(15), 
    alamat VARCHAR(100) 
);

-- Tabel pemesanan
CREATE TABLE pemesanan (
    id_pesan VARCHAR(8) PRIMARY KEY,
    id_customer VARCHAR(8) NOT NULL,
    tgl_pesan DATE NOT NULL,
    tgl_kirim DATE,
    quantity INT(10) NOT NULL,
    uang_muka DECIMAL(15, 2) DEFAULT 0.00, 
    sub_total DECIMAL(15, 2) NOT NULL,
    sisa DECIMAL(15, 2) NOT NULL,
    status_pesanan VARCHAR(20) DEFAULT 'pending',
    FOREIGN KEY (id_customer) REFERENCES customer(id_customer) ON DELETE CASCADE ON UPDATE CASCADE
);

-- Tabel transaksi (menggabungkan informasi dari 'Data yang Dikelola' dan 'Struktur Tabel Database')
CREATE TABLE transaksi (
    id_transaksi VARCHAR(8) PRIMARY KEY,
    id_pesan VARCHAR(8), 
    id_akun VARCHAR(8) NOT NULL,
    id_customer VARCHAR(8) NOT NULL, 
    jumlah_dibayar DECIMAL(15, 2) NOT NULL,
    metode_pembayaran VARCHAR(30),
    keterangan VARCHAR(255), 
    tgl_transaksi DATE NOT NULL,
    total_tagihan DECIMAL(15, 2), 
    sisa_pembayaran DECIMAL(15, 2), 
    FOREIGN KEY (id_pesan) REFERENCES pemesanan(id_pesan) ON DELETE SET NULL ON UPDATE CASCADE, 
    FOREIGN KEY (id_akun) REFERENCES akun(id_akun) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (id_customer) REFERENCES customer(id_customer) ON DELETE RESTRICT ON UPDATE CASCADE
);

-- Tabel kas_masuk (berdasarkan transaksi)
CREATE TABLE kas_masuk (
    id_kas_masuk VARCHAR(8) PRIMARY KEY,
    id_transaksi VARCHAR(8) UNIQUE NOT NULL, 
    tgl_kas_masuk DATE NOT NULL,
    jumlah DECIMAL(15, 2) NOT NULL, 
    keterangan VARCHAR(255), 
    FOREIGN KEY (id_transaksi) REFERENCES transaksi(id_transaksi) ON DELETE CASCADE ON UPDATE CASCADE
);

-- Tabel kas_keluar
CREATE TABLE kas_keluar (
    id_kas_keluar VARCHAR(8) PRIMARY KEY,
    id_akun VARCHAR(8) NOT NULL,
    tgl_kas_keluar DATE NOT NULL,
    jumlah DECIMAL(15, 2) NOT NULL, 
    keterangan VARCHAR(255), 
    FOREIGN KEY (id_akun) REFERENCES akun(id_akun) ON DELETE RESTRICT ON UPDATE CASCADE
);

-- Contoh data awal (untuk testing)
INSERT INTO pengguna (id_pengguna, nama, password, jabatan, email) VALUES
('PGN001', 'Admin Utama', '$2y$10$C82oHn1k9jK2F.1m5q6.0eZ.g.l/uY/8h1/a7h.x0.y1.z2.w3.t4.v5.r6.q7.s8.m9', 'Admin', 'admin@example.com'),
('PGN002', 'Pemilik Bisnis', '$2y$10$C82oHn1k9jK2F.1m5q6.0eZ.g.l/uY/8h1/a7h.x0.y1.z2.w3.t4.v5.r6.q7.s8.m9', 'Pemilik', 'pemilik@example.com'),
('PGN003', 'Pegawai Penjualan', '$2y$10$C82oHn1k9jK2F.1m5q6.0eZ.g.l/uY/8h1/a7h.x0.y1.z2.w3.t4.v5.r6.q7.s8.m9', 'Pegawai', 'pegawai@example.com');
-- Password untuk semua di atas adalah 'password' (setelah di-hash dengan bcrypt)

INSERT INTO akun (id_akun, nama_akun) VALUES
('AK001', 'Kas'),
('AK002', 'Penjualan Ampyang'),
('AK003', 'Pembelian Bahan Baku'),
('AK004', 'Biaya Gaji'),
('AK005', 'Pendapatan Lain-lain'),
('AK006', 'Beban Operasional');

INSERT INTO customer (id_customer, nama_customer, no_hp, alamat) VALUES
('CUST01', 'Budi Santoso', '081234567890', 'Jl. Merdeka No.10'),
('CUST02', 'Siti Aminah', '087654321098', 'Jl. Maju Jaya No.5'),
('CUST03', 'Pak Karta', '085000000000', 'Jl. Kebon Durian');
