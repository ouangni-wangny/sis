<?php

namespace App\Providers;

use App\Events\ModelAudited;
use App\Listeners\EnregistrerAuditListener;
use App\Models\Abonnement;
use App\Models\Agent;
use App\Models\Anomalie;
use App\Models\Client;
use App\Models\CompteTresorerie;
use App\Models\Depense;
use App\Models\Facture;
use App\Models\Offre;
use App\Models\Paiement;
use App\Models\Site;
use App\Models\User;
use App\Models\Vacation;
use App\Models\Zone;
use App\Policies\AbonnementPolicy;
use App\Policies\AgentPolicy;
use App\Policies\AnomaliePolicy;
use App\Policies\ClientPolicy;
use App\Policies\CompteTresoreriePolicy;
use App\Policies\DepensePolicy;
use App\Policies\FacturePolicy;
use App\Policies\OffrePolicy;
use App\Policies\PaiementPolicy;
use App\Policies\SitePolicy;
use App\Policies\UserPolicy;
use App\Policies\VacationPolicy;
use App\Policies\ZonePolicy;
use App\Support\SystemSettingsRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Zone::class, ZonePolicy::class);
        Gate::policy(Agent::class, AgentPolicy::class);
        Gate::policy(Site::class, SitePolicy::class);
        Gate::policy(Vacation::class, VacationPolicy::class);
        Gate::policy(Anomalie::class, AnomaliePolicy::class);
        Gate::policy(Facture::class, FacturePolicy::class);
        Gate::policy(Offre::class, OffrePolicy::class);
        Gate::policy(Abonnement::class, AbonnementPolicy::class);
        Gate::policy(Paiement::class, PaiementPolicy::class);
        Gate::policy(CompteTresorerie::class, CompteTresoreriePolicy::class);
        Gate::policy(Depense::class, DepensePolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        Event::listen(ModelAudited::class, EnregistrerAuditListener::class);

        SystemSettingsRegistry::applyToConfig();
    }
}
