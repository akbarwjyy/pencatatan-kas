<?php
// Sertakan header
require_once '../../layout/header.php';

// Pastikan hanya Admin yang bisa mengakses halaman ini
if (!has_permission('Admin')) {
    set_flash_message("Anda tidak memiliki izin untuk mengakses halaman ini.", "error");
    redirect('../../modules/dashboard/index.php');
}

$id_akun_error = $nama_akun_error = $jenis_akun_error = "";
$id_akun = $nama_akun = "";
$jenis_akun = "";

// Definisi jenis akun dan kode awalnya
$jenis_akun_list = [
    '1' => 'Aktiva',
    '2' => 'Kewajiban',
    '3' => 'Modal',
    '4' => 'Pendapatan',
    '5' => 'Beban',
    '6' => 'Kas'
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitasi input
    $jenis_akun = sanitize_input($_POST['jenis_akun']);
    $nama_akun = sanitize_input($_POST['nama_akun']);

    // Generate ID akun berdasarkan jenis akun
    if (!empty($jenis_akun)) {
        // Cari ID terakhir dengan awalan jenis akun yang dipilih
        $last_id_query = "SELECT MAX(id_akun) as last_id FROM akun WHERE id_akun LIKE '{$jenis_akun}%'";
        $result = $conn->query($last_id_query);
        $row = $result->fetch_assoc();
        $last_id = $row['last_id'];

        if ($last_id) {
            // Ekstrak angka dari ID terakhir dan tambahkan 1
            $last_num = (int)substr($last_id, 1);
            $new_num = $last_num + 1;
        } else {
            // Jika belum ada akun dengan jenis ini, mulai dari 001
            $new_num = 1;
        }

        // Format ID: [jenis_akun][3 digit angka]
        $id_akun = $jenis_akun . sprintf("%03d", $new_num);
    } else {
        $jenis_akun_error = "Jenis Akun harus dipilih.";
    }

    if (empty($nama_akun)) {
        $nama_akun_error = "Nama Akun tidak boleh kosong.";
    } elseif (strlen($nama_akun) > 50) {
        $nama_akun_error = "Nama Akun maksimal 50 karakter.";
    }

    // Jika tidak ada error validasi, coba simpan ke database
    if (empty($jenis_akun_error) && empty($nama_akun_error)) {
        // Cek apakah id_akun sudah ada di database
        $check_sql = "SELECT id_akun FROM akun WHERE id_akun = ?";
        $stmt_check = $conn->prepare($check_sql);
        $stmt_check->bind_param("s", $id_akun);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $id_akun_error = "ID Akun sudah ada. Gunakan ID lain.";
            set_flash_message("Gagal menambahkan akun: ID Akun sudah ada.", "error");
        } else {
            $stmt_check->close();

            // Query untuk menambah data akun
            $sql = "INSERT INTO akun (id_akun, nama_akun) VALUES (?, ?)";

            // Gunakan prepared statement untuk keamanan
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ss", $id_akun, $nama_akun); // 'ss' karena kedua parameter adalah string

                if ($stmt->execute()) {
                    set_flash_message("Akun berhasil ditambahkan!", "success");
                    redirect('index.php'); // Redirect ke halaman daftar akun
                } else {
                    set_flash_message("Gagal menambahkan akun: " . $stmt->error, "error");
                }
                $stmt->close();
            } else {
                set_flash_message("Error prepared statement: " . $conn->error, "error");
            }
        }
    } else {
        set_flash_message("Silakan perbaiki kesalahan pada formulir.", "error");
    }
}
?>

<div class="bg-white p-8 rounded-lg shadow-xl max-w-md mx-auto my-8">
    <h1 class="text-2xl font-bold text-gray-800 mb-4 text-center">Tambah Akun</h1>
    <p class="text-gray-600 mb-6 text-center">Pilih jenis akun dan masukkan nama akun. ID akan dibuat otomatis.</p>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="mb-4">
            <label for="jenis_akun" class="block text-gray-700 text-sm font-bold mb-2">Kelompok Akun:</label>
            <select id="jenis_akun" name="jenis_akun" required
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
                <option value="">-- Pilih Kelompok Akun --</option>
                <?php foreach ($jenis_akun_list as $kode => $nama) : ?>
                    <option value="<?php echo $kode; ?>" <?php echo ($jenis_akun == $kode) ? 'selected' : ''; ?>>
                        <?php echo $kode . ' - ' . $nama; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $jenis_akun_error; ?></span>
        </div>
        <!-- <div class="mb-4">
            <label for="id_akun" class="block text-gray-700 text-sm font-bold mb-2">ID Akun:</label>
            <input type="text" id="id_akun" name="id_akun" value="<?php echo htmlspecialchars($id_akun); ?>" readonly
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100 cursor-not-allowed">
            <span class="text-gray-500 text-xs mt-1 block">ID akan dibuat otomatis berdasarkan jenis akun</span>
        </div> -->
        <div class="mb-6">
            <label for="nama_akun" class="block text-gray-700 text-sm font-bold mb-2">Nama Akun:</label>
            <input type="text" id="nama_akun" name="nama_akun" value="<?php echo htmlspecialchars($nama_akun); ?>" required maxlength="50"
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:ring-2 focus:ring-green-500">
            <span class="text-red-500 text-xs italic mt-1 block"><?php echo $nama_akun_error; ?></span>
        </div>
        <div class="flex items-center justify-between">
            <button type="submit"
                class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Simpan
            </button>
            <a href="index.php"
                class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Batal
            </a>
        </div>
    </form>
</div>

<?php
?>