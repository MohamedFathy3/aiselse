<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class WebResearchController extends Controller
{
    public function scrape(Request $request)
    {
        $data = $request->validate(['url' => ['required', 'url', 'max:2048']]);
        $url = $data['url'];
        abort_unless(in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true), 422, 'Only public HTTP(S) URLs are supported.');
        $host = parse_url($url, PHP_URL_HOST);
        abort_unless($host && !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) && !in_array(strtolower($host), ['localhost', '127.0.0.1']), 422, 'Private and local network URLs are not allowed.');
        if (!$this->allowedByRobots($url)) return response()->json(['message' => 'This website disallows automated crawling in robots.txt.', 'url' => $url], 422);
        $response = Http::timeout(config('services.scraper.timeout', 10))->connectTimeout(5)->withHeaders(['User-Agent' => 'PyramidthResearchBot/1.0 (+public-web-research)'])->get($url);
        abort_unless($response->successful(), 422, 'The public page could not be fetched.');
        $html = $response->body();
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags(preg_replace('/<(script|style|noscript)[^>]*>.*?<\/\1>/is', ' ', $html))));
        $emails = collect(preg_match_all('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $html, $matches) ? $matches[0] : [])->map(fn ($email) => strtolower($email))->unique()->filter(fn ($email) => !str_ends_with($email, '.png'))->values();
        preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $titleMatch);
        return response()->json(['url' => $url, 'title' => isset($titleMatch[1]) ? trim(strip_tags($titleMatch[1])) : null, 'text' => Str::limit($text, 12000, '...'), 'emails' => $emails, 'source' => ['url' => $url, 'fetched_at' => now()->toIso8601String()]]);
    }

    private function allowedByRobots(string $url): bool
    {
        $robotsUrl = rtrim(parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST), '/') . '/robots.txt';
        try {
            $body = Http::timeout(5)->get($robotsUrl)->body();
            if (!$body) return true;
            $rules = preg_split('/\R/', $body);
            $active = false;
            foreach ($rules as $line) {
                $line = trim(preg_replace('/#.*/', '', $line));
                if (!$line) continue;
                [$key, $value] = array_pad(explode(':', $line, 2), 2, '');
                if (strtolower(trim($key)) === 'user-agent') $active = trim($value) === '*' || stripos(trim($value), 'PyramidthResearchBot') !== false;
                if ($active && strtolower(trim($key)) === 'disallow' && trim($value) === '/') return false;
            }
            return true;
        } catch (\Throwable) { return true; }
    }
}
