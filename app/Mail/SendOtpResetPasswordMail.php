<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendOtpResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public $otp) {}

    public function build()
    {
        return $this->subject('Mã OTP đặt lại mật khẩu - TechStore')
            ->view('emails.send-otp')
            ->with(['otp' => $this->otp]);
    }
}