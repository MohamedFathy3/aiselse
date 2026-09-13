<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleController extends Controller
{
    public function connect(Request $request): RedirectResponse
    {
        abort_unless(config('services.google.client_id'), 503, 'Google OAuth is not configured.');

        $state = Crypt::encryptString(json_encode([
            'user_id' => $request->user()->id,
            'nonce' => Str::random(32),
            'expires' => now()->addMinutes(10)->timestamp,
        ], JSON_THROW_ON_ERROR));

        $query = http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'scope' => implode(' ', config('services.google.scopes', [])),
            'state' => $state,
        ]);

        return redirect('https://accounts.google.com/o/oauth2/v2/auth?' . $query);
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless($request->filled('code') && $request->filled('state'), 400, 'Invalid Google callback.');
        $payload = json_decode(Crypt::decryptString($request->string('state')->toString()), true, 512, JSON_THROW_ON_ERROR);
        abort_if(($payload['expires'] ?? 0) < now()->timestamp, 400, 'Google authorization expired.');

        $token = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $request->string('code')->toString(),
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => config('services.google.redirect'),
            'grant_type' => 'authorization_code',
        ])->throw()->json();

        $user = \App\Models\User::findOrFail($payload['user_id']);
        $accessToken = $token['access_token'];
        $profile = Http::withToken($accessToken)->get('https://www.googleapis.com/oauth2/v2/userinfo')->throw()->json();
        $user->forceFill([
            'google_account_email' => $profile['email'] ?? null,
            'google_access_token' => encrypt($accessToken),
            'google_refresh_token' => isset($token['refresh_token']) ? encrypt($token['refresh_token']) : $user->google_refresh_token,
            'google_token_expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 3600)),
            'google_connected_at' => now(),
        ])->save();

        return redirect(rtrim(config('app.frontend_url', env('FRONTEND_URL', '/dashboard')), '/') . '/dashboard?google=connected');
    }

    public function status(Request $request)
    {
        return response()->json([
            'connected' => $request->user()->hasConnectedGoogle(),
            'email' => $request->user()->google_account_email,
            'connected_at' => $request->user()->google_connected_at,
        ]);
    }

    public function disconnect(Request $request)
    {
        $request->user()->forceFill([
            'google_account_email' => null,
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
            'google_connected_at' => null,
        ])->save();
        return response()->json(['message' => 'Google account disconnected.']);
    }
}

final class GoogleWorkspaceController extends Controller
{
    public function gmail(Request $request)
    {
        return $this->googleRequest($request, 'https://gmail.googleapis.com/gmail/v1/users/me/messages', ['maxResults' => min($request->integer('limit', 20), 50), 'q' => $request->string('q', 'newer_than:30d')->toString()]);
    }

    public function calendar(Request $request)
    {
        return $this->googleRequest($request, 'https://www.googleapis.com/calendar/v3/calendars/primary/events', ['maxResults' => min($request->integer('limit', 20), 50), 'singleEvents' => 'true', 'orderBy' => 'startTime', 'timeMin' => now()->toRfc3339String()]);
    }

    private function googleRequest(Request $request, string $url, array $query)
    {
        abort_unless($request->user()->hasConnectedGoogle(), 409, 'Connect your own Google account first.');
        $user = $request->user();
        $token = decrypt($user->google_access_token);
        if ($user->google_token_expires_at?->isPast() && $user->google_refresh_token) {
            $refreshed = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'refresh_token' => decrypt($user->google_refresh_token),
                'grant_type' => 'refresh_token',
            ])->throw()->json();
            $token = $refreshed['access_token'];
            $user->forceFill(['google_access_token' => encrypt($token), 'google_token_expires_at' => now()->addSeconds((int) ($refreshed['expires_in'] ?? 3600))])->save();
        }
        return response()->json(Http::withToken($token)->get($url, $query)->throw()->json());
    }
}

final class AiResearchController extends Controller
{
    public function search(Request $request)
    {
        $data = $request->validate(['query' => ['required', 'string', 'min:3', 'max:500'], 'filters' => ['nullable', 'array']]);
        $search = \App\Models\LeadSearch::create(['user_id' => $request->user()->id, 'query' => $data['query'], 'filters' => $data['filters'] ?? [], 'status' => 'processing', 'current_step' => 'searching', 'started_at' => now()]);
        try {
            $items = Http::get('https://www.googleapis.com/customsearch/v1', [
                'key' => config('services.search.key'), 'cx' => config('services.search.engine_id'), 'q' => $data['query'], 'num' => 10,
            ])->throw()->json('items', []);
            $results = collect($items)->map(fn ($item) => [
                'lead_search_id' => $search->id, 'company_name' => $item['title'] ?? 'Unknown company', 'website' => $item['link'] ?? null,
                'description' => $item['snippet'] ?? null, 'source_url' => $item['link'] ?? null, 'source_title' => $item['title'] ?? null,
                'source_snippet' => $item['snippet'] ?? null, 'confidence_score' => 60, 'lead_score' => 50, 'review_status' => 'pending',
            ])->values();
            foreach ($results as $result) { \App\Models\LeadSearchResult::create($result); }
            $search->update(['status' => 'completed', 'current_step' => 'completed', 'results_count' => $results->count(), 'completed_at' => now()]);
        } catch (\Throwable $e) {
            report($e);
            $search->update(['status' => 'failed', 'current_step' => 'failed', 'error_message' => app()->isProduction() ? 'Search provider failed.' : $e->getMessage()]);
        }
        return response()->json(['search' => $search->fresh(), 'results' => $search->results()->latest()->get()], $search->status === 'failed' ? 502 : 201);
    }

    public function chat(Request $request)
    {
        $data = $request->validate(['message' => ['required', 'string', 'min:2', 'max:2000']]);
        $context = [
            'leads' => \App\Models\Lead::where('assigned_to', $request->user()->id)->latest()->limit(20)->get(['company_name', 'status', 'city', 'industry']),
            'clients' => \App\Models\Client::where('user_id', $request->user()->id)->latest()->limit(20)->get(['company_name', 'country', 'city']),
        ];
        if (!config('services.ai.key')) return response()->json(['message' => 'AI is not configured. Add AI_API_KEY on the server.', 'context' => $context], 503);
        $response = Http::withHeaders(['x-goog-api-key' => config('services.ai.key'), 'Content-Type' => 'application/json'])->post('https://generativelanguage.googleapis.com/v1beta/models/' . config('services.ai.model', 'gemini-1.5-flash') . ':generateContent', ['contents' => [['parts' => [['text' => 'You are a sales CRM assistant. Answer using only this CRM context and say when data is missing. Context: ' . json_encode($context) . "\nUser: " . $data['message']]]]]]);
        return response()->json(['answer' => $response->throw()->json('candidates.0.content.parts.0.text')]);
    }
}

final class GoogleMailboxController extends Controller
{
    public function send(Request $request)
    {
        $data = $request->validate(['to' => ['required', 'email'], 'subject' => ['required', 'string', 'max:255'], 'body' => ['required', 'string']]);
        abort_unless($request->user()->hasConnectedGoogle(), 409, 'Connect your own Gmail account first.');
        $token = decrypt($request->user()->google_access_token);
        $raw = base64_encode("To: {$data['to']}\r\nSubject: {$data['subject']}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$data['body']}");
        $raw = strtr($raw, '+/', '-_');
        return response()->json(Http::withToken($token)->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', ['raw' => rtrim($raw, '=')])->throw()->json());
    }
}
