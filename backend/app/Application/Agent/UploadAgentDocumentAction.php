<?php

namespace App\Application\Agent;

use App\Models\Agent;
use Illuminate\Http\UploadedFile;

final class UploadAgentDocumentAction
{
    public function execute(Agent $agent, string $collection, UploadedFile $file): Agent
    {
        $allowed = ['piece_identite', 'permis'];

        if (! in_array($collection, $allowed, true)) {
            throw new \InvalidArgumentException('Collection document invalide.');
        }

        $agent->addMedia($file)->toMediaCollection($collection);

        return $agent->load('media');
    }
}
