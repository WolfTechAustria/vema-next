<?php

namespace App\Models;

use App\Enums\Plan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Neuigkeit zu VEMA (z. B. eine neue Funktion) aus dem Plattform-Admin.
 */
class PlatformNews extends Model
{
    protected $connection = 'landlord';

    protected $table = 'platform_news';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_plan' => Plan::class,
            'published_at' => 'datetime',
            'emailed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PlatformAdmin, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'platform_admin_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /**
     * Veröffentlicht und für das Paket des Vereins gedacht.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, Tenant $tenant): void
    {
        $rank = $tenant->effectivePlan()->rank();
        $plans = array_map(
            fn (Plan $plan) => $plan->value,
            array_filter(Plan::cases(), fn (Plan $plan) => $plan->rank() <= $rank),
        );

        $query->published()->where(fn (Builder $query) => $query
            ->whereNull('min_plan')
            ->orWhereIn('min_plan', $plans));
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->lte(now());
    }

    public function isVisibleTo(Tenant $tenant): bool
    {
        return $this->min_plan === null || $tenant->effectivePlan()->rank() >= $this->min_plan->rank();
    }

    /**
     * Text als HTML (Markdown, eigenes HTML wird nicht übernommen).
     */
    public function bodyHtml(): HtmlString
    {
        return new HtmlString(Str::markdown($this->body, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]));
    }
}
