<?php

namespace App;

enum LotStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Sold = 'sold';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Tersedia',
            self::Reserved => 'Dipesan',
            self::Sold => 'Terjual',
            self::Inactive => 'Tidak Aktif',
        };
    }
}
