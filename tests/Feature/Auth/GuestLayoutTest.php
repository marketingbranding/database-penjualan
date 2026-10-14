<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_auth_pages_render_the_kavling_layout(): void
    {
        foreach ([
            route('login'),
            route('register'),
            route('password.request'),
            route('password.reset', 'test-token'),
        ] as $url) {
            $response = $this->get($url);

            $response
                ->assertOk()
                ->assertSee('data-auth-kavling-grid')
                ->assertSee('data-total="49"', false)
                ->assertSee('Oasis Next');
        }
    }

    public function test_authenticated_auth_pages_render_the_kavling_layout(): void
    {
        $user = User::factory()->unverified()->create();

        foreach ([route('verification.notice'), route('password.confirm')] as $url) {
            $response = $this->actingAs($user)->get($url);

            $response
                ->assertOk()
                ->assertSee('data-auth-kavling-grid')
                ->assertSee('data-total="49"', false);
        }
    }
}
