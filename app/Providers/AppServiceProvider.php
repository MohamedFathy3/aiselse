<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Lead;
use App\Policies\ClientPolicy;
use App\Policies\LeadPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Web-search / AI provider abstractions are bound here once implemented, e.g.:
        // $this->app->bind(WebSearchServiceInterface::class, GoogleWebSearchService::class);
        // $this->app->bind(AiServiceInterface::class, GeminiAiService::class);
    }

    public function boot(): void
    {
        \Illuminate\Http\Resources\Json\JsonResource::withoutWrapping();
        Gate::policy(Lead::class, LeadPolicy::class);
        Gate::policy(Client::class, ClientPolicy::class);
    }
}
