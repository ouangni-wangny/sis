# ADR-0006 — Encaissements factures → trésorerie

- **Statut** : Accepté
- **Date** : 2026-09-25
- **Liés** : ADR-0001, ADR-0002, ADR-0003

## Contexte

`Paiement` commercial enregistre déjà montant, date, `mode`, référence liés à une `Facture`. Sans pont, le solde Trésorerie ignore les entrées clients — incohérent avec le suivi de caisse.

## Décision

Conserver le modèle **Commercial `Paiement`** tel quel (AR, solde facture calculé).

À chaque **création / mise à jour significative / soft-delete** d’un `Paiement` :

| Événement | Effet trésorerie |
|-----------|------------------|
| Create | Mouvement `entree` (`source_type = facture_paiement`) |
| Update montant / date / mode / compte | Inverse de l’ancien + nouveau (ou update interdit : forcer delete+recreate) |
| Soft-delete | Mouvement inverse |

Le compte de destination est **obligatoire** à la création d’un paiement dès que `module.tresorerie` est actif (champ ajouté au store paiement, ou compte par défaut configurable en settings).

Le statut de paiement facture (`non_payee` / `partiel` / `soldee`) **reste calculé** côté Commercial comme aujourd’hui.

## Conséquences

- Un seul cashbook pour entrées clients + sorties dépenses/paie.
- Couplage applicatif Commercial → Trésorerie (action Application dédiée, pas de logique SQL cachée dans le modèle seul).
- Migration des paiements existants : même règle que la paie — **pas de backfill auto** en phase 1 (option outil artisan plus tard).
