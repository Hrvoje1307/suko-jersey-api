<?php

namespace Tests\Feature;

use App\Enums\Personalization;
use App\Models\AdminUser;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductPlayer;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProductPersonalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsAdmin(): static
    {
        return $this->actingAs(AdminUser::factory()->create(), 'sanctum');
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    protected function orderPayload(array $items): array
    {
        return [
            'customer' => ['email' => 'kupac@example.com', 'name' => 'Ivan Horvat'],
            'items' => $items,
            'shipping_address' => [
                'line1' => 'Ilica 1',
                'city' => 'Zagreb',
                'postal_code' => '10000',
                'country' => 'HR',
            ],
        ];
    }

    protected function variantFor(Personalization $personalization): ProductVariant
    {
        $product = Product::factory()->create(['personalization' => $personalization]);

        return ProductVariant::factory()->for($product)->create(['size' => 'M', 'stock_quantity' => 5]);
    }

    public function test_detail_exposes_personalization_and_available_players(): void
    {
        $product = Product::factory()->create(['personalization' => Personalization::PresetOnly]);
        ProductPlayer::factory()->for($product)->create([
            'player_name' => 'Modrić', 'player_number' => '10', 'sort_order' => 1,
        ]);
        ProductPlayer::factory()->for($product)->create([
            'player_name' => 'Verstappen', 'player_number' => null, 'sort_order' => 0,
        ]);

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('personalization', 'preset_only')
            // sort_order određuje redoslijed.
            ->assertJsonPath('available_players.0.player_name', 'Verstappen')
            ->assertJsonPath('available_players.0.player_number', null)
            ->assertJsonPath('available_players.1.player_name', 'Modrić');
    }

    public function test_index_filters_by_personalization(): void
    {
        $custom = Product::factory()->create(['personalization' => Personalization::CustomText]);
        Product::factory()->create(['personalization' => Personalization::None]);

        $this->getJson('/api/products?personalization=custom_text')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $custom->id);

        $this->getJson('/api/products?personalization=nesto')->assertStatus(422);
    }

    public function test_admin_replaces_player_list(): void
    {
        $product = Product::factory()->create(['personalization' => Personalization::Both]);
        ProductPlayer::factory()->for($product)->create(['player_name' => 'Stari']);

        $this->actingAsAdmin()
            ->putJson("/api/admin/products/{$product->id}/players", [
                'players' => [
                    ['player_name' => 'Modrić', 'player_number' => '10'],
                    ['player_name' => 'Verstappen'],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'available_players')
            ->assertJsonPath('available_players.0.player_name', 'Modrić')
            ->assertJsonPath('available_players.1.player_number', null);

        $this->assertDatabaseMissing('product_players', ['player_name' => 'Stari']);
        $this->assertSame([0, 1], $product->players()->pluck('sort_order')->all());
    }

    public function test_players_endpoint_requires_authentication(): void
    {
        $product = Product::factory()->create();

        $this->putJson("/api/admin/products/{$product->id}/players", ['players' => []])
            ->assertStatus(401);
    }

    public function test_none_rejects_any_personalization(): void
    {
        $variant = $this->variantFor(Personalization::None);
        $player = ProductPlayer::factory()->for($variant->product)->create();

        $this->postJson('/api/orders', $this->orderPayload([
            ['product_variant_id' => $variant->id, 'quantity' => 1, 'product_player_id' => $player->id],
        ]))->assertStatus(422)->assertJsonValidationErrors('items.0.product_player_id');

        $this->postJson('/api/orders', $this->orderPayload([
            ['product_variant_id' => $variant->id, 'quantity' => 1, 'custom_player_name' => 'Ivan'],
        ]))->assertStatus(422)->assertJsonValidationErrors('items.0.custom_player_name');
    }

    public function test_preset_only_requires_a_player_from_the_list(): void
    {
        $variant = $this->variantFor(Personalization::PresetOnly);

        $this->postJson('/api/orders', $this->orderPayload([
            ['product_variant_id' => $variant->id, 'quantity' => 1],
        ]))->assertStatus(422)->assertJsonValidationErrors('items.0.product_player_id');

        $this->postJson('/api/orders', $this->orderPayload([
            [
                'product_variant_id' => $variant->id,
                'quantity' => 1,
                'product_player_id' => ProductPlayer::factory()->for($variant->product)->create()->id,
                'custom_player_name' => 'Ivan',
            ],
        ]))->assertStatus(422)->assertJsonValidationErrors('items.0.custom_player_name');
    }

    public function test_preset_from_another_product_is_rejected(): void
    {
        $variant = $this->variantFor(Personalization::PresetOnly);
        $foreign = ProductPlayer::factory()->create();

        $this->postJson('/api/orders', $this->orderPayload([
            ['product_variant_id' => $variant->id, 'quantity' => 1, 'product_player_id' => $foreign->id],
        ]))->assertStatus(422)->assertJsonValidationErrors('items.0.product_player_id');
    }

    public function test_custom_text_requires_a_name_and_forbids_preset(): void
    {
        $variant = $this->variantFor(Personalization::CustomText);

        $this->postJson('/api/orders', $this->orderPayload([
            ['product_variant_id' => $variant->id, 'quantity' => 1, 'custom_player_number' => '7'],
        ]))->assertStatus(422)->assertJsonValidationErrors('items.0.custom_player_name');

        $this->postJson('/api/orders', $this->orderPayload([
            [
                'product_variant_id' => $variant->id,
                'quantity' => 1,
                'custom_player_name' => 'Ivan',
                'product_player_id' => ProductPlayer::factory()->for($variant->product)->create()->id,
            ],
        ]))->assertStatus(422)->assertJsonValidationErrors('items.0.product_player_id');
    }

    public function test_both_allows_one_option_but_not_two(): void
    {
        Notification::fake();

        $variant = $this->variantFor(Personalization::Both);
        $player = ProductPlayer::factory()->for($variant->product)->create();

        $this->postJson('/api/orders', $this->orderPayload([
            [
                'product_variant_id' => $variant->id,
                'quantity' => 1,
                'product_player_id' => $player->id,
                'custom_player_name' => 'Ivan',
            ],
        ]))->assertStatus(422)->assertJsonValidationErrors('items.0.product_player_id');

        // Nijedna opcija je kod 'both' također valjano.
        $this->postJson('/api/orders', $this->orderPayload([
            ['product_variant_id' => $variant->id, 'quantity' => 1],
        ]))->assertCreated();
    }

    public function test_personalization_is_persisted_on_the_order_item(): void
    {
        Notification::fake();

        $variant = $this->variantFor(Personalization::Both);
        $player = ProductPlayer::factory()->for($variant->product)->create();

        $this->postJson('/api/orders', $this->orderPayload([
            ['product_variant_id' => $variant->id, 'quantity' => 1, 'product_player_id' => $player->id],
        ]))->assertCreated();

        $this->postJson('/api/orders', $this->orderPayload([
            [
                'product_variant_id' => $variant->id,
                'quantity' => 1,
                'custom_player_name' => 'Ivan',
                'custom_player_number' => '7',
            ],
        ]))->assertCreated();

        [$first, $second] = Order::with('items')->orderBy('id')->get()->all();

        $this->assertSame($player->id, $first->items->first()->product_player_id);
        $this->assertSame($player->player_name, $first->items->first()->productPlayer->player_name);
        $this->assertNull($first->items->first()->custom_player_name);
        $this->assertSame('Ivan', $second->items->first()->custom_player_name);
        $this->assertSame('7', $second->items->first()->custom_player_number);
        $this->assertNull($second->items->first()->product_player_id);
    }

    public function test_admin_can_set_personalization_on_a_product(): void
    {
        $this->actingAsAdmin()
            ->postJson('/api/admin/products', [
                'name' => 'Dinamo Home 25/26',
                'club_or_team' => 'Dinamo Zagreb',
                'category' => 'football',
                'kit_type' => 'home',
                'audience' => 'men',
                'price' => 89.99,
                'personalization' => 'both',
            ])
            ->assertCreated()
            ->assertJsonPath('personalization', 'both');
    }

    public function test_replace_keeps_ids_of_players_that_stay_on_the_list(): void
    {
        $product = Product::factory()->create(['personalization' => Personalization::PresetOnly]);
        $modric = ProductPlayer::factory()->for($product)->create([
            'player_name' => 'Modrić', 'player_number' => '10', 'sort_order' => 0,
        ]);
        $perisic = ProductPlayer::factory()->for($product)->create([
            'player_name' => 'Perišić', 'player_number' => '4', 'sort_order' => 1,
        ]);

        $this->actingAsAdmin()
            ->putJson("/api/admin/products/{$product->id}/players", [
                'players' => [
                    ['player_name' => 'Perišić', 'player_number' => '4'],
                    ['player_name' => 'Modrić', 'player_number' => '10'],
                    ['player_name' => 'Gvardiol', 'player_number' => '20'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('available_players.0.id', $perisic->id)
            ->assertJsonPath('available_players.1.id', $modric->id)
            ->assertJsonPath('available_players.2.player_name', 'Gvardiol');

        $this->assertSame(0, $perisic->fresh()->sort_order);
        $this->assertSame(1, $modric->fresh()->sort_order);
        $this->assertSame(3, $product->players()->count());
    }

    public function test_replace_refuses_to_remove_a_player_that_is_already_ordered(): void
    {
        $product = Product::factory()->create(['personalization' => Personalization::PresetOnly]);
        $player = ProductPlayer::factory()->for($product)->create(['player_name' => 'Modrić']);
        $keep = ProductPlayer::factory()->for($product)->create(['player_name' => 'Perišić']);

        $order = Order::factory()->create();
        OrderItem::factory()->for($order)->create(['product_player_id' => $player->id]);

        $this->actingAsAdmin()
            ->putJson("/api/admin/products/{$product->id}/players", [
                'players' => [['player_name' => 'Perišić', 'player_number' => $keep->player_number]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('players');

        // Transakcija je vraćena u cijelosti — ništa nije obrisano ni prepisano.
        $this->assertSame(2, $product->players()->count());
        $this->assertNotNull($player->fresh());
    }

    public function test_replace_removes_players_without_order_history(): void
    {
        $product = Product::factory()->create(['personalization' => Personalization::PresetOnly]);
        ProductPlayer::factory()->for($product)->create(['player_name' => 'Stari']);

        $this->actingAsAdmin()
            ->putJson("/api/admin/products/{$product->id}/players", [
                'players' => [['player_name' => 'Novi', 'player_number' => '9']],
            ])
            ->assertOk()
            ->assertJsonCount(1, 'available_players');

        $this->assertDatabaseMissing('product_players', ['player_name' => 'Stari']);
    }

    public function test_custom_number_is_rejected_next_to_a_preset_player(): void
    {
        $variant = $this->variantFor(Personalization::Both);
        $player = ProductPlayer::factory()->for($variant->product)->create();

        $this->postJson('/api/orders', $this->orderPayload([
            [
                'product_variant_id' => $variant->id,
                'quantity' => 1,
                'product_player_id' => $player->id,
                'custom_player_number' => '7',
            ],
        ]))->assertStatus(422)->assertJsonValidationErrors('items.0.custom_player_number');
    }

    public function test_admin_order_list_shows_the_name_to_print(): void
    {
        Notification::fake();

        $variant = $this->variantFor(Personalization::Both);
        $player = ProductPlayer::factory()->for($variant->product)->create([
            'player_name' => 'Modrić', 'player_number' => '10',
        ]);

        $this->postJson('/api/orders', $this->orderPayload([
            ['product_variant_id' => $variant->id, 'quantity' => 1, 'product_player_id' => $player->id],
        ]))->assertCreated();

        $this->postJson('/api/orders', $this->orderPayload([
            [
                'product_variant_id' => $variant->id,
                'quantity' => 1,
                'custom_player_name' => 'Ivan',
                'custom_player_number' => '7',
            ],
        ]))->assertCreated();

        $plain = $this->variantFor(Personalization::None);
        $this->postJson('/api/orders', $this->orderPayload([
            ['product_variant_id' => $plain->id, 'quantity' => 1],
        ]))->assertCreated();

        $response = $this->actingAsAdmin()->getJson('/api/admin/orders')->assertOk();

        // Lista je sortirana silazno po id-u.
        $response->assertJsonPath('0.items.0.player_name', null)
            ->assertJsonPath('0.items.0.player_source', null)
            ->assertJsonPath('1.items.0.player_name', 'Ivan')
            ->assertJsonPath('1.items.0.player_number', '7')
            ->assertJsonPath('1.items.0.player_source', 'custom')
            ->assertJsonPath('2.items.0.player_name', 'Modrić')
            ->assertJsonPath('2.items.0.player_number', '10')
            ->assertJsonPath('2.items.0.player_source', 'preset');
    }
}
