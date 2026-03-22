<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (Category $category) {
            if (blank($category->slug) && filled($category->name)) {
                $slug = Str::slug($category->name);
                if (Category::where('slug', $slug)->exists()) {
                    $slug .= '-'.Str::lower(Str::random(6));
                }
                $category->slug = $slug;
            }
        });
    }

    public function getDepthAttribute(): int
    {
        $depth = 0;
        $current = $this->parent;

        while ($current && $depth < 20) {
            $depth++;
            $current = $current->parent;
        }

        return $depth;
    }

    public static function treeOptions(?int $excludeId = null, int $maxDepth = 2): array
    {
        $categories = self::query()
            ->select(['id', 'name', 'parent_id'])
            ->orderBy('name')
            ->get();

        $childrenByParent = [];
        foreach ($categories as $category) {
            if ($excludeId && (int) $category->id === (int) $excludeId) {
                continue;
            }

            $childrenByParent[$category->parent_id ?? 0][] = $category;
        }

        $options = [];

        $walk = function (int $parentId, int $depth) use (&$walk, &$options, $childrenByParent, $maxDepth): void {
            if (! isset($childrenByParent[$parentId])) {
                return;
            }

            foreach ($childrenByParent[$parentId] as $node) {
                $options[$node->id] = str_repeat('— ', $depth).$node->name;

                if ($depth + 1 < $maxDepth) {
                    $walk((int) $node->id, $depth + 1);
                }
            }
        };

        $walk(0, 0);

        return $options;
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
