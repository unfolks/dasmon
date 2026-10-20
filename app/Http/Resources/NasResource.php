<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NasResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'name' => $this->resource['name'],
            'location' => $this->resource['location'],
            'ip' => $this->resource['ip'],
            'status' => $this->resource['status'],
            'isReachable' => $this->resource['isReachable'],
            'snmpStatus' => $this->resource['snmpStatus'],
            'health' => $this->resource['health'],
            'backupStatus' => $this->resource['backupStatus'],
            'model' => $this->resource['model'],
            'version' => $this->resource['version'],
            'cpuPercent' => $this->resource['cpuPercent'],
            'temperature' => $this->resource['temperature'],
            'memory' => $this->resource['memory'],
            'storageUsage' => $this->resource['storageUsage'],
            'totalBytes' => $this->resource['totalBytes'],
            'usedBytes' => $this->resource['usedBytes'],
            'freeBytes' => $this->resource['freeBytes'],
            'checks' => $this->resource['checks'],
            'volumes' => $this->resource['volumes'],
            'raids' => $this->resource['raids'],
            'disks' => $this->resource['disks'],
            'smart' => $this->resource['smart'],
            'warnings' => $this->resource['warnings'],
            'lastCheckedAt' => $this->resource['lastCheckedAt'],
            'detail' => $this->resource['detail'],
        ];
    }
}
