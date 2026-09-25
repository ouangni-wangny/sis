# ADR-0007 — Hors scope phase 1 Trésorerie

- **Statut** : Accepté
- **Date** : 2026-09-25
- **Liés** : ADR-0001 … ADR-0006

## Contexte

Le risque principal est de dériver vers une « vraie comptabilité » (plan de comptes, bilans) trop tôt, alors que le besoin immédiat est opérationnel : solde, dépenses, salaires payés, stats.

## Décision

**Explicitement hors phase 1** (reporté / P2+) :

| Sujet | Notes |
|-------|--------|
| Plan comptable OHADA, journal légal, grand livre | Export vers logiciel externe plutôt (cf. [RH-AMELIORATIONS-P2](../RH-AMELIORATIONS-P2.md)) |
| Double écriture (débit/crédit) | Le journal trésorerie à une seule ligne suffit |
| TVA déductible / déclarations fiscales | Hors SIS phase 1 |
| Fournisseurs & comptes fournisseurs (AP complets) | Dépenses libres + catégorie suffisent |
| Acomptes / avances sur salaire | Listés P2 paie avancée |
| Virements groupés SEPA / fichier bancaire | P2 |
| Multi-devises | XOF only |
| Multi-entreprises / multi-tenant | Hors cadrage SIS |
| Transfert interne compte↔compte UX dédiée | Contournement : deux mouvements ; UX transfert = phase 1.1 possible |
| Backfill auto des historiques `paye` / paiements | Outil manuel / artisan ultérieur |

## Conséquences

- Périmètre MVP clair pour le codage ultérieur.
- Toute demande listée ici nécessite un **nouvel ADR** (ou révision de statut) avant implémentation.
