# S.I.S — Société Ivoirienne de Sécurité

Back-office, API et app terrain pour la gestion d’une société de sécurité
privée (Côte d’Ivoire / FCFA).

```
SIS/
├── backend/      API REST Laravel 13
├── frontend/     Back-office Next.js (App Router)
└── sis-mobile/   App terrain Expo (agents / rondiers)
```

## Production

| App | URL |
|---|---|
| Back-office | https://sis-administration.com |
| API | https://v1.sis-administration.com |

Déploiement CI/CD via le kit
[`xsel-deploy-mutualise`](https://github.com/ouangni-wangny/xsel-deploy-mutualise)
(même schéma que PECI) : push sur `main` → tests → rsync SSH vers cPanel.

Un premier déploiement **manuel** a déjà été fait sur le serveur
(`sisadmin@paloma`, home `/home/sisadmin`). Le CI/CD reprend ces chemins :
il n’y a **pas** à relancer `bootstrap-app.sh`.

### Secrets GitHub Actions (une fois)

```bash
gh secret set DEPLOY_SSH_HOST --repo ouangni-wangny/sis --body "91.204.209.51"
gh secret set DEPLOY_SSH_PORT --repo ouangni-wangny/sis --body "22"
gh secret set DEPLOY_SSH_USER --repo ouangni-wangny/sis --body "sisadmin"
gh secret set DEPLOY_SSH_PRIVATE_KEY --repo ouangni-wangny/sis < ~/chemin/vers/cle-privee
```

Détail de la génération de clé : README du kit, section *Onboarding*.

### Chemins serveur

| App | `deploy_path` | À vérifier |
|---|---|---|
| Frontend | `/home/sisadmin/sis-web` | cPanel Setup Node.js App (protège `.htaccess`, `tmp/`) |
| Backend | `/home/sisadmin/public_html/v1.sis-administration.com` | sous-domaine API (protège `.env`, `storage/`) |

PHP CloudLinux : les workflows pointent vers `/opt/alt/php83/usr/bin/php`.
Si le compte utilise 8.4, remplacer par `php84` comme sur PECI.

### Premier déploiement automatisé

1. Créer le repo GitHub, pousser `main`.
2. Renseigner les 4 secrets SSH.
3. Push (ou *Run workflow* → Deploy frontend / Deploy backend).
4. Le front passe du build **sur le serveur** (npm install + `next build`)
   au build **standalone en CI** — ne plus cliquer « Run NPM Install » dans
   cPanel ensuite. Le fichier de démarrage reste `server.js`.

Pas de rollback automatique : en cas de problème, revert + repush.
