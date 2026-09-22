<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Initial = 'initial';
    case Purchase = 'purchase';
    case Sale = 'sale';
    case Return = 'return';
    case Damage = 'damage';
    case Adjustment = 'adjustment';
}
