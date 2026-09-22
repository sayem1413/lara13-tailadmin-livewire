<?php

namespace App\Enums;

enum ProductUnit: string
{
    case Piece = 'pc';
    case Kilogram = 'kg';
    case Gram = 'g';
    case Litre = 'l';
    case Millilitre = 'ml';
    case Dozen = 'dozen';
    case Packet = 'packet';
    case Bag = 'bag';
}
