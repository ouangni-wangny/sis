# Workflow — Module Trésorerie

Voici le workflow ajouté avec le module **Trésorerie**.

Décisions structurantes : [docs/adr/](adr/README.md) (ADR-0001 à ADR-0007).

---

## Idée générale

On ne fait pas de comptabilité légale. On suit l’**argent réel** de l’entreprise via :

1. des **comptes** (Banque, Caisse, Mobile Money)
2. un **journal de mouvements** (entrées / sorties)
3. le **solde** = solde d’ouverture + entrées − sorties

```mermaid
flowchart LR
  subgraph entrees [Entrées]
    FacturePaiement[Encaissement client]
  end
  subgraph sorties [Sorties]
    Depense[Dépense]
    Paie[Règlement salaire]
    Ajust[Ajustement]
  end
  FacturePaiement --> Journal[Journal mouvements]
  Depense --> Journal
  Paie --> Journal
  Ajust --> Journal
  Journal --> Comptes[Soldes Banque / Caisse / MM]
```

---

## 0. Paramétrage (`/parametres`)

Avant d’utiliser les flux, on paramètre les référentiels (permission `grades.manage`) :

| Onglet | Rôle |
|--------|------|
| **Catégories** | Catégories de dépense (loyer, carburant, etc.) |
| **Comptes** | Comptes Banque / Caisse / Mobile Money + solde d’ouverture |
| **Modes** | Modes de paiement (espèces, virement, Mobile Money, etc.) |

Les formulaires (dépenses, paie, encaissements, ajustements) consomment ces listes actives.

---

## 1. Dépenses (`/tresorerie/depenses`)

1. RH/admin saisit une dépense (catégorie, libellé, montant, date, **compte**, mode).
2. Le système crée la dépense **et** un mouvement **sortie** sur le compte choisi.
3. Le solde du compte baisse tout de suite.

Annuler une dépense → mouvement **inverse** (entrée) sur le même compte + statut **Annulée**.
Le solde revient comme avant la dépense (sortie d’origine + entrée d’annulation = net 0).

Si d’anciennes annulations avaient soft-deleté *et* créé un inverse (solde trop haut) :
`php artisan tresorerie:fix-double-reverse`

---

## 2. Paie (`/rh/paie`)

Le calcul salarial (brut, CNPS, IGR, net) **ne change pas** à la génération (base contrat).

Le règlement se fait en **deux étapes** :

### Étape A — RH (`paie.manage`, voit les salaires via `contrats.manage`)

Saisir le **salaire perçu** du mois (ajustable selon jours travaillés) sur chaque bulletin — unitaire ou groupé.  
Cela met à jour `salaire_net` / `details.salaire_percu` **sans** passer le statut à `paye`.

### Étape B — Comptable (`paie.payer`, **ne voit pas** les montants)

Marquer payé (unitaire ou groupé) avec uniquement :

- moyen de paiement
- compte débité
- référence optionnelle

Le montant débité est celui déjà saisi par la RH. L’API renvoie `salaire_*` à `null` et un flag `salaire_renseigne` pour savoir si le bulletin est prêt.

Puis, en une transaction :

1. bulletin → statut `paye` + date + mode + compte
2. mouvement **sortie** = montant perçu sur ce compte

Valider la période passe aussi les bulletins brouillon → `valide`.

Les PDF bulletins sont réservés aux profils qui peuvent voir les salaires (RH).

Les stats Trésorerie montrent la masse salariale **payée** du mois, par mode et par compte.

---

## 3. Encaissements clients (`/paiements`)

Un paiement facture reste un encaissement commercial.

En plus, à la création :

1. on choisit le **compte** qui reçoit l’argent
2. un mouvement **entrée** est posté sur ce compte

Modification / suppression → annulation (mouvement inverse) puis éventuel nouveau mouvement.

---

## 4. Vue Trésorerie (`/tresorerie`)

- solde par compte + solde consolidé
- journal des mouvements
- dépenses du mois
- salaires payés du mois
- ajustement manuel (entrée/sortie hors facture/paie/dépense)

---

## Ce qui ne se mélange pas

| Domaine | Rôle |
|--------|------|
| **Commercial** | Factures, créances (`/recouvrement`), qui a payé quoi |
| **RH / Paie** | Qui gagne quoi, bulletins |
| **Trésorerie** | Où est l’argent, entrées/sorties, soldes |

Même cashbook, trois portes d’entrée : dépense, salaire, encaissement client.

Le **recouvrement** reste Commercial jusqu’à l’encaissement ; Trésorerie ne voit que le cash une fois le paiement enregistré.

---

## Feature flag & droits

| Élément | Valeur |
|---------|--------|
| Feature flag | `module.tresorerie` |
| Permissions | `tresorerie.view` / `tresorerie.manage`, `depenses.view` / `depenses.manage` |
| Paie | RH : `paie.manage` (+ `contrats.manage` pour voir salaires) · Comptable : `paie.view` + `paie.payer` |
| Paramètres référentiels | `grades.manage` (ou `tresorerie.manage`) |
