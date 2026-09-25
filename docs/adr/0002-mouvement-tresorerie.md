# ADR-0002 — Modèle mouvement de trésorerie

- **Statut** : Accepté
- **Date** : 2026-09-25
- **Liés** : ADR-0001, ADR-0003

## Contexte

Il faut une source de vérité unique pour les entrées et sorties d’argent (dépenses, salaires, encaissements clients, ajustements). Un solde stocké seul sans journal devient vite incohérent.

## Décision

Introduire une table **`mouvements_tresorerie`** (journal), source de vérité des soldes.

Champs minimaux :

| Champ | Rôle |
|-------|------|
| `compte_tresorerie_id` | Compte impacté (ADR-0003) |
| `direction` | `entree` \| `sortie` |
| `montant` | Décimal FCFA (> 0) |
| `date_mouvement` | Date métier |
| `mode` | Réutilise l’enum `ModePaiement` (`especes`, `virement`, `cheque`, `mobile_money`, `autre`) |
| `source_type` | `facture_paiement` \| `bulletin_paie` \| `depense` \| `ajustement` |
| `source_id` | UUID de la source (nullable pour `ajustement` manuel) |
| `reference` | Réf. chèque / virement / transaction MM (optionnel) |
| `notes` | Texte libre |
| `user_id` | Auteur (nullable si système) |

Règles :

1. Un mouvement n’est **jamais** modifié en place pour corriger une erreur métier : on crée un **mouvement inverse** (ou un workflow d’annulation documenté).
2. Soft-delete éventuel réservé aux cas techniques ; le solde métier ignore les soft-deleted.
3. Unicité recommandée `(source_type, source_id)` quand la source n’autorise qu’un seul mouvement (évite double-posting).

## Conséquences

- Soldes toujours auditables et rejouables.
- Stats (mois, mode, compte) = agrégations sur le journal.
- Légère verbosité pour les corrections (mouvement inverse obligatoire).
