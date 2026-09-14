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

        $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $request->string('code')->toString(),
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => config('services.google.redirect'),
            'grant_type' => 'authorization_code',
        ]);

        abort_if($tokenResponse->failed(), 502, 'Google token exchange failed: ' . $tokenResponse->body());
        $token = $tokenResponse->json();
        abort_unless(filled($token['access_token'] ?? null), 502, 'Google did not return an access token.');

        $user = \App\Models\User::findOrFail($payload['user_id']);
        $accessToken = $token['access_token'];
        $profileResponse = Http::acceptJson()
            ->withHeaders(['Authorization' => 'Bearer ' . $accessToken])
            ->get('https://gmail.googleapis.com/gmail/v1/users/me/profile');
        if ($profileResponse->failed()) {
            report(new \RuntimeException('Google Gmail profile request failed: ' . $profileResponse->body()));
            $profile = [];
        } else {
            $profile = $profileResponse->json();
        }
        $user->forceFill([
            'google_account_email' => $profile['emailAddress'] ?? null,
            'google_access_token' => encrypt($accessToken),
            'google_refresh_token' => isset($token['refresh_token']) ? encrypt($token['refresh_token']) : $user->google_refresh_token,
            'google_token_expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 3600)),
            'google_connected_at' => now(),
        ])->save();

        return redirect(rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/') . '/inbox?google=connected');
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
