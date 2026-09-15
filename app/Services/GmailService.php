<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;

class GmailService
{
    public function send(User $user, string $to, string $subject, string $body, array $cc = []): array
    {
        $token = $this->token($user);
        $ccHeader = $cc ? "Cc: " . implode(', ', $cc) . "\r\n" : '';
        $raw = base64_encode("To: {$to}\r\n{$ccHeader}Subject: {$subject}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$body}");
        return Http::withToken($token)->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', ['raw' => rtrim(strtr($raw, '+/', '-_'), '=')])->throw()->json();
    }

    private function token(User $user): string
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
            $user->forceFill(['google_access_token' => encrypt($token), 'google_token_expires_at' => now()->addSeconds((int) ($refreshed['expires_in'] ?? 3600))])->save();
        }
        return $token;
    }
}
