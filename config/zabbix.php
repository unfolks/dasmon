<?php

return [
    'url' => env('ZABBIX_URL'),
    'token' => env('ZABBIX_TOKEN'),
    'timeout' => 10,
    'cache_seconds' => 20,
    'max_item_age' => 300,
    'latency_warning_ms' => 150,
    'loss_warning_percent' => 1,
    'computer_thresholds' => [
        'cpu' => (float) env('ZABBIX_COMPUTER_CPU_WARNING', 85),
        'memory' => (float) env('ZABBIX_COMPUTER_MEMORY_WARNING', 90),
        'disk' => (float) env('ZABBIX_COMPUTER_DISK_WARNING', 85),
    ],
    'nas_thresholds' => [
        'cpu' => (float) env('ZABBIX_NAS_CPU_WARNING', 85),
        'storage' => (float) env('ZABBIX_NAS_STORAGE_WARNING', 85),
        'temperature' => (float) env('ZABBIX_NAS_TEMPERATURE_WARNING', 60),
        'disk_temperature' => (float) env('ZABBIX_NAS_DISK_TEMPERATURE_WARNING', 55),
    ],
    'sites' => [
        'krian' => ['name' => 'Krian Warehouse', 'cctv_group' => env('ZBX_KRIAN_CCTV', '28,29,30,31,32,33,34,35,36'), 'access_point_group' => env('ZBX_KRIAN_AP', '23'), 'computer_group' => env('ZBX_KRIAN_COMPUTERS', '25'), 'nas_group' => env('ZBX_KRIAN_NAS', '27')],
        'sidoarjo' => ['name' => 'Sidoarjo Warehouse', 'cctv_group' => env('ZBX_SIDOARJO_CCTV'), 'access_point_group' => env('ZBX_SIDOARJO_AP'), 'computer_group' => env('ZBX_SIDOARJO_COMPUTERS'), 'nas_group' => env('ZBX_SIDOARJO_NAS')],
        'surabaya' => ['name' => 'Surabaya Warehouse', 'cctv_group' => env('ZBX_SURABAYA_CCTV'), 'access_point_group' => env('ZBX_SURABAYA_AP'), 'computer_group' => env('ZBX_SURABAYA_COMPUTERS'), 'nas_group' => env('ZBX_SURABAYA_NAS')],
    ],
];
