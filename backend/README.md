# S.I.S API — Laravel 13

API REST mono-entreprise pour la gestion d'une société de sécurité privée (FCFA / Côte d'Ivoire).

Production : https://v1.sis-administration.com — déploiement via le kit
`xsel-deploy-mutualise` (voir le README à la racine du monorepo).

## Stack

- Laravel 13 + Sanctum (SPA cookie + Bearer mobile)
- Spatie Permission (RBAC) + Policies
- Spatie Media Library (photos / PDF / exports — **pas de colonnes path**)
- DomPDF + Maatwebsite Excel
- Pest

## Décisions de cadrage

| Sujet | Choix |
|---|---|
| Multi-tenant | Non (mono-entreprise) |
| Auth mobile | Matricule + PIN |
| Checkpoints | QR + GPS (Haversine 200 m) |
| Médias | Media Library |
| PK | UUID (`HasUuids`) |
| Soft deletes | Oui sur modèles métier |
| Devise | XOF / FCFA |

## Setup rapide

```bash
cd backend
cp .env.example .env
php artisan key:generate

# SQLite (dev) ou PostgreSQL
touch database/database.sqlite

php artisan migrate:fresh --seed
php artisan storage:link

# Queue : database ou sync en local (Horizon non requis en Phase 1)
php artisan queue:work   # si QUEUE_CONNECTION=database
```

Compte admin seedé :

- **email** : `admin@sis.ci`
- **password** : `password`

## Variables utiles

```ini
APP_NAME=SIS
MEDIA_DISK=media
QUEUE_CONNECTION=database   # ou sync pour tests / local simple
SANCTUM_STATEFUL_DOMAINS=localhost:3000
FRONTEND_URL=http://localhost:3000
```

Disks : `media` (public) et `private` (pièces d'identité, contrats).

## Auth

| Client | Endpoint | Mode |
|---|---|---|
| Next.js | `POST /api/v1/auth/login` | Session/cookie + CSRF (`/sanctum/csrf-cookie`) ou Bearer |
| Mobile | `POST /api/v1/auth/mobile-login` | Bearer (matricule + PIN) |

## Tests

```bash
php artisan test
```

## Structure

```
app/
  Domain/Shared/{Enums,Exceptions,Traits}
  Application/{Identity,Referentiel,Site,Agent,Operation,Commercial,Reporting,Paie,Rh,Tresorerie,Contrat}
  Models/
  Http/{Controllers/Api/V1,Requests,Resources}
  Policies/ Jobs/ Events/ Listeners/
  Support/Geo/Haversine.php
```
