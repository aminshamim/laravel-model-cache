<?php

declare(strict_types=1);

namespace AminShamim\LaravelModelCache\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestRelatedModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_model_id',
        'title',
        'description',
    ];

    public function testModel(): BelongsTo
    {
        return $this->belongsTo(TestModel::class);
    }
}
