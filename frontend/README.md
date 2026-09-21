# S.I.S Web — Back-office

Back-office opérationnel Next.js pour la gestion S.I.S (sécurité privée, Côte d'Ivoire).

## Prérequis

- Node.js 20+
- API Laravel `backend` démarrée sur `http://localhost:8000`

## Démarrage

```bash
cp .env.example .env.local
npm install
npm run dev
```

Ouvrir [http://localhost:3000](http://localhost:3000).

Production : https://sis-administration.com — déploiement via le kit
`xsel-deploy-mutualise` (voir le README à la racine du monorepo).

Compte seed : `admin@sis.ci` / `password`

## Architecture

```
src/
  domain/           # types + schémas Zod
  infrastructure/   # axios client, auth token, APIs ressources
  application/      # hooks TanStack Query
  presentation/     # UI, layout, DataTable, providers
  shared/           # cn(), env, formatteurs
  app/
    (auth)/login
    (app)/          # dashboard + modules métier
```

## Stack

- Next.js 16 (App Router) + TypeScript
- Tailwind CSS v4
- TanStack Query + Table
- axios, zod, react-hook-form
- Manrope + IBM Plex Mono

## Scripts

| Commande | Description |
|---|---|
| `npm run dev` | Serveur de développement |
| `npm run build` | Build production |
| `npm run start` | Serveur production |
| `npm run lint` | ESLint |

## Variables d'environnement

| Variable | Défaut |
|---|---|
| `NEXT_PUBLIC_API_URL` | `http://localhost:8000/api/v1` |
