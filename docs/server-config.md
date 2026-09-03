# Konfigurasi Server untuk Upload File Geospasial Besar

Konfigurasi ini **wajib** diterapkan agar proses upload data geospasial berukuran besar (DEM, Raster, Shapefile ZIP) tidak gagal karena batas default PHP dan Nginx.

---

## 1. PHP Configuration (`php.ini`)

Temukan lokasi `php.ini` aktif dengan menjalankan:

```bash
php --ini
# atau untuk PHP-FPM:
php-fpm --ini
```

Edit file `php.ini` dan sesuaikan nilai berikut:

```ini
; Ukuran maksimum single file yang bisa di-upload
upload_max_filesize = 1024M

; Ukuran maksimum total POST body (harus >= upload_max_filesize + overhead)
post_max_size = 1200M

; Maksimum waktu eksekusi script (600 detik = 10 menit untuk analisis besar)
max_execution_time = 600

; Maksimum input time (pastikan sama atau lebih besar dari execution_time)
max_input_time = 600

; Memory yang tersedia untuk PHP script
memory_limit = 1024M
```

Setelah mengubah `php.ini`, restart PHP-FPM atau web server:

```bash
# Untuk PHP-FPM
sudo systemctl restart php8.x-fpm

# Untuk Apache
sudo systemctl restart apache2

# Untuk Nginx + PHP-FPM (tidak perlu restart Nginx, cukup PHP-FPM)
sudo systemctl restart php8.x-fpm
```

---

## 2. Nginx Configuration

Edit file konfigurasi Nginx untuk virtual host aplikasi ini (biasanya di `/etc/nginx/sites-available/dishut` atau `/etc/nginx/conf.d/dishut.conf`):

```nginx
server {
    listen 80;
    server_name dishut.example.com;
    root /var/www/dishut/public;

    # Wajib: Batas ukuran request body untuk upload file besar
    client_max_body_size 1200M;

    # Timeout untuk request yang lama (analisis geospasial bisa > 5 menit)
    proxy_read_timeout 600;
    proxy_connect_timeout 60;
    proxy_send_timeout 600;

    # Timeout untuk FastCGI (PHP-FPM)
    fastcgi_read_timeout 600;
    fastcgi_send_timeout 600;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.x-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

Test dan reload konfigurasi Nginx:

```bash
sudo nginx -t && sudo systemctl reload nginx
```

---

## 3. Python CPI Engine Configuration

Python service dijalankan **secara terpisah** dari PHP. Ia menggunakan Uvicorn dan berjalan di port **8001** (bukan 8000 agar tidak konflik dengan PHP dev server).

### Konfigurasi `.env` Python (file `CPI/.env`):

Buat file `CPI/.env` dari template `CPI/.env.example`:

```bash
cd CPI
cp .env.example .env
```

Edit `CPI/.env`:

```env
CRITICAL_LAND_BASE_DIR=D:/apps/dishut/CPI
CRITICAL_LAND_UPLOADS_DIR=D:/apps/dishut/CPI/uploads
CRITICAL_LAND_OUTPUTS_DIR=D:/apps/dishut/CPI/outputs
CRITICAL_LAND_CONFIG=D:/apps/dishut/CPI/config/rules.yaml
CRITICAL_LAND_PUBLIC_BASE_URL=http://127.0.0.1:8001
```

### Menjalankan Python Service (Development):

```powershell
# Windows PowerShell
cd D:\apps\dishut\CPI
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
uvicorn app:app --reload --host 0.0.0.0 --port 8001
```

```bash
# Linux/Mac
cd /var/www/dishut/CPI
python -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
uvicorn app:app --reload --host 0.0.0.0 --port 8001
```

### Health Check:

```bash
curl http://127.0.0.1:8001/health
# Expected: {"status":"ok","service":"critical-land-ahp-cpi","version":"2.0.0"}
```

### Menjalankan sebagai Systemd Service (Linux Production):

```ini
# /etc/systemd/system/cpi-engine.service
[Unit]
Description=CPI Engine Python Service
After=network.target

[Service]
User=www-data
WorkingDirectory=/var/www/dishut/CPI
ExecStart=/var/www/dishut/CPI/.venv/bin/uvicorn app:app --host 0.0.0.0 --port 8001 --workers 2
Restart=always
RestartSec=5
EnvironmentFile=/var/www/dishut/CPI/.env

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable cpi-engine
sudo systemctl start cpi-engine
sudo systemctl status cpi-engine
```

---

## 4. Storage Directory

Pastikan direktori storage dapat ditulis oleh PHP dan dibaca oleh Python:

```bash
# Linux: berikan permission ke web server user
mkdir -p /var/www/dishut/storage/app/projects
chown -R www-data:www-data /var/www/dishut/storage
chmod -R 775 /var/www/dishut/storage

# Atau jika Python berjalan sebagai user berbeda, gunakan group yang sama
usermod -aG www-data python_service_user
```

> **Penting**: PHP dan Python **harus berbagi filesystem** karena PHP hanya mengirim path file ke Python, bukan konten file. Pada deployment terpisah (Docker, dll.), gunakan shared volume.

---

## 5. Ringkasan Port

| Service | Port | Keterangan |
|---------|------|------------|
| PHP Laravel (dev) | 8000 | `php artisan serve` |
| PHP Laravel (prod) | 80/443 | Via Nginx |
| Python CPI Engine | 8001 | `uvicorn app:app --port 8001` |
| MySQL | 3306 | Database PHP |
