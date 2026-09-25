# ADR-0003 — Comptes de trésorerie multi

- **Statut** : Accepté
- **Date** : 2026-09-25
- **Liés** : ADR-0001, ADR-0002

## Contexte

Un seul « pot » entreprise masque la réalité opérationnelle (espèces en caisse vs solde bancaire vs Mobile Money). Les moyens de paiement déjà modélisés (`ModePaiement`) collent naturellement à plusieurs comptes.

## Décision

Table **`comptes_tresorerie`** avec au moins trois types :

| Type | Exemple libellé seed |
|------|----------------------|
| `banque` | Banque principale |
| `caisse` | Caisse siège |
| `mobile_money` | Mobile Money |

Champs : `libelle`, `type`, `solde_ouverture` (FCFA), `actif`, timestamps, UUID, soft delete optionnel.

**Solde courant** = `solde_ouverture` + Σ(entrées) − Σ(sorties) sur les mouvements non annulés.  
Un cache `solde_courant` est autorisé **uniquement** s’il est mis à jour dans la même transaction que le mouvement.

Contraintes produit :

- Mono-entreprise (aligné [backend/README.md](../../backend/README.md)).
- Devise **XOF / FCFA** uniquement.
- Pas de multi-devise ni de comptes clients / fournisseurs dans ce modèle.

Seed initial : 3 comptes actifs avec `solde_ouverture = 0` (ajustement manuel via mouvement `ajustement` après inventaire réel).

## Conséquences

- Chaque dépense / règlement paie / encaissement **choisit** un compte.
- Transfert interne Banque ↔ Caisse = **deux** mouvements liés (sortie + entrée) ou un type `transfert` ultérieur (hors phase 1 minimale : deux mouvements manuels suffisent).
- Mapping indicatif mode → compte (suggestion UI, non forcé) : virement→banque, espèces→caisse, mobile_money→mobile_money.
