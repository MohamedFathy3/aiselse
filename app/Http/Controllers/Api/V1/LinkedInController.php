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
        abort_unless($request->filled('code') && $request->filled('state'), 419, 'Invalid LinkedIn OAuth response.');
        try { $state = json_decode(Crypt::decryptString((string) $request->string('state')), true,  flags: JSON_THROW_ON_ERROR); } catch (\Throwable) { abort(419, 'Invalid LinkedIn OAuth state.'); }
        abort_unless(($state['issued_at'] ?? 0) >= now()->subMinutes(10)->timestamp && !empty($state['user_id']), 419, 'LinkedIn OAuth state expired.');
        $token = Http::asForm()->timeout(15)->post('https://www.linkedin.com/oauth/v2/accessToken', ['grant_type' => 'authorization_code', 'code' => $request->string('code'), 'redirect_uri' => config('services.linkedin.redirect'), 'client_id' => config('services.linkedin.client_id'), 'client_secret' => config('services.linkedin.client_secret')])->throw()->json();
        $profile = Http::withToken($token['access_token'])->timeout(15)->get('https://api.linkedin.com/v2/userinfo')->throw()->json();
        $user = \App\Models\User::find($state['user_id']);
        abort_unless($user, 401, 'The LinkedIn connection user could not be found.');
        $user->forceFill(['linkedin_access_token' => $token['access_token'], 'linkedin_sub' => $profile['sub'] ?? null, 'linkedin_connected_at' => now()])->save();
        return redirect(rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/') . '/linkedin?linkedin=connected');
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
