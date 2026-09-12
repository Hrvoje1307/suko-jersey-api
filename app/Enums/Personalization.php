<?php

namespace App\Enums;

enum Personalization: string
{
    case None = 'none';
    case PresetOnly = 'preset_only';
    case CustomText = 'custom_text';
    case Both = 'both';

    /**
     * Smije li kupac odabrati igrača s gotove liste (product_players).
     */
    public function allowsPreset(): bool
    {
        return $this === self::PresetOnly || $this === self::Both;
    }

    /**
     * Smije li kupac slobodno upisati ime/broj.
     */
    public function allowsCustomText(): bool
    {
        return $this === self::CustomText || $this === self::Both;
    }
}
