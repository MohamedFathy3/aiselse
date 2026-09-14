<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

final class GoogleMailboxController extends Controller
{
    public function send(Request $request)
    {
        $data = $request->validate(['to' => ['required', 'email'], 'subject' => ['required', 'string', 'max:255'], 'body' => ['required', 'string']]);
        abort_unless($request->user()->hasConnectedGoogle(), 409, 'Connect your own Gmail account first.');
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
            $user->forceFill([
                'google_access_token' => encrypt($token),
                'google_token_expires_at' => now()->addSeconds((int) ($refreshed['expires_in'] ?? 3600)),
            ])->save();
        }
        $raw = base64_encode("To: {$data['to']}\r\nSubject: {$data['subject']}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$data['body']}");
        $raw = strtr($raw, '+/', '-_');
        return response()->json(Http::withToken($token)->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', ['raw' => rtrim($raw, '=')])->throw()->json());
    }
}
