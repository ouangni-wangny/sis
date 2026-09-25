# ADR-0004 — Dépenses d’entreprise

- **Statut** : Accepté
- **Date** : 2026-09-25
- **Liés** : ADR-0001, ADR-0002, ADR-0003

## Contexte

L’entreprise doit enregistrer toutes ses dépenses (loyer, carburant, fournitures, etc.) et voir l’impact immédiat sur le solde du compte choisi. Aucune table dépense n’existe aujourd’hui.

## Décision

Introduire :

1. **`categories_depense`** — référentiel (libelle, actif). Seed de catégories courantes (ex. Loyer, Carburant, Fournitures, Maintenance, Télécom, Divers).
2. **`depenses`** — enregistrement métier :
   - `categorie_id`, `libelle`, `montant`, `date_depense`
   - `compte_tresorerie_id`, `mode` (`ModePaiement`)
   - `reference`, `notes`
   - pièce jointe optionnelle via Spatie Media Library (collection `justificatif`, disk `private`)
   - `user_id` auteur

**Règle d’or** : la création d’une dépense crée **atomiquement** un mouvement `sortie` (`source_type = depense`).  
La suppression / annulation d’une dépense crée un mouvement inverse (ou soft-delete cohérent journal + dépense) — jamais une dépense « orpheline » sans impact cash, ni un cash sans dépense métier pour ce type.

Hors scope phase 1 : workflow d’approbation multi-niveaux, fournisseurs complets (AP), TVA déductible détaillée.

## Conséquences

- UI simple : formulaire dépense → solde mis à jour.
- Stats « dépenses du mois par catégorie » triviales.
- Distinction claire avec les sorties de **paie** (source `bulletin_paie`, ADR-0005).
