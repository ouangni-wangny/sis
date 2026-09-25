# ADR-0005 — Règlement de paie → trésorerie

- **Statut** : Accepté
- **Date** : 2026-09-25
- **Liés** : ADR-0001, ADR-0002, ADR-0003

## Contexte

Aujourd’hui `BulletinPaieController::marquerPaye` pose seulement `statut = paye` et `paye_le`. Pas de moyen de paiement, pas d’impact sur un solde entreprise. Le besoin métier : savoir pour chaque agent le salaire perçu, le **moyen**, et les stats de masse salariale payée du mois.

Le calcul (IGR, CNPS, brut/net) reste dans **RH / Paie** (`CalculRemunerationCi`).

## Décision

Enrichir le règlement d’un bulletin en **deux rôles** :

1. **RH** (`paie.manage`) : endpoint `salaire-percu` — saisit le salaire perçu (jours travaillés) sans marquer payé.
2. **Comptable** (`paie.payer`) : `marquer-paye` — mode + compte uniquement ; montant = salaire déjà renseigné. Ne voit pas les montants (`canSeeSalaire` = `contrats.manage` seulement).

Prérequis métier : bulletin au moins `valide` (ou période validée) avant paiement ; `salaire_renseigne` obligatoire.

Payload de règlement :

- `mode` (`ModePaiement`)
- `compte_tresorerie_id`
- `reference` (optionnel)
- `paye_le` (défaut : maintenant)

Transaction atomique :

- mise à jour bulletin → `paye` + champs de règlement
- création mouvement `sortie` montant = salaire perçu RH, `source_type = bulletin_paie`

Annulation d’un « payé » (si autorisée) : mouvement inverse + retour statut `valide` ; droits restreints (`paie.payer` + éventuellement `tresorerie.manage`).

**Bulletins déjà `paye` avant le module** : pas de backfill automatique ; listing historique sans mouvement jusqu’à correction manuelle éventuelle (ajustement).

## Conséquences

- Paie reste owner du calcul ; Trésorerie owner de l’effet cash.
- Séparation confidentialité : RH voit les salaires ; comptable règle sans les voir.
- Stats : somme nets payés du mois, répartition par `mode` / par compte.
- Breaking soft de l’API `marquer-paye` (plus de `salaire_net` dans le body) — documenté côté front `/rh/paie`.
