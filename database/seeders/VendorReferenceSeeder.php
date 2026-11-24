<?php

namespace Database\Seeders;

use App\Models\County;
use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class VendorReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $file = fopen(database_path('reference/2026_Gaz_counties_national.txt'), 'r');
        fgetcsv($file, separator: '|', escape: '');
        $rows = [];
        while (($row = fgetcsv($file, separator: '|', escape: '')) !== false) {
            if (count($row) >= 5) {
                $rows[] = ['state' => $row[0], 'fips' => $row[1], 'name' => trim($row[4])];
            }
        }
        fclose($file);
        foreach (array_chunk($rows, 500) as $chunk) {
            County::upsert($chunk, ['fips'], ['state', 'name']);
        }
        foreach (['Deed', 'Mortgage', 'Deed of Trust', 'Lien', 'Release', 'Assignment', 'Judgment', 'Plat', 'Tax Record', 'Court Record', 'Other'] as $name) {
            DocumentType::firstOrCreate(['name' => $name]);
        }
    }
}
