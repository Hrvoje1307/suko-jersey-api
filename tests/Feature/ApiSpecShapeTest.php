<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Yaml\Yaml;
use Tests\Concerns\FakesCheckout;
use Tests\TestCase;

/**
 * Spec (public/openapi.yaml) se održava ručno i jedini je ugovor koji frontend
 * čita, pa mora ostati iskren: polja koja obećava moraju biti točno ona koja
 * endpointi vraćaju, a `required` mora odgovarati validaciji.
 *
 * ApiDocsTest čuva da svaka ruta postoji u specu; ovaj čuva njihov oblik.
 */
class ApiSpecShapeTest extends TestCase
{
    use FakesCheckout;
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function spec(): array
    {
        return Yaml::parseFile(public_path('openapi.yaml'));
    }

    /** @return array<int, string> */
    private function props(array $spec, string $schema): array
    {
        $s = $spec['components']['schemas'][$schema];

        if (isset($s['allOf'])) {
            $keys = [];
            foreach ($s['allOf'] as $part) {
                $keys = array_merge($keys, isset($part['$ref'])
                    ? $this->props($spec, basename($part['$ref']))
                    : array_keys($part['properties'] ?? []));
            }

            return $keys;
        }

        return array_keys($s['properties'] ?? []);
    }

    private function compare(string $label, array $specKeys, array $actual): void
    {
        sort($specKeys);
        $actualKeys = array_keys($actual);
        sort($actualKeys);

        $this->assertSame($specKeys, $actualKeys, "{$label}: spec i odgovor se razilaze");
    }

    public function test_spec_matches_every_response_shape(): void
    {
        $spec = $this->spec();

        $product = Product::factory()
            ->sizes(['M'])->images(['https://cdn.example.com/a.jpg'])
            ->create();

        // ProductSummary
        $this->compare('ProductSummary', $this->props($spec, 'ProductSummary'),
            $this->getJson('/api/products')->json('data.0'));

        // ProductDetail
        $this->compare('ProductDetail', $this->props($spec, 'ProductDetail'),
            $this->getJson("/api/products/{$product->id}")->json());

        // ProductFilters
        $this->compare('ProductFilters', $this->props($spec, 'ProductFilters'),
            $this->getJson('/api/products/filters')->json());

        // PaginationMeta
        $this->compare('PaginationMeta', $this->props($spec, 'PaginationMeta'),
            $this->getJson('/api/products')->json('meta'));

        $order = Order::factory()->paid()->create();
        OrderItem::factory()->for($order)->create(['product_id' => $product->id, 'size' => 'M']);

        // OrderStatusPublic
        $this->compare('OrderStatusPublic', $this->props($spec, 'OrderStatusPublic'),
            $this->getJson('/api/orders/lookup?order_reference='.$order->order_reference
                .'&email='.$order->customer->email)->json());

        // OrderPaymentStatus
        $this->compare('OrderPaymentStatus', $this->props($spec, 'OrderPaymentStatus'),
            $this->getJson("/api/orders/{$order->order_reference}/status")->json());

        // OrderAdmin (+ ugniježđene stavke)
        $admin = $this->actingAs(AdminUser::factory()->create(), 'sanctum');
        $adminOrder = $admin->getJson('/api/admin/orders')->json('0');
        $this->compare('OrderAdmin', $this->props($spec, 'OrderAdmin'), $adminOrder);
        $this->compare('OrderAdmin.items[]',
            array_keys($spec['components']['schemas']['OrderAdmin']['properties']['items']['items']['properties']),
            $adminOrder['items'][0]);

        // ProductDetail kroz admin (isti resource, drugi put)
        $this->compare('admin ProductDetail', $this->props($spec, 'ProductDetail'),
            $admin->getJson('/api/admin/products')->json('0'));
    }

    public function test_spec_matches_the_order_confirmation(): void
    {
        Notification::fake();
        $this->fakeCheckout();

        $spec = $this->spec();
        $product = Product::factory()->sizes(['M'])->create();

        $body = $this->postJson('/api/orders', [
            'customer' => ['email' => 'kupac@example.com', 'name' => 'Ivan'],
            'items' => [['product_id' => $product->id, 'size' => 'M', 'quantity' => 1]],
            'shipping_address' => [
                'line1' => 'Ilica 1', 'city' => 'Zagreb',
                'postal_code' => '10000', 'country' => 'HR',
            ],
        ])->json();

        $this->compare('OrderConfirmation', $this->props($spec, 'OrderConfirmation'), $body);
    }

    public function test_spec_required_fields_match_validation(): void
    {
        $spec = $this->spec();

        // Proizvod: spec kaže što je obavezno, validacija to mora tražiti.
        $required = $spec['components']['schemas']['CreateProductRequest']['required'];
        $errors = $this->actingAs(AdminUser::factory()->create(), 'sanctum')
            ->postJson('/api/admin/products', [])
            ->assertStatus(422)
            ->json('errors');

        sort($required);
        $actual = array_keys($errors);
        sort($actual);

        $this->assertSame($required, $actual, 'CreateProductRequest.required se razilazi s validacijom');

        // Narudžba: obavezna polja stavke.
        $itemRequired = $spec['components']['schemas']['CreateOrderRequest']['properties']['items']['items']['required'];

        $itemErrors = $this->postJson('/api/orders', [
            'customer' => ['email' => 'a@b.com', 'name' => 'A'],
            'items' => [[]],
            'shipping_address' => [
                'line1' => 'x', 'city' => 'x', 'postal_code' => 'x', 'country' => 'HR',
            ],
        ])->assertStatus(422)->json('errors');

        sort($itemRequired);
        $actualItem = array_map(fn ($k) => str_replace('items.0.', '', $k), array_keys($itemErrors));
        sort($actualItem);

        $this->assertSame($itemRequired, $actualItem, 'CreateOrderRequest.items.required se razilazi s validacijom');
    }
}
