<?php

namespace App\Http\Controllers;

use App\Http\Resources\NasResource;
use App\Services\Zabbix\NasService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NasController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(string $site, NasService $service): AnonymousResourceCollection
    {
        abort_unless(array_key_exists($site, config('zabbix.sites')), 404);
        $snapshot = $service->snapshot($site);

        return NasResource::collection($snapshot['data'])->additional(['meta' => $snapshot['meta']]);
    }
}
