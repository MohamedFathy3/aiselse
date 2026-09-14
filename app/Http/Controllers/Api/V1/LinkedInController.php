<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;

final class LinkedInController extends Controller
{
    public function connect(Request $request)
    {
        abort_unless(config('services.linkedin.client_id') && config('services.linkedin.client_secret'), 503, 'LinkedIn OAuth is not configured.');
        $state = Crypt::encryptString(json_encode(['user_id' => $request->user()->id, 'nonce' => bin2hex(random_bytes(24)), 'issued_at' => now()->timestamp]));
        $query = http_build_query(['response_type' => 'code', 'client_id' => config('services.linkedin.client_id'), 'redirect_uri' => config('services.linkedin.redirect'), 'state' => $state, 'scope' => config('services.linkedin.scopes')]);
        return response()->json(['url' => 'https://www.linkedin.com/oauth/v2/authorization?' . $query]);
    }

    public function callback(Request $request)
    {
        $frontend = rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/') . '/linkedin';
        if ($request->filled('error')) {
            logger()->warning('LinkedIn OAuth was denied', ['error' => $request->string('error')->toString(), 'description' => $request->string('error_description')->toString()]);
            return redirect($frontend . '?linkedin=error&reason=' . urlencode($request->string('error_description', 'authorization_denied')->toString()));
        }
        try {
            if (!$request->filled('code') || !$request->filled('state')) throw new \RuntimeException('LinkedIn did not return code and state.');
            $state = json_decode(Crypt::decryptString((string) $request->string('state')), true, flags: JSON_THROW_ON_ERROR);
            if (($state['issued_at'] ?? 0) < now()->subMinutes(10)->timestamp || empty($state['user_id'])) throw new \RuntimeException('OAuth state expired or has no user.');
            $tokenResponse = Http::asForm()->timeout(15)->post('https://www.linkedin.com/oauth/v2/accessToken', ['grant_type' => 'authorization_code', 'code' => $request->string('code'), 'redirect_uri' => config('services.linkedin.redirect'), 'client_id' => config('services.linkedin.client_id'), 'client_secret' => config('services.linkedin.client_secret')]);
            if ($tokenResponse->failed()) throw new \RuntimeException('LinkedIn token exchange failed with HTTP ' . $tokenResponse->status() . ': ' . substr((string) $tokenResponse->body(), 0, 300));
            $token = $tokenResponse->json();
            $profileResponse = Http::withToken($token['access_token'])->timeout(15)->get('https://api.linkedin.com/v2/userinfo');
            if ($profileResponse->failed()) throw new \RuntimeException('LinkedIn profile request failed with HTTP ' . $profileResponse->status() . ': ' . substr((string) $profileResponse->body(), 0, 300));
            $profile = $profileResponse->json();
            $user = \App\Models\User::find($state['user_id']);
            if (!$user) throw new \RuntimeException('The LinkedIn connection user could not be found.');
            $user->forceFill(['linkedin_access_token' => $token['access_token'], 'linkedin_sub' => $profile['sub'] ?? null, 'linkedin_connected_at' => now()])->save();
            logger()->info('LinkedIn OAuth connected', ['user_id' => $user->id, 'linkedin_sub' => $profile['sub'] ?? null]);
            return redirect($frontend . '?linkedin=connected');
        } catch (\Throwable $e) {
            report($e);
            logger()->error('LinkedIn OAuth callback failed', ['message' => $e->getMessage()]);
            return redirect($frontend . '?linkedin=error&reason=' . urlencode($e->getMessage()));
        }
    }

    public function profile(Request $request)
    {
        abort_unless($request->user()->linkedin_access_token, 409, 'Connect your LinkedIn account first.');
        return response()->json(Http::withToken($request->user()->linkedin_access_token)->timeout(15)->get('https://api.linkedin.com/v2/userinfo')->throw()->json());
    }

    public function status(Request $request)
    {
        return response()->json(['connected' => filled($request->user()->linkedin_access_token), 'connected_at' => optional($request->user()->linkedin_connected_at)?->toIso8601String()]);
    }

    public function disconnect(Request $request)
    {
        $request->user()->forceFill(['linkedin_access_token' => null, 'linkedin_sub' => null, 'linkedin_connected_at' => null])->save();
        return response()->json(['message' => 'LinkedIn disconnected.']);
    }
}
