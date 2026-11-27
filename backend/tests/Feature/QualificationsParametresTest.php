<?php

use App\Models\Qualification;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create(['email' => 'admin-primes@sis.ci']);
    $this->admin->assignRole('super-admin');
});

it('fournit les qualifications de base (CP, GA, MC)', function () {
    $this->actingAs($this->admin)
        ->getJson('/api/v1/qualifications?all=1')
        ->assertOk()
        ->assertJsonFragment(['abreviation' => 'CP', 'libelle' => 'Chef de poste'])
        ->assertJsonFragment(['abreviation' => 'GA'])
        ->assertJsonFragment(['abreviation' => 'MC']);
});

it('crée une qualification avec une abréviation unique en majuscules', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/qualifications', ['libelle' => 'Prime de nuit', 'abreviation' => 'pn'])
        ->assertCreated()
        ->assertJsonPath('data.abreviation', 'PN');

    $this->actingAs($this->admin)
        ->postJson('/api/v1/qualifications', ['libelle' => 'Autre', 'abreviation' => 'CP'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('abreviation');
});

it('enregistre la prime d’une qualification par mois ou par jour, une seule par unité', function () {
    $cp = Qualification::query()->where('abreviation', 'CP')->firstOrFail();

    $this->actingAs($this->admin)
        ->postJson('/api/v1/primes-qualification', ['qualification_id' => $cp->id, 'montant' => 1000, 'unite' => 'mois'])
        ->assertCreated()
        ->assertJsonPath('data.qualification.abreviation', 'CP')
        ->assertJsonPath('data.unite', 'mois');

    $this->actingAs($this->admin)
        ->postJson('/api/v1/primes-qualification', ['qualification_id' => $cp->id, 'montant' => 50, 'unite' => 'jour'])
        ->assertCreated();

    $this->actingAs($this->admin)
        ->postJson('/api/v1/primes-qualification', ['qualification_id' => $cp->id, 'montant' => 2000, 'unite' => 'mois'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('unite');

    $this->actingAs($this->admin)
        ->postJson('/api/v1/primes-qualification', ['qualification_id' => $cp->id, 'montant' => 10, 'unite' => 'semaine'])
        ->assertStatus(422);
});

it('refuse de supprimer une qualification qui a des primes', function () {
    $cp = Qualification::query()->where('abreviation', 'CP')->firstOrFail();
    $cp->primes()->create(['montant' => 1000, 'unite' => 'mois']);

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/qualifications/{$cp->id}")
        ->assertStatus(422);
});

it('réserve la modification des qualifications aux profils autorisés', function () {
    $commercial = User::factory()->create(['email' => 'commercial-primes@sis.ci']);
    $commercial->assignRole('commercial');

    $this->actingAs($commercial)
        ->postJson('/api/v1/qualifications', ['libelle' => 'X', 'abreviation' => 'X'])
        ->assertForbidden();
});
