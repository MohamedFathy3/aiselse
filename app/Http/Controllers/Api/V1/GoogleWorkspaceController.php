<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Contact;
use App\Services\GmailService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

final class GoogleWorkspaceController extends Controller
{
    public function gmail(Request $request)
    {
        $user = $request->user();
        $query = $request->string('q', 'newer_than:30d')->toString();
        $limit = min(max($request->integer('limit', 25), 1), 50);
        $queryParams = [
            'maxResults' => $limit,
            'q' => $query,
        ];
        if ($request->filled('pageToken')) {
            $queryParams['pageToken'] = $request->string('pageToken')->toString();
        }
        $list = $this->googleRequest($user, 'https://gmail.googleapis.com/gmail/v1/users/me/messages', $queryParams);

        $messages = collect($list['messages'] ?? [])->map(function (array $message) use ($user) {
            $detail = $this->googleRequest($user, 'https://gmail.googleapis.com/gmail/v1/users/me/messages/' . $message['id'], [
                'format' => 'metadata',
                'metadataHeaders' => ['From', 'To', 'Cc', 'Subject', 'Date'],
            ]);
            $headers = collect($detail['payload']['headers'] ?? [])->mapWithKeys(fn ($header) => [strtolower($header['name']) => $header['value']]);
            return [
                'id' => $detail['id'] ?? $message['id'],
                'thread_id' => $detail['threadId'] ?? null,
                'label_ids' => $detail['labelIds'] ?? [],
                'snippet' => $detail['snippet'] ?? '',
                'from' => $headers->get('from'),
                'to' => $headers->get('to'),
                'cc' => $headers->get('cc'),
                'subject' => $headers->get('subject', '(No subject)'),
                'date' => $headers->get('date'),
                'internal_date' => $detail['internalDate'] ?? null,
                'is_unread' => in_array('UNREAD', $detail['labelIds'] ?? [], true),
            ];
        })->values();

        return response()->json([
            'messages' => $messages,
            'next_page_token' => $list['nextPageToken'] ?? null,
            'result_size_estimate' => $list['resultSizeEstimate'] ?? $messages->count(),
        ]);
    }

    public function message(Request $request, string $id)
    {
        $detail = $this->googleRequest($request->user(), 'https://gmail.googleapis.com/gmail/v1/users/me/messages/' . rawurlencode($id), [
            'format' => 'full',
        ]);
        return response()->json($this->normalizeMessage($detail));
    }

    public function deleteMessages(Request $request)
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:100'], 'ids.*' => ['required', 'string']]);
        $user = $request->user();
        foreach ($data['ids'] as $id) {
            // Move to Trash instead of permanently deleting. messages.delete
            // requires the broader https://mail.google.com/ scope, while
            // messages.trash is supported by the already-requested gmail.modify.
            $this->googleRequest($user, 'https://gmail.googleapis.com/gmail/v1/users/me/messages/' . rawurlencode($id) . '/trash', [], 'post');
        }
        return response()->json(['deleted' => count($data['ids'])]);
    }

    public function permanentlyDeleteMessages(Request $request)
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:100'], 'ids.*' => ['required', 'string']]);
        $user = $request->user();
        foreach ($data['ids'] as $id) {
            $this->googleRequest($user, 'https://gmail.googleapis.com/gmail/v1/users/me/messages/' . rawurlencode($id), [], 'delete');
        }

        return response()->json(['permanently_deleted' => count($data['ids'])]);
    }

    public function calendar(Request $request)
    {
        return response()->json($this->googleRequest($request->user(), 'https://www.googleapis.com/calendar/v3/calendars/primary/events', [
            'maxResults' => min($request->integer('limit', 20), 50),
            'singleEvents' => 'true',
            'orderBy' => 'startTime',
            'timeMin' => now()->toRfc3339String(),
        ]));
    }

    public function createCalendarEvent(Request $request, GmailService $gmail)
    {
        $data = $request->validate([
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'attendees' => ['nullable', 'array', 'max:20'],
            'attendees.*' => ['email'],
            'send_email' => ['boolean'],
            'email_to' => ['nullable', 'email'],
            'email_subject' => ['nullable', 'string', 'max:255'],
            'email_body' => ['nullable', 'string', 'max:10000'],
            'timezone' => ['nullable', 'timezone'],
        ]);

        $user = $request->user();
        if ($user->isSales() && !empty($data['client_id'])) {
            abort_unless(Client::where('id', $data['client_id'])->where('user_id', $user->id)->exists(), 403, 'You cannot schedule an event for this client.');
        }

        $timezone = $data['timezone'] ?? $user->timezone();
        $start = Carbon::parse($data['starts_at'])->setTimezone($timezone);
        $end = Carbon::parse($data['ends_at'])->setTimezone($timezone);
        $event = $this->googleRequest($user, 'https://www.googleapis.com/calendar/v3/calendars/primary/events?sendUpdates=all', [
            'summary' => $data['title'],
            'description' => $data['description'] ?? '',
            'start' => ['dateTime' => $start->toRfc3339String(), 'timeZone' => $timezone],
            'end' => ['dateTime' => $end->toRfc3339String(), 'timeZone' => $timezone],
            'attendees' => collect($data['attendees'] ?? [])->map(fn ($email) => ['email' => $email])->values()->all(),
        ], 'post');

        $local = CalendarEvent::create([
            'user_id' => $user->id,
            'subject_type' => !empty($data['client_id']) ? 'client' : null,
            'subject_id' => $data['client_id'] ?? null,
            'google_event_id' => $event['id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'starts_at' => $start,
            'ends_at' => $end,
            'attendees' => $data['attendees'] ?? [],
            'status' => 'confirmed',
        ]);

        $emailSent = false;
        if ($request->boolean('send_email')) {
            $recipient = $data['email_to'] ?? null;
            if (!$recipient && !empty($data['client_id'])) {
                $recipient = Contact::where('contactable_type', 'client')->where('contactable_id', $data['client_id'])->whereNotNull('email')->orderByDesc('is_primary')->value('email');
            }
            $recipient ??= $user->email;
            $gmail->send($user, $recipient, $data['email_subject'] ?? $data['title'], $data['email_body'] ?? ($data['description'] ?? 'Appointment scheduled for ' . $start->toDateTimeString()));
            $emailSent = true;
        }
        return response()->json(['event' => $event, 'local_event' => $local, 'email_sent' => $emailSent], 201);
    }

    private function normalizeMessage(array $message): array
    {
        $headers = collect($message['payload']['headers'] ?? [])->mapWithKeys(fn ($header) => [strtolower($header['name']) => $header['value']]);
        return [
            'id' => $message['id'] ?? null,
            'thread_id' => $message['threadId'] ?? null,
            'snippet' => $message['snippet'] ?? '',
            'from' => $headers->get('from'),
            'to' => $headers->get('to'),
            'cc' => $headers->get('cc'),
            'subject' => $headers->get('subject', '(No subject)'),
            'date' => $headers->get('date'),
            'body' => $this->extractBody($message['payload'] ?? []),
        ];
    }

    private function extractBody(array $payload): string
    {
        if (($payload['mimeType'] ?? '') === 'text/plain' && isset($payload['body']['data'])) {
            return base64_decode(strtr($payload['body']['data'], '-_', '+/')) ?: '';
        }
        foreach ($payload['parts'] ?? [] as $part) {
            $body = $this->extractBody($part);
            if ($body !== '') return $body;
        }
        if (($payload['mimeType'] ?? '') === 'text/html' && isset($payload['body']['data'])) {
            return trim(strip_tags(base64_decode(strtr($payload['body']['data'], '-_', '+/')) ?: ''));
        }
        return '';
    }

    private function googleRequest($user, string $url, array $query = [], string $method = 'get'): array
    {
        abort_unless($user->hasConnectedGoogle(), 409, 'Connect your own Gmail account first.');
        $token = decrypt($user->google_access_token);
        if ($user->google_token_expires_at?->isPast() && $user->google_refresh_token) {
            $refreshed = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'refresh_token' => decrypt($user->google_refresh_token),
                'grant_type' => 'refresh_token',
            ])->throw()->json();
            $token = $refreshed['access_token'];
            $user->forceFill([
                'google_access_token' => encrypt($token),
                'google_token_expires_at' => now()->addSeconds((int) ($refreshed['expires_in'] ?? 3600)),
            ])->save();
        }
        $response = match ($method) {
            'delete' => Http::withToken($token)->delete($url),
            'post' => Http::withToken($token)->post($url, $query),
            default => Http::withToken($token)->get($url, $query),
        };
        return $response->throw()->json() ?? [];
    }
}
