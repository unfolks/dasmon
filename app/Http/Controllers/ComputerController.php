<?php

namespace App\Http\Controllers;

use App\Http\Resources\ComputerResource;
use App\Services\Zabbix\ComputerService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ComputerController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(string $site, ComputerService $service): AnonymousResourceCollection
    {
        abort_unless(array_key_exists($site, config('zabbix.sites')), 404);
        $snapshot = $service->snapshot($site);

        return ComputerResource::collection($snapshot['data'])->additional(['meta' => $snapshot['meta']]);
    }
}
