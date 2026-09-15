<?php

namespace App\Console\Commands;

use App\Models\ScheduledEmail;
use App\Services\GmailService;
use Illuminate\Console\Command;

class SendScheduledEmails extends Command
{
    protected $signature = 'crm:send-scheduled-emails {--dry-run : Preview without sending}';
    protected $description = 'Send pending scheduled emails that are due';

    public function handle(GmailService $gmail): int
    {
        $items = ScheduledEmail::with('user')->where('status', 'pending')->where('scheduled_at', '<=', now())->limit(100)->get();
        foreach ($items as $email) {
            if ($this->option('dry-run')) { $this->line("Would send #{$email->id} to {$email->to_email}"); continue; }
            try {
                $gmail->send($email->user, $email->to_email, $email->subject, $email->body, $email->cc_emails ?? []);
                $email->forceFill(['status' => 'sent', 'sent_at' => now(), 'error_message' => null])->save();
                $this->info("Sent scheduled email #{$email->id}");
            } catch (\Throwable $e) {
                $email->forceFill(['status' => 'failed', 'error_message' => $e->getMessage()])->save();
                $this->error("Scheduled email #{$email->id} failed: {$e->getMessage()}");
            }
        }
        return self::SUCCESS;
    }
}
