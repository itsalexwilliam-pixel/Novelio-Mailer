<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendgridTestEmailCommand extends Command
{
    protected $signature = 'mail:sendgrid-test {to : Recipient email address} {--subject=SendGrid Test Email}';

    protected $description = 'Send a test email using the configured SendGrid mailer';

    public function handle(): int
    {
        $to = (string) $this->argument('to');
        $subject = (string) $this->option('subject');

        try {
            Mail::mailer('sendgrid')->raw('This is a SendGrid Web API integration test email from ProAdvisor Support.', function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });

            $this->info('Test email sent successfully via SendGrid mailer.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to send test email: '.$e->getMessage());
            return self::FAILURE;
        }
    }
}
