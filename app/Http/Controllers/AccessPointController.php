<?php

namespace App\Http\Controllers;

use App\Http\Resources\AccessPointResource;
use App\Services\Zabbix\AccessPointService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccessPointController extends Controller
{
    public function __invoke(string $site, AccessPointService $service): AnonymousResourceCollection
    {
        abort_unless(array_key_exists($site, config('zabbix.sites')), 404);
        $snapshot = $service->snapshot($site);

        return AccessPointResource::collection($snapshot['data'])->additional(['meta' => $snapshot['meta']]);
    }
}
