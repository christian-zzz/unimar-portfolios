<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug'])]
class Category extends Model
{
    use HasUuids;

    public function portfolios(): BelongsToMany
    {
        return $this->belongsToMany(Portfolio::class, 'category_portfolio');
    }
}
