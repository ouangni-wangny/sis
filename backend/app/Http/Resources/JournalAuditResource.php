<?php

namespace App\Http\Resources;

use App\Support\AuditLabels;
use App\Support\AuditValueResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalAuditResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $typeBase = class_basename((string) $this->auditable_type);
        $changes = $this->buildChanges();

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'action' => $this->action,
            'action_label' => AuditLabels::action((string) $this->action),
            'action_explication' => $this->actionExplication(),
            'auditable_type' => $this->auditable_type,
            'auditable_type_label' => AuditLabels::entity((string) $this->auditable_type),
            'auditable_id' => $this->auditable_id,
            'auditable_label' => $this->auditableLabel(),
            'ancien' => $this->ancien,
            'nouveau' => $this->nouveau,
            'changes' => $changes,
            'resume' => $this->resume ?: $this->fallbackResume($changes),
            'explication' => $this->explicationHumaine($changes),
            'contexte' => $this->contexte,
            'contexte_labels' => $this->contexteLabels(),
            'ip' => $this->ip,
            'created_at' => $this->created_at,
            'user' => $this->whenLoaded('user', function () {
                if (! $this->user) {
                    return null;
                }

                return [
                    'id' => $this->user->id,
                    'nom' => $this->user->nom,
                    'prenom' => $this->user->prenom,
                    'email' => $this->user->email,
                    'label' => trim(($this->user->prenom ?? '').' '.($this->user->nom ?? ''))
                        ?: ($this->user->email ?? 'Utilisateur'),
                ];
            }),
            'acteur' => $this->acteurLabel(),
        ];
    }

    /** @return list<array{field: string, field_label: string, before: mixed, after: mixed, before_label: string, after_label: string}> */
    private function buildChanges(): array
    {
        $ancien = is_array($this->ancien) ? $this->ancien : [];
        $nouveau = is_array($this->nouveau) ? $this->nouveau : [];

        $keys = match ($this->action) {
            'created', 'restored' => array_keys($nouveau),
            'deleted' => array_keys($ancien),
            default => array_values(array_unique([...array_keys($nouveau), ...array_keys($ancien)])),
        };

        $hidden = ['remember_token', 'password', 'pin_hash'];
        $changes = [];

        foreach ($keys as $key) {
            if (in_array($key, $hidden, true)) {
                continue;
            }

            $before = $ancien[$key] ?? null;
            $after = $nouveau[$key] ?? null;

            if ($this->action === 'updated' && $before === $after) {
                continue;
            }

            $changes[] = [
                'field' => $key,
                'field_label' => AuditLabels::field($key),
                'before' => $this->action === 'created' || $this->action === 'restored' ? null : $before,
                'after' => $this->action === 'deleted' ? null : $after,
                'before_label' => $this->action === 'created' || $this->action === 'restored'
                    ? '—'
                    : AuditLabels::value($before, $key),
                'after_label' => $this->action === 'deleted'
                    ? '—'
                    : AuditLabels::value($after, $key),
            ];
        }

        return $changes;
    }

    private function auditableLabel(): ?string
    {
        $type = (string) $this->auditable_type;
        $id = (string) $this->auditable_id;
        if ($type === '' || $id === '') {
            return null;
        }

        $base = class_basename($type);
        $field = match ($base) {
            'Agent' => 'agent_id',
            'User' => 'user_id',
            'Grade' => 'grade_id',
            'Ville' => 'ville_id',
            'Client' => 'client_id',
            'Site' => 'site_id',
            'Zone' => 'zone_id',
            'Poste' => 'poste_id',
            'Offre' => 'offre_id',
            'Contrat' => 'contrat_id',
            'Facture' => 'facture_id',
            'Abonnement' => 'abonnement_id',
            default => null,
        };

        if (! $field) {
            return null;
        }

        $label = AuditValueResolver::label($field, $id);

        return $label === 'référence introuvable' ? null : $label;
    }

    private function actionExplication(): string
    {
        return match ($this->action) {
            'created' => 'Une nouvelle fiche a été ajoutée dans l’application.',
            'updated' => 'Des informations existantes ont été modifiées.',
            'deleted' => 'Une fiche a été supprimée (ou archivée logiquement).',
            'restored' => 'Une fiche précédemment supprimée a été rétablie.',
            default => 'Une action a été enregistrée dans le journal.',
        };
    }

    /** @param  list<array{field_label: string, before_label: string, after_label: string}>  $changes */
    private function explicationHumaine(array $changes): string
    {
        $acteur = $this->acteurLabel();
        $entity = AuditLabels::entity((string) $this->auditable_type);
        $action = match ($this->action) {
            'created' => 'a créé',
            'updated' => 'a modifié',
            'deleted' => 'a supprimé',
            'restored' => 'a restauré',
            default => 'a effectué une action sur',
        };

    if ($this->action === 'updated' && $changes !== []) {
            $useful = array_values(array_filter(
                $changes,
                fn ($c) => ! in_array($c['field'], ['created_at', 'updated_at', 'deleted_at', 'id'], true),
            ));
            $focus = $useful !== [] ? $useful : $changes;
            $first = $focus[0];
            $extra = count($focus) > 1
                ? ' (+'.(count($focus) - 1).' autre'.(count($focus) > 2 ? 's' : '').')'
                : '';

            return "{$acteur} {$action} {$entity} : « {$first['field_label']} » {$first['before_label']} → {$first['after_label']}{$extra}.";
        }

        return "{$acteur} {$action} un(e) {$entity}.";
    }

    /** @param  list<array{field_label: string}>  $changes */
    private function fallbackResume(array $changes): string
    {
        $entity = AuditLabels::entity((string) $this->auditable_type);
        $action = AuditLabels::action((string) $this->action);

        if ($this->action === 'updated' && $changes !== []) {
            $fields = implode(', ', array_map(fn ($c) => $c['field_label'], array_slice($changes, 0, 4)));

            return "{$action} — {$entity} ({$fields})";
        }

        return "{$action} — {$entity}";
    }

    private function acteurLabel(): string
    {
        if ($this->relationLoaded('user') && $this->user) {
            $name = trim(($this->user->prenom ?? '').' '.($this->user->nom ?? ''));

            return $name !== '' ? $name : ($this->user->email ?? 'Utilisateur');
        }

        if ($this->user_id) {
            return 'Utilisateur (compte introuvable)';
        }

        $via = is_array($this->contexte) ? ($this->contexte['via'] ?? null) : null;

        return match ($via) {
            'console' => 'Système automatique (tâche / console)',
            default => 'Système automatique',
        };
    }

    /** @return array<string, string> */
    private function contexteLabels(): array
    {
        $ctx = is_array($this->contexte) ? $this->contexte : [];
        $labels = [];

        if (isset($ctx['via'])) {
            $labels['Origine'] = match ($ctx['via']) {
                'console' => 'Action automatique (job, commande ou script)',
                'http' => 'Action depuis l’application (navigateur / API)',
                default => (string) $ctx['via'],
            };
        }
        if (isset($ctx['method'])) {
            $labels['Méthode'] = (string) $ctx['method'];
        }
        if (isset($ctx['route'])) {
            $labels['Écran / route'] = (string) $ctx['route'];
        }
        if (isset($ctx['url'])) {
            $labels['Adresse'] = (string) $ctx['url'];
        }
        if (isset($ctx['user_agent'])) {
            $labels['Navigateur'] = (string) $ctx['user_agent'];
        }

        return $labels;
    }
}
