# Penjelasan Proyek

Dokumen ini menjelaskan struktur kode dan alasan di balik keputusan desainnya, mengikuti poin di brief.

## Struktur

```
app/
  Console/Commands/DispatchWelcomeEmail.php   artisan command dispatch manual
  Exceptions/WeatherUnavailableException.php
  Http/
    Controllers/Api/                          Auth, Post, User, Weather
    Requests/                                 validasi (Form Request)
    Resources/                                bentuk response JSON
  Jobs/                                       SendWelcomeEmail, RefreshWeatherData
  Mail/WelcomeMail.php
  Models/                                     User, Post
  Policies/PostPolicy.php
  Services/WeatherService.php
config/weather.php
database/migrations/                          tabel posts
routes/api.php, routes/console.php            endpoint dan scheduler
tests/Feature/                                Post, Auth/User, Weather, WelcomeEmail
```

## Database dan relasi

Tabel `users`, `personal_access_tokens` (Sanctum), `jobs`, dan `cache` datang dari skeleton Laravel. Migrasi kustom hanya `posts`:

- `user_id` foreign key ke `users` dengan `cascadeOnDelete`
- `title`, `body`
- index pada `created_at`

Relasi: `User hasMany Post` dan `Post belongsTo User`. Daftar post memakai `with('user')` agar tidak terjadi N+1 query.

## Autentikasi

Sanctum dengan token personal. `register` dan `login` mengembalikan token, `logout` menghapus token yang sedang dipakai saja (perangkat lain tetap login). Password di-hash otomatis lewat cast `hashed` di model.

Endpoint `login` diberi throttle 5 per menit untuk mengurangi brute force, dan pesan error sengaja sama untuk email tidak ada dan password salah.

## Endpoint dan REST

- Route memakai `apiResource` dengan route model binding, sehingga id yang tidak ada otomatis menghasilkan 404.
- Response memakai API Resource agar field yang keluar eksplisit (password tidak pernah bocor).
- Kode status: 201 untuk create, 204 untuk delete, 401 belum login, 403 bukan pemilik, 422 validasi, 503 layanan cuaca bermasalah.
- Di `bootstrap/app.php`, semua path `api/*` selalu dirender sebagai JSON, walau client lupa header `Accept`.

Keputusan yang perlu diketahui:

- `GET /posts` dan `GET /posts/{id}` publik (baca saja). Tulis butuh token.
- `GET /users/{id}` dan `GET /users` butuh token karena berisi email. Brief hanya menyebut `users/{id}`, tapi poin pagination menyebut list users, jadi `GET /users` ditambahkan.
- Ubah dan hapus post hanya untuk pemilik, lewat `PostPolicy`.

## Validasi

Semua input lewat Form Request (`RegisterRequest`, `LoginRequest`, `StorePostRequest`, `UpdatePostRequest`). `per_page` di list dibatasi 1 sampai 100 supaya client tidak bisa meminta seluruh tabel sekaligus. Untuk `PATCH`, aturan memakai `sometimes` agar update parsial valid.

## Pagination

`paginate()` dengan default 15 per halaman. Response otomatis memuat `links` dan `meta`.

## Queue: welcome email

`register` memanggil `SendWelcomeEmail::dispatch($user)`. Job mengirim `WelcomeMail` (markdown template), dengan 3 percobaan dan backoff 10 detik lalu 60 detik. Registrasi tidak menunggu pengiriman email, jadi response cepat dan kegagalan SMTP tidak membatalkan pendaftaran.

Command `php artisan app:send-welcome-email {user}` menerima ID atau email. Tanpa opsi, job masuk queue. Dengan `--sync`, email dikirim langsung, berguna untuk cek konfigurasi mail tanpa worker.

## Weather

Alur `GET /api/weather`:

1. `WeatherService::current()` cek cache (`weather.current`, TTL 15 menit).
2. Jika kosong, ambil dari OpenWeatherMap (timeout 5 detik, retry 2 kali), ubah ke format ringkas, simpan di cache.
3. Jika API gagal, coba data terakhir yang diketahui (`weather.last_known`, disimpan 24 jam) dan tandai `stale: true`.
4. Jika tidak ada juga, lempar `WeatherUnavailableException` dan controller membalas 503.

`RefreshWeatherData` dijadwalkan per jam (`Schedule::job(...)->hourly()->withoutOverlapping()`), sehingga cache umumnya sudah hangat dan request user jarang menunggu API eksternal. Job ini punya retry dengan backoff.

Detail dan API key ada di README. Kota, satuan, dan TTL bisa diubah lewat env tanpa mengubah kode.

## Keamanan

- Token Sanctum, password ter-hash, resource tidak mengekspos field sensitif.
- Otorisasi kepemilikan lewat Policy.
- Rate limit pada `register`, `login`, dan `weather`.
- Mass assignment dibatasi lewat `$fillable`; `user_id` post selalu diambil dari user yang login, bukan dari input.
- API key cuaca hanya dari env, dan tidak pernah muncul di response atau log.

## Performa

- Eager loading `user` pada daftar post.
- Index `created_at` pada `posts`.
- Batas `per_page` maksimal 100.
- Cache cuaca plus refresh terjadwal.
- Pengiriman email lewat queue.

## Dokumentasi API

Tersedia tiga bentuk: `docs/API.md` (Markdown), `docs/openapi.yaml` (OpenAPI 3), dan `docs/postman_collection.json` (Postman). Collection Postman menyimpan token dan id secara otomatis lewat script tes, jadi alur register, buat post, ubah, hapus bisa dijalankan berurutan tanpa menyalin nilai manual.

## Testing

`php artisan test` memakai SQLite in-memory, cache `array`, dan mail `array`. Panggilan ke OpenWeatherMap di-mock dengan `Http::fake`.

- `PostTest`: pagination, validasi `per_page`, show, 404, auth, validasi, create, update pemilik, larangan non-pemilik, delete.
- `AuthTest`: register (termasuk job masuk queue), validasi, login sukses/gagal, logout mencabut token, show user, list user.
- `WeatherTest`: sukses, request kedua dari cache (hanya 1 panggilan HTTP), 503 saat API error, 503 tanpa API key, fallback stale, job refresh.
- `WelcomeEmailTest`: job mengirim mail, command dengan ID/email, `--sync`, user tidak ditemukan.

## Catatan

Kode ini belum dijalankan di lingkungan tempat ia dibuat, karena instalasi Composer tidak tersedia di sana. File sudah lolos pengecekan sintaks PHP. Jalankan `php artisan test` setelah setup untuk memastikan semuanya hijau; jika ada penyesuaian kecil karena perbedaan versi Laravel, biasanya hanya di `bootstrap/app.php` atau `phpunit.xml`.
