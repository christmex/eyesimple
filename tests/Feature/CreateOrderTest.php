<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_is_idempotent_by_client_token(): void
    {
        $this->seed(\Database\Seeders\ProductSeeder::class);

        $payload = [
            'user_id' => 123,
            'client_token' => 'tok_ABC123',
            'items' => [
                ['product_id' => Product::first()->id, 'qty' => 2],
            ],
        ];

        $r1 = $this->postJson('/api/orders', $payload);
        $r1->assertStatus(201);

        // retry same request (should NOT create a new order)
        $r2 = $this->postJson('/api/orders', $payload);

        // Expected after fix:
        // - second call returns 200 and same order id
        // - only 1 order row exists for that client_token
        $r2->assertStatus(200);
        $this->assertSame($r1->json('id'), $r2->json('id'));

        $this->assertSame(1, Order::where('client_token', 'tok_ABC123')->count());
    }

    public function test_invalid_item_must_not_create_partial_order(): void
    {
        $this->seed(\Database\Seeders\ProductSeeder::class);

        $payload = [
            'user_id' => 1,
            'client_token' => 'tok_PARTIAL',
            'items' => [
                ['product_id' => 999999, 'qty' => 1], // invalid product
                ['product_id' => Product::first()->id, 'qty' => 1],
            ],
        ];

        $r = $this->postJson('/api/orders', $payload);

        // Expected after fix:
        // - validation error (422)
        // - NOTHING is written to orders
        $r->assertStatus(422);

        $this->assertSame(0, Order::count());
    }
}
