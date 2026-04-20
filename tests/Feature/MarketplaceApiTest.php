<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Registered;
use Tests\TestCase;

class MarketplaceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed categories for tests
        Category::create(['name' => 'Elektronik']);
        Category::create(['name' => 'Furniture']);
    }

    /** @test */
    public function test_a_user_can_register()
    {
        Event::fake();

        $response = $this->postJson('/api/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'asal_kampus' => 'Universitas Indonesia',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['access_token', 'user']);

        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
        Event::assertDispatched(Registered::class);
    }

    /** @test */
    public function test_a_user_can_login()
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['access_token']);
    }

    /** @test */
    public function test_anyone_can_list_products()
    {
        Product::factory()->count(3)->create([
            'category_id' => Category::first()->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    /** @test */
    public function test_authenticated_user_can_create_product()
    {
        $user = User::factory()->create();
        $category = Category::first();

        $response = $this->actingAs($user)
            ->postJson('/api/products', [
                'category_id' => $category->id,
                'nama_barang' => 'Laptop Bekas',
                'deskripsi' => 'Masih bagus gan',
                'harga' => 5000000,
                'kondisi' => 'sangat baik',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('products', ['nama_barang' => 'Laptop Bekas']);
    }

    /** @test */
    public function test_user_cannot_update_others_product()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $product = Product::factory()->create([
            'user_id' => $owner->id,
            'category_id' => Category::first()->id,
        ]);

        $response = $this->actingAs($otherUser)
            ->putJson("/api/products/{$product->id}", [
                'category_id' => $product->category_id,
                'nama_barang' => 'Hacker attempt',
                'deskripsi' => 'Should fail',
                'harga' => 1,
                'kondisi' => 'baru',
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function test_owner_can_delete_their_product()
    {
        $owner = User::factory()->create();
        $product = Product::factory()->create([
            'user_id' => $owner->id,
            'category_id' => Category::first()->id,
        ]);

        $response = $this->actingAs($owner)
            ->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }
}
