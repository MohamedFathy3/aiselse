<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Client;
use App\Models\Contact;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadSearch;
use App\Models\LeadSearchResult;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CrmController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $leadQuery = Lead::query();
        $clientQuery = Client::query();
        $shipmentQuery = Shipment::query();
        $followUpQuery = FollowUp::query();
        if ($user->isSales()) {
            $leadQuery->where('assigned_to', $user->id);
            $clientQuery->where('user_id', $user->id);
            $shipmentQuery->where('salesman_id', $user->id);
            $followUpQuery->where('assigned_to', $user->id);
        }
        return response()->json([
            'stats' => [
                'leads' => (clone $leadQuery)->count(),
                'open_leads' => (clone $leadQuery)->whereNotIn('status', ['converted', 'lost', 'not_interested'])->count(),
                'clients' => (clone $clientQuery)->count(),
                'shipments' => (clone $shipmentQuery)->where('status', 'open')->count(),
                'pending_follow_ups' => (clone $followUpQuery)->where('status', 'pending')->whereDate('due_date', '<=', now()->addDays(7))->count(),
            ],
            'pipeline' => (clone $leadQuery)->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status'),
            'recent_leads' => (clone $leadQuery)->latest()->limit(6)->get(['id', 'company_name', 'city', 'status', 'lead_score', 'updated_at']),
            'upcoming_follow_ups' => (clone $followUpQuery)->where('status', 'pending')->whereDate('due_date', '>=', today())->orderBy('due_date')->limit(6)->get(),
        ]);
    }

    public function leads(Request $request)
    {
        $query = Lead::query()->with('assignedTo:id,name')->latest();
        if ($request->user()->isSales()) $query->where('assigned_to', $request->user()->id);
        $query->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
            $term = $request->string('search');
            $q->where('company_name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")->orWhere('city', 'like', "%{$term}%");
        }));
        $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')));
        return $query->paginate(min($request->integer('per_page', 20), 100));
    }

    public function storeLead(Request $request)
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'], 'website' => ['nullable', 'url', 'max:255'],
            'country' => ['nullable', 'string', 'max:100'], 'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'], 'industry' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string'], 'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_title' => ['nullable', 'string', 'max:150'], 'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'], 'linkedin_url' => ['nullable', 'url', 'max:255'],
            'source' => ['nullable', 'string', 'max:100'], 'source_url' => ['nullable', 'url', 'max:255'],
            'lead_score' => ['nullable', 'integer', 'min:0', 'max:100'], 'shipping_relevance' => ['nullable', 'string', 'max:100'],
            'potential_need' => ['nullable', 'string'], 'status' => ['nullable', 'in:new,contacted,interested,follow_up,qualified,converted,not_interested,lost'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);
        $data['assigned_to'] = $request->user()->isAdmin() && !empty($data['assigned_to']) ? $data['assigned_to'] : $request->user()->id;
        $data['normalized_company_name'] = Str::lower(trim($data['company_name']));
        $data['company_domain'] = $this->domain($data['website'] ?? null);
        $duplicate = Lead::where('normalized_company_name', $data['normalized_company_name'])->whereNotIn('status', ['lost', 'not_interested'])->first();
        if ($duplicate) return response()->json(['message' => 'This company may already exist.', 'duplicate' => $duplicate], 409);
        return response()->json(Lead::create($data)->fresh('assignedTo:id,name'), 201);
    }

    public function updateLead(Request $request, Lead $lead)
    {
        $this->authorize('update', $lead);
        $data = $request->validate(['company_name' => ['sometimes', 'string', 'max:255'], 'website' => ['nullable', 'url'], 'country' => ['nullable', 'string'], 'city' => ['nullable', 'string'], 'industry' => ['nullable', 'string'], 'description' => ['nullable', 'string'], 'email' => ['nullable', 'email'], 'phone' => ['nullable', 'string'], 'status' => ['sometimes', 'in:new,contacted,interested,follow_up,qualified,converted,not_interested,lost'], 'lead_score' => ['nullable', 'integer', 'min:0', 'max:100'], 'potential_need' => ['nullable', 'string']]);
        if (isset($data['company_name'])) $data['normalized_company_name'] = Str::lower(trim($data['company_name']));
        $lead->update($data);
        return $lead->fresh('assignedTo:id,name');
    }

    public function destroyLead(Request $request, Lead $lead)
    {
        $this->authorize('delete', $lead); $lead->delete(); return response()->noContent();
    }

    public function convertLead(Request $request, Lead $lead)
    {
        $this->authorize('convert', $lead);
        $client = DB::transaction(function () use ($request, $lead) {
            $client = Client::create(['company_name' => $lead->company_name, 'website' => $lead->website, 'country' => $lead->country, 'city' => $lead->city, 'address' => $lead->address, 'industry' => $lead->industry, 'description' => $lead->description, 'user_id' => $lead->assigned_to, 'source_lead_id' => $lead->id, 'company_domain' => $lead->company_domain, 'normalized_company_name' => $lead->normalized_company_name]);
            $lead->update(['status' => LeadStatus::Converted, 'client_id' => $client->id, 'converted_at' => now(), 'converted_by' => $request->user()->id]);
            if ($lead->contact_name) Contact::create(['contactable_type' => 'client', 'contactable_id' => $client->id, 'name' => $lead->contact_name, 'job_title' => $lead->contact_title, 'email' => $lead->email, 'phone' => $lead->phone, 'is_primary' => true]);
            return $client;
        });
        return response()->json(['message' => 'Lead converted successfully.', 'client' => $client], 201);
    }

    public function clients(Request $request)
    {
        $query = Client::query()->latest();
        if ($request->user()->isSales()) $query->where('user_id', $request->user()->id);
        return $query->when($request->filled('search'), fn ($q) => $q->where('company_name', 'like', '%' . $request->string('search') . '%'))->paginate(min($request->integer('per_page', 20), 100));
    }

    public function storeClient(Request $request)
    {
        $data = $request->validate(['company_name' => ['required', 'string', 'max:255'], 'website' => ['nullable', 'url'], 'country' => ['nullable', 'string'], 'city' => ['nullable', 'string'], 'address' => ['nullable', 'string'], 'industry' => ['nullable', 'string'], 'description' => ['nullable', 'string']]);
        $data['user_id'] = $request->user()->id; $data['normalized_company_name'] = Str::lower(trim($data['company_name'])); $data['company_domain'] = $this->domain($data['website'] ?? null);
        return response()->json(Client::create($data), 201);
    }

    public function updateClient(Request $request, Client $client)
    {
        $this->authorize('update', $client);
        $data = $request->validate(['company_name' => ['sometimes', 'string', 'max:255'], 'website' => ['nullable', 'url'], 'country' => ['nullable', 'string'], 'city' => ['nullable', 'string'], 'address' => ['nullable', 'string'], 'industry' => ['nullable', 'string'], 'description' => ['nullable', 'string'], 'status' => ['nullable', 'in:active,inactive,on_hold']]);
        if (isset($data['company_name'])) $data['normalized_company_name'] = Str::lower(trim($data['company_name']));
        if (array_key_exists('website', $data)) $data['company_domain'] = $this->domain($data['website']);
        $client->update($data); return response()->json($client);
    }

    public function destroyClient(Request $request, Client $client)
    {
        $this->authorize('update', $client); $client->delete(); return response()->noContent();
    }

    public function contacts(Request $request)
    {
        return Contact::query()->when($request->user()->isSales(), function ($q) use ($request) { $q->where(function ($q) use ($request) { $q->where('contactable_type', 'client')->whereIn('contactable_id', Client::where('user_id', $request->user()->id)->select('id'))->orWhere('contactable_type', 'lead')->whereIn('contactable_id', Lead::where('assigned_to', $request->user()->id)->select('id')); }); })->latest()->paginate(50);
    }

    public function storeContact(Request $request)
    {
        $data = $request->validate(['contactable_type' => ['required', 'in:lead,client'], 'contactable_id' => ['required', 'integer'], 'name' => ['required', 'string', 'max:255'], 'job_title' => ['nullable', 'string'], 'email' => ['nullable', 'email'], 'phone' => ['nullable', 'string'], 'mobile' => ['nullable', 'string'], 'whatsapp' => ['nullable', 'string'], 'notes' => ['nullable', 'string'], 'is_primary' => ['boolean']]);
        return response()->json(Contact::create($data), 201);
    }

    public function agents(Request $request) { $query = Agent::query()->latest(); if ($request->user()->isSales()) $query->where('user_id', $request->user()->id); return $query->paginate(50); }
    public function storeAgent(Request $request) { $data = $request->validate(['company_name' => ['required', 'string', 'max:255'], 'country' => ['nullable', 'string'], 'city' => ['nullable', 'string'], 'address' => ['nullable', 'string'], 'website' => ['nullable', 'url'], 'contact_person' => ['nullable', 'string'], 'email' => ['nullable', 'email'], 'phone' => ['nullable', 'string'], 'services' => ['nullable', 'array'], 'notes' => ['nullable', 'string']]); $data['user_id'] = $request->user()->id; return response()->json(Agent::create($data), 201); }
    public function updateAgent(Request $request, Agent $agent) { $this->canEdit($request, $agent->user_id); $data = $request->validate(['company_name' => ['sometimes', 'string', 'max:255'], 'country' => ['nullable', 'string'], 'city' => ['nullable', 'string'], 'address' => ['nullable', 'string'], 'website' => ['nullable', 'url'], 'contact_person' => ['nullable', 'string'], 'email' => ['nullable', 'email'], 'phone' => ['nullable', 'string'], 'services' => ['nullable', 'array'], 'notes' => ['nullable', 'string'], 'status' => ['nullable', 'in:active,inactive']]); $agent->update($data); return response()->json($agent); }
    public function destroyAgent(Request $request, Agent $agent) { $this->canEdit($request, $agent->user_id); $agent->delete(); return response()->noContent(); }
    public function shipments(Request $request) { $q = Shipment::query()->with(['client:id,company_name', 'agent:id,company_name'])->latest(); if ($request->user()->isSales()) $q->where('salesman_id', $request->user()->id); return $q->paginate(50); }
    public function storeShipment(Request $request) { $data = $request->validate(['client_id' => ['required', 'exists:clients,id'], 'agent_id' => ['nullable', 'exists:agents,id'], 'direction' => ['required', 'in:import,export,domestic,cross_booking'], 'transport_type' => ['required', 'in:ocean,air,inland,customs_clearance'], 'shipment_type' => ['required', 'in:fcl,lcl,bulk,flexi,tank'], 'open_date' => ['required', 'date'], 'branch' => ['nullable', 'string'], 'notes' => ['nullable', 'string'], 'warehousing' => ['boolean'], 'dangerous_goods' => ['boolean'], 'sales_lead_flag' => ['boolean']]); $this->canUseClient($request, (int) $data['client_id']); if (!empty($data['agent_id'])) $this->canUseAgent($request, (int) $data['agent_id']); $data['salesman_id'] = $request->user()->id; $data['reference_number'] = 'PYR-' . now()->format('Y') . '-' . strtoupper(Str::random(6)); return response()->json(Shipment::create($data)->load(['client:id,company_name', 'agent:id,company_name']), 201); }
    public function updateShipment(Request $request, Shipment $shipment) { $this->canEdit($request, $shipment->salesman_id); $data = $request->validate(['client_id' => ['sometimes', 'exists:clients,id'], 'agent_id' => ['nullable', 'exists:agents,id'], 'direction' => ['sometimes', 'in:import,export,domestic,cross_booking'], 'transport_type' => ['sometimes', 'in:ocean,air,inland,customs_clearance'], 'shipment_type' => ['sometimes', 'in:fcl,lcl,bulk,flexi,tank'], 'open_date' => ['sometimes', 'date'], 'status' => ['nullable', 'in:open,handed_over,cancelled'], 'branch' => ['nullable', 'string'], 'notes' => ['nullable', 'string'], 'warehousing' => ['boolean'], 'dangerous_goods' => ['boolean'], 'sales_lead_flag' => ['boolean']]); if (isset($data['client_id'])) $this->canUseClient($request, (int) $data['client_id']); if (!empty($data['agent_id'])) $this->canUseAgent($request, (int) $data['agent_id']); $shipment->update($data); return response()->json($shipment->load(['client:id,company_name', 'agent:id,company_name'])); }
    public function destroyShipment(Request $request, Shipment $shipment) { $this->canEdit($request, $shipment->salesman_id); $shipment->delete(); return response()->noContent(); }
    public function followUps(Request $request) { $q = FollowUp::query()->with(['assignedTo:id,name', 'contact:id,name,email,contactable_type,contactable_id'])->latest('due_date')->latest('due_time'); if ($request->user()->isSales()) $q->where('assigned_to', $request->user()->id); return $q->paginate(min($request->integer('per_page', 50), 100)); }
    public function storeFollowUp(Request $request) {
        $data = $request->validate(['subject_type' => ['required', 'in:lead,client'], 'subject_id' => ['required', 'integer'], 'contact_id' => ['nullable', 'exists:contacts,id'], 'type' => ['required', 'in:call,email,meeting,whatsapp,general'], 'due_date' => ['required', 'date'], 'due_time' => ['nullable', 'date_format:H:i'], 'timezone' => ['nullable', 'timezone'], 'note' => ['nullable', 'string']]);
        $subject = $data['subject_type'] === 'lead' ? Lead::findOrFail($data['subject_id']) : Client::findOrFail($data['subject_id']);
        $this->canUseSubject($request, $data['subject_type'], $subject);
        if (!empty($data['contact_id'])) abort_unless(Contact::whereKey($data['contact_id'])->where('contactable_type', $data['subject_type'])->where('contactable_id', $data['subject_id'])->exists(), 422, 'The contact does not belong to the selected record.');
        if ($data['type'] === 'email') abort_unless(!empty($data['contact_id']) && Contact::whereKey($data['contact_id'])->whereNotNull('email')->where('email', '!=', '')->exists(), 422, 'Email follow-ups require a contact with an email address.');
        $data['assigned_to'] = $request->user()->id; $data['timezone'] ??= $request->user()->timezone();
        return response()->json(FollowUp::create($data)->load(['assignedTo:id,name', 'contact:id,name,email']), 201);
    }
    public function updateFollowUp(Request $request, FollowUp $followUp) { $this->canEdit($request, $followUp->assigned_to); $data = $request->validate(['contact_id' => ['nullable', 'exists:contacts,id'], 'type' => ['sometimes', 'in:call,email,meeting,whatsapp,general'], 'due_date' => ['sometimes', 'date'], 'due_time' => ['nullable', 'date_format:H:i'], 'timezone' => ['nullable', 'timezone'], 'note' => ['nullable', 'string'], 'status' => ['nullable', 'in:pending,completed,cancelled,overdue']]); $contactId = array_key_exists('contact_id', $data) ? $data['contact_id'] : $followUp->contact_id; $type = $data['type'] ?? $followUp->type->value; if ($contactId && !Contact::whereKey($contactId)->where('contactable_type', $followUp->subject_type)->where('contactable_id', $followUp->subject_id)->exists()) abort(422, 'The contact does not belong to this follow-up subject.'); if ($type === 'email') abort_unless($contactId && Contact::whereKey($contactId)->whereNotNull('email')->where('email', '!=', '')->exists(), 422, 'Email follow-ups require a contact with an email address.'); if (($data['status'] ?? null) === 'completed') $data['completed_at'] = now(); if (($data['status'] ?? null) === 'pending') $data['completed_at'] = null; $followUp->update($data); return response()->json($followUp->fresh(['assignedTo:id,name', 'contact:id,name,email'])); }
    public function destroyFollowUp(Request $request, FollowUp $followUp) { $this->canEdit($request, $followUp->assigned_to); $followUp->delete(); return response()->noContent(); }

    private function canEdit(Request $request, ?int $ownerId): void { abort_unless($request->user()->isAdmin() || $ownerId === $request->user()->id, 403, 'You cannot edit this record.'); }
    private function canUseClient(Request $request, int $clientId): void { abort_unless($request->user()->isAdmin() || Client::whereKey($clientId)->where('user_id', $request->user()->id)->exists(), 403, 'You cannot use this client.'); }
    private function canUseSubject(Request $request, string $type, Lead|Client $subject): void { abort_unless($request->user()->isAdmin() || ($type === 'lead' ? (int) $subject->assigned_to === (int) $request->user()->id : (int) $subject->user_id === (int) $request->user()->id), 403, 'You cannot use this record.'); }
    private function canUseAgent(Request $request, int $agentId): void { abort_unless($request->user()->isAdmin() || Agent::whereKey($agentId)->where('user_id', $request->user()->id)->exists(), 403, 'You cannot use this agent.'); }

    public function search(Request $request)
    {
        $data = $request->validate(['query' => ['required', 'string', 'min:5', 'max:1000'], 'filters' => ['nullable', 'array']]);
        $search = LeadSearch::create(['user_id' => $request->user()->id, 'query' => $data['query'], 'filters' => $data['filters'] ?? [], 'status' => 'failed', 'current_step' => 'failed', 'error_message' => 'Configure WEB_SEARCH_API_KEY and AI_API_KEY to enable live research.']);
        return response()->json(['message' => 'Search queued but provider configuration is required.', 'search' => $search, 'results' => LeadSearchResult::where('lead_search_id', $search->id)->get()], 202);
    }

    private function domain(?string $url): ?string { if (!$url) return null; return strtolower(preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST))); }
}
