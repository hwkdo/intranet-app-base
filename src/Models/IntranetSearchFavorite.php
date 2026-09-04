<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Models;

use Hwkdo\IntranetAppBase\Data\SearchResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntranetSearchFavorite extends Model
{
    protected $table = 'intranet_search_favorites';

    protected $fillable = [
        'user_id',
        'favorite_key',
        'title',
        'url',
        'icon',
        'subtitle',
        'app_identifier',
        'app_name',
        'source_key',
        'download',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'download' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        $userClass = config('auth.providers.users.model');

        return $this->belongsTo($userClass, 'user_id');
    }

    public function toSearchResult(): SearchResult
    {
        return new SearchResult(
            title: $this->title,
            url: $this->url,
            appIdentifier: $this->app_identifier,
            appName: $this->app_name,
            icon: $this->icon,
            favoriteKey: $this->favorite_key,
            subtitle: $this->subtitle,
            sourceKey: $this->source_key,
            download: $this->download,
        );
    }
}
