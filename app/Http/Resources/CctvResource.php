<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CctvResource extends JsonResource
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
            'switchGroups' => $this->resource['switchGroups'],
            'location' => $this->resource['location'],
            'ip' => $this->resource['ip'],
            'status' => $this->resource['status'],
            'isReachable' => $this->resource['isReachable'],
            'latencyMs' => $this->resource['latencyMs'],
            'packetLossPercent' => $this->resource['packetLossPercent'],
            'lastCheckedAt' => $this->resource['lastCheckedAt'],
            'detail' => $this->resource['detail'],
        ];
    }
}
