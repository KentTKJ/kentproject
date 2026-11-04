SISTEM MBG SEKOLAH - VERSI XAMPP
================================

PASANG
1. Salin folder "mbg" ini ke C:\xampp\htdocs\  (hasilnya C:\xampp\htdocs\mbg).
2. Buka XAMPP Control Panel, Start "Apache" dan "MySQL".
3. Buka http://localhost/mbg/install.php di browser (cukup sekali).
4. Login di http://localhost/mbg/ dengan akun awal:
     admin   / admin123
     piket   / piket123
     pembagi / pembagi123
5. Masuk sebagai admin, ganti ketiga password di bagian "Ganti password".
6. HAPUS file install.php dari folder mbg.

PEMAKAIAN
- Admin  : isi daftar siswa tiap kelas, atur isi per ikat (awal 7 ompreng).
           Daftar siswa bisa diunggah dari Excel (.xlsx) atau .csv: kolom A = Kelas
           (sesuai nama kelas di sistem), kolom B = Nama. Contoh template ada di halaman Admin.
           Jika .xlsx gagal dibaca, simpan sebagai CSV, atau aktifkan extension=zip
           di php.ini (XAMPP > Config > PHP) lalu restart Apache.
- Piket  : pilih kelas, hilangkan centang siswa yang tidak masuk, kirim.
           Kelas tanpa siswa absen tetap dikirim (semua tercentang).
- Pembagi: melihat total ikat dan per kelas yang harus diambil, lalu
           menekan "Tandai dibagikan". Pembagi tidak bisa mengubah kehadiran.
  Halaman pembagi memuat ulang otomatis tiap 30 detik.

DIAKSES GURU LAIN (WIFI SEKOLAH)
- Cari IP komputer (cmd: ipconfig, lihat IPv4, misal 192.168.1.10).
- Guru membuka http://192.168.1.10/mbg/ dari HP/laptop di WiFi yang sama.
- Jika tidak bisa dibuka, izinkan Apache di Windows Firewall (port 80).
- Komputer harus menyala dan Apache + MySQL berjalan selama dipakai.

KEAMANAN & CADANGAN
- Jangan buka XAMPP ke internet. Pakai hanya di jaringan sekolah.
- Disarankan beri password pada user root MySQL (phpMyAdmin > Akun pengguna),
  lalu samakan DB_PASS di lib.php.
- Cadangan data: phpMyAdmin > database mbg_sekolah > Ekspor, simpan berkala.
- Nama siswa yang tidak hadir tidak disimpan; hanya nama yang hadir dan jumlahnya.
- Daftar kelas ada di fungsi kelas_list() di lib.php. Contoh penamaan dengan jurusan
  (hasilnya X TKJ 1, X TKJ 2, X AKL 1, dst.):

  function kelas_list() {
      $k = [];
      $susunan = [
          'X'   => ['TKJ' => 3, 'AKL' => 2],
          'XI'  => ['TKJ' => 3, 'AKL' => 2],
          'XII' => ['TKJ' => 3, 'AKL' => 2],
      ];
      foreach ($susunan as $tingkat => $jurusan)
          foreach ($jurusan as $nama => $jml)
              for ($i = 1; $i <= $jml; $i++) $k[] = "$tingkat $nama $i";
      return $k;
  }

  Angka di sebelah nama jurusan = jumlah kelas jurusan itu di tingkat tersebut.
  Nama kelas maksimal 10 karakter (contoh: XII TKJ 1 = 9 karakter).
