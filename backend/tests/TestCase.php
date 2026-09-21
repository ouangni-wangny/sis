<?php

namespace Tests;

use App\Models\Agent;
use App\Models\Contrat;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase recreates the application each test; listeners must be
        // re-registered. A process-wide static flag would leave later tests
        // without a CDI and fail vacation assignment (422 contrat valide).
        Agent::created(function (Agent $agent): void {
            if (Contrat::query()->where('agent_id', $agent->id)->exists()) {
                return;
            }

            Contrat::query()->create([
                'agent_id' => $agent->id,
                'type' => 'cdi',
                'date_debut' => '2026-01-01',
                'statut' => 'actif',
            ]);
        });
    }
}
