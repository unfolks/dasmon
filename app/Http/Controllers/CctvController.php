<?php

namespace App\Http\Controllers;

use App\Http\Resources\CctvResource;
use App\Services\Zabbix\CctvService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CctvController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(string $site, CctvService $service): AnonymousResourceCollection
    {
        abort_unless(array_key_exists($site, config('zabbix.sites')), 404);
        $snapshot = $service->snapshot($site);

        return CctvResource::collection($snapshot['data'])->additional(['meta' => $snapshot['meta']]);
    }
}
