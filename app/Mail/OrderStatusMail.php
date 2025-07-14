<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public $order) {}

    public function build()
    {
        return $this->subject('Cập nhật đơn hàng #' . $this->order->id)
            ->view('emails.order-status')
            ->with(['order' => $this->order]);
    }
}