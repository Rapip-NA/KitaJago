<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class ParticipantOtp extends Mailable
{
    public function __construct(public string $code) {}

    public function build()
    {
        return $this->subject('Kode masuk JagoKita')->text('mail.otp');
    }
}
