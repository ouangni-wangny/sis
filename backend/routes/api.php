<?php

use App\Http\Controllers\Api\V1\AbonnementController;
use App\Http\Controllers\Api\V1\AbsenceController;
use App\Http\Controllers\Api\V1\AgentController;
use App\Http\Controllers\Api\V1\AnomalieController;
use App\Http\Controllers\Api\V1\AuditController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BulletinPaieController;
use App\Http\Controllers\Api\V1\CategorieDepenseController;
use App\Http\Controllers\Api\V1\CheckpointController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\ContratController;
use App\Http\Controllers\Api\V1\CompteTresorerieController;
use App\Http\Controllers\Api\V1\ControleController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DepenseController;
use App\Http\Controllers\Api\V1\FactureController;
use App\Http\Controllers\Api\V1\GradeController;
use App\Http\Controllers\Api\V1\MobileAuthController;
use App\Http\Controllers\Api\V1\MobileSyncController;
use App\Http\Controllers\Api\V1\ModePaiementParamController;
use App\Http\Controllers\Api\V1\OffreController;
use App\Http\Controllers\Api\V1\PaiementController;
use App\Http\Controllers\Api\V1\PerimetreController;
use App\Http\Controllers\Api\V1\PeriodePaieController;
use App\Http\Controllers\Api\V1\PosteController;
use App\Http\Controllers\Api\V1\RapportController;
use App\Http\Controllers\Api\V1\RondeController;
use App\Http\Controllers\Api\V1\SisNotificationController;
use App\Http\Controllers\Api\V1\SiteController;
use App\Http\Controllers\Api\V1\System\RoleController;
use App\Http\Controllers\Api\V1\System\SystemConfigController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\VacationController;
use App\Http\Controllers\Api\V1\VilleController;
use App\Http\Controllers\Api\V1\ZoneController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('auth/mobile-login', [MobileAuthController::class, 'login'])->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        // Console système (jamais derrière un feature flag)
        Route::get('system/runtime-config', [SystemConfigController::class, 'runtimeConfig']);
        Route::get('system/feature-flags', [SystemConfigController::class, 'featureFlags']);
        Route::post('system/feature-flags/reset', [SystemConfigController::class, 'resetFeatureFlags']);
        Route::patch('system/feature-flags/group/{group}', [SystemConfigController::class, 'updateFeatureFlagGroup']);
        Route::patch('system/feature-flags/{key}', [SystemConfigController::class, 'updateFeatureFlag']);
        Route::get('system/menu-overrides', [SystemConfigController::class, 'menuOverrides']);
        Route::put('system/menu-overrides', [SystemConfigController::class, 'syncMenuOverrides']);
        Route::get('system/history', [SystemConfigController::class, 'history']);
        Route::get('system/settings', [SystemConfigController::class, 'settings']);
        Route::put('system/settings', [SystemConfigController::class, 'updateSettings']);
        Route::post('system/settings/reset', [SystemConfigController::class, 'resetSettings']);
        Route::get('system/permissions', [RoleController::class, 'permissions']);
        Route::get('system/roles', [RoleController::class, 'index']);
        Route::post('system/roles', [RoleController::class, 'store']);
        Route::patch('system/roles/{role}', [RoleController::class, 'update']);
        Route::delete('system/roles/{role}', [RoleController::class, 'destroy']);

        Route::get('notifications', [SisNotificationController::class, 'index']);
        Route::post('notifications/marquer-toutes-lues', [SisNotificationController::class, 'marquerToutesLues']);
        Route::post('notifications/{sisNotification}/lue', [SisNotificationController::class, 'marquerLue']);

        Route::post('mobile/sync', [MobileSyncController::class, 'sync']);

        // Référentiels partagés (utilisés par agents, sites, etc.)
        Route::apiResource('grades', GradeController::class);
        Route::apiResource('villes', VilleController::class);
        Route::get('modes-paiement', [ModePaiementParamController::class, 'index']);
        Route::post('modes-paiement', [ModePaiementParamController::class, 'store']);
        Route::put('modes-paiement/{modePaiementParam}', [ModePaiementParamController::class, 'update']);
        Route::delete('modes-paiement/{modePaiementParam}', [ModePaiementParamController::class, 'destroy']);
        Route::get('categories-depense', [CategorieDepenseController::class, 'index']);
        Route::post('categories-depense', [CategorieDepenseController::class, 'store']);
        Route::put('categories-depense/{categorieDepense}', [CategorieDepenseController::class, 'update']);
        Route::delete('categories-depense/{categorieDepense}', [CategorieDepenseController::class, 'destroy']);
        Route::get('comptes-tresorerie', [CompteTresorerieController::class, 'index']);
        Route::post('comptes-tresorerie', [CompteTresorerieController::class, 'store']);
        Route::put('comptes-tresorerie/{compteTresorerie}', [CompteTresorerieController::class, 'update']);
        Route::delete('comptes-tresorerie/{compteTresorerie}', [CompteTresorerieController::class, 'destroy']);

        Route::middleware('feature:module.dashboard')->group(function () {
            Route::get('dashboard/stats', [DashboardController::class, 'stats']);
        });

        Route::middleware('feature:module.rapports')->group(function () {
            Route::get('rapports', [RapportController::class, 'index']);
            Route::post('rapports/{type}', [RapportController::class, 'store']);
            Route::get('rapports/jobs/{rapport}', [RapportController::class, 'show']);
            Route::get('rapports/jobs/{rapport}/download', [RapportController::class, 'download']);
        });

        Route::middleware('feature:module.zones')->group(function () {
            Route::apiResource('zones', ZoneController::class);
            Route::put('zones/{zone}/controleurs', [ZoneController::class, 'syncControleurs']);
            Route::put('zones/{zone}/releve', [ZoneController::class, 'syncReleve']);
        });

        Route::middleware('feature:module.sites')->group(function () {
            Route::apiResource('sites', SiteController::class);
            Route::get('sites/{site}/postes', [PosteController::class, 'index']);
            Route::post('sites/{site}/postes', [PosteController::class, 'store']);
            Route::put('sites/{site}/postes/{poste}', [PosteController::class, 'update']);
            Route::delete('sites/{site}/postes/{poste}', [PosteController::class, 'destroy']);
            Route::get('postes/coverage', [PosteController::class, 'coverage']);
            Route::get('postes', [PosteController::class, 'indexAll']);
            Route::get('sites/{site}/checkpoints', [CheckpointController::class, 'index']);
            Route::post('sites/{site}/checkpoints', [CheckpointController::class, 'store']);
            Route::delete('sites/{site}/checkpoints/{checkpoint}', [CheckpointController::class, 'destroy']);
        });

        Route::middleware('feature:module.agents')->group(function () {
            Route::apiResource('agents', AgentController::class);
            Route::post('agents/{agent}/photo', [AgentController::class, 'storePhoto']);
            Route::put('agents/{agent}/perimetre', [AgentController::class, 'syncPerimetre']);
            Route::post('agents/{agent}/documents/{collection}', [AgentController::class, 'uploadDocument']);
            Route::get('agents/{agent}/documents/{collection}', [AgentController::class, 'downloadDocument']);
            Route::get('agents/{agent}/conges/mouvements', [AgentController::class, 'congesMouvements']);
            Route::post('agents/{agent}/conges/acquisition', [AgentController::class, 'accorderConges']);
        });

        Route::middleware('feature:module.planning_controleurs')->group(function () {
            Route::get('perimetres', [PerimetreController::class, 'index']);
        });

        Route::middleware('feature:module.vacations')->group(function () {
            Route::get('vacations/conflits', [VacationController::class, 'conflits']);
            Route::post('vacations/bulk', [VacationController::class, 'storeBulk']);
            Route::post('vacations/{vacation}/recouvrir', [VacationController::class, 'recouvrir']);
            Route::apiResource('vacations', VacationController::class);
            Route::apiResource('rondes', RondeController::class)->except(['update', 'destroy']);
            Route::post('rondes/{ronde}/demarrer', [RondeController::class, 'demarrer']);
            Route::post('rondes/{ronde}/scanner', [RondeController::class, 'scanner']);
            Route::post('rondes/{ronde}/terminer', [RondeController::class, 'terminer']);
        });

        Route::middleware('feature:module.controles')->group(function () {
            Route::apiResource('controles', ControleController::class)->only(['index', 'store', 'show']);
        });

        Route::middleware('feature:module.anomalies')->group(function () {
            Route::get('anomalies', [AnomalieController::class, 'index']);
            Route::post('anomalies', [AnomalieController::class, 'store']);
            Route::get('anomalies/{anomalie}', [AnomalieController::class, 'show']);
            Route::patch('anomalies/{anomalie}/statut', [AnomalieController::class, 'updateStatut']);
        });

        Route::middleware('feature:module.rh')->group(function () {
            Route::get('contrats/alerts', [ContratController::class, 'alerts']);
            Route::get('contrats/{contrat}/document', [ContratController::class, 'downloadDocument']);
            Route::post('contrats/{contrat}/avenant', [ContratController::class, 'storeAvenant']);
            Route::apiResource('contrats', ContratController::class);
            Route::apiResource('absences', AbsenceController::class)->except(['show']);
        });

        Route::middleware('feature:module.paie')->group(function () {
            Route::get('agents/{agent}/bulletins', [AgentController::class, 'bulletins']);
            Route::get('periodes-paie', [PeriodePaieController::class, 'index']);
            Route::post('periodes-paie', [PeriodePaieController::class, 'store']);
            Route::get('periodes-paie/{periodePaie}', [PeriodePaieController::class, 'show']);
            Route::post('periodes-paie/{periodePaie}/generer-bulletins', [PeriodePaieController::class, 'genererBulletins']);
            Route::get('periodes-paie/{periodePaie}/bulletins', [PeriodePaieController::class, 'bulletins']);
            Route::post('periodes-paie/{periodePaie}/valider', [PeriodePaieController::class, 'valider']);
            Route::post('periodes-paie/{periodePaie}/cloturer', [PeriodePaieController::class, 'cloturer']);
            Route::delete('periodes-paie/{periodePaie}', [PeriodePaieController::class, 'destroy']);
            Route::get('bulletins-paie/{bulletinPaie}', [BulletinPaieController::class, 'show']);
            Route::post('bulletins-paie/{bulletinPaie}/generer-pdf', [BulletinPaieController::class, 'genererPdf']);
            Route::get('bulletins-paie/{bulletinPaie}/pdf', [BulletinPaieController::class, 'downloadPdf']);
            Route::post('bulletins-paie/{bulletinPaie}/marquer-paye', [BulletinPaieController::class, 'marquerPaye']);
        });

        Route::middleware('feature:module.clients')->group(function () {
            Route::apiResource('clients', ClientController::class);
        });

        Route::middleware('feature:module.offres')->group(function () {
            Route::apiResource('offres', OffreController::class);
        });

        Route::middleware('feature:module.abonnements')->group(function () {
            Route::apiResource('abonnements', AbonnementController::class);
        });

        Route::middleware('feature:module.factures')->group(function () {
            Route::get('factures', [FactureController::class, 'index']);
            Route::post('factures/proforma', [FactureController::class, 'storeProforma']);
            Route::put('factures/{facture}/proforma', [FactureController::class, 'updateProforma']);
            Route::get('factures/{facture}', [FactureController::class, 'show']);
            Route::patch('factures/{facture}/statut', [FactureController::class, 'updateStatut']);
            Route::post('factures/generer', [FactureController::class, 'generer']);
            Route::post('factures/{facture}/generer-pdf', [FactureController::class, 'genererPdf']);
            Route::get('factures/{facture}/pdf', [FactureController::class, 'telechargerPdf']);
        });

        Route::middleware('feature:module.paiements')->group(function () {
            Route::apiResource('paiements', PaiementController::class);
        });

        Route::middleware('feature:module.tresorerie')->group(function () {
            Route::get('tresorerie/stats', [CompteTresorerieController::class, 'stats']);
            Route::get('tresorerie/mouvements', [CompteTresorerieController::class, 'mouvements']);
            Route::post('tresorerie/ajustements', [CompteTresorerieController::class, 'ajustement']);
            Route::post('tresorerie/transferts', [CompteTresorerieController::class, 'transfert']);
            Route::get('depenses', [DepenseController::class, 'index']);
            Route::post('depenses', [DepenseController::class, 'store']);
            Route::get('depenses/{depense}', [DepenseController::class, 'show']);
            Route::delete('depenses/{depense}', [DepenseController::class, 'destroy']);
        });

        // Alias options (compat formulaires paie / encaissements)
        Route::get('comptes-tresorerie-options', [CompteTresorerieController::class, 'index']);

        Route::middleware('feature:module.users')->group(function () {
            Route::apiResource('users', UserController::class);
        });

        Route::middleware('feature:module.audit')->group(function () {
            Route::get('journal-audit', [AuditController::class, 'index']);
            Route::get('journal-audit/{journalAudit}', [AuditController::class, 'show']);
        });
    });
});
