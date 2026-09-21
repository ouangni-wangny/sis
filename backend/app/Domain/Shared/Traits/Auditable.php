<?php

namespace App\Domain\Shared\Traits;

use App\Events\ModelAudited;
use Illuminate\Database\Eloquent\Model;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            event(new ModelAudited(
                $model,
                'created',
                null,
                static::auditSerialize($model->getAttributes()),
            ));
        });

        static::updated(function (Model $model): void {
            $changes = $model->getChanges();
            unset($changes['updated_at']);

            if ($changes === []) {
                return;
            }

            $ancien = [];
            foreach (array_keys($changes) as $key) {
                $ancien[$key] = $model->getOriginal($key);
            }

            event(new ModelAudited(
                $model,
                'updated',
                static::auditSerialize($ancien),
                static::auditSerialize($changes),
            ));
        });

        static::deleted(function (Model $model): void {
            event(new ModelAudited(
                $model,
                'deleted',
                static::auditSerialize($model->getOriginal() ?: $model->getAttributes()),
                null,
            ));
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model): void {
                event(new ModelAudited(
                    $model,
                    'restored',
                    null,
                    static::auditSerialize($model->getAttributes()),
                ));
            });
        }
    }

    /** Libellé lisible de la cible (surchargeable par modèle). */
    public function auditLabel(): ?string
    {
        if (isset($this->matricule, $this->prenom, $this->nom)) {
            return trim("{$this->prenom} {$this->nom} ({$this->matricule})");
        }

        foreach (['reference', 'numero', 'raison_sociale', 'libelle', 'nom', 'email', 'name'] as $field) {
            $value = $this->getAttribute($field);
            if ($value === null || $value === '') {
                continue;
            }

            if (is_object($value) && property_exists($value, 'value')) {
                return (string) $value->value;
            }

            return is_scalar($value) ? (string) $value : null;
        }

        return null;
    }

    /** @param  array<string, mixed>  $data
     *  @return array<string, mixed>
     */
    protected static function auditSerialize(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if ($value instanceof \BackedEnum) {
                $out[$key] = $value->value;
            } elseif ($value instanceof \UnitEnum) {
                $out[$key] = $value->name;
            } elseif ($value instanceof \DateTimeInterface) {
                $out[$key] = $value->format('Y-m-d H:i:s');
            } elseif (is_object($value)) {
                continue;
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
