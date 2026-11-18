<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class CommerceNotice extends Mailable
{
    public function __construct(public string $heading, public string $bodyText, public string $url) {}

    public function build(): static
    {
        return $this->subject($this->heading)->text('mail.commerce-notice');
    }
}
