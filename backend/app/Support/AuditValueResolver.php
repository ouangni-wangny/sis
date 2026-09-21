<?php

namespace App\Support;

use App\Models\Abonnement;
use App\Models\Agent;
use App\Models\Client;
use App\Models\Contrat;
use App\Models\Facture;
use App\Models\Grade;
use App\Models\Offre;
use App\Models\Poste;
use App\Models\Site;
use App\Models\User;
use App\Models\Ville;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class AuditValueResolver
{
    /** @var array<string, class-string<Model>> */
    private const RELATIONS = [
        'grade_id' => Grade::class,
        'ville_id' => Ville::class,
        'agent_id' => Agent::class,
        'client_id' => Client::class,
        'site_id' => Site::class,
        'zone_id' => Zone::class,
        'poste_id' => Poste::class,
        'poste_siege_id' => Poste::class,
        'offre_id' => Offre::class,
        'user_id' => User::class,
        'contrat_id' => Contrat::class,
        'contrat_parent_id' => Contrat::class,
        'abonnement_id' => Abonnement::class,
        'facture_id' => Facture::class,
    ];

    /** @var array<string, string|null> */
    private static array $cache = [];

    public static function label(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'vide';
        }

        if (is_bool($value)) {
            return $value ? 'oui' : 'non';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '[]';
        }

        $str = (string) $value;

        if (isset(AuditLabels::VALUES[$str])) {
            return AuditLabels::VALUES[$str];
        }

        if (isset(self::RELATIONS[$field]) || str_ends_with($field, '_id')) {
            $resolved = self::resolveRelation($field, $str);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        // UUID brut sans résolution connue → ne pas afficher l'ID technique seul
        if (self::looksLikeUuid($str)) {
            return 'référence introuvable';
        }

        return $str;
    }

    private static function resolveRelation(string $field, string $id): ?string
    {
        $cacheKey = $field.':'.$id;
        if (array_key_exists($cacheKey, self::$cache)) {
            return self::$cache[$cacheKey];
        }

        $modelClass = self::RELATIONS[$field] ?? null;
        if (! $modelClass) {
            self::$cache[$cacheKey] = null;

            return null;
        }

        $query = $modelClass::query();
        if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            $query = $modelClass::withTrashed();
        }

        /** @var Model|null $model */
        $model = $query->find($id);
        if (! $model) {
            self::$cache[$cacheKey] = null;

            return null;
        }

        $label = self::modelLabel($model);
        self::$cache[$cacheKey] = $label;

        return $label;
    }

    private static function modelLabel(Model $model): string
    {
        return match ($model::class) {
            Agent::class => trim(($model->prenom ?? '').' '.($model->nom ?? ''))
                .($model->matricule ? ' ('.$model->matricule.')' : ''),
            User::class => trim(($model->prenom ?? '').' '.($model->nom ?? ''))
                ?: ($model->email ?? 'Utilisateur'),
            Client::class => (string) ($model->raison_sociale ?: $model->nom_responsable ?: 'Client'),
            Grade::class, Ville::class, Offre::class => (string) ($model->libelle ?: class_basename($model)),
            Zone::class, Site::class, Poste::class => (string) ($model->nom ?: class_basename($model)),
            Contrat::class => (string) ($model->reference ?: 'Contrat'),
            Facture::class => (string) ($model->numero ?: 'Facture'),
            Abonnement::class => (string) ($model->designation ?: 'Abonnement'),
            default => (string) ($model->getAttribute('libelle')
                ?? $model->getAttribute('nom')
                ?? $model->getAttribute('reference')
                ?? $model->getKey()),
        };
    }

    private static function looksLikeUuid(string $value): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $value,
        ) || Str::isUuid($value);
    }
}
