<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\GmailService;
use Illuminate\Http\Request;

final class GoogleMailboxController extends Controller
{
    public function send(Request $request, GmailService $gmail)
    {
        $data = $request->validate(['to' => ['required', 'email'], 'subject' => ['required', 'string', 'max:255'], 'body' => ['required', 'string']]);
        return response()->json($gmail->send($request->user(), $data['to'], $data['subject'], $data['body']));
    }
}
