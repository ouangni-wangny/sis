<?php

/**
 * One-shot generator for SIS API scaffolding.
 * Run: php scripts/generate_sis.php
 */

$base = dirname(__DIR__);

function write(string $rel, string $content): void
{
    global $base;
    $path = $base.'/'.$rel;
    $dir = dirname($path);
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($path, $content);
    echo "Wrote $rel\n";
}

// ─── ENUMS ───────────────────────────────────────────────────────────

$enums = [
    'TypeClient' => ['entreprise', 'particulier'],
    'StatutClient' => ['actif', 'resilie', 'suspendu'],
    'TypeUser' => ['backoffice', 'mobile'],
    'StatutUser' => ['actif', 'inactif', 'bloque'],
    'TypeAgent' => ['agent', 'rondier'],
    'StatutAgent' => ['disponible', 'en_mission', 'en_conge', 'suspendu', 'archive'],
    'StatutVacation' => ['planifiee', 'en_cours', 'terminee', 'annulee'],
    'StatutRonde' => ['planifiee', 'en_cours', 'terminee', 'annulee'],
    'StatutAnomalie' => ['ouverte', 'en_cours', 'resolue'],
    'GraviteAnomalie' => ['basse', 'moyenne', 'haute', 'critique'],
    'TypeAnomalie' => ['intrusion', 'vol', 'incendie', 'technique', 'comportement', 'autre'],
    'StatutFacture' => ['brouillon', 'emise', 'payee', 'annulee'],
    'StatutAbsence' => ['en_attente', 'approuvee', 'refusee', 'annulee'],
    'StatutAbonnement' => ['actif', 'suspendu', 'resilie', 'expire'],
    'TypeContrat' => ['cdi', 'cdd', 'prestation', 'stage'],
];

foreach ($enums as $name => $cases) {
    $caseLines = implode("\n", array_map(fn ($c) => "    case ".strtoupper($c)." = '$c';", $cases));
    // Fix: backed enum cases can't use accents in identifiers. Use PascalCase from values.
    $caseLines = '';
    foreach ($cases as $c) {
        $ident = str_replace(' ', '', ucwords(str_replace('_', ' ', $c)));
        // Map known French accents-free
        $ident = match ($c) {
            'entreprise' => 'Entreprise',
            'particulier' => 'Particulier',
            'actif' => 'Actif',
            'resilie' => 'Resilie',
            'suspendu' => 'Suspendu',
            'backoffice' => 'Backoffice',
            'mobile' => 'Mobile',
            'inactif' => 'Inactif',
            'bloque' => 'Bloque',
            'agent' => 'Agent',
            'rondier' => 'Rondier',
            'disponible' => 'Disponible',
            'en_mission' => 'EnMission',
            'en_conge' => 'EnConge',
            'archive' => 'Archive',
            'planifiee' => 'Planifiee',
            'en_cours' => 'EnCours',
            'terminee' => 'Terminee',
            'annulee' => 'Annulee',
            'ouverte' => 'Ouverte',
            'resolue' => 'Resolue',
            'basse' => 'Basse',
            'moyenne' => 'Moyenne',
            'haute' => 'Haute',
            'critique' => 'Critique',
            'intrusion' => 'Intrusion',
            'vol' => 'Vol',
            'incendie' => 'Incendie',
            'technique' => 'Technique',
            'comportement' => 'Comportement',
            'autre' => 'Autre',
            'brouillon' => 'Brouillon',
            'emise' => 'Emise',
            'payee' => 'Payee',
            'en_attente' => 'EnAttente',
            'approuvee' => 'Approuvee',
            'refusee' => 'Refusee',
            'expire' => 'Expire',
            'cdi' => 'Cdi',
            'cdd' => 'Cdd',
            'prestation' => 'Prestation',
            'stage' => 'Stage',
            default => $ident,
        };
        $caseLines .= "    case {$ident} = '{$c}';\n";
    }
    write("app/Domain/Shared/Enums/{$name}.php", <<<PHP
<?php

namespace App\Domain\Shared\Enums;

enum {$name}: string
{
{$caseLines}}

PHP);
}

// ─── EXCEPTIONS ──────────────────────────────────────────────────────

write('app/Domain/Shared/Exceptions/DomainException.php', <<<'PHP'
<?php

namespace App\Domain\Shared\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class DomainException extends Exception
{
    protected int $status = 422;

    public function render($request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => class_basename(static::class),
        ], $this->status);
    }

    public function status(): int
    {
        return $this->status;
    }
}

PHP);

$domainExceptions = [
    'PresenceHorsZoneException' => [422, 'Présence hors zone autorisée.'],
    'VacationConflitException' => [409, 'Conflit de vacation détecté pour cet agent.'],
    'RondeDejaDemarreeException' => [409, 'Une ronde est déjà démarrée.'],
    'TransitionAnomalieInvalideException' => [422, 'Transition de statut d\'anomalie invalide.'],
];

foreach ($domainExceptions as $cls => [$status, $msg]) {
    write("app/Domain/Shared/Exceptions/{$cls}.php", <<<PHP
<?php

namespace App\\Domain\\Shared\\Exceptions;

class {$cls} extends DomainException
{
    protected int \$status = {$status};

    public function __construct(string \$message = '{$msg}')
    {
        parent::__construct(\$message);
    }
}

PHP);
}

// ─── HAVERSINE ───────────────────────────────────────────────────────

write('app/Support/Geo/Haversine.php', <<<'PHP'
<?php

namespace App\Support\Geo;

final class Haversine
{
    public static function distanceMeters(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2,
    ): float {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 2 * $earthRadius * asin(min(1, sqrt($a)));
    }

    public static function isWithinRadius(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2,
        float $radiusMeters = 200,
    ): bool {
        return self::distanceMeters($lat1, $lon1, $lat2, $lon2) <= $radiusMeters;
    }
}

PHP);

echo "Foundation done\n";
