# Tutorial: Mengambil Data Zabbix ke Laravel Inertia

Panduan ini menjelaskan cara Laravel mengambil data dari Zabbix lewat **Zabbix JSON-RPC API**, mulai dari membuat token sampai data siap dipakai oleh dashboard (CCTV, ISP, Computers, Access Point, NAS, Temperature, UPS, Recent Alerts, Network Latency).

---

## 1. Gambaran Alur

```
Zabbix Server
   ▲  JSON-RPC (HTTPS, Bearer token)
   │
Laravel  ──  ZabbixClient  ──  Service per kategori (Cctv, Ping, Alert, Latency, ...)
   │
   ├── Scheduler: php artisan zabbix:sync  (tiap 30 detik)
   │        └── hasil disimpan di Cache (Redis)
   │
   └── Controller (Inertia) ── baca Cache ──► Dashboard.vue / .tsx / .svelte
```

Prinsip utamanya: **halaman dashboard tidak pernah memanggil Zabbix secara langsung**. Scheduler yang memanggil Zabbix, hasilnya disimpan di cache, dan controller hanya membaca cache. Dengan begitu dashboard tetap cepat dan Zabbix tidak terbebani oleh jumlah user yang membuka dashboard.

---

## 2. Prasyarat

| Kebutuhan | Keterangan |
|---|---|
| Zabbix | 6.4+ disarankan (mendukung header `Authorization: Bearer`). Zabbix 7.x aman. |
| Akses jaringan | Server Laravel harus bisa mengakses URL Zabbix (port 80/443). |
| Laravel | 11 atau 12, dengan Redis untuk cache. |
| Data di Zabbix | Host sudah terdaftar dan sudah punya item ping/SNMP. |

> **Catatan versi:** pada Zabbix di bawah 6.4, token dikirim lewat field `"auth"` di body JSON, bukan lewat header. Mulai Zabbix 7.2, field `auth` di body sudah dihapus, jadi gunakan header Bearer.

---

## 3. Siapkan Akses di Zabbix

### 3.1 Buat user khusus API

1. **Users → User groups → Create user group**
   - Nama: `API Readonly`
   - Tab *Host permissions*: beri **Read** ke host group yang akan ditampilkan (CCTV, AP, dst.)
2. **Users → Users → Create user**
   - Alias: `laravel-api`
   - Groups: `API Readonly`
   - Role: **User role** (bukan Admin)
3. Pastikan role tersebut punya **API access = Enabled**
   (*Users → User roles → [role] → Access to API*)

### 3.2 Buat API token

1. **Users → API tokens → Create API token**
2. User: `laravel-api`, isi nama dan (opsional) tanggal kedaluwarsa.
3. **Salin token saat muncul.** Token hanya ditampilkan sekali.

### 3.3 Catat Group ID

Group ID dibutuhkan untuk memfilter data per kategori dan per lokasi (lihat langkah 4).

---

## 4. Uji API dengan curl

Jalankan dari **server Laravel**, bukan dari laptop, supaya sekaligus menguji konektivitas jaringan.

```bash
# 1) Cek versi (tanpa token)
curl -s -X POST https://zabbix.domain.local/api_jsonrpc.php \
  -H "Content-Type: application/json-rpc" \
  -d '{"jsonrpc":"2.0","method":"apiinfo.version","params":{},"id":1}'

# 2) Daftar host group (dengan token)
curl -s -X POST https://zabbix.domain.local/api_jsonrpc.php \
  -H "Content-Type: application/json-rpc" \
  -H "Authorization: Bearer TOKEN_KAMU" \
  -d '{"jsonrpc":"2.0","method":"hostgroup.get","params":{"output":["groupid","name"]},"id":1}'
```

Hasil nomor 2 berisi `groupid` setiap host group. Catat ID untuk CCTV, ISP, Computers, AP, NAS, UPS, dan seterusnya.

Jika kedua perintah berhasil, jalur Laravel → Zabbix sudah benar.

---

## 5. Konfigurasi Laravel

### 5.1 `.env`

```env
ZABBIX_URL=https://zabbix.domain.local/api_jsonrpc.php
ZABBIX_TOKEN=isi_token_dari_zabbix
ZABBIX_VERIFY_SSL=true
ZABBIX_TIMEOUT=10

CACHE_STORE=redis
QUEUE_CONNECTION=redis

# Group ID per kategori untuk site Krian
ZBX_KRIAN_CCTV=
ZBX_KRIAN_ISP=
ZBX_KRIAN_PC=
ZBX_KRIAN_AP=
ZBX_KRIAN_NAS=
ZBX_KRIAN_UPS=
ZBX_KRIAN_TEMP=
```

### 5.2 `config/zabbix.php`

```php
<?php

return [
    'url'        => env('ZABBIX_URL'),
    'token'      => env('ZABBIX_TOKEN'),
    'verify_ssl' => env('ZABBIX_VERIFY_SSL', true),
    'timeout'    => env('ZABBIX_TIMEOUT', 10),

    // Ambang batas klasifikasi
    'thresholds' => [
        'latency_warning_ms' => 150,   // AP/ISP dianggap "Low" jika di atas ini
        'loss_warning_pct'   => 1,     // packet loss di atas ini dianggap bermasalah
    ],

    // Key item di Zabbix. Sesuaikan dengan template yang kamu pakai.
    'keys' => [
        'ping'    => 'icmpping',
        'latency' => 'icmppingsec',
        'loss'    => 'icmppingloss',

        // Contoh, WAJIB disesuaikan dengan item SNMP milik kamu (lihat bagian 9).
        'ups' => [
            'battery'  => 'GANTI_KEY_BATTERY',
            'load'     => 'GANTI_KEY_LOAD',
            'runtime'  => 'GANTI_KEY_RUNTIME',
        ],
        'nas' => [
            'storage_used' => 'GANTI_KEY_STORAGE',
            'health'       => 'GANTI_KEY_HEALTH',
        ],
    ],

    // Satu entri per lokasi. Dropdown "Krian Warehouse" memakai ini.
    'sites' => [
        'krian' => [
            'name'   => 'Krian Warehouse',
            'groups' => [
                'cctv'      => env('ZBX_KRIAN_CCTV'),
                'isp'       => env('ZBX_KRIAN_ISP'),
                'computers' => env('ZBX_KRIAN_PC'),
                'ap'        => env('ZBX_KRIAN_AP'),
                'nas'       => env('ZBX_KRIAN_NAS'),
                'ups'       => env('ZBX_KRIAN_UPS'),
                'temp'      => env('ZBX_KRIAN_TEMP'),
            ],
        ],
    ],
];
```

---

## 6. ZabbixClient (inti koneksi)

Buat file `app/Services/Zabbix/ZabbixClient.php`:

```php
<?php

namespace App\Services\Zabbix;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ZabbixException extends RuntimeException {}

class ZabbixClient
{
    protected function http(): PendingRequest
    {
        return Http::withToken(config('zabbix.token'))
            ->timeout((int) config('zabbix.timeout', 10))
            ->retry(2, 200)
            ->withOptions(['verify' => (bool) config('zabbix.verify_ssl', true)]);
    }

    /**
     * Panggil satu method Zabbix API dan kembalikan isi "result".
     */
    public function call(string $method, array $params = []): mixed
    {
        $payload = [
            'jsonrpc' => '2.0',
            'method'  => $method,
            'params'  => $params,
            'id'      => 1,
        ];

        $response = $this->http()
            ->withBody(json_encode($payload), 'application/json-rpc')
            ->post(config('zabbix.url'));

        $response->throw();

        $json = $response->json();

        if (isset($json['error'])) {
            throw new ZabbixException(sprintf(
                '[%s] %s %s',
                $method,
                $json['error']['message'] ?? 'Unknown error',
                $json['error']['data'] ?? ''
            ));
        }

        return $json['result'] ?? [];
    }
}
```

### Uji di tinker

```bash
php artisan tinker
```
```php
$z = app(\App\Services\Zabbix\ZabbixClient::class);
$z->call('hostgroup.get', ['output' => ['groupid', 'name']]);
```

Jika daftar host group muncul, koneksi Laravel → Zabbix sudah berfungsi.

---

## 7. Pola Query per Kartu Dashboard

Tips efisiensi: **satu panggilan `item.get` per kategori**, difilter dengan `groupids`, jauh lebih ringan daripada memanggil per host.

### 7.1 Kartu CCTV / Computers / Access Point (online vs offline)

Semua memakai pola yang sama: ambil item `icmpping` seluruh host dalam satu group.

```php
<?php

namespace App\Services\Zabbix;

class PingGroupService
{
    public function __construct(protected ZabbixClient $zabbix) {}

    /**
     * Ringkasan online/offline untuk satu host group.
     */
    public function summary(string $groupId): array
    {
        $items = $this->zabbix->call('item.get', [
            'groupids'    => [$groupId],
            'monitored'   => true,                       // hanya host & item yang aktif
            'filter'      => ['key_' => config('zabbix.keys.ping')],
            'output'      => ['itemid', 'hostid', 'lastvalue', 'lastclock'],
            'selectHosts' => ['hostid', 'name'],
        ]);

        $rows = collect($items)->map(fn ($i) => [
            'host_id'    => $i['hostid'],
            'name'       => $i['hosts'][0]['name'] ?? '-',
            'online'     => $i['lastvalue'] === '1',
            'last_check' => (int) $i['lastclock'],
        ]);

        return [
            'total'   => $rows->count(),
            'online'  => $rows->where('online', true)->count(),
            'offline' => $rows->where('online', false)->count(),
            'items'   => $rows->values()->all(),
        ];
    }
}
```

Penjelasan:
- `lastvalue` selalu bertipe **string**. Untuk `icmpping`, `"1"` = up dan `"0"` = down.
- `monitored => true` membuang host/item yang dinonaktifkan, supaya total (misalnya 48/50) akurat.
- Jika key di template berupa `icmpping[,3]` (dengan parameter), `filter` exact tidak akan cocok. Gunakan `search` + `startSearch`, dan pastikan tidak ikut menangkap `icmppingsec`/`icmppingloss`:
  ```php
  'search' => ['key_' => 'icmpping['], 'startSearch' => true,
  ```

### 7.2 Daftar kamera lengkap dengan IP (halaman CCTV)

`item.get` tidak mengembalikan IP, jadi tambahkan satu `host.get` lalu gabungkan berdasarkan `hostid`.

```php
public function cameras(string $groupId): array
{
    $summary = $this->summary($groupId);

    $hosts = collect($this->zabbix->call('host.get', [
        'groupids'         => [$groupId],
        'output'           => ['hostid', 'name'],
        'selectInterfaces' => ['ip'],
    ]))->keyBy('hostid');

    return collect($summary['items'])->map(fn ($row) => $row + [
        'ip' => $hosts[$row['host_id']]['interfaces'][0]['ip'] ?? '-',
    ])->values()->all();
}
```

> **Catatan "Last Seen":** `lastclock` adalah waktu pengecekan terakhir, bukan waktu terakhir kamera online. Untuk kamera offline, tampilkan "offline sejak" dari `lastchange` pada trigger yang sedang aktif (lihat 7.3).

### 7.3 Recent Alerts

Gunakan `trigger.get` dengan filter trigger yang sedang bermasalah.

```php
<?php

namespace App\Services\Zabbix;

class AlertService
{
    public function __construct(protected ZabbixClient $zabbix) {}

    /**
     * @param array<string,string> $groups  contoh: ['CCTV' => '12', 'Access Point' => '15']
     */
    public function recent(array $groups, int $limit = 5): array
    {
        $alerts = collect();

        foreach ($groups as $category => $groupId) {
            if (! $groupId) continue;

            $triggers = $this->zabbix->call('trigger.get', [
                'groupids'          => [$groupId],
                'only_true'         => true,    // hanya yang sedang PROBLEM
                'monitored'         => true,
                'skipDependent'     => true,    // hindari alert turunan
                'min_severity'      => 2,       // Warning ke atas
                'expandDescription' => true,
                'output'            => ['triggerid', 'description', 'priority', 'lastchange'],
                'selectHosts'       => ['hostid', 'name'],
                'sortfield'         => 'lastchange',
                'sortorder'         => 'DESC',
                'limit'             => $limit,
            ]);

            foreach ($triggers as $t) {
                $alerts->push([
                    'time'     => (int) $t['lastchange'],
                    'device'   => $t['hosts'][0]['name'] ?? '-',
                    'category' => $category,
                    'message'  => $t['description'],
                    'status'   => (int) $t['priority'] >= 4 ? 'Critical' : 'Warning',
                ]);
            }
        }

        return $alerts->sortByDesc('time')->take($limit)->values()->all();
    }
}
```

Karena kategori diambil dari **group yang sedang di-query**, kolom *Category* otomatis benar tanpa bergantung pada tag.

Pemetaan severity Zabbix:

| `priority` | Nama di Zabbix | Tampil di dashboard |
|---|---|---|
| 0 | Not classified | (diabaikan) |
| 1 | Information | (diabaikan) |
| 2 | Warning | Warning |
| 3 | Average | Warning |
| 4 | High | Critical |
| 5 | Disaster | Critical |

> Pesan alert mengikuti **nama trigger** di Zabbix. Agar tampil seperti "Camera offline" atau "High latency 180 ms", ubah nama trigger di template/host, misalnya `Camera offline: {HOST.NAME}`.

### 7.4 Network Latency (grafik)

Ambil item `icmppingsec` lalu riwayatnya lewat `history.get`.

```php
<?php

namespace App\Services\Zabbix;

class LatencyService
{
    public function __construct(protected ZabbixClient $zabbix) {}

    /**
     * Rata-rata latency (ms) per menit untuk semua host di satu group.
     */
    public function series(string $groupId, int $minutes = 60): array
    {
        $itemIds = collect($this->zabbix->call('item.get', [
            'groupids'  => [$groupId],
            'monitored' => true,
            'filter'    => ['key_' => config('zabbix.keys.latency')],
            'output'    => ['itemid'],
        ]))->pluck('itemid')->all();

        if (! $itemIds) return [];

        $history = $this->zabbix->call('history.get', [
            'history'   => 0,                                   // 0 = float (lihat tabel value_type)
            'itemids'   => $itemIds,
            'time_from' => now()->subMinutes($minutes)->timestamp,
            'output'    => 'extend',
            'sortfield' => 'clock',
            'sortorder' => 'ASC',
            'limit'     => 20000,
        ]);

        return collect($history)
            ->groupBy(fn ($h) => intdiv((int) $h['clock'], 60) * 60)   // bucket per menit
            ->map(fn ($rows, $ts) => [
                'time'   => (int) $ts,
                'avg_ms' => round($rows->avg(fn ($r) => (float) $r['value']) * 1000, 1), // detik → ms
            ])
            ->values()
            ->all();
    }
}
```

Hal yang perlu diperhatikan:
- Parameter `history` **harus sama dengan `value_type` item**, kalau tidak hasilnya kosong (lihat tabel di bagian 12).
- `icmppingsec` dalam **detik**, kalikan 1000 untuk milidetik.
- Untuk rentang panjang (24 jam ke atas) gunakan `trend.get` (data per jam: `value_min`, `value_avg`, `value_max`), bukan `history.get`, supaya cepat.
- Satu garis per ISP (ISP 1, ISP 2): panggil `series()` terpisah per host/item, atau tambahkan `selectHosts` pada `item.get` lalu kelompokkan per host.

### 7.5 UPS, NAS, Temperature (item SNMP)

Key item SNMP berbeda-beda tergantung template dan merek, jadi **cari key aslinya dulu**:

1. Zabbix UI → **Monitoring → Latest data** → pilih host UPS → klik nama item → salin **Key**.
2. Atau lewat API:
   ```php
   $z->call('item.get', [
       'hostids' => ['ID_HOST_UPS'],
       'output'  => ['itemid', 'name', 'key_', 'lastvalue', 'units'],
   ]);
   ```
3. Masukkan key tersebut ke `config/zabbix.php` (bagian `keys`).

Setelah itu pola ambilnya sama:

```php
public function ups(string $groupId): array
{
    $keys = config('zabbix.keys.ups');

    $items = collect($this->zabbix->call('item.get', [
        'groupids'  => [$groupId],
        'monitored' => true,
        'filter'    => ['key_' => array_values($keys)],
        'output'    => ['key_', 'lastvalue', 'units'],
    ]))->keyBy('key_');

    $val = fn ($k) => isset($items[$keys[$k]]) ? (float) $items[$keys[$k]]['lastvalue'] : null;

    return [
        'battery' => $val('battery'),   // %
        'load'    => $val('load'),      // %
        'runtime' => $val('runtime'),   // sesuaikan satuan (detik/menit) dengan item kamu
    ];
}
```

Cara yang sama dipakai untuk NAS (storage usage, health, backup) dan Temperature (ruangan, NAS, cache).

---

## 8. Sinkronisasi Terjadwal + Cache

### 8.1 Artisan command

```bash
php artisan make:command SyncZabbixSummary
```

`app/Console/Commands/SyncZabbixSummary.php`:

```php
<?php

namespace App\Console\Commands;

use App\Services\Zabbix\{AlertService, LatencyService, PingGroupService};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncZabbixSummary extends Command
{
    protected $signature   = 'zabbix:sync {site? : key site, kosongkan untuk semua}';
    protected $description = 'Ambil data ringkasan dari Zabbix dan simpan ke cache';

    public function handle(
        PingGroupService $ping,
        AlertService $alerts,
        LatencyService $latency,
    ): int {
        $sites = config('zabbix.sites');

        if ($only = $this->argument('site')) {
            $sites = array_intersect_key($sites, [$only => true]);
        }

        foreach ($sites as $key => $site) {
            $g = $site['groups'];

            try {
                $data = [
                    'cctv'      => $ping->summary($g['cctv']),
                    'computers' => $ping->summary($g['computers']),
                    'ap'        => $ping->summary($g['ap']),
                    'isp'       => $ping->summary($g['isp']),
                    'alerts'    => $alerts->recent([
                        'CCTV'         => $g['cctv'],
                        'Access Point' => $g['ap'],
                        'NAS'          => $g['nas'],
                        'UPS'          => $g['ups'],
                        'Temperature'  => $g['temp'],
                    ]),
                    'latency'   => $latency->series($g['isp'], 60),
                    'updated_at'=> now()->toIso8601String(),
                ];

                // TTL sengaja lebih panjang dari interval sync (30 detik).
                // Jika Zabbix sedang down, data terakhir tetap tampil.
                Cache::put("dash:{$key}:summary", $data, now()->addMinutes(10));

                $this->info("[$key] OK");
            } catch (\Throwable $e) {
                report($e);
                $this->error("[$key] GAGAL: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
```

> Jika sebuah panggilan gagal, cache lama **tidak ditimpa**, jadi dashboard tetap menampilkan data terakhir. Karena payload menyimpan `updated_at`, UI bisa menandai "data lama" bila terlalu tertinggal.

### 8.2 Scheduler

`routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('zabbix:sync')
    ->everyThirtySeconds()
    ->withoutOverlapping()
    ->runInBackground();
```

### 8.3 Jalankan scheduler di server

Cron (cukup satu baris, Laravel menangani jadwal per 30 detik di dalam menit yang sama):

```
* * * * * cd /var/www/it-monitoring && php artisan schedule:run >> /dev/null 2>&1
```

Saat deploy, jalankan `php artisan schedule:interrupt` agar proses scheduler memakai kode terbaru. Untuk development cukup `php artisan schedule:work`.

Uji manual kapan saja:

```bash
php artisan zabbix:sync krian
```

---

## 9. Controller & Inertia

```bash
php artisan make:controller DashboardController
```

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $sites = config('zabbix.sites');
        $site  = $request->query('site', array_key_first($sites));

        abort_unless(isset($sites[$site]), 404);

        return Inertia::render('Dashboard', [
            'site'  => $site,
            'sites' => collect($sites)
                ->map(fn ($s, $k) => ['key' => $k, 'name' => $s['name']])
                ->values(),

            // Closure: hanya dievaluasi saat prop ini diminta (partial reload)
            'summary' => fn () => Cache::get("dash:{$site}:summary"),
        ]);
    }
}
```

`routes/web.php`:

```php
use App\Http\Controllers\DashboardController;

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
});
```

### Auto-refresh di frontend (Vue)

Inertia v2:

```vue
<script setup>
import { usePoll } from '@inertiajs/vue3'

defineProps({ site: String, sites: Array, summary: Object })

// Reload hanya prop "summary" tiap 30 detik
usePoll(30000, { only: ['summary'] })
</script>
```

Inertia v1 atau jika ingin kontrol manual:

```vue
<script setup>
import { router } from '@inertiajs/vue3'
import { onMounted, onUnmounted } from 'vue'

let timer
onMounted(() => {
  timer = setInterval(
    () => router.reload({ only: ['summary'], preserveScroll: true }),
    30000
  )
})
onUnmounted(() => clearInterval(timer))
</script>
```

Untuk React dan Svelte, API-nya sama (`usePoll` / `router.reload`), hanya impor dari `@inertiajs/react` atau `@inertiajs/svelte`.

### Bentuk data yang diterima frontend

```jsonc
{
  "cctv":      { "total": 50, "online": 48, "offline": 2, "items": [ ... ] },
  "computers": { "total": 35, "online": 32, "offline": 3, "items": [ ... ] },
  "ap":        { "total": 20, "online": 18, "offline": 2, "items": [ ... ] },
  "isp":       { "total": 2,  "online": 2,  "offline": 0, "items": [ ... ] },
  "alerts":    [ { "time": 1759216860, "device": "CCTV-02", "category": "CCTV",
                   "message": "Camera offline", "status": "Critical" } ],
  "latency":   [ { "time": 1759215000, "avg_ms": 23.4 } ],
  "updated_at": "2026-09-30T14:25:32+07:00"
}
```

---

## 10. Pengujian Tanpa Zabbix Asli

Gunakan `Http::fake` agar test tidak bergantung pada Zabbix.

```php
use Illuminate\Support\Facades\Http;
use App\Services\Zabbix\PingGroupService;

it('menghitung cctv online dan offline', function () {
    config(['zabbix.url' => 'https://zabbix.test/api_jsonrpc.php']);

    Http::fake([
        'zabbix.test/*' => Http::response([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'result'  => [
                ['itemid' => '1', 'hostid' => '10', 'lastvalue' => '1', 'lastclock' => '1759216000',
                 'hosts' => [['hostid' => '10', 'name' => 'CCTV-01']]],
                ['itemid' => '2', 'hostid' => '11', 'lastvalue' => '0', 'lastclock' => '1759216000',
                 'hosts' => [['hostid' => '11', 'name' => 'CCTV-02']]],
            ],
        ]),
    ]);

    $summary = app(PingGroupService::class)->summary('12');

    expect($summary['total'])->toBe(2)
        ->and($summary['online'])->toBe(1)
        ->and($summary['offline'])->toBe(1);
});
```

---

## 11. Troubleshooting

| Gejala / pesan error | Penyebab umum | Solusi |
|---|---|---|
| `Not authorised` / `Session terminated, re-login, please.` | Token salah, kedaluwarsa, atau user dinonaktifkan | Buat token baru; cek status user `laravel-api` |
| `No permissions to referred object or it does not exist!` | User tidak punya hak baca ke host group | Tambahkan permission **Read** di user group |
| `API access is disabled` / akses API ditolak | Role user belum mengizinkan API | Aktifkan *Access to API* di User role |
| Hasil `[]` (kosong) padahal host ada | `groupid` salah, host/item nonaktif (`monitored`), atau tidak ada permission | Cek `hostgroup.get`; coba tanpa `monitored`; cek permission |
| `lastvalue` kosong `""` | Item belum pernah terkumpul / unsupported | Lihat *Latest data* di Zabbix, periksa status item |
| `history.get` kosong | Parameter `history` tidak sesuai `value_type` | Samakan dengan tabel di bawah |
| `cURL error 60: SSL certificate problem` | Sertifikat internal tidak dikenal | Pasang CA internal di server Laravel. Sementara boleh `ZABBIX_VERIFY_SSL=false` (hanya jaringan internal) |
| `Connection timed out` | Firewall / DNS / URL salah | Tes `curl` dari server Laravel; cek path `/api_jsonrpc.php` (bisa `/zabbix/api_jsonrpc.php`) |
| `Invalid params` / `unexpected parameter` | Beda versi Zabbix | Cek `apiinfo.version` dan dokumentasi method untuk versi tersebut |
| `history.get` sangat lambat | Rentang terlalu panjang | Kecilkan `time_from`, pakai `limit`, atau gunakan `trend.get` |
| Dashboard menampilkan data lama | Scheduler tidak berjalan | Cek cron/Supervisor; jalankan `php artisan zabbix:sync` manual |

---

## 12. Referensi Cepat

### `value_type` item (untuk parameter `history` pada `history.get`)

| value_type | Tipe | Nilai `history` |
|---|---|---|
| 0 | Numeric (float) | 0 |
| 1 | Character | 1 |
| 2 | Log | 2 |
| 3 | Numeric (unsigned) | 3 |
| 4 | Text | 4 |

### Item ICMP bawaan

| Key | Arti | Satuan |
|---|---|---|
| `icmpping` | Up/down | 1 = up, 0 = down |
| `icmppingsec` | Waktu respons | detik (×1000 untuk ms) |
| `icmppingloss` | Packet loss | persen |

### Method Zabbix API yang dipakai

| Method | Dipakai untuk |
|---|---|
| `hostgroup.get` | Mencari `groupid` |
| `host.get` | Daftar host, IP (`selectInterfaces`) |
| `item.get` | Nilai terakhir (`lastvalue`) banyak host sekaligus |
| `history.get` | Riwayat nilai untuk grafik (rentang pendek) |
| `trend.get` | Data agregat per jam (rentang panjang) |
| `trigger.get` | Alert yang sedang aktif |
| `problem.get` | Alternatif untuk alert, termasuk status acknowledge |

---

## 13. Praktik Terbaik

1. **Jangan panggil Zabbix dari controller** pada setiap request. Selalu lewat scheduler + cache.
2. **Satu `item.get` per kategori** (difilter `groupids`), bukan satu panggilan per host.
3. Batasi `output` hanya pada field yang dibutuhkan agar respons kecil.
4. Gunakan **user API read-only** dan simpan token hanya di `.env`. Jangan pernah mengirim token ke frontend.
5. Beri **TTL cache lebih panjang** dari interval sync supaya dashboard tidak kosong ketika Zabbix sedang bermasalah.
6. Simpan snapshot harian ke database (misalnya jumlah kamera online per hari) untuk menu **Reports**, jadi laporan tidak perlu query berat ke Zabbix.
7. Mulai dari **satu kategori sampai tuntas** (CCTV: service → command → cache → kartu di UI), lalu duplikasi polanya ke kategori lain.

---

## 14. Urutan Pengerjaan yang Disarankan

1. Buat user, token, dan atur permission di Zabbix (bagian 3).
2. Uji `curl` dari server Laravel (bagian 4).
3. Isi `.env` dan `config/zabbix.php` (bagian 5).
4. Buat `ZabbixClient` dan uji di tinker (bagian 6).
5. Buat `PingGroupService` dan tampilkan kartu **CCTV** (bagian 7.1).
6. Buat command `zabbix:sync`, scheduler, dan cache (bagian 8).
7. Hubungkan controller ke halaman Inertia (bagian 9).
8. Tambahkan kategori lain: Computers, AP, ISP → Alerts → Latency → UPS/NAS/Temperature.
9. Tambahkan test dengan `Http::fake` (bagian 10).
