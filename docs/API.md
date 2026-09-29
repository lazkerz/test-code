# Dokumentasi API

Base URL: `http://localhost:8000/api`

Semua request dan response memakai JSON. Kirim header `Accept: application/json`.

Endpoint terproteksi memakai header `Authorization: Bearer <token>`. Token didapat dari `register` atau `login`.

## Ringkasan endpoint

| Method | Path | Auth | Keterangan |
| --- | --- | --- | --- |
| POST | `/register` | - | Daftar user baru |
| POST | `/login` | - | Login, dapat token |
| POST | `/logout` | Ya | Cabut token saat ini |
| GET | `/posts` | - | Daftar post (paginasi) |
| GET | `/posts/{id}` | - | Detail post |
| POST | `/posts` | Ya | Buat post |
| PATCH | `/posts/{id}` | Ya (pemilik) | Ubah post |
| DELETE | `/posts/{id}` | Ya (pemilik) | Hapus post |
| GET | `/users` | Ya | Daftar user (paginasi) |
| GET | `/users/{id}` | Ya | Detail user |
| GET | `/weather` | - | Cuaca Perth saat ini |

## Format error

| Status | Kapan | Bentuk |
| --- | --- | --- |
| 401 | Token tidak ada/tidak valid, login salah | `{"message": "..."}` |
| 403 | Bukan pemilik post | `{"message": "..."}` |
| 404 | Resource tidak ditemukan | `{"message": "..."}` |
| 422 | Validasi gagal | `{"message": "...", "errors": {"field": ["..."]}}` |
| 429 | Terlalu banyak request | `{"message": "Too Many Attempts."}` |
| 503 | API cuaca tidak tersedia | `{"message": "..."}` |

## Auth

### POST /register

Rate limit: 10/menit. Mengirim welcome email lewat queue.

```json
{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

`201 Created`

```json
{
  "data": { "id": 1, "name": "Jane Doe", "email": "jane@example.com", "created_at": "2026-09-29T03:00:00+00:00" },
  "token": "1|abcdef..."
}
```

### POST /login

Rate limit: 5/menit.

```json
{ "email": "jane@example.com", "password": "password123" }
```

`200 OK` dengan bentuk sama seperti register. Kredensial salah: `401`.

### POST /logout

Butuh token. `200 OK`

```json
{ "message": "Logged out." }
```

## Posts

### GET /posts

Query: `per_page` (1 sampai 100, default 15), `page`.

`200 OK`

```json
{
  "data": [
    {
      "id": 10,
      "title": "Hello",
      "body": "World",
      "author": { "id": 1, "name": "Jane Doe", "email": "jane@example.com", "created_at": "..." },
      "created_at": "...",
      "updated_at": "..."
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1 }
}
```

### GET /posts/{id}

`200 OK` dengan `{"data": {...}}`, atau `404`.

### POST /posts

Butuh token.

```json
{ "title": "Hello", "body": "World" }
```

Aturan: `title` wajib, maks 255. `body` wajib, maks 10000. `201 Created` dengan `{"data": {...}}`.

### PATCH /posts/{id}

Butuh token, hanya pemilik. Field opsional (`title`, `body`), yang dikirim harus valid. `200 OK`, `403` jika bukan pemilik.

### DELETE /posts/{id}

Butuh token, hanya pemilik. `204 No Content`, `403` jika bukan pemilik.

## Users

### GET /users

Butuh token. Query sama seperti `/posts`.

### GET /users/{id}

Butuh token. `200 OK`

```json
{ "data": { "id": 1, "name": "Jane Doe", "email": "jane@example.com", "created_at": "..." } }
```

## Weather

### GET /weather

Rate limit: 60/menit. Data di-cache 15 menit.

`200 OK`

```json
{
  "data": {
    "city": "Perth",
    "country": "AU",
    "temperature": 21.5,
    "feels_like": 20.9,
    "humidity": 55,
    "condition": "Clear",
    "description": "clear sky",
    "wind_speed": 4.1,
    "units": "metric",
    "observed_at": "2026-09-29T03:00:00+00:00"
  }
}
```

Jika API eksternal gagal tetapi ada data lama, field `"stale": true` ditambahkan. Jika tidak ada data: `503`.

## Postman

Import `docs/postman_collection.json` di Postman (File > Import). Variabel collection: `baseUrl` (default `http://localhost:8000/api`), `token`, `email`, `password`, `userId`, `postId`.

Urutan pakai yang disarankan:

1. Jalankan **Auth > Register**. Email dibuat acak, dan token otomatis disimpan ke variabel `token`.
2. Semua request terproteksi memakai `token` itu secara otomatis (Bearer). Untuk sesi berikutnya gunakan **Auth > Login**.
3. **Posts > Create post** menyimpan id post ke `postId`, sehingga **Get**, **Update**, dan **Delete post** langsung bisa dijalankan.
4. **Weather** butuh `WEATHER_API_KEY` terisi di `.env` server.
5. **Auth > Logout** mencabut token dan mengosongkan variabel `token`.

## Contoh curl

```bash
curl -X POST http://localhost:8000/api/register \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"name":"Jane","email":"jane@example.com","password":"password123","password_confirmation":"password123"}'

curl -X POST http://localhost:8000/api/posts \
  -H "Accept: application/json" -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"title":"Hello","body":"World"}'

curl http://localhost:8000/api/weather -H "Accept: application/json"
```
