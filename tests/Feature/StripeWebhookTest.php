<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Notifications\OrderPlaced;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config()->set('services.stripe.webhook_secret', self::SECRET);
    }

    /**
     * Šalje event s pravim potpisom — verifikacija se ne mocka, nego se
     * potpis računa isto kao što ga Stripe računa.
     *
     * @param  array<string, mixed>  $object
     */
    protected function sendEvent(string $type, array $object, ?string $secret = null): TestResponse
    {
        $payload = json_encode([
            'id' => 'evt_test_'.uniqid(),
            'type' => $type,
            'data' => ['object' => $object],
        ], JSON_THROW_ON_ERROR);

        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret ?? self::SECRET);

        return $this->call(
            'POST',
            '/api/webhooks/stripe',
            server: [
                'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
                'CONTENT_TYPE' => 'application/json',
            ],
            content: $payload,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function sessionPayload(Order $order, array $overrides = []): array
    {
        return array_merge([
            'id' => $order->stripe_checkout_session_id,
            'object' => 'checkout.session',
            'payment_intent' => 'pi_test_123',
            'client_reference_id' => $order->order_reference,
            'metadata' => ['order_reference' => $order->order_reference],
        ], $overrides);
    }

    protected function unpaidOrder(): Order
    {
        return Order::factory()->create(['stripe_checkout_session_id' => 'cs_test_abc123']);
    }

    public function test_completed_session_marks_order_paid_and_sends_confirmation(): void
    {
        $order = $this->unpaidOrder();

        $this->sendEvent('checkout.session.completed', $this->sessionPayload($order))
            ->assertOk()
            ->assertJsonPath('received', true);

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame('pi_test_123', $order->stripe_payment_intent_id);

        Notification::assertSentOnDemand(OrderPlaced::class);
    }

    public function test_fulfillment_status_is_untouched_by_payment(): void
    {
        $order = $this->unpaidOrder();

        $this->sendEvent('checkout.session.completed', $this->sessionPayload($order))->assertOk();

        // `status` je zasebna os — plaćanje je ne pomiče.
        $this->assertSame('ordered', $order->refresh()->status->value);
    }

    public function test_redelivery_does_not_send_a_second_confirmation(): void
    {
        $order = $this->unpaidOrder();

        $this->sendEvent('checkout.session.completed', $this->sessionPayload($order))->assertOk();
        $this->sendEvent('checkout.session.completed', $this->sessionPayload($order))->assertOk();

        // Stripe ponavlja dostave; kupac ne smije dobiti dva maila.
        Notification::assertSentOnDemandTimes(OrderPlaced::class, 1);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $order = $this->unpaidOrder();

        $this->sendEvent('checkout.session.completed', $this->sessionPayload($order), secret: 'whsec_krivi')
            ->assertStatus(400);

        $this->assertSame(PaymentStatus::Unpaid, $order->refresh()->payment_status);
        Notification::assertNothingSent();
    }

    public function test_missing_signature_header_is_rejected(): void
    {
        $this->call('POST', '/api/webhooks/stripe', content: '{}')->assertStatus(400);
    }

    public function test_unknown_session_returns_ok_so_stripe_stops_retrying(): void
    {
        // `stripe trigger` generira sesije koje nemaju našu narudžbu.
        $this->sendEvent('checkout.session.completed', [
            'id' => 'cs_test_nepoznato',
            'object' => 'checkout.session',
            'payment_intent' => 'pi_test_nepoznato',
            'metadata' => [],
        ])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_expired_session_marks_order_failed(): void
    {
        $order = $this->unpaidOrder();

        $this->sendEvent('checkout.session.expired', $this->sessionPayload($order))->assertOk();

        $this->assertSame(PaymentStatus::Failed, $order->refresh()->payment_status);
        Notification::assertNothingSent();
    }

    public function test_expired_event_cannot_undo_a_paid_order(): void
    {
        $order = Order::factory()->paid()->create(['stripe_checkout_session_id' => 'cs_test_abc123']);

        // Zakašnjeli `expired` nakon uspješnog plaćanja.
        $this->sendEvent('checkout.session.expired', $this->sessionPayload($order))->assertOk();

        $this->assertSame(PaymentStatus::Paid, $order->refresh()->payment_status);
    }

    public function test_failed_payment_intent_is_matched_through_metadata(): void
    {
        $order = $this->unpaidOrder();

        // Payment intent nema session id — jedina poveznica je metadata koju
        // šaljemo kroz payment_intent_data.
        $this->sendEvent('payment_intent.payment_failed', [
            'id' => 'pi_test_pao',
            'object' => 'payment_intent',
            'metadata' => ['order_reference' => $order->order_reference],
        ])->assertOk();

        $this->assertSame(PaymentStatus::Failed, $order->refresh()->payment_status);
    }

    public function test_unhandled_event_type_is_acknowledged(): void
    {
        $order = $this->unpaidOrder();

        $this->sendEvent('customer.created', ['id' => 'cus_test_123', 'object' => 'customer'])
            ->assertOk();

        $this->assertSame(PaymentStatus::Unpaid, $order->refresh()->payment_status);
    }
}
