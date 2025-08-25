<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public $otp) {}

    public function build()
    {
        return $this->subject('Mã OTP từ TechStore')
            ->view('emails.otp')
            ->with(['otp' => $this->otp]);
    }
}