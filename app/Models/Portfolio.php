<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'title', 'slug', 'draft_content', 'published_content', 'settings', 'is_published', 'lighthouse_scores', 'last_audited_at', 'thumbnail_path'])]
class Portfolio extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'draft_content' => 'array',
            'published_content' => 'array',
            'settings' => 'array',
            'is_published' => 'boolean',
            'lighthouse_scores' => 'array',
            'last_audited_at' => 'datetime',
            'analytics_data' => 'array',
            'last_analytics_updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_portfolio');
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PortfolioRevision::class);
    }
}
