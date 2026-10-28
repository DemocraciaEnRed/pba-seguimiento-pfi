<?php

namespace App;

use Database\Factories\InitiativeFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

#[UseFactory(InitiativeFactory::class)]
class Initiative extends Model
{
    /** @use HasFactory<InitiativeFactory> */
    use HasFactory;

    public const string DISK = 'initiatives';

    public const string HOME_CACHE_KEY = 'home.initiatives';

    protected $fillable = [
        'title',
        'subtitle',
        'url',
        'featured_on_home',
        'order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'featured_on_home' => 'boolean',
            'order' => 'integer',
        ];
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->orderBy('order');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order')->orderBy('title');
    }

    public function scopeFeaturedOnHome(Builder $query): Builder
    {
        return $query->where('featured_on_home', true);
    }

    public static function flushPublicCache(): void
    {
        Cache::forget(self::HOME_CACHE_KEY);
    }

    public function backgroundUrl(): ?string
    {
        return $this->background_path ? Storage::disk(self::DISK)->url($this->background_path) : null;
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk(self::DISK)->url($this->logo_path) : null;
    }
}
