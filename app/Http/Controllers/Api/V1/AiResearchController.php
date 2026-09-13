<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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
