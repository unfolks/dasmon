<?php

namespace App\Services\Zabbix;

use App\Exceptions\ZabbixException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class ZabbixClient
{
    /**
     * @param  array<string, mixed>  $parameters
     * @return list<array<string, mixed>>
     */
    public function call(string $method, array $parameters = []): array
    {
        if (! config('zabbix.url') || ! config('zabbix.token')) {
            throw new ZabbixException('URL atau token Zabbix belum dikonfigurasi.');
        }
        try {
            $response = Http::withToken(config('zabbix.token'))
                ->acceptJson()->contentType('application/json-rpc')
                ->connectTimeout(3)->timeout(config('zabbix.timeout'))
                ->post(config('zabbix.url'), [
                    'jsonrpc' => '2.0', 'method' => $method,
                    'params' => $parameters === [] ? (object) [] : $parameters, 'id' => 1,
                ]);
        } catch (ConnectionException) {
            throw new ZabbixException('Server Zabbix tidak dapat dihubungi.');
        }
        if (! $response->successful()) {
            throw new ZabbixException('Server Zabbix mengembalikan HTTP '.$response->status().'.');
        }
        $payload = $response->json();
        if (! is_array($payload) || ($payload['jsonrpc'] ?? null) !== '2.0') {
            throw new ZabbixException('Response Zabbix tidak valid.');
        }
        if (isset($payload['error'])) {
            throw new ZabbixException('Request Zabbix ditolak. Periksa token dan izin pengguna API.');
        }
        if (! isset($payload['result']) || ! is_array($payload['result']) || ! array_is_list($payload['result'])) {
            throw new ZabbixException('Data Zabbix tidak valid.');
        }

        return $payload['result'];
    }
}
