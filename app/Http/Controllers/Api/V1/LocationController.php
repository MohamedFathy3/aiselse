<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use Illuminate\Http\Request;
class LocationController extends Controller {
    public function countries(Request $request) { return Country::query()->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%' . $request->string('search') . '%'))->orderBy('name')->get(['id', 'name', 'iso2', 'iso3']); }
    public function cities(Request $request, Country $country) { return City::where('country_id', $country->id)->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%' . $request->string('search') . '%'))->orderBy('name')->limit(500)->get(['id', 'country_id', 'name', 'state_name']); }
    public function showCountry(Country $country) { return response()->json($country->loadCount('cities')); }
    public function showCity(City $city) { return response()->json($city->load('country:id,name,iso2')); }
}
