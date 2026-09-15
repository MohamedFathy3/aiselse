<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use Illuminate\Http\Request;

final class AiConversationController extends Controller
{
    public function index(Request $request)
    {
        return AiConversation::where('user_id', $request->user()->id)
            ->withCount('messages')
            ->latest('last_message_at')
            ->latest()
            ->paginate(50);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['title' => ['nullable', 'string', 'max:255']]);
        return response()->json(AiConversation::create([
            'user_id' => $request->user()->id,
            'title' => $data['title'] ?? 'New conversation',
            'last_message_at' => now(),
        ]), 201);
    }

    public function show(Request $request, AiConversation $conversation)
    {
        abort_unless($conversation->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        return response()->json($conversation->load('messages'));
    }

    public function destroy(Request $request, AiConversation $conversation)
    {
        abort_unless($conversation->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        $conversation->delete();
        return response()->noContent();
    }
}
