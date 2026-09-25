# ADR-0001 — Contexte borné Trésorerie

- **Statut** : Accepté
- **Date** : 2026-09-25

## Contexte

SIS dispose déjà de deux domaines monétaires distincts :

- **Commercial** : factures, abonnements, `Paiement` (encaissement client).
- **RH / Paie** : contrats, périodes, bulletins, calcul rémunération CI.

Le besoin métier (« comptabilité ») est de suivre le **solde** de l’entreprise, les **dépenses**, et l’impact des **salaires payés** — sans logiciel de comptabilité légale OHADA.

## Décision

Créer un contexte borné nommé **Trésorerie** (pas « Comptabilité » en produit / code).

| Contexte | Possède | Ne possède pas |
|----------|---------|----------------|
| **Trésorerie** | Comptes, mouvements, dépenses, stats cash, enregistrement du règlement paie côté cash | Cycle de vie facture, formules IGR/CNPS, abonnements |
| **Commercial** | Factures, AR, encaissements clients | Solde entreprise, dépenses, règlement salaires |
| **RH / Paie** | Rémunération, périodes, bulletins, PDF | Comptes de caisse, catégories de dépense |

Feature flag : `module.tresorerie`.  
Permissions : `tresorerie.view` / `tresorerie.manage` (+ `depenses.*` si besoin de granularité).  
Navigation : groupe **Trésorerie**.

## Conséquences

- Vocabulaire clair pour l’équipe et le client (pas d’attente « expert-comptable agréé »).
- Évite de surcharger `Paiement` commercial ou `BulletinPaie` avec la logique de solde.
- Nécessite des ponts explicites (ADR-0005, ADR-0006) plutôt qu’un modèle unique fourre-tout.
