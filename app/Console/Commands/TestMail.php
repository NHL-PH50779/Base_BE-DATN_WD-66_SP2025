<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Mail\OtpMail;

class TestMail extends Command
{
    protected $signature = 'mail:test {email}';
    protected $description = 'Test mail configuration';

    public function handle()
    {
        $email = $this->argument('email');
        $otp = rand(100000, 999999);

        try {
            $this->info('Testing mail configuration...');
            $this->info('Email: ' . $email);
            $this->info('OTP: ' . $otp);
            
            Mail::to($email)->send(new OtpMail($otp));
            
            $this->info('✅ Mail sent successfully!');
            
        } catch (\Exception $e) {
            $this->error('❌ Mail failed: ' . $e->getMessage());
            $this->error('Trace: ' . $e->getTraceAsString());
        }
    }
}