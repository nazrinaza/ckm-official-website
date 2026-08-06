CUCIKARPETMASJID.COM — CARA UPLOAD KE CPANEL
==============================================

KEPERLUAN HOSTING
- PHP 8.0 atau lebih baharu
- PHP cURL aktif
- Apache/cPanel biasa

LANGKAH UPLOAD
1. Login cPanel dan buka File Manager.
2. Buka folder public_html untuk domain cucikarpetmasjid.com.
3. Upload semua fail ke dalam public_html.
4. Pastikan index.html berada terus dalam public_html, bukan dalam folder tambahan.

AKTIFKAN BORANG SENDGRID
1. Dalam File Manager, duplicate config.example.php.
2. Namakan salinan itu config.php.
3. Edit config.php dan isi:
   - SENDGRID_API_KEY
   - SENDGRID_FROM_EMAIL (mesti Verified Sender / authenticated domain SendGrid)
   - ENQUIRY_TO_EMAIL (alamat penerima enquiry)
4. Simpan. Fail .htaccess dalam pakej akan menghalang config.php daripada dibuka melalui web.
5. Buka website dan hantar satu enquiry percubaan.

PENTING
- Jangan masukkan API key ke dalam index.html atau assets/form.js.
- Jangan kongsi config.php kepada pihak lain.
- Jika borang gagal, semak SendGrid Activity dan cPanel Error Log.
- Sekiranya .htaccess menghasilkan Error 500, minta pihak hosting mengaktifkan
  mod_rewrite/mod_headers atau buang blok yang tidak disokong.

STRUKTUR FAIL
- index.html                  Halaman utama
- assets/style.css            Rekaan laman
- assets/form.js              Penghantaran borang
- assets/logo-square.png      Logo (square)
- assets/logo-text.png        Logo (text)
- assets/Logo1.webp           Logo lama NAQI (backup)
- assets/Past_Job_...webp     Gambar portfolio
- send-enquiry.php            API borang SendGrid
- config.example.php          Contoh konfigurasi
- .htaccess                   Keselamatan asas dan routing
