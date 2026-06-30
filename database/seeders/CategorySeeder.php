<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Identidad de marca', 'slug' => 'identidad-de-marca'],
            ['name' => 'Fotografía', 'slug' => 'fotografia'],
            ['name' => 'UI/UX', 'slug' => 'ui-ux'],
            ['name' => 'Ilustración', 'slug' => 'ilustracion'],
            ['name' => 'Arquitectura', 'slug' => 'arquitectura'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(
                ['slug' => $cat['slug']],
                ['name' => $cat['name']]
            );
        }
    }
}
