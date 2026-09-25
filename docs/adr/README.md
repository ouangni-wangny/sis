# Architecture Decision Records (ADR) — S.I.S

Décisions structurantes du produit, versionnées avec le code.

## Format

Chaque ADR suit le canevas :

1. **Statut** — Proposé | Accepté | Déprécié | Remplacé par ADR-XXXX
2. **Contexte** — Pourquoi la décision se pose
3. **Décision** — Ce qui est choisi
4. **Conséquences** — Impacts positifs / négatifs / à suivre

## Index

| ADR | Titre | Statut |
|-----|-------|--------|
| [0001](0001-contexte-tresorerie.md) | Contexte borné Trésorerie | Accepté |
| [0002](0002-mouvement-tresorerie.md) | Modèle mouvement de trésorerie | Accepté |
| [0003](0003-comptes-tresorerie.md) | Comptes multi (Banque / Caisse / MM) | Accepté |
| [0004](0004-depenses.md) | Dépenses d’entreprise | Accepté |
| [0005](0005-paie-vers-tresorerie.md) | Règlement de paie → trésorerie | Accepté |
| [0006](0006-commercial-vers-tresorerie.md) | Encaissements factures → trésorerie | Accepté |
| [0007](0007-hors-scope-phase-1.md) | Hors scope phase 1 | Accepté |

## Règle

Toute évolution qui change un ADR **Accepté** crée un nouvel ADR (ou met à jour le statut) ; on ne réécrit pas silencieusement l’historique.
