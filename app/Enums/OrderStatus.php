<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Ordered = 'ordered';
    case SentToSupplier = 'sent_to_supplier';
    case ArrivedHr = 'arrived_hr';
    case Shipped = 'shipped';
    case Delivered = 'delivered';

    /**
     * Human-readable oznaka statusa za kupca.
     */
    public function label(): string
    {
        return match ($this) {
            self::Ordered => 'Narudžba zaprimljena',
            self::SentToSupplier => 'Naručeno kod dobavljača',
            // Kupac ne smije vidjeti da roba ide preko Hrvatske — label je
            // neutralan, a interni naziv koraka ostaje `arrived_hr`.
            self::ArrivedHr => 'Priprema za slanje',
            self::Shipped => 'Poslano',
            self::Delivered => 'Dostavljeno',
        };
    }

    /**
     * Je li tracking broj vidljiv kupcu u ovom statusu.
     */
    public function exposesTracking(): bool
    {
        return in_array($this, [self::Shipped, self::Delivered], true);
    }
}
