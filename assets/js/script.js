// assets/js/script.js

document.addEventListener("DOMContentLoaded", function () {
  console.log("Script JavaScript berhasil dimuat!");

  // Contoh sederhana: Menampilkan alert saat tombol tertentu diklik (jika ada)
  // const myButton = document.getElementById('myButton');
  // if (myButton) {
  //     myButton.addEventListener('click', function() {
  //         alert('Tombol diklik!');
  //     });
  // }

  // Contoh validasi form sederhana (Anda akan mengembangkan ini)
  const forms = document.querySelectorAll("form");
  forms.forEach((form) => {
    form.addEventListener("submit", function (event) {
      // Contoh validasi: Pastikan input teks tidak kosong
      // const requiredInputs = form.querySelectorAll('input[type="text"][required]');
      // requiredInputs.forEach(input => {
      //     if (input.value.trim() === '') {
      //         alert('Semua field wajib harus diisi!');
      //         event.preventDefault(); // Mencegah form disubmit
      //     }
      // });
      // Anda akan menambahkan lebih banyak logika validasi di sini
    });
  });
});
