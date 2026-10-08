# Laravel REST API

REST API dengan Laravel, MySQL, dan Laravel Sanctum: posts, users, autentikasi token, cuaca Perth (dengan cache), background job, dan queue welcome email.

## Stack

- Laravel (versi stabil terbaru), PHP 8.2+
- MySQL 8
- Laravel Sanctum (token auth)
- Queue driver `database`
- OpenWeatherMap (Weather API)

## Setup

```bash
git clone <repo-url> laravel-api
cd laravel-api
composer install
cp .env.example .env
php artisan key:generate
```

Atur `.env` (database MySQL dan API key cuaca), buat database kosong, lalu:

```bash
php artisan migrate
php artisan serve
```

API tersedia di `http://localhost:8000/api`.

## Environment variables

| Variabel | Default | Keterangan |
| --- | --- | --- |
| `DB_CONNECTION` | `mysql` | Gunakan MySQL |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | | Koneksi MySQL |
| `QUEUE_CONNECTION` | `database` | Driver queue |
| `MAIL_MAILER` | `log` | Untuk development email ditulis ke `storage/logs/laravel.log` |
| `WEATHER_API_KEY` | | **Wajib**, API key OpenWeatherMap |
| `WEATHER_BASE_URL` | `https://api.openweathermap.org/data/2.5` | |
| `WEATHER_CITY` | `Perth,AU` | |
| `WEATHER_UNITS` | `metric` | `metric` (°C, m/s), `imperial`, atau `standard` |
| `WEATHER_CACHE_TTL` | `900` | Lama cache dalam detik (15 menit) |

## Weather API (OpenWeatherMap)

1. Daftar gratis di https://openweathermap.org/api dan buat API key (Current Weather Data).
2. Isi `WEATHER_API_KEY` di `.env`. Key baru bisa butuh beberapa menit sampai aktif.
3. Panggil `GET /api/weather`.

Perilaku:

- Hasil di-cache 15 menit (`WEATHER_CACHE_TTL`), jadi panggilan berulang tidak menembak API eksternal.
- Job `RefreshWeatherData` memperbarui cache setiap jam lewat scheduler.
- Jika API eksternal gagal dan ada data lama, data lama dikembalikan dengan `"stale": true`. Jika tidak ada data sama sekali, response `503`.

## Queue worker

Job welcome email dan refresh cuaca berjalan lewat queue.

```bash
php artisan queue:work --tries=3
```

Untuk development bisa juga `php artisan queue:listen`. Untuk production jalankan worker di bawah Supervisor.

## Scheduler

Job cuaca dijadwalkan per jam di `routes/console.php`. Jalankan scheduler:

```bash
php artisan schedule:work          # development
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1   # production (cron)
```

Menjalankan refresh cuaca manual:

```bash
php artisan tinker --execute="App\Jobs\RefreshWeatherData::dispatch();"
```

## Artisan command: kirim welcome email manual

```bash
php artisan app:send-welcome-email 1                 # by user ID, masuk queue
php artisan app:send-welcome-email jane@example.com  # by email, masuk queue
php artisan app:send-welcome-email 1 --sync          # kirim langsung tanpa queue
```

Pastikan `queue:work` berjalan agar job yang masuk queue diproses. Dengan `MAIL_MAILER=log`, hasil email ada di `storage/logs/laravel.log`.

## Menjalankan tes

Tes memakai SQLite in-memory dan HTTP fake untuk API cuaca, jadi tidak butuh MySQL atau API key.

```bash
php artisan test
```

Cakupan: Posts (CRUD, otorisasi, validasi, pagination), Users/Auth (register, login, logout, show, list), Weather (sukses, cache, error, stale fallback, job), dan Welcome Email (job + artisan command).

## Dokumentasi

- [`docs/API.md`](docs/API.md): referensi endpoint dengan contoh request/response
- [`docs/openapi.yaml`](docs/openapi.yaml): spesifikasi OpenAPI 3 (bisa dibuka di Swagger Editor)
- [`docs/postman_collection.json`](docs/postman_collection.json): Postman collection (lihat bagian di bawah)

