<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ScheduledEmail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ScheduledEmailController extends Controller
{
    public function index(Request $request)
    {
        return ScheduledEmail::where('user_id', $request->user()->id)->latest('scheduled_at')->paginate(50);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'to_email' => ['nullable', 'email', 'required_without:recipients'],
            'recipients' => ['nullable', 'array', 'min:1', 'max:100', 'required_without:to_email'],
            'recipients.*' => ['required', 'email'],
            'cc_emails' => ['nullable', 'array'],
            'cc_emails.*' => ['email'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:50000'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'timezone' => ['required', 'timezone'],
        ]);
        $timezone = $data['timezone'];
        $data['scheduled_at'] = Carbon::parse($data['scheduled_at'], $timezone)->utc();
        $data['user_id'] = $request->user()->id;
        $data['status'] = 'pending';
        $recipients = array_values(array_unique($data['recipients'] ?? [$data['to_email']]));
        unset($data['recipients']);
        $emails = DB::transaction(fn () => collect($recipients)->map(function (string $recipient) use ($data) {
            $row = $data;
            $row['to_email'] = $recipient;
            return ScheduledEmail::create($row);
        }));
        return response()->json(['message' => count($emails) . ' email(s) scheduled.', 'emails' => $emails], 201);
    }

    public function destroy(Request $request, ScheduledEmail $scheduledEmail)
    {
        abort_unless($scheduledEmail->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        abort_if($scheduledEmail->status !== 'pending', 422, 'Only pending emails can be cancelled.');
        $scheduledEmail->update(['status' => 'cancelled']);
        return response()->json(['message' => 'Scheduled email cancelled.']);
    }
}
