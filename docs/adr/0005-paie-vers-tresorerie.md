# ADR-0005 — Règlement de paie → trésorerie

- **Statut** : Accepté
- **Date** : 2026-09-25
- **Liés** : ADR-0001, ADR-0002, ADR-0003

## Contexte

Aujourd’hui `BulletinPaieController::marquerPaye` pose seulement `statut = paye` et `paye_le`. Pas de moyen de paiement, pas d’impact sur un solde entreprise. Le besoin métier : savoir pour chaque agent le salaire perçu, le **moyen**, et les stats de masse salariale payée du mois.

Le calcul (IGR, CNPS, brut/net) reste dans **RH / Paie** (`CalculRemunerationCi`).

## Décision

Enrichir le règlement d’un bulletin :

1. Prérequis métier : bulletin au moins `valide` (ou équivalent actuel) avant paiement.
2. Payload de règlement obligatoire :
   - `mode` (`ModePaiement`)
   - `compte_tresorerie_id`
   - `reference` (optionnel)
   - `paye_le` (défaut : maintenant)
3. Transaction atomique :
   - mise à jour bulletin → `paye` + champs de règlement
   - création mouvement `sortie` montant = `salaire_net`, `source_type = bulletin_paie`, `source_id = bulletin.id`
4. Stockage des champs de règlement :
   - soit colonnes sur `bulletins_paie` (`mode_paiement`, `compte_tresorerie_id`, `reference_paiement`)
   - soit table `reglements_paie` 1:1 — **préférence phase 1 : colonnes sur le bulletin** (simplicité), révisable si batch virements arrive (P2).

Annulation d’un « payé » (si autorisée) : mouvement inverse + retour statut `valide` ; droits restreints (`paie.manage` + éventuellement `tresorerie.manage`).

**Bulletins déjà `paye` avant le module** : pas de backfill automatique ; listing historique sans mouvement jusqu’à correction manuelle éventuelle (ajustement).

## Conséquences

- Paie reste owner du calcul ; Trésorerie owner de l’effet cash.
- Stats : somme nets payés du mois, répartition par `mode` / par compte.
- Breaking soft de l’API `marquer-paye` (body désormais requis) — à documenter côté front `/rh/paie`.
