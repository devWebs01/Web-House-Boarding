# E-Kos - Sistem Manajemen Rumah Kost

[![Laravel](https://img.shields.io/badge/Laravel-11.x-FF2D20?style=for-the-badge&logo=laravel)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php)](https://php.net)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.2-7952B3?style=for-the-badge&logo=bootstrap)](https://getbootstrap.com)

## 📋 Deskripsi Proyek

E-Kos adalah platform web modern untuk pencarian, booking, dan manajemen rumah kost (boarding house) secara online. Sistem ini menghubungkan pemilik kost dengan pencari kost melalui platform yang terintegrasi dengan sistem pembayaran dan notifikasi.

### 🎯 Tujuan Utama

- Memudahkan pencari kost dalam menemukan hunian yang sesuai dengan kebutuhan
- Membantu pemilik kost dalam mengelola properti dan transaksi secara digital
- Menyediakan sistem pembayaran yang aman dan transparan
- Meningkatkan efisiensi proses pencarian dan booking kost

### ✨ Fitur Utama

- **🔍 Pencarian dan Filter**: Pencarian kost berdasarkan lokasi, harga, fasilitas, dan kategori
- **🏠 Manajemen Kost**: Sistem manajemen lengkap untuk pemilik kost
- **💳 Pembayaran Terintegrasi**: Integrasi dengan Midtrans untuk pembayaran online
- **📱 Notifikasi WhatsApp**: Notifikasi otomatis via WhatsApp menggunakan Fonnte
- **📊 Dashboard Admin**: Panel admin untuk verifikasi dan monitoring
- **🖼️ Galeri Foto**: Upload dan tampilan foto kost
- **📋 Sistem Booking**: Booking kamar dengan konfirmasi otomatis
- **🔐 Multi-Role Authentication**: Sistem autentikasi dengan multiple role (guest, owner, admin)
- **📈 Activity Logging**: Pencatatan aktivitas sistem menggunakan Spatie Activity Log
- **💾 Backup System**: Sistem backup database otomatis

## 🛠️ Teknologi Stack

### Backend
- **Framework**: Laravel 11.x
- **Bahasa**: PHP 8.2+
- **Database**: MySQL
- **Authentication**: Laravel Sanctum
- **Payment Gateway**: Midtrans
- **WhatsApp API**: Fonnte
- **Image Optimization**: Spatie Laravel Image Optimizer
- **Backup**: Spatie Laravel Backup
- **Activity Logging**: Spatie Laravel Activity Log

### Frontend
- **Framework CSS**: Bootstrap 5.2
- **JavaScript**: Vanilla JS + Axios
- **Build Tool**: Vite
- **CSS Preprocessor**: Sass

### Development Tools
- **Package Manager**: Composer (PHP), NPM (Node.js)
- **Code Quality**: Laravel Pint
- **Testing**: PHPUnit
- **Database Seeding**: Laravel Factories

## 🏗️ Arsitektur Aplikasi

### Struktur Database

#### Model Utama
- **User**: Pengguna sistem dengan role (guest, owner, admin)
- **BoardingHouse**: Data rumah kost dengan informasi lengkap
- **Room**: Kamar dalam rumah kost dengan harga dan status
- **Transaction**: Transaksi pemesanan kamar
- **Facility**: Fasilitas yang tersedia di kost
- **Regulation**: Peraturan kost
- **Gallery**: Galeri foto kost
- **Comment**: Sistem komentar dan review
- **Identity**: Data identitas pengguna
- **WebsiteSystem**: Konfigurasi sistem website

#### Entity Relationship Diagram (ERD)

ERD lengkap sistem E-Kos telah dibuat dalam format Mermaid dan disimpan dalam file [`ERD.mmd`](ERD.mmd) di root directory project.

**Diagram Interaktif:**

```mermaid
erDiagram
    USER ||--o{ BOARDING_HOUSE : owns
    USER ||--o{ TRANSACTION : makes
    USER ||--|| IDENTITY : has

    BOARDING_HOUSE ||--o{ ROOM : contains
    BOARDING_HOUSE ||--o{ FACILITY : provides
    BOARDING_HOUSE ||--o{ REGULATION : has
    BOARDING_HOUSE ||--o{ GALLERY : showcases
    BOARDING_HOUSE ||--o{ TRANSACTION : receives
    BOARDING_HOUSE ||--o{ COMMENT : receives

    ROOM ||--o{ TRANSACTION : booked_in

    USER } {
        int id PK
        string name
        string email
        string password
        string role
        timestamp email_verified_at
        timestamps created_at, updated_at
    }

    BOARDING_HOUSE } {
        int id PK
        string name
        text address
        string location_map
        int owner_id FK
        string thumbnail
        string category
        string verification_status
        int minimum_rental_period
        timestamps created_at, updated_at
    }

    ROOM } {
        int id PK
        int boarding_house_id FK
        string room_number
        decimal price
        decimal size
        string status
        timestamps created_at, updated_at
    }

    TRANSACTION } {
        int id PK
        int user_id FK
        int boarding_house_id FK
        int room_id FK
        string code
        date check_in
        date check_out
        decimal total
        string status
        string snapToken
        timestamps created_at, updated_at
    }

    FACILITY } {
        int id PK
        int boarding_house_id FK
        string name
        string icon
        timestamps created_at, updated_at
    }

    REGULATION } {
        int id PK
        int boarding_house_id FK
        string title
        text description
        timestamps created_at, updated_at
    }

    GALLERY } {
        int id PK
        int boarding_house_id FK
        string image_path
        string caption
        timestamps created_at, updated_at
    }

    COMMENT } {
        int id PK
        int boarding_house_id FK
        int user_id FK
        text content
        int rating
        timestamps created_at, updated_at
    }

    IDENTITY } {
        int id PK
        int user_id FK
        string full_name
        string phone_number
        string whatsapp_number
        text address
        string profile_picture
        timestamps created_at, updated_at
    }
```

**Legenda:**
- `||--o{` : One-to-Many relationship
- `||--||` : One-to-One relationship
- `PK` : Primary Key
- `FK` : Foreign Key
- `timestamps` : Laravel's automatic timestamps (created_at, updated_at)

**File ERD Lengkap:**
- File [`ERD.mmd`](ERD.mmd) berisi diagram ERD dengan dokumentasi detail setiap tabel
- Format Mermaid yang dapat digunakan untuk presentasi atau dokumentasi
- Dapat di-render di GitHub, GitLab, dan tools lain yang mendukung Mermaid

#### Relasi Database
```
User (1) ─── (N) BoardingHouse (owner_id)
User (1) ─── (N) Transaction (user_id)
User (1) ─── (1) Identity

BoardingHouse (1) ─── (N) Room (boarding_house_id)
BoardingHouse (1) ─── (N) Facility (boarding_house_id)
BoardingHouse (1) ─── (N) Regulation (boarding_house_id)
BoardingHouse (1) ─── (N) Gallery (boarding_house_id)
BoardingHouse (1) ─── (N) Transaction (boarding_house_id)
BoardingHouse (1) ─── (N) Comment (boarding_house_id)

Room (1) ─── (N) Transaction (room_id)
```

### Struktur Direktori

```
app/
├── Console/           # Artisan commands
├── Exceptions/        # Custom exceptions
├── Helpers/          # Helper functions
├── Http/
│   ├── Controllers/  # Request controllers
│   ├── Middleware/   # HTTP middleware
│   └── Kernel.php
├── Mail/             # Email templates
├── Models/           # Eloquent models
├── Providers/        # Service providers
├── Services/         # Business logic services
└── View/Components/  # Blade components

config/               # Configuration files
database/            # Migrations & seeders
public/              # Public assets
resources/
├── css/            # Stylesheets
├── js/             # JavaScript files
├── sass/           # Sass files
└── views/          # Blade templates
routes/              # Route definitions
storage/             # File storage
```

## 📋 Persyaratan Sistem

### Server Requirements
- **Web Server**: Apache/Nginx
- **PHP**: 8.2 atau lebih tinggi
- **Database**: MySQL 5.7+ / MariaDB 10.3+
- **Node.js**: 16.x atau lebih tinggi (untuk asset compilation)
- **Composer**: Untuk manajemen dependensi PHP
- **Git**: Untuk version control

### Extension PHP yang Dibutuhkan
- `php-curl`
- `php-gd`
- `php-mbstring`
- `php-xml`
- `php-zip`
- `php-bcmath`
- `php-intl`
- `php-fileinfo`
- `pdo_mysql`

## 🚀 Panduan Instalasi

### 1. Persiapan Environment

```bash
# Clone repository
git clone <repository-url>
cd rumah-kost

# Copy environment file
cp .env.example .env
```

### 2. Instalasi PHP Dependencies

```bash
# Install composer dependencies
composer install

# Generate application key
php artisan key:generate
```

### 3. Konfigurasi Database

Edit file `.env` dan sesuaikan konfigurasi database:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rumah_kost
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 4. Migrasi Database

```bash
# Jalankan migrasi
php artisan migrate

# Seed database (opsional)
php artisan db:seed
```

### 5. Instalasi Node Dependencies

```bash
# Install npm dependencies
npm install

# Build assets untuk production
npm run build

# Atau untuk development
npm run dev
```

### 6. Konfigurasi Payment Gateway (Midtrans)

Edit file `.env` dan tambahkan konfigurasi Midtrans:

```env
MIDTRANS_SERVER_KEY=your_server_key
MIDTRANS_CLIENT_KEY=your_client_key
MIDTRANS_IS_PRODUCTION=false
```

### 7. Konfigurasi WhatsApp API (Fonnte)

```env
FONNTE_API_KEY=your_fonnte_api_key
FONNTE_BASE_URL=https://api.fonnte.com
```

### 8. Konfigurasi Email (Opsional)

```env
MAIL_MAILER=smtp
MAIL_HOST=your_smtp_host
MAIL_PORT=587
MAIL_USERNAME=your_email@domain.com
MAIL_PASSWORD=your_email_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
```

## 🔧 Konfigurasi Tambahan

### Environment Variables

Buat file `.env` dengan konfigurasi berikut:

```env
APP_NAME="E-Kos"
APP_ENV=local
APP_KEY=base64:your-generated-key
APP_DEBUG=true
APP_URL=http://localhost

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rumah_kost
DB_USERNAME=your_username
DB_PASSWORD=your_password

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

MEMCACHED_HOST=127.0.0.1

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

# Payment Gateway
MIDTRANS_SERVER_KEY=your_server_key
MIDTRANS_CLIENT_KEY=your_client_key
MIDTRANS_IS_PRODUCTION=false

# WhatsApp API
FONNTE_API_KEY=your_fonnte_api_key

# File Storage
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false
```

## 🎯 Penggunaan Aplikasi

### 1. Menjalankan Aplikasi

```bash
# Jalankan server development
php artisan serve

# Akses aplikasi di browser
# http://localhost:8000
```

### 2. Mengakses Fitur Utama

#### Untuk Pengguna (Guest)
- **Pencarian Kost**: Kunjungi halaman utama dan gunakan fitur pencarian
- **Detail Kost**: Klik pada kost untuk melihat detail lengkap
- **Booking**: Login dan lakukan pemesanan kamar
- **Pembayaran**: Sistem akan mengarahkan ke halaman pembayaran Midtrans

#### Untuk Pemilik Kost (Owner)
- **Registrasi**: Daftar sebagai pemilik kost
- **Kelola Kost**: Tambah, edit, dan kelola informasi kost
- **Kelola Kamar**: Atur harga dan status kamar
- **Konfirmasi Booking**: Terima/konfirmasi pemesanan dari penyewa
- **Laporan Transaksi**: Monitor transaksi dan pendapatan

#### Untuk Admin
- **Verifikasi Kost**: Verifikasi kost yang didaftarkan pemilik
- **Kelola Pengguna**: Monitor aktivitas pengguna
- **Laporan Sistem**: Lihat laporan aktivitas dan transaksi
- **Backup Data**: Kelola sistem backup database

### 3. Contoh Penggunaan API

#### Payment API
```php
// Membuat transaksi pembayaran
POST /payment/create
{
    "order_id": "TRX-20250101-001",
    "gross_amount": 1500000,
    "customer_name": "John Doe",
    "customer_email": "john@example.com",
    "item_name": "Kamar Kost Premium",
    "quantity": 1,
    "price": 1500000
}
```

#### Payment Callback (Webhook)
```php
// Midtrans akan mengirim callback ke endpoint ini
POST /payment/callback
```

## 🔌 API Endpoints

### Payment Endpoints
- `POST /payment/create` - Membuat transaksi pembayaran
- `POST /payment/callback` - Webhook callback dari Midtrans
- `GET /payment/status/{orderId}` - Cek status pembayaran
- `POST /payment/cancel/{orderId}` - Batal pembayaran

### Authentication Endpoints
- `POST /login` - Login pengguna
- `POST /register` - Registrasi pengguna baru
- `POST /logout` - Logout pengguna
- `POST /password/forgot` - Lupa password
- `POST /password/reset` - Reset password

## 📊 Monitoring & Logging

### Activity Logging
Sistem menggunakan Spatie Laravel Activity Log untuk mencatat aktivitas pengguna:

```php
// Contoh log aktivitas
$user = User::find(1);
$user->name = 'Nama Baru';
$user->save(); // Otomatis dicatat dalam log
```

### Backup System
Sistem backup otomatis dapat dijalankan dengan:

```bash
# Backup database
php artisan backup:run

# Membersihkan backup lama
php artisan backup:clean

# Monitor backup
php artisan backup:list
```

## 🔒 Keamanan

### Fitur Keamanan
- **CSRF Protection**: Perlindungan CSRF pada form
- **Rate Limiting**: Pembatasan request untuk mencegah spam
- **Input Validation**: Validasi input pada semua endpoint
- **Password Hashing**: Enkripsi password dengan bcrypt
- **File Upload Security**: Validasi dan sanitasi file upload
- **SQL Injection Prevention**: Menggunakan Eloquent ORM

### Best Practices Security
- Environment variables untuk konfigurasi sensitif
- Secure headers dengan middleware
- Input sanitization
- File permission yang tepat
- Regular security updates

## 🧪 Testing

### Menjalankan Tests

```bash
# Jalankan semua tests
php artisan test

# Jalankan dengan coverage
php artisan test --coverage

# Jalankan test spesifik
php artisan test --filter=UserTest
```

### Menulis Tests

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register()
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/home');
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);
    }
}
```

## 🚀 Deployment

### Production Deployment

1. **Environment Setup**
   ```bash
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://yourdomain.com
   ```

2. **Database Migration**
   ```bash
   php artisan migrate --force
   ```

3. **Asset Compilation**
   ```bash
   npm run build
   ```

4. **Cache Optimization**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

5. **Permission Setup**
   ```bash
   chmod -R 755 storage
   chmod -R 755 bootstrap/cache
   chown -R www-data:www-data storage
   ```

### Web Server Configuration

#### Nginx Configuration
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /path/to/your/project/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

## 🤝 Kontribusi

### Cara Berkontribusi

1. Fork repository
2. Buat feature branch (`git checkout -b feature/amazing-feature`)
3. Commit perubahan (`git commit -m 'Add amazing feature'`)
4. Push ke branch (`git push origin feature/amazing-feature`)
5. Buat Pull Request

### Guidelines Kontribusi

- Ikuti PSR-12 coding standards
- Tulis tests untuk fitur baru
- Update dokumentasi jika diperlukan
- Pastikan semua tests lolos
- Gunakan conventional commits

### Conventional Commits Format

```
type(scope): description

[optional body]

[optional footer]
```

Types:
- `feat`: Fitur baru
- `fix`: Perbaikan bug
- `docs`: Dokumentasi
- `style`: Perubahan styling
- `refactor`: Refactoring kode
- `test`: Menambah tests
- `chore`: Maintenance

## 📝 Lisensi

Proyek ini menggunakan lisensi MIT. Lihat file [LICENSE](LICENSE) untuk detail lebih lanjut.

## 📞 Kontak & Support

### Tim Development
- **Email**: dev@ekos.com
- **Phone**: +62 xxx-xxxx-xxxx

### Dokumentasi API
- **Swagger Documentation**: `/api/documentation`
- **Postman Collection**: Tersedia di repository

### Issue & Bug Report
- **GitHub Issues**: [Buat Issue Baru](https://github.com/username/repository/issues)
- **Bug Template**: Gunakan template yang disediakan

## 🔄 Update & Maintenance

### Update Dependencies

```bash
# Update PHP dependencies
composer update

# Update Node dependencies
npm update

# Update Laravel framework
composer update laravel/framework
```

### Database Maintenance

```bash
# Optimasi database
php artisan optimize

# Clear cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Backup database
php artisan backup:run
```

## 📈 Performance Optimization

### Caching Strategy
- **Route Caching**: `php artisan route:cache`
- **Config Caching**: `php artisan config:cache`
- **View Caching**: `php artisan view:cache`
- **Application Caching**: Menggunakan Redis untuk cache

### Database Optimization
- **Indexing**: Index pada kolom yang sering di-query
- **Query Optimization**: Menggunakan Eloquent efficiently
- **Database Connection Pooling**: Konfigurasi pooling untuk production

### Asset Optimization
- **CSS/JS Minification**: Menggunakan Vite untuk production build
- **Image Optimization**: Menggunakan Spatie Image Optimizer
- **Lazy Loading**: Implementasi lazy loading untuk gambar

## 🔧 Troubleshooting

### Masalah Umum

#### Error 500 Internal Server Error
```bash
# Check PHP error logs
tail -f storage/logs/laravel.log

# Clear cache
php artisan cache:clear
php artisan config:clear
```

#### Migration Error
```bash
# Reset migrations (hati-hati dengan data)
php artisan migrate:reset

# Fresh migration
php artisan migrate:fresh --seed
```

#### Permission Error
```bash
# Fix storage permissions
chmod -R 755 storage
chmod -R 755 bootstrap/cache
chown -R www-data:www-data storage
```

#### Payment Gateway Error
```bash
# Check Midtrans configuration
php artisan tinker
>>> config('midtrans.server_key')
>>> config('midtrans.client_key')
```

## 📚 Resources

### Dokumentasi Laravel
- [Laravel Documentation](https://laravel.com/docs)
- [Laravel API Reference](https://laravel.com/api)

### Package Documentation
- [Midtrans PHP](https://github.com/Midtrans/midtrans-php)
- [Spatie Activity Log](https://spatie.be/docs/laravel-activitylog)
- [Spatie Backup](https://spatie.be/docs/laravel-backup)
- [Fonnte WhatsApp API](https://fonnte.com/)

### Tools & Utilities
- [Composer](https://getcomposer.org/)
- [NPM](https://www.npmjs.com/)
- [Vite](https://vitejs.dev/)
- [Bootstrap](https://getbootstrap.com/)

---

**E-Kos** - Platform terintegrasi untuk pencarian, booking, dan manajemen kost secara online 🚀