# Panduan Deploy PENINK ke Server Kominfo

## 📋 Prasyarat Server

- **OS:** Ubuntu 22.04 LTS / Windows Server (sesuai kebijakan Diskominfo)
- **Web Server:** Nginx atau Apache
- **PHP:** 8.1+ dengan ekstensi lengkap
- **Database:** MySQL 8.0+ atau MariaDB 10.3+
- **Composer:** 2.x
- **Git:** untuk pull source code
- **SSL Certificate:** dari Let's Encrypt atau dari Diskominfo

---

## 🚀 Langkah Deployment

### 1. Setup Server (sekali saja)

Install dependency:

```bash
sudo apt update
sudo apt install -y php8.1-fpm php8.1-mysql php8.1-mbstring php8.1-xml \
  php8.1-bcmath php8.1-curl php8.1-gd php8.1-zip mysql-server nginx git unzip
```

Install Composer:

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

Install Node.js (untuk build frontend):

```bash
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs
```

### 2. Setup Database

Login ke MySQL:

```bash
sudo mysql
```

Bikin database & user:

```sql
CREATE DATABASE penink_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'penink_user'@'localhost' IDENTIFIED BY 'PASSWORD_KUAT_ANDA';
GRANT ALL PRIVILEGES ON penink_db.* TO 'penink_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 3. Deploy Backend

```bash
cd /var/www
sudo git clone https://github.com/edwinfeb-dotcom/penink-backend.git
cd penink-backend

# Install dependencies (production mode)
sudo composer install --no-dev --optimize-autoloader

# Setup environment
sudo cp .env.example .env
sudo nano .env
```

**Edit `.env` untuk production:**

```env
APP_NAME=PENINK
APP_ENV=production
APP_DEBUG=false
APP_URL=https://penink.landak.go.id

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=penink_db
DB_USERNAME=penink_user
DB_PASSWORD=PASSWORD_KUAT_ANDA

SANCTUM_STATEFUL_DOMAINS=penink.landak.go.id

GOOGLE_CLIENT_ID=xxx
GOOGLE_CLIENT_SECRET=xxx
GOOGLE_REDIRECT_URI=https://penink.landak.go.id/api/auth/google/callback
```

**Generate key & migrate:**

```bash
sudo php artisan key:generate
sudo php artisan migrate --force
sudo php artisan storage:link
```

**Set permission:**

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### 4. Deploy Frontend

```bash
cd /var/www/penink-frontend
sudo git clone https://github.com/edwinfeb-dotcom/penink-frontend.git .
sudo npm install
```

Bikin `.env`:

```env
VITE_API_URL=https://penink.landak.go.id
```

Build:

```bash
sudo npm run build
```

Hasil build ada di `dist/`. Copy ke folder yang bisa diakses Nginx:

```bash
sudo cp -r dist/* /var/www/penink-backend/public/
```

### 5. Setup Nginx

Bikin file `/etc/nginx/sites-available/penink`:

```nginx
server {
    listen 80;
    server_name penink.landak.go.id;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name penink.landak.go.id;

    root /var/www/penink-backend/public;
    index index.php index.html;

    # SSL (Let's Encrypt)
    ssl_certificate /etc/letsencrypt/live/penink.landak.go.id/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/penink.landak.go.id/privkey.pem;

    # Security headers
    server_tokens off;

    # Logs
    access_log /var/log/nginx/penink-access.log;
    error_log /var/log/nginx/penink-error.log;

    # Frontend SPA
    location / {
        try_files $uri $uri/ /index.html;
    }

    # API
    location /api {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Block hidden files
    location ~ /\. {
        deny all;
    }
}
```

Enable site:

```bash
sudo ln -s /etc/nginx/sites-available/penink /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 6. Setup SSL (Let's Encrypt)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d penink.landak.go.id
```

### 7. Setup Cron untuk Laravel Scheduler

```bash
sudo crontab -e -u www-data
```

Tambah:

```
* * * * * cd /var/www/penink-backend && php artisan schedule:run >> /dev/null 2>&1
```

---

## 🔄 Cara Update Aplikasi

Kalau ada update dari developer:

```bash
cd /var/www/penink-backend
sudo git pull origin main
sudo composer install --no-dev --optimize-autoloader
sudo php artisan migrate --force
sudo php artisan optimize:clear
sudo php artisan config:cache
sudo php artisan route:cache
sudo php artisan view:cache
sudo chown -R www-data:www-data storage bootstrap/cache
```

Untuk frontend:

```bash
cd /var/www/penink-frontend
sudo git pull origin main
sudo npm install
sudo npm run build
sudo cp -r dist/* /var/www/penink-backend/public/
```

---

## 💾 Backup Rutin

Bikin script backup di `/home/backup/penink-backup.sh`:

```bash
#!/bin/bash
BACKUP_DIR="/home/backup/penink"
DATE=$(date +%Y%m%d_%H%M%S)
mkdir -p $BACKUP_DIR

# Backup database
mysqldump -u penink_user -p'PASSWORD' penink_db > $BACKUP_DIR/db_$DATE.sql

# Backup storage (logo uploads)
tar -czf $BACKUP_DIR/storage_$DATE.tar.gz /var/www/penink-backend/storage/app/public

# Hapus backup lebih dari 30 hari
find $BACKUP_DIR -type f -mtime +30 -delete
```

Set cron untuk backup harian jam 2 pagi:

```
0 2 * * * /bin/bash /home/backup/penink-backup.sh
```

---

## 🚨 Troubleshooting

### "502 Bad Gateway"

- Cek PHP-FPM: `sudo systemctl status php8.1-fpm`
- Restart: `sudo systemctl restart php8.1-fpm`

### "500 Internal Server Error"

- Cek log Laravel: `tail -f /var/www/penink-backend/storage/logs/laravel.log`
- Cek permission: `sudo chmod -R 775 storage bootstrap/cache`

### Logo upload gagal

- Cek folder: `ls -la /var/www/penink-backend/storage/app/public/link-hub-logos`
- Cek permission: `sudo chown -R www-data:www-data storage`

### SSL error

- Cek certificate: `sudo certbot certificates`
- Renew: `sudo certbot renew --dry-run`

---

## 📞 Kontak

Untuk kendala deployment, hubungi developer: [Nama Kamu]