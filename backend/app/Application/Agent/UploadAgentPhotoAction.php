<?php

namespace App\Application\Agent;

use App\Models\Agent;
use Illuminate\Http\UploadedFile;

final class UploadAgentPhotoAction
{
    public function execute(Agent $agent, UploadedFile $file): Agent
    {
        $agent->addMedia($file)->toMediaCollection('photo');

        return $agent->load('media');
    }
}
