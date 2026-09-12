<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePlayersRequest;
use App\Http\Resources\ProductDetailResource;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductPlayer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminProductPlayerController extends Controller
{
    /**
     * Replace semantika: lista se postavlja u cijelosti, a redoslijed u tijelu
     * zahtjeva određuje sort_order.
     *
     * Nije implementirano kao "obriši sve pa ubaci nanovo": order_items.product_player_id
     * je FK bez ON DELETE, pa bi takvo brisanje puklo čim proizvod ima narudžbe.
     * Zato se igrači koji ostaju na listi prepoznaju po imenu i broju i zadržavaju
     * svoj id, a odbija se samo uklanjanje onih koji se već nalaze u narudžbi.
     */
    public function update(UpdatePlayersRequest $request, Product $product): ProductDetailResource
    {
        $players = $request->validated('players');

        DB::transaction(function () use ($product, $players) {
            $pool = $this->existingByFingerprint($product);

            foreach ($players as $index => $player) {
                $number = $this->normalizeNumber($player['player_number'] ?? null);
                $key = $this->fingerprint($player['player_name'], $number);

                $existing = empty($pool[$key]) ? null : array_shift($pool[$key]);

                if ($existing instanceof ProductPlayer) {
                    $existing->update(['sort_order' => $index]);

                    continue;
                }

                $product->players()->create([
                    'player_name' => $player['player_name'],
                    'player_number' => $number,
                    'sort_order' => $index,
                ]);
            }

            $this->removeLeftovers($product, collect($pool)->flatten());
        });

        return new ProductDetailResource(
            $product->load(['images', 'variants', 'players'])
        );
    }

    /**
     * @return array<string, array<int, ProductPlayer>>
     */
    protected function existingByFingerprint(Product $product): array
    {
        $pool = [];

        foreach ($product->players()->get() as $player) {
            $pool[$this->fingerprint($player->player_name, $player->player_number)][] = $player;
        }

        return $pool;
    }

    /**
     * Briše igrače koji su ispali s liste. Oni koji su već naručeni se ne mogu
     * obrisati bez gubitka podatka o tome koje ime treba naručiti kod dobavljača,
     * pa se takav zahtjev odbija umjesto da padne na FK constraintu.
     *
     * @param  Collection<int, ProductPlayer>  $leftovers
     */
    protected function removeLeftovers(Product $product, Collection $leftovers): void
    {
        if ($leftovers->isEmpty()) {
            return;
        }

        $ordered = OrderItem::query()
            ->whereIn('product_player_id', $leftovers->pluck('id'))
            ->pluck('product_player_id')
            ->unique();

        if ($ordered->isNotEmpty()) {
            $names = $leftovers->whereIn('id', $ordered)->pluck('player_name')->implode(', ');

            throw ValidationException::withMessages([
                'players' => ["Igrači koji se već nalaze u narudžbama se ne mogu ukloniti s liste: {$names}."],
            ]);
        }

        $product->players()->whereIn('id', $leftovers->pluck('id'))->delete();
    }

    protected function fingerprint(string $name, ?string $number): string
    {
        return $name.'|'.($number ?? '');
    }

    protected function normalizeNumber(?string $number): ?string
    {
        return $number === '' ? null : $number;
    }
}
