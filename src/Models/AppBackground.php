<?php

namespace Hwkdo\IntranetAppBase\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class AppBackground extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'intranet_app_backgrounds';

    protected $fillable = ['app_identifier'];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('background')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
    }

    public static function forApp(string $appIdentifier): self
    {
        return self::firstOrCreate(['app_identifier' => $appIdentifier]);
    }

    public static function getCustomBackgroundUrl(string $appIdentifier): ?string
    {
        $record = self::where('app_identifier', $appIdentifier)->first();

        if ($record && $record->hasMedia('background')) {
            return $record->getFirstMediaUrl('background');
        }

        return null;
    }

    public function hasBackground(): bool
    {
        return $this->hasMedia('background');
    }

    public function currentBackgroundUrl(): ?string
    {
        return $this->hasBackground() ? $this->getFirstMediaUrl('background') : null;
    }
}
