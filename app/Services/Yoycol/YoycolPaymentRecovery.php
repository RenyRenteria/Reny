<?php

namespace App\Services\Yoycol;

use App\Services\Commerce\PayPalPaymentRecovery;

// Preserve the existing Yoycol service binding while sharing checkout finalization.
class YoycolPaymentRecovery extends PayPalPaymentRecovery {}
