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
            $failure = $this->providerFailure($e);
            $search->update(['status' => 'failed', 'current_step' => 'failed', 'error_message' => $failure['message']]);
            return response()->json(['search' => $search->fresh(), 'results' => [], 'error_code' => $failure['code'], 'message' => $failure['message'], 'provider_status' => $failure['status']], $failure['http_status']);
        }
    }

    public function chat(Request $request)
    {
        $data = $request->validate(['message' => ['required', 'string', 'min:2', 'max:4000']]);
        $context = $this->crmContext($request);
        try {
            $result = $this->gemini('You are a helpful sales CRM assistant. Answer in Arabic when the user writes Arabic. Use the private CRM context below. Do not use web search in this chat; explain when information is not available in the CRM. Be practical and explain your reasoning. CRM context: ' . json_encode($context, JSON_UNESCAPED_UNICODE) . "\nUser question: " . trim($data['message']), false);
            return response()->json(['answer' => $result['text'], 'sources' => $result['citations']]);
        } catch (\Throwable $e) {
            report($e);
            $failure = $this->providerFailure($e);
            return response()->json(['error_code' => $failure['code'], 'message' => $failure['message'], 'provider_status' => $failure['status']], $failure['http_status']);
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
            $failure = $this->providerFailure($e);
            return response()->json(['error_code' => $failure['code'], 'message' => $failure['message'], 'provider_status' => $failure['status']], $failure['http_status']);
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

    private function providerFailure(\Throwable $e): array
    {
        $message = $e->getMessage();
        $status = $e instanceof \Illuminate\Http\Client\RequestException ? optional($e->response)->status() : null;
        if ($status === 429 || str_contains($message, 'quota')) return ['code' => 'AI_QUOTA_EXCEEDED', 'message' => 'Gemini رفض الطلب لأن الحصة أو معدل الاستخدام انتهى. افحص Google AI Studio/Cloud Quotas.', 'status' => $status, 'http_status' => 429];
        if ($status === 404 || str_contains($message, 'not found') || str_contains($message, '404')) return ['code' => 'AI_MODEL_NOT_AVAILABLE', 'message' => 'موديل Gemini غير متاح لهذا الحساب أو لا يدعم هذا endpoint. الموديل الحالي: ' . config('services.ai.model'), 'status' => $status, 'http_status' => 502];
        if ($status === 401 || $status === 403 || str_contains($message, 'API key')) return ['code' => 'AI_AUTH_FAILED', 'message' => 'مفتاح Gemini غير صحيح أو لا يملك صلاحية استخدام API.', 'status' => $status, 'http_status' => 502];
        if ($e instanceof \GuzzleHttp\Exception\ConnectException || str_contains($message, 'cURL error 28') || str_contains($message, 'timed out')) return ['code' => 'AI_UPSTREAM_TIMEOUT', 'message' => 'السيرفر لم يستطع الوصول إلى Gemini قبل انتهاء المهلة. افحص اتصال الخادم بالإنترنت وDNS وFirewall.', 'status' => null, 'http_status' => 504];
        return ['code' => 'AI_PROVIDER_ERROR', 'message' => app()->isProduction() ? 'حدث خطأ غير متوقع أثناء الاتصال بخدمة Gemini. راجع laravel.log باستخدام error_code: AI_PROVIDER_ERROR.' : $message, 'status' => $status, 'http_status' => 502];
    }
}
