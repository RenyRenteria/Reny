<?php

namespace App\Enums;

enum AccessState: string
{
    case Open = 'open';
    case RoyalActive = 'royal_active';
    case RoyalGrace = 'royal_grace';
    case RoyalExpired = 'royal_expired';
}
