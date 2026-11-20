<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Services;

use Hwkdo\IntranetAppBase\Enums\UserSignatureAction;
use Hwkdo\IntranetAppBase\Models\UserSignature;
use Hwkdo\IntranetAppBase\Models\UserSignatureEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class UserSignatureStore
{
    public function active(Authenticatable $user): ?UserSignature
    {
        return UserSignature::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->whereNull('revoked_at')
            ->latest('registered_at')
            ->first();
    }

    public function register(Authenticatable $for, Authenticatable $by, string $pngBase64, ?string $raw = null): UserSignature
    {
        $binary = $this->decodePng($pngBase64);
        $path = 'user-signatures/'.$for->getAuthIdentifier().'/'.now()->format('YmdHis').'-'.Str::uuid().'.png';
        Storage::disk('local')->put($path, $binary);

        return DB::transaction(function () use ($for, $by, $raw, $path): UserSignature {
            $bisher = $this->active($for);
            if ($bisher instanceof UserSignature) {
                $bisher->forceFill([
                    'revoked_at' => now(),
                    'revoked_by_id' => $by->getAuthIdentifier(),
                ])->save();

                $this->protokoll($for, $by, UserSignatureAction::Revoked, null, null);
            }

            $signature = UserSignature::query()->create([
                'user_id' => $for->getAuthIdentifier(),
                'image_path' => $path,
                'signature_data' => $raw,
                'registered_by_id' => $by->getAuthIdentifier(),
                'registered_at' => now(),
            ]);

            $this->protokoll($for, $by, UserSignatureAction::Registered, null, null);

            return $signature;
        });
    }

    public function revoke(Authenticatable $for, Authenticatable $by): void
    {
        $signature = $this->active($for);
        if (! $signature instanceof UserSignature) {
            return;
        }

        DB::transaction(function () use ($signature, $for, $by): void {
            $signature->forceFill([
                'revoked_at' => now(),
                'revoked_by_id' => $by->getAuthIdentifier(),
            ])->save();

            $this->protokoll($for, $by, UserSignatureAction::Revoked, null, null);
        });
    }

    public function useFor(Authenticatable $signer, string $context, Model $subject): string
    {
        $signature = $this->active($signer);
        if (! $signature instanceof UserSignature || ! Storage::disk('local')->exists($signature->image_path)) {
            throw new RuntimeException('Für diesen Mitarbeiter ist keine registrierte Unterschrift vorhanden.');
        }

        $bytes = Storage::disk('local')->get($signature->image_path);
        if (! is_string($bytes) || $bytes === '') {
            throw new RuntimeException('Die registrierte Unterschrift konnte nicht gelesen werden.');
        }

        $this->protokoll($signer, $signer, UserSignatureAction::Used, $context, $subject);

        return $bytes;
    }

    public function dataUri(UserSignature $signature): ?string
    {
        if (! Storage::disk('local')->exists($signature->image_path)) {
            return null;
        }

        $bytes = Storage::disk('local')->get($signature->image_path);
        if (! is_string($bytes) || $bytes === '') {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($bytes);
    }

    public function decodePng(string $input): string
    {
        $payload = $input;
        if (str_contains($payload, ',')) {
            $payload = (string) substr($payload, (int) strpos($payload, ',') + 1);
        }

        $payload = preg_replace('/\s+/', '', $payload) ?? '';
        $binary = base64_decode($payload, true);

        if ($binary === false || ! str_starts_with($binary, "\x89PNG\r\n\x1a\n")) {
            throw new InvalidArgumentException('Die Unterschrift ist kein gültiges PNG.');
        }

        return $binary;
    }

    private function protokoll(
        Authenticatable $for,
        Authenticatable $by,
        UserSignatureAction $action,
        ?string $context,
        ?Model $subject,
    ): void {
        UserSignatureEvent::query()->create([
            'user_id' => $for->getAuthIdentifier(),
            'actor_id' => $by->getAuthIdentifier(),
            'action' => $action,
            'context' => $context,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'ip' => request()?->ip(),
        ]);
    }
}
