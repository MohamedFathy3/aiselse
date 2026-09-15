<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\AiConversation;
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
        $data = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:4000'],
            'conversation_id' => ['nullable', 'integer', 'exists:ai_conversations,id'],
            'history' => ['nullable', 'array', 'max:20'],
            'history.*.role' => ['required', 'in:user,assistant'],
            'history.*.text' => ['required', 'string', 'max:12000'],
            'history.*.sources' => ['nullable', 'array', 'max:10'],
            'history.*.sources.*.title' => ['nullable', 'string', 'max:500'],
            'history.*.sources.*.url' => ['nullable', 'url', 'max:2000'],
        ]);
        $conversation = !empty($data['conversation_id'])
            ? AiConversation::whereKey($data['conversation_id'])->where('user_id', $request->user()->id)->firstOrFail()
            : AiConversation::create(['user_id' => $request->user()->id, 'title' => mb_substr(trim($data['message']), 0, 80), 'last_message_at' => now()]);
        $conversation->messages()->create(['role' => 'user', 'content' => trim($data['message'])]);
        $conversation->forceFill(['last_message_at' => now()])->save();
        $context = $this->crmContext($request);
        $web = $this->webSearch($data['message']);
        $directPages = $this->directPages($data['message']);
        $web['results'] = array_merge($directPages, $web['results']);
        $web['sources'] = collect($web['results'])->map(fn ($item) => ['title' => $item['title'], 'url' => $item['url']])->unique('url')->values()->all();
        try {
            $history = collect($data['history'] ?? [])->take(-20)->map(fn (array $item) => [
                'role' => $item['role'],
                'text' => $item['text'],
                'sources' => $item['sources'] ?? [],
            ])->values()->all();
            $prompt = 'You are a grounded CRM research assistant. Answer in the same language as the user. Maintain the conversation context below and resolve follow-up questions using it. If the user asks where a previous answer came from, inspect the previous assistant message and its attached sources; never claim a source, CRM record, website, or verification that is not present in the context. If the source is missing or uncertain, say clearly that you cannot verify it and ask for the URL or explain what was actually searched. Never invent company names, page contents, memberships, certifications, or citations. Use only evidence from DIRECT PAGE CONTENT, WEB RESULTS, CRM context, and conversation history. Treat direct page content as primary evidence. Clearly separate verified facts from inference. When citing, use only URLs present in the supplied sources. Be practical and concise.' . "\nCONVERSATION HISTORY: " . json_encode($history, JSON_UNESCAPED_UNICODE) . "\nCRM context: " . json_encode($context, JSON_UNESCAPED_UNICODE) . "\nDIRECT PAGE CONTENT: " . json_encode($directPages, JSON_UNESCAPED_UNICODE) . "\nWEB RESULTS: " . json_encode($web['results'], JSON_UNESCAPED_UNICODE) . "\nCurrent user question: " . trim($data['message']);
            $result = $this->gemini($prompt, false);
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $result['text'],
                'sources' => $web['sources'],
                'metadata' => ['provider' => config('services.ai.provider'), 'model' => config('services.ai.model')],
            ]);
            $conversation->forceFill(['last_message_at' => now()])->save();
            return response()->json(['conversation_id' => $conversation->id, 'answer' => $result['text'], 'sources' => $web['sources']]);
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

    public function emailDraft(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'min:10', 'max:20000'],
            'tone' => ['nullable', 'string', 'in:professional,friendly,concise,persuasive'],
            'goal' => ['nullable', 'string', 'max:500'],
        ]);
        try {
            $result = $this->gemini('Write only a ready-to-review email reply in the same language as the email below. Do not add a subject, analysis, greeting explanation, markdown, or labels. Be accurate and never invent facts. Tone: ' . ($data['tone'] ?? 'professional') . '. Goal: ' . ($data['goal'] ?? 'answer helpfully and move the conversation forward') . "\nEMAIL:\n" . $data['email'], false);
            return response()->json(['draft' => trim($result['text']), 'sources' => []]);
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

    private function webSearch(string $query): array
    {
        $key = config('services.search.key');
        $engine = config('services.search.engine_id');
        if (!$key || !$engine) return ['results' => [], 'sources' => []];
        try {
            $items = Http::timeout(10)->connectTimeout(5)->get('https://www.googleapis.com/customsearch/v1', ['key' => $key, 'cx' => $engine, 'q' => trim($query), 'num' => 8])->throw()->json('items', []);
            $results = collect($items)->map(fn ($item) => ['title' => $item['title'] ?? '', 'url' => $item['link'] ?? '', 'snippet' => $item['snippet'] ?? ''])->filter(fn ($item) => filled($item['url']))->values()->all();
            return ['results' => $results, 'sources' => collect($results)->map(fn ($item) => ['title' => $item['title'], 'url' => $item['url']])->all()];
        } catch (\Throwable $e) {
            report($e);
            return ['results' => [], 'sources' => []];
        }
    }

    private function researchPrompt(string $query): string
    {
        return 'Search the public web for real companies matching this request. Answer in Arabic if the request is Arabic. Return a useful list with company name, country/city, software or business focus, why it may need freight forwarding, and a source URL for every company. Clearly separate verified facts from inference. Request: ' . $query;
    }

    private function directPages(string $message): array
    {
        preg_match_all('/https?:\/\/[^\s<>{}\[\]"\']+/i', $message, $matches);
        return collect($matches[0] ?? [])->map(function (string $url) {
            $url = rtrim($url, '.,!?،؛)');
            $host = parse_url($url, PHP_URL_HOST);
            if (!$host || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true) || in_array(strtolower($host), ['localhost', '127.0.0.1'], true)) return null;
            try {
                $response = Http::timeout(config('services.scraper.timeout', 10))->connectTimeout(5)->withHeaders(['User-Agent' => 'PyramidthResearchBot/1.0 (+public-web-research)'])->get($url);
                if (!$response->successful()) return ['title' => 'Page unavailable', 'url' => $url, 'snippet' => 'The page returned HTTP ' . $response->status() . '.'];
                $html = preg_replace('/<(script|style|noscript)[^>]*>.*?<\/\1>/is', ' ', $response->body());
                preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $title);
                return ['title' => trim(strip_tags($title[1] ?? $host)), 'url' => $url, 'snippet' => \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags($html))), 10000, '...')];
            } catch (\Throwable $e) { report($e); return ['title' => 'Page fetch failed', 'url' => $url, 'snippet' => 'The page could not be fetched safely from the server.']; }
        })->filter()->values()->all();
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
