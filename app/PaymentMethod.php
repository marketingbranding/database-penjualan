<?php

namespace App;

enum PaymentMethod: string
{
    case Kpr = 'KPR';
    case Cash = 'CASH';

    public function label(): string
    {
        return $this->value;
    }
}
