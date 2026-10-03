<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home')
                ->where('appName', 'Dashboard Monitor')
                ->has('laravelVersion')
            );
    }

    public function test_inertia_navigation_returns_the_svelte_page_as_json(): void
    {
        $initialPage = $this->get('/')->viewData('page');

        $response = $this->get('/', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $initialPage['version'],
        ]);

        $response->assertOk()
            ->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'Home')
            ->assertJsonPath('props.appName', 'Dashboard Monitor');
    }
}
