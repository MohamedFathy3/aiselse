<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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

    public function calendar(Request $request)
    {
        return response()->json($this->googleRequest($request->user(), 'https://www.googleapis.com/calendar/v3/calendars/primary/events', [
            'maxResults' => min($request->integer('limit', 20), 50),
            'singleEvents' => 'true',
            'orderBy' => 'startTime',
            'timeMin' => now()->toRfc3339String(),
        ]));
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
        return $response->throw()->json();
    }
}
