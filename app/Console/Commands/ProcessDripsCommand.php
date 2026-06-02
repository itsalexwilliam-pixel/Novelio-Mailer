<?php

namespace App\Console\Commands;

use App\Mail\DripMail;
use App\Models\Contact;
use App\Models\DripCampaign;
use App\Models\DripEnrollment;
use App\Models\DripStep;
use App\Models\EmailQueue;
use App\Models\SmtpServer;
use App\Models\SmtpServerUsage;
use App\Models\Unsubscribe;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class ProcessDripsCommand extends Command
{
    protected $signature = 'queue:process-drips';
    protected $description = 'Process due drip campaign enrollments and send the next step email';

    public function handle()
    {
        $due = DripEnrollment::with(['dripCampaign.steps', 'contact'])
            ->where('status', 'active')
            ->where('next_send_at', '<=', now())
            ->whereHas('dripCampaign', fn($q) => $q->where('status', 'active'))
            ->get();

        if ($due->isEmpty()) {
            $this->info('No drip enrollments due.');
            return self::SUCCESS;
        }

        $this->info("Processing {$due->count()} due drip enrollment(s)...");

        foreach ($due as $enrollment) {
            $this->processEnrollment($enrollment);
        }

        $this->info('Drip processing complete.');
        return self::SUCCESS;
    }

    private function processEnrollment(DripEnrollment $enrollment): void
    {
        $contact = $enrollment->contact;
        $drip    = $enrollment->dripCampaign;

        if (!$contact || !$drip) {
            $enrollment->update(['status' => 'completed']);
            return;
        }

        // Skip unsubscribed / bounced / suppressed
        $isUnsubscribed = Unsubscribe::whereRaw('LOWER(email) = ?', [strtolower($contact->email)])->exists();
        $isBounced      = $contact->is_bounced ?? false;
        $isSuppressed   = \App\Models\SuppressionEntry::where('account_id', $drip->account_id)
            ->whereRaw('LOWER(email) = ?', [strtolower($contact->email)])
            ->exists();

        if ($isUnsubscribed || $isBounced || $isSuppressed) {
            $enrollment->update(['status' => 'unsubscribed']);
            $this->warn("Skipped {$contact->email} (unsubscribed/bounced/suppressed)");
            return;
        }

        // Get the step to send
        $steps     = $drip->steps->sortBy('position')->values();
        $stepIndex = $enrollment->current_step - 1; // 0-based index
        $step      = $steps->get($stepIndex);

        if (!$step) {
            // All steps sent — mark completed
            $enrollment->update(['status' => 'completed', 'next_send_at' => null]);
            $this->line("Enrollment #{$enrollment->id} completed (all steps sent).");
            return;
        }

        $accountId = (int) $drip->account_id;

        // Fetch ALL active SMTP servers ordered by priority so we can fall back
        // to the next one if the first is at its daily limit or a send fails.
        $smtpServers = SmtpServer::forAccount($accountId)
            ->active()
            ->orderBy('priority')
            ->orderBy('last_used_at')
            ->orderBy('id')
            ->get();

        if ($smtpServers->isEmpty()) {
            $this->warn("No active SMTP for account #{$accountId} — skipping enrollment #{$enrollment->id}");
            return;
        }

        $today = Carbon::today()->toDateString();

        // Create an email_queue row for tracking (pending until a server succeeds)
        $queueRow = EmailQueue::create([
            'account_id'    => $accountId,
            'contact_id'    => $contact->id,
            'email'         => $contact->email,
            'type'          => 'drip',
            'subject'       => $step->subject,
            'body'          => $step->body,
            'body_snapshot' => $step->body,
            'status'        => 'pending',
            'attempts'      => 0,
        ]);

        $sent      = false;
        $lastError = null;

        foreach ($smtpServers as $smtp) {
            // Skip servers that have hit their daily limit
            if (!is_null($smtp->daily_limit)) {
                $sentToday = SmtpServerUsage::where('smtp_server_id', $smtp->id)
                    ->where('usage_date', $today)
                    ->value('sent_count') ?? 0;

                if ($sentToday >= (int) $smtp->daily_limit) {
                    $this->warn("SMTP #{$smtp->id} daily limit reached — trying next.");
                    continue;
                }
            }

            // Configure mailer dynamically for this SMTP server
            config([
                'mail.default'                => 'smtp',
                'mail.mailers.smtp.transport' => 'smtp',
                'mail.mailers.smtp.host'      => $smtp->host,
                'mail.mailers.smtp.port'      => $smtp->port,
                'mail.mailers.smtp.encryption' => $smtp->encryption === 'none' ? null : $smtp->encryption,
                'mail.mailers.smtp.username'  => $smtp->username,
                'mail.mailers.smtp.password'  => $smtp->password,
                'mail.mailers.smtp.timeout'   => 8,
                'mail.from.address'           => $smtp->from_email,
                'mail.from.name'              => $smtp->from_name,
            ]);

            try {
                Mail::to($contact->email)->send(new DripMail($step, $contact, $queueRow->id));

                $queueRow->update(['status' => 'sent', 'sent_at' => now(), 'last_error' => null]);
                $smtp->update(['last_used_at' => now()]);

                // Track SMTP usage
                $usage = SmtpServerUsage::firstOrCreate(
                    ['smtp_server_id' => $smtp->id, 'usage_date' => $today],
                    ['account_id' => $accountId, 'sent_count' => 0, 'fail_count' => 0]
                );
                $usage->increment('sent_count');

                // Advance enrollment to next step
                $nextStep = $steps->get($stepIndex + 1);

                if ($nextStep) {
                    $enrollment->update([
                        'current_step' => $enrollment->current_step + 1,
                        'next_send_at' => now()->addDays($nextStep->delay_days),
                    ]);
                    $this->info("Sent step {$enrollment->current_step} to {$contact->email} via SMTP #{$smtp->id} — next in {$nextStep->delay_days} day(s).");
                } else {
                    $enrollment->update(['status' => 'completed', 'next_send_at' => null]);
                    $this->info("Sent final step to {$contact->email} via SMTP #{$smtp->id} — enrollment completed.");
                }

                $sent = true;
                break; // Successfully sent — stop trying other servers
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                $smtp->update(['last_used_at' => now()]);

                $usage = SmtpServerUsage::firstOrCreate(
                    ['smtp_server_id' => $smtp->id, 'usage_date' => $today],
                    ['account_id' => $accountId, 'sent_count' => 0, 'fail_count' => 0]
                );
                $usage->increment('fail_count');

                $this->warn("SMTP #{$smtp->id} failed for {$contact->email}: {$lastError} — trying next.");
            }
        }

        if (!$sent) {
            $queueRow->update([
                'status'     => 'failed',
                'attempts'   => 1,
                'last_error' => $lastError ?? 'All SMTP servers failed or at daily limit.',
            ]);
            $this->error("Failed to send drip step to {$contact->email}: " . ($lastError ?? 'All SMTP servers failed or at daily limit.'));
        }
    }
}
