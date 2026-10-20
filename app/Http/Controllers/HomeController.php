<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Application;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Home', [
            'laravelVersion' => Application::VERSION,
            'cctvEndpoints' => collect(array_keys(config('zabbix.sites')))->mapWithKeys(
                fn (string $site): array => [$site => route('cctv.index', ['site' => $site], absolute: false)],
            )->all(),
            'nasEndpoints' => collect(array_keys(config('zabbix.sites')))->mapWithKeys(
                fn (string $site): array => [$site => route('nas.index', ['site' => $site], absolute: false)],
            )->all(),
            'computerEndpoints' => collect(array_keys(config('zabbix.sites')))->mapWithKeys(
                fn (string $site): array => [$site => route('computers.index', ['site' => $site], absolute: false)],
            )->all(),
            'accessPointEndpoints' => collect(array_keys(config('zabbix.sites')))->mapWithKeys(
                fn (string $site): array => [$site => route('access-points.index', ['site' => $site], absolute: false)],
            )->all(),
        ]);
    }
}
