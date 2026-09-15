<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ScheduledEmail;
use Carbon\Carbon;
use Illuminate\Http\Request;

final class ScheduledEmailController extends Controller
{
    public function index(Request $request)
    {
        return ScheduledEmail::where('user_id', $request->user()->id)->latest('scheduled_at')->paginate(50);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'to_email' => ['required', 'email'],
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
        return response()->json(ScheduledEmail::create($data), 201);
    }

    public function destroy(Request $request, ScheduledEmail $scheduledEmail)
    {
        abort_unless($scheduledEmail->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        abort_if($scheduledEmail->status !== 'pending', 422, 'Only pending emails can be cancelled.');
        $scheduledEmail->update(['status' => 'cancelled']);
        return response()->json(['message' => 'Scheduled email cancelled.']);
    }
}
