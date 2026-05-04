<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class DocumentTypesSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['code' => 'NID', 'name' => 'National Identity'],
            ['code' => 'BIRTH', 'name' => 'Birth Certificate'],
            ['code' => 'GCE_AL', 'name' => 'GCE A-Level / Baccalauréat'],
            ['code' => 'HND', 'name' => 'HND Diploma'],
            ['code' => 'BACH', 'name' => 'Bachelors Degree'],
            ['code' => 'MAST', 'name' => 'Masters Degree'],
        ];

        foreach ($defaults as $type) {
            DocumentType::query()->firstOrCreate(['code' => $type['code']], $type);
        }
    }
}
