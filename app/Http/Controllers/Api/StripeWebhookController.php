<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Notifications\OrderPlaced;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Throwable;
use UnexpectedValueException;

/**
 * CSRF ovdje nije tema: routes/api.php se registrira kroz withRouting(api:),
 * dakle u stateless `api` grupi koja nikad ne prolazi kroz ValidateCsrfToken
 * (statefulApi() se u bootstrap/app.php ne poziva). Autentikacija je potpis.
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // Potpis se računa nad sirovim tijelom — json_encode($request->all())
        // bi promijenio bajtove i potpis nikad ne bi prošao.
        $payload = $request->getContent();

        try {
            $event = Webhook::constructEvent(
                $payload,
                (string) $request->header('Stripe-Signature'),
                (string) config('services.stripe.webhook_secret'),
            );
        } catch (SignatureVerificationException|UnexpectedValueException $e) {
            Log::warning('Stripe webhook odbijen: neispravan potpis', [
                'message' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'invalid signature'], 400);
        }

        $object = $event->data->object;

        match ($event->type) {
            'checkout.session.completed' => $this->handlePaid($object),
            'checkout.session.expired',
            'payment_intent.payment_failed' => $this->handleFailed($object),
            default => null,
        };

        // Nakon verifikacije Stripeu uvijek 200 — sve ostalo ga gura u retry.
        return response()->json(['received' => true]);
    }

    protected function handlePaid(object $session): void
    {
        $order = $this->resolveOrder($session);

        if (! $order) {
            // Npr. `stripe trigger` generira sesiju bez naše narudžbe.
            Log::info('Stripe webhook: nema narudžbe za sesiju', ['id' => $session->id ?? null]);

            return;
        }

        // Stripe ponavlja dostave — drugi put ne diramo ništa i ne šaljemo mail.
        if ($order->isPaid()) {
            return;
        }

        $order->update([
            'payment_status' => PaymentStatus::Paid,
            'stripe_payment_intent_id' => $this->paymentIntentId($session) ?? $order->stripe_payment_intent_id,
        ]);

        // Potvrda narudžbe ide tek sad — prije plaćanja narudžba nije stvarna.
        // Mail je best-effort: uz QUEUE_CONNECTION=sync šalje se u ovom
        // zahtjevu, pa bi pad SMTP-a vratio 500 i Stripe bi ponovio dostavu —
        // a retry bi zbog isPaid() odmah izašao i mail bi se trajno izgubio.
        try {
            Notification::route('mail', $order->customer->email)
                ->notify(new OrderPlaced($order));
        } catch (Throwable $e) {
            Log::error('Potvrda narudžbe nije poslana', [
                'order_reference' => $order->order_reference,
                'exception' => $e,
            ]);
        }
    }

    protected function handleFailed(object $object): void
    {
        $order = $this->resolveOrder($object);

        if (! $order) {
            Log::info('Stripe webhook: nema narudžbe za neuspjelo plaćanje', ['id' => $object->id ?? null]);

            return;
        }

        // Zakašnjeli `expired` ne smije poništiti već potvrđeno plaćanje.
        if ($order->isPaid()) {
            return;
        }

        $order->update(['payment_status' => PaymentStatus::Failed]);
    }

    /**
     * Sesija se veže po spremljenom id-u; payment intent nema session id pa
     * ide preko vlastitog id-a ili metadate koju šaljemo kod otvaranja sesije.
     */
    protected function resolveOrder(object $object): ?Order
    {
        $candidates = array_filter([
            'stripe_checkout_session_id' => is_string($object->id ?? null) ? $object->id : null,
            'stripe_payment_intent_id' => $this->paymentIntentId($object),
            'order_reference' => $this->orderReference($object),
        ]);

        // Bez ijednog traga ne smijemo pustiti prazan upit — vratio bi
        // nasumičnu prvu narudžbu i označio tuđe plaćanje.
        if ($candidates === []) {
            return null;
        }

        return Order::query()
            ->where(function ($query) use ($candidates) {
                foreach ($candidates as $column => $value) {
                    $query->orWhere($column, $value);
                }
            })
            ->first();
    }

    protected function orderReference(object $object): ?string
    {
        $reference = $object->metadata->order_reference
            ?? $object->client_reference_id
            ?? null;

        return is_string($reference) ? $reference : null;
    }

    /**
     * Na checkout sesiji je `payment_intent`, a na samom intentu je to `id`.
     */
    protected function paymentIntentId(object $object): ?string
    {
        $intent = $object->payment_intent
            ?? (($object->object ?? null) === 'payment_intent' ? $object->id : null);

        return is_string($intent) ? $intent : $intent?->id;
    }
}
