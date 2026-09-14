<?php
namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\FollowUp;
use App\Services\GmailService;
use Illuminate\Console\Command;

class SendDueFollowUpEmails extends Command
{
    protected $signature = 'crm:send-due-follow-up-emails {--dry-run : Preview without sending}';
    protected $description = 'Send one Gmail reminder for each due email follow-up with a contact email';

    public function handle(GmailService $gmail): int
    {
        $items = FollowUp::query()->with('assignedTo')->where('type', 'email')->where('status', 'pending')->whereNull('email_sent_at')->whereDate('due_date', '<=', today())->get();
        foreach ($items as $followUp) {
            $contact = $followUp->contact_id ? Contact::find($followUp->contact_id) : null;
            if (!$contact?->email) { $this->warn("Skipping follow-up {$followUp->id}: no contact email"); continue; }
            $subject = 'Follow-up: ' . ucfirst(str_replace('_', ' ', $followUp->subject_type)) . ' #' . $followUp->subject_id;
            $body = $followUp->note ?: 'This is a scheduled follow-up from our team.';
            if ($this->option('dry-run')) { $this->line("Would send {$contact->email} for follow-up {$followUp->id}"); continue; }
            try {
                $gmail->send($followUp->assignedTo, $contact->email, $subject, $body);
                $followUp->forceFill(['email_sent_at' => now()])->save();
                $this->info("Sent follow-up {$followUp->id} to {$contact->email}");
            } catch (\Throwable $e) { $this->error("Follow-up {$followUp->id} failed: {$e->getMessage()}"); }
        }
        return self::SUCCESS;
    }
}
