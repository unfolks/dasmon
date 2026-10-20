<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComputerResource extends JsonResource
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
            'pingReachable' => $this->resource['pingReachable'],
            'pingLastCheckedAt' => $this->resource['pingLastCheckedAt'],
            'snmpStatus' => $this->resource['snmpStatus'],
            'agentStatus' => $this->resource['agentStatus'],
            'monitoringType' => $this->resource['monitoringType'],
            'monitoringStatus' => $this->resource['monitoringStatus'],
            'cpuPercent' => $this->resource['cpuPercent'],
            'cpuLastCheckedAt' => $this->resource['cpuLastCheckedAt'],
            'latencyMs' => $this->resource['latencyMs'],
            'packetLossPercent' => $this->resource['packetLossPercent'],
            'systemName' => $this->resource['systemName'],
            'systemDescription' => $this->resource['systemDescription'],
            'uptimeSeconds' => $this->resource['uptimeSeconds'],
            'networkUptimeSeconds' => $this->resource['networkUptimeSeconds'],
            'memory' => $this->resource['memory'],
            'disks' => $this->resource['disks'],
            'interfaces' => $this->resource['interfaces'],
            'warnings' => $this->resource['warnings'],
            'lastCheckedAt' => $this->resource['lastCheckedAt'],
            'detail' => $this->resource['detail'],
        ];
    }
}
