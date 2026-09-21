<?php

namespace App\Http\Requests\Controle;

use App\Application\Operation\SiegeControleAuthorization;
use App\Domain\Shared\Enums\ResultatControle;
use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreControleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $siege = $this->isSiegeOperation();

        return [
            'client_uuid' => ['nullable', 'uuid'],
            'agent_id' => [
                $siege ? 'nullable' : 'required',
                'uuid',
                'exists:agents,id',
            ],
            'site_id' => ['required', 'uuid', 'exists:sites,id'],
            'poste_id' => ['nullable', 'uuid', 'exists:postes,id'],
            'controle_agent_id' => [
                'required',
                'uuid',
                'exists:agents,id',
                'different:agent_id',
            ],
            'ronde_id' => ['nullable', 'uuid', 'exists:rondes,id'],
            'latitude' => [$siege ? 'nullable' : 'required', 'numeric'],
            'longitude' => [$siege ? 'nullable' : 'required', 'numeric'],
            'resultat' => ['required', Rule::enum(ResultatControle::class)],
            'commentaire' => ['nullable', 'string'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,heic,heif'],
            'photo' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,heic,heif'],
            'photo_base64' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->isSiegeOperation()) {
                return;
            }

            $hasUpload = $this->hasFile('photos')
                || $this->hasFile('photos.0')
                || $this->hasFile('photo');
            $hasBase64 = filled($this->input('photo_base64'));

            if (! $hasUpload && ! $hasBase64) {
                $validator->errors()->add('photos', 'Une photo de l’agent au poste est obligatoire.');
            }

            if ($hasBase64 && ! $this->looksLikeImageBase64((string) $this->input('photo_base64'))) {
                $validator->errors()->add('photo_base64', 'La photo encodée est invalide.');
            }
        });
    }

    public function isSiegeOperation(): bool
    {
        $siteId = $this->input('site_id');
        if (! is_string($siteId) || $siteId === '') {
            return false;
        }

        $site = Site::query()->find($siteId);
        if (! $site) {
            return false;
        }

        return SiegeControleAuthorization::canOperate($this->user(), $site);
    }

    private function looksLikeImageBase64(string $value): bool
    {
        $raw = preg_replace('#^data:image/[\w.+-]+;base64,#i', '', trim($value)) ?? '';
        if ($raw === '' || strlen($raw) < 32) {
            return false;
        }

        $decoded = base64_decode($raw, true);

        return $decoded !== false && strlen($decoded) > 32;
    }
}
