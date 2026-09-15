<?php
namespace App\Console\Commands;

use App\Models\Client;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Services\GmailService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class SendDueFollowUpEmails extends Command
{
    protected $signature = 'crm:send-due-follow-up-emails {--dry-run : Preview without sending}';
    protected $description = 'Send one Gmail reminder for each due email follow-up using the selected record email';

    public function handle(GmailService $gmail): int
    {
        $items = FollowUp::query()->with('assignedTo')->where('type', 'email')->where('status', 'pending')->whereNull('email_sent_at')->get();
        foreach ($items as $followUp) {
            $timezone = $followUp->timezone ?: $followUp->assignedTo?->timezone() ?: (config('app.timezone') ?: 'UTC');
            $dueAt = Carbon::parse($followUp->due_date->format('Y-m-d') . ' ' . ($followUp->due_time ?: '00:00:00'), $timezone);
            if ($dueAt->isFuture()) { continue; }
            $subject = $followUp->subject_type === 'lead'
                ? Lead::find($followUp->subject_id)
                : Client::with('sourceLead')->find($followUp->subject_id);
            $recipient = $subject?->email ?: ($subject instanceof Client ? $subject->sourceLead?->email : null);
            if (!$recipient) { $this->warn("Skipping follow-up {$followUp->id}: no email on the selected record"); continue; }
            $subject = 'Follow-up: ' . ucfirst(str_replace('_', ' ', $followUp->subject_type)) . ' #' . $followUp->subject_id;
            $body = $followUp->note ?: 'This is a scheduled follow-up from our team.';
            if ($this->option('dry-run')) { $this->line("Would send {$recipient} for follow-up {$followUp->id}"); continue; }
            try {
                $gmail->send($followUp->assignedTo, $recipient, $subject, $body);
                $followUp->forceFill(['email_sent_at' => now($timezone)])->save();
                $this->info("Sent follow-up {$followUp->id} to {$recipient}");
            } catch (\Throwable $e) { $this->error("Follow-up {$followUp->id} failed: {$e->getMessage()}"); }
        }
        return self::SUCCESS;
    }
}
