<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadSearch;
use App\Models\LeadSearchResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

final class AiResearchController extends Controller
{
    public function search(Request $request)
    {
        $data = $request->validate(['query' => ['required', 'string', 'min:3', 'max:1000'], 'filters' => ['nullable', 'array']]);
        $search = LeadSearch::create(['user_id' => $request->user()->id, 'query' => trim($data['query']), 'filters' => $data['filters'] ?? [], 'status' => 'processing', 'current_step' => 'searching', 'started_at' => now()]);
        try {
            $result = $this->gemini($this->researchPrompt($data['query']), true);
            $text = $result['text'];
            $citations = $result['citations'];
            $created = collect($citations)->take(10)->map(fn ($citation) => LeadSearchResult::create([
                'lead_search_id' => $search->id,
                'company_name' => $citation['title'] ?: 'Web result',
                'website' => $citation['url'],
                'description' => $text,
                'source_url' => $citation['url'],
                'source_title' => $citation['title'],
                'source_snippet' => $text,
                'confidence_score' => 75,
                'lead_score' => 60,
                'review_status' => 'pending',
            ]));
            $search->update(['status' => 'completed', 'current_step' => 'completed', 'results_count' => $created->count(), 'completed_at' => now(), 'error_message' => null]);
            return response()->json(['search' => $search->fresh(), 'answer' => $text, 'results' => $created->values(), 'sources' => $citations], 201);
        } catch (\Throwable $e) {
            report($e);
            $message = $this->providerMessage($e);
            $search->update(['status' => 'failed', 'current_step' => 'failed', 'error_message' => $message]);
            return response()->json(['search' => $search->fresh(), 'results' => [], 'message' => $message], 502);
        }
    }

    public function chat(Request $request)
    {
        $data = $request->validate(['message' => ['required', 'string', 'min:2', 'max:4000']]);
        $context = $this->crmContext($request);
        try {
            $result = $this->gemini('You are a helpful sales CRM assistant. Answer in Arabic when the user writes Arabic. Use the private CRM context below, but if the user asks about companies, people, markets or facts outside the CRM, use Google Search and cite sources. Be practical and explain your reasoning. CRM context: ' . json_encode($context, JSON_UNESCAPED_UNICODE) . "\nUser question: " . trim($data['message']), true);
            return response()->json(['answer' => $result['text'], 'sources' => $result['citations']]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => $this->providerMessage($e)], 502);
        }
    }

    public function emailCoach(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'string', 'min:10', 'max:20000'], 'goal' => ['nullable', 'string', 'max:500'], 'tone' => ['nullable', 'string', 'in:professional,friendly,concise,persuasive']]);
        try {
            $result = $this->gemini('You are an expert B2B sales email coach. Analyze the email below and return Arabic headings: ملخص, نقاط القوة, نقاط الضعف, المخاطر أو الاعتراضات, الرد المقترح in the same language as the email, and next steps. Make the reply specific, respectful, and ready to send. Never invent facts. Goal: ' . ($data['goal'] ?? 'advance the conversation') . '. Tone: ' . ($data['tone'] ?? 'professional') . "\nEMAIL:\n" . $data['email'], false);
            return response()->json(['analysis' => $result['text'], 'sources' => []]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => $this->providerMessage($e)], 502);
        }
    }

    private function gemini(string $prompt, bool $search): array
    {
        $key = config('services.ai.key');
        abort_unless($key, 503, 'AI is not configured. Add AI_API_KEY on the server.');
        $model = config('services.ai.model', 'gemini-3.6-flash');
        if (in_array($model, ['gemini-1.5-pro', 'gemini-1.5-flash', 'gemini-2.5-flash'], true)) {
            $model = 'gemini-3.6-flash';
        }
        $payload = ['contents' => [['parts' => [['text' => $prompt]]]]];
        if ($search) $payload['tools'] = [['google_search' => new \stdClass()]];
        $response = Http::timeout(25)->connectTimeout(8)->withHeaders(['x-goog-api-key' => $key, 'Content-Type' => 'application/json'])->post('https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent', $payload)->throw()->json();
        $candidate = $response['candidates'][0] ?? [];
        $text = collect($candidate['content']['parts'] ?? [])->pluck('text')->filter()->implode("\\n");
        $citations = collect($candidate['groundingMetadata']['groundingChunks'] ?? [])->map(function ($chunk) {
            $web = $chunk['web'] ?? [];
            return ['url' => $web['uri'] ?? null, 'title' => $web['title'] ?? ($web['uri'] ?? '')];
        })->filter(fn ($item) => filled($item['url']))->unique('url')->values()->all();
        return ['text' => $text ?: 'لم يرجع مزود الذكاء الاصطناعي نصًا.', 'citations' => $citations];
    }

    private function crmContext(Request $request): array
    {
        return ['leads' => Lead::where('assigned_to', $request->user()->id)->latest()->limit(30)->get(['company_name', 'status', 'city', 'industry', 'email']), 'clients' => Client::where('user_id', $request->user()->id)->latest()->limit(30)->get(['company_name', 'country', 'city', 'industry'])];
    }

    private function researchPrompt(string $query): string
    {
        return 'Search the public web for real companies matching this request. Answer in Arabic if the request is Arabic. Return a useful list with company name, country/city, software or business focus, why it may need freight forwarding, and a source URL for every company. Clearly separate verified facts from inference. Request: ' . $query;
    }

    private function providerMessage(\Throwable $e): string
    {
        $message = $e->getMessage();
        if (str_contains($message, 'not found') || str_contains($message, '404')) return 'موديل Gemini الموجود في .env غير متاح لهذا الحساب. استخدم AI_MODEL=gemini-3.6-flash ثم نفّذ php artisan optimize:clear وphp artisan config:cache.';
        if (str_contains($message, 'API key') || str_contains($message, '401') || str_contains($message, '403')) return 'مفتاح AI_API_KEY غير صحيح أو لا يملك صلاحية Gemini API.';
        return app()->isProduction() ? 'تعذر الاتصال بخدمة الذكاء الاصطناعي. راجع إعدادات AI_API_KEY وAI_MODEL.' : $message;
    }
}
