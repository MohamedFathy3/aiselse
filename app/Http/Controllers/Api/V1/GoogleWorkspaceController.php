<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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
