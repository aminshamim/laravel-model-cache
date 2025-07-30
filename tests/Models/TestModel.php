<?php

declare(strict_types=1);

namespace AminShamim\LaravelModelCache\Tests\Models;

use AminShamim\LaravelModelCache\Models\Traits\ModelCacheable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestModel extends Model
{
    use HasFactory, ModelCacheable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function relatedModels(): HasMany
    {
        return $this->hasMany(TestRelatedModel::class);
    }

    public function shouldCache(): bool
    {
        // Don't cache if not active (for testing custom logic)
        if (! $this->is_active) {
            return false;
        }

        // Call parent implementation from trait
        $primaryValue = $this->getKey();

        if ($primaryValue === null) {
            return false;
        }

        // Additional checks can be added here
        if (method_exists($this, 'trashed') && $this->trashed()) {
            return false;
        }

        return true;
    }

    protected function getCustomCacheableProperties(): array
    {
        return [
            'ttl' => 600, // 10 minutes for testing
            'cache_relationships' => true,
            'override_find_method' => true, // Enable custom query builder
        ];
    }

    protected static function newFactory()
    {
        return \AminShamim\LaravelModelCache\Tests\Factories\TestModelFactory::new();
    }
}
