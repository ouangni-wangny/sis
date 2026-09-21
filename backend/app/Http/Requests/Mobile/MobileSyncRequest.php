<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class MobileSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'controles' => ['nullable', 'array'],
            'controles.*.client_uuid' => ['required', 'uuid'],
            // agent_id is accepted for backward compatibility but is always
            // overridden server-side with the authenticated agent's id.
            'controles.*.agent_id' => ['nullable', 'uuid'],
            'controles.*.site_id' => ['required', 'uuid', 'exists:sites,id'],
            'controles.*.controle_agent_id' => ['required', 'uuid', 'exists:agents,id'],
            'controles.*.poste_id' => ['nullable', 'uuid'],
            'controles.*.latitude' => ['required', 'numeric'],
            'controles.*.longitude' => ['required', 'numeric'],
            'controles.*.commentaire' => ['nullable', 'string'],
            'controles.*.effectue_at' => ['nullable', 'date'],

            'anomalies' => ['nullable', 'array'],
            'anomalies.*.client_uuid' => ['required', 'uuid'],
            // signale_par_id is accepted for backward compatibility but is
            // always overridden server-side with the authenticated agent's id.
            'anomalies.*.signale_par_id' => ['nullable', 'uuid'],
            'anomalies.*.site_id' => ['required', 'uuid', 'exists:sites,id'],
            'anomalies.*.type' => ['required', 'string'],
            'anomalies.*.gravite' => ['nullable', 'string'],
            'anomalies.*.commentaire' => ['nullable', 'string'],

            'ronde_scans' => ['nullable', 'array'],
            'ronde_scans.*.client_uuid' => ['nullable', 'uuid'],
            'ronde_scans.*.ronde_id' => ['required', 'uuid', 'exists:rondes,id'],
            'ronde_scans.*.checkpoint_id' => ['required', 'uuid', 'exists:checkpoints,id'],
            'ronde_scans.*.latitude' => ['required', 'numeric'],
            'ronde_scans.*.longitude' => ['required', 'numeric'],
            'ronde_scans.*.code_qr' => ['required', 'string'],
            'ronde_scans.*.scanne_at' => ['nullable', 'date'],
        ];
    }
}
