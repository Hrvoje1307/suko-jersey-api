<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';

    /**
     * Human-readable oznaka stanja plaćanja za kupca.
     */
    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Čeka plaćanje',
            self::Paid => 'Plaćeno',
            self::Failed => 'Plaćanje nije uspjelo',
            self::Refunded => 'Iznos vraćen',
        };
    }

    /**
     * Je li narudžba plaćena, tj. smije li se prikazivati fulfillment status.
     */
    public function isSettled(): bool
    {
        return $this === self::Paid;
    }
}
