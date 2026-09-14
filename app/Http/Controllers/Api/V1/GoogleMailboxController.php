<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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

    public function sendToClient(Request $request, Client $client)
    {
        abort_unless($request->user()->isAdmin() || $client->user_id === $request->user()->id, 403, 'You cannot email this client.');
        $contact = $client->contacts()->whereNotNull('email')->first();
        abort_unless($contact, 422, 'This client has no contact email address.');
        $request->merge(['to' => $contact->email]);
        return $this->send($request);
    }
}
