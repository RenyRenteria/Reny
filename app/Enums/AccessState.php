<?php

namespace App\Enums;

enum AccessState: string
{
    case Open = 'open';
    case RoyalActive = 'royal_active';
    case RoyalGrace = 'royal_grace';
    case RoyalExpired = 'royal_expired';
    case PaymentFailed = 'payment_failed';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';
}
