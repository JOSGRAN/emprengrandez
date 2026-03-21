<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ExpenseCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (ExpenseCategory $category) {
            if (blank($category->slug) && filled($category->name)) {
                $slug = Str::slug($category->name);
                if (ExpenseCategory::where('slug', $slug)->exists()) {
                    $slug .= '-'.Str::lower(Str::random(6));
                }
                $category->slug = $slug;
            }
        });
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
