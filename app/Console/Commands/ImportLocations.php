<?php
namespace App\Console\Commands;
use App\Models\City;
use App\Models\Country;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
class ImportLocations extends Command {
    protected $signature = 'locations:import {--refresh : Replace existing location rows}';
    protected $description = 'Import countries and cities from the Countries States Cities Database';
    public function handle(): int {
        // The upstream city export is large; importing it requires more than Laravel's common 128M CLI limit.
        if (function_exists('ini_set')) ini_set('memory_limit', '1024M');
        $base = 'https://github.com/dr5hn/countries-states-cities-database/releases/latest/download/';
        $dir = storage_path('app/location-import'); if (!is_dir($dir)) mkdir($dir, 0755, true);
        $countryFile = $dir . '/countries.json'; $cityFile = $dir . '/cities.json.gz';
        $this->info('Downloading country and city data...');
        Http::timeout(300)->sink($countryFile)->get('https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/master/json/countries.json')->throw();
        Http::timeout(900)->sink($cityFile)->get($base . 'json-cities.json.gz')->throw();
        if ($this->option('refresh')) { City::query()->delete(); Country::query()->delete(); }
        $countries = json_decode(file_get_contents($countryFile), true, 512, JSON_THROW_ON_ERROR);
        $countryMap = [];
        DB::transaction(function () use ($countries, &$countryMap) { foreach ($countries as $item) { $country = Country::updateOrCreate(['source_id' => $item['id']], ['name' => $item['name'], 'iso2' => $item['iso2'], 'iso3' => $item['iso3'] ?? null, 'phonecode' => $item['phonecode'] ?? null]); $countryMap[$item['id']] = $country->id; } });
        unset($countries);
        $cities = json_decode(gzdecode(file_get_contents($cityFile)), true, 512, JSON_THROW_ON_ERROR);
        $count = 0; foreach (array_chunk($cities, 1000) as $chunk) { $rows = []; foreach ($chunk as $item) { if (!isset($countryMap[$item['country_id']])) continue; $rows[] = ['source_id' => $item['id'], 'country_id' => $countryMap[$item['country_id']], 'name' => $item['name'], 'state_name' => $item['state_name'] ?? null, 'latitude' => $item['latitude'] ?? null, 'longitude' => $item['longitude'] ?? null, 'created_at' => now(), 'updated_at' => now()]; } City::upsert($rows, ['source_id'], ['country_id', 'name', 'state_name', 'latitude', 'longitude', 'updated_at']); $count += count($rows); if ($count % 10000 === 0) $this->info("Imported {$count} cities..."); }
        $this->info("Imported " . count($countryMap) . " countries and {$count} cities."); return self::SUCCESS;
    }
}
