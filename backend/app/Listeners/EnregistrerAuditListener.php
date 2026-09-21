<?php

namespace App\Listeners;

use App\Events\ModelAudited;
use App\Models\JournalAudit;
use App\Support\AuditLabels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class EnregistrerAuditListener
{
    private const SECRET_KEYS = [
        'password',
        'pin_hash',
        'remember_token',
        'pin',
        'token',
        'api_token',
        'secret',
    ];

    public function handle(ModelAudited $event): void
    {
        $ancien = $this->sanitize($event->ancien);
        $nouveau = $this->sanitize($event->nouveau);

        JournalAudit::query()->create([
            'user_id' => Auth::id(),
            'action' => $event->action,
            'auditable_type' => $event->model->getMorphClass(),
            'auditable_id' => (string) $event->model->getKey(),
            'ancien' => $ancien,
            'nouveau' => $nouveau,
            'resume' => $this->buildResume($event, $ancien, $nouveau),
            'contexte' => $this->buildContexte(),
            'ip' => Request::ip(),
        ]);
    }

    private function sanitize(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        foreach (self::SECRET_KEYS as $key) {
            unset($data[$key]);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>|null  $ancien
     * @param  array<string, mixed>|null  $nouveau
     */
    private function buildResume(ModelAudited $event, ?array $ancien, ?array $nouveau): string
    {
        $entity = AuditLabels::entity($event->model::class);
        $label = method_exists($event->model, 'auditLabel')
            ? $event->model->auditLabel()
            : null;
        $cible = $label ? "{$entity} « {$label} »" : $entity;
        $action = AuditLabels::action($event->action);

        return match ($event->action) {
            'created' => "{$action} de {$cible}",
            'deleted' => "{$action} de {$cible}",
            'restored' => "{$action} de {$cible}",
            'updated' => $this->resumeUpdated($cible, $ancien, $nouveau),
            default => "{$action} — {$cible}",
        };
    }

    /**
     * @param  array<string, mixed>|null  $ancien
     * @param  array<string, mixed>|null  $nouveau
     */
    private function resumeUpdated(string $cible, ?array $ancien, ?array $nouveau): string
    {
        $keys = array_keys($nouveau ?? []);
        $parts = [];
        foreach (array_slice($keys, 0, 5) as $key) {
            $field = AuditLabels::field($key);
            $from = AuditLabels::value($ancien[$key] ?? null, $key);
            $to = AuditLabels::value($nouveau[$key] ?? null, $key);
            $parts[] = "{$field} : {$from} → {$to}";
        }

        $extra = count($keys) > 5 ? '…' : '';
        $detail = $parts !== [] ? ' — '.implode(' ; ', $parts).$extra : '';

        return 'Modification de '.$cible.$detail;
    }

    /** @return array<string, mixed> */
    private function buildContexte(): array
    {
        $via = app()->runningInConsole() ? 'console' : 'http';

        return array_filter([
            'via' => $via,
            'method' => Request::method(),
            'url' => Request::fullUrl(),
            'route' => Request::route()?->getName() ?? Request::path(),
            'user_agent' => Request::userAgent(),
        ], fn ($v) => filled($v));
    }
}
