<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_returns_a_successful_response(): void
    {
        // Create a root page so the homepage resolves
        Page::create([
            'title' => 'Home',
            'slug' => 'home',
            'is_root' => true,
            'is_active' => true,
            'order' => 0,
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
