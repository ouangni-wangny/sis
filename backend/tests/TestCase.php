<?php

namespace Tests;

use App\Models\Agent;
use App\Models\Contrat;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    private static bool $agentContratHookRegistered = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$agentContratHookRegistered) {
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

            self::$agentContratHookRegistered = true;
        }
    }
}
