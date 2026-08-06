# CKM Official Website -- cucikarpetmasjid.com

Official website for cucikarpetmasjid.com, designed for cPanel hosting with PHP backend.

## Structure
- `index.html` -- Main website
- `assets/` -- CSS, JS, images (logo, portfolio)
- `send-enquiry.php` -- PHP backend for enquiry form
- `config.example.php` -- Configuration template (copy to config.php)
- `.htaccess` -- Apache configuration
- `favicon.svg` -- Favicon
- `README-UPLOAD.txt` -- Upload instructions

## Deployment
1. Copy `config.example.php` to `config.php` and fill in details
2. Upload all files to cPanel `public_html`
3. Ensure PHP mail() is configured on server
4. See `README-UPLOAD.txt` for detailed instructions

## Tech
- HTML/CSS/JS frontend
- PHP backend for enquiry form
- WebP optimized images
- .htaccess for clean URLs and caching
