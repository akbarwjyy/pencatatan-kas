
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

