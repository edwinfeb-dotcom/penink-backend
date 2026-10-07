# PENINK Statistics API

API untuk integrasi PENINK dengan BATIK LANDAK.
BATIK dapat mengambil data statistik penggunaan PENINK melalui endpoint ini.

---

## Base URL

| Environment | Base URL |
|-------------|----------|
| Development | http://127.0.0.1:8000 |
| Production | https://s.landakkab.go.id |

---

## Authentication

Setiap request WAJIB menyertakan header:

    X-PENINK-API-KEY: <api_key>

API Key didapatkan dari admin PENINK (Bidang APTIKA Diskominfo Landak).

Contoh:

    X-PENINK-API-KEY: penink-statistics-2026-landak

PENTING: Jangan share API Key ke pihak yang tidak berwenang.

---

## Endpoint: Get Statistics

### GET /api/integration/statistics

Mengambil data statistik penggunaan PENINK.

### Query Parameters

| Parameter | Tipe | Wajib | Default | Deskripsi |
|-----------|------|-------|---------|-----------|
| period | integer | Tidak | 7 | Rentang hari. Nilai: 7 atau 30 |

### Contoh Request

cURL:

    curl -X GET "https://s.landakkab.go.id/api/integration/statistics?period=7" \
      -H "Accept: application/json" \
      -H "X-PENINK-API-KEY: penink-statistics-2026-landak"

JavaScript (fetch):

    fetch('https://s.landakkab.go.id/api/integration/statistics?period=7', {
      headers: {
        'Accept': 'application/json',
        'X-PENINK-API-KEY': 'penink-statistics-2026-landak',
      },
    })
      .then(res => res.json())
      .then(data => console.log(data));

PHP (cURL):

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://s.landakkab.go.id/api/integration/statistics?period=7',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'X-PENINK-API-KEY: penink-statistics-2026-landak',
        ],
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);

### Contoh Response (Sukses - 200 OK)

    {
        "success": true,
        "message": "Statistics API PENINK berhasil diambil.",
        "data": {
            "period": 7,
            "period_start": "2026-10-01",
            "period_end": "2026-10-07",
            "total_users": 13,
            "total_shortlinks": 27,
            "total_link_hubs": 8,
            "total_clicks": 25,
            "users_by_type": {
                "ASN": 5,
                "UMUM": 7
            },
            "statistics_by_unit": [
                {
                    "unit_kerja": "DINAS KOMUNIKASI DAN INFORMATIKA KABUPATEN LANDAK",
                    "users": 3,
                    "shortlinks": 1,
                    "clicks": 5
                }
            ],
            "statistics_by_period": [
                { "tanggal": "2026-10-01", "clicks": 3 },
                { "tanggal": "2026-10-02", "clicks": 1 }
            ]
        }
    }

### Struktur Response

| Field | Tipe | Deskripsi |
|-------|------|-----------|
| success | boolean | Status keberhasilan request |
| message | string | Pesan deskriptif |
| data.period | integer | Periode yang diminta (7/30 hari) |
| data.period_start | date | Tanggal awal periode |
| data.period_end | date | Tanggal akhir periode |
| data.total_users | integer | Total semua user terdaftar |
| data.total_shortlinks | integer | Total semua short link dibuat |
| data.total_link_hubs | integer | Total semua Link Hub dibuat |
| data.total_clicks | integer | Total klik seluruh short link |
| data.users_by_type | object | Jumlah user per jenis (ASN/UMUM) |
| data.statistics_by_unit | array | Statistik per unit kerja |
| data.statistics_by_period | array | Klik per tanggal dalam periode |

---

## Error Responses

### 401 Unauthorized - API Key Salah / Tidak Ada

    {
        "success": false,
        "message": "API Key tidak valid atau tidak ditemukan."
    }

Penyebab:
- Header X-PENINK-API-KEY tidak dikirim
- API Key salah / tidak cocok

### 422 Unprocessable Entity - Parameter Invalid

    {
        "success": false,
        "message": "Parameter period hanya boleh 7 atau 30."
    }

Penyebab:
- Nilai period bukan 7 atau 30

### 500 Internal Server Error

    {
        "success": false,
        "message": "Server error"
    }

Penyebab: Ada bug di server. Hubungi tim developer PENINK.

---

## Catatan untuk Integrasi BATIK

1. Frekuensi Fetch Data:
   Disarankan 1x sehari (misal jam 00:00 WIB) untuk sinkronisasi data harian.
   Atau on-demand sesuai kebutuhan.

2. Cache:
   Response tidak di-cache. Setiap request akan query langsung ke database.

3. Rate Limit:
   Belum ada rate limit khusus. Mohon tidak melakukan request berlebihan.

4. Timezone:
   Semua tanggal dalam WIB (UTC+7).

5. Kontak Developer:
   Bidang APTIKA, Diskominfo Kabupaten Landak.

---

## Version

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-10-07 | Initial release |

---

Copyright 2026 Diskominfo Kabupaten Landak