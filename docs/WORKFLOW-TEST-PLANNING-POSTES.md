# Workflow de test — Planning postes

**Module :** Opérations → Planning postes  
**Fonctionnalités :** Planifier le poste · Alterner le poste · Conflits / repos légal  
**Public :** Testeur QA  
**Environnement :** admin (rôle avec droits création vacations)

---

## Prérequis

| Élément | Détail |
|--------|--------|
| Compte | Admin ou rôle avec `vacations.create` |
| Données | Au moins 1 site avec 1 poste **jour** et idéalement 1 poste **24h** |
| Agents | Au moins 4 agents `disponible` / `en_activite` (pas en congé) |
| Poste | Noter `agents_requis` (ex. 1 ou 2) et horaires du poste |

**Rappel métier**

- **Planifier le poste** = remplir l’effectif du poste (même rythme pour tous).
- **Alterner le poste** = rotation / cycles (un travaille, l’autre se repose, etc.).
- Horaires = ceux du **poste** (plus de saisie horaires dans le formulaire).
- Repos légal minimum entre deux vacations : **11 h**.

---

## Scénario A — Planifier le poste (effectif)

### A1 — Formulaire réactif au poste

| # | Action | Résultat attendu |
|---|--------|------------------|
| 1 | Aller sur **Planning postes** → **Planifier le poste** | Onglet actif « Planifier le poste » |
| 2 | Sans poste : observer le formulaire | Seule la section Site / Poste est utile ; le reste apparaît après choix du poste |
| 3 | Choisir un **site** puis un **poste** | Affiche : « N agent(s) requis », 24h si applicable, **horaires du poste en lecture seule** |
| 4 | Vérifier | **Pas** de boutons « 1 sem. / 2 sem. / 4 sem. » |
| 5 | Vérifier | **Pas** de presets Jour 07-19 / Nuit / Matin / Aprem |
| 6 | Compter les listes Agents | Nombre de listes = `agents_requis` du poste |
| 7 | Si poste 24h avec 2+ agents | Labels type « Agent A · jour » / « Agent B · nuit » |

### A2 — Planification multi-agents (happy path)

| # | Action | Résultat attendu |
|---|--------|------------------|
| 1 | Poste avec `agents_requis = 2` (ou plus) | 2 (ou N) sélecteurs agents |
| 2 | Choisir N agents distincts | Pas le même agent deux fois |
| 3 | Période : début / fin (ex. 7–14 jours) | Dates OK |
| 4 | Jours travaillés : laisser Lun–Dim ou retirer un jour | Aperçu calendrier se met à jour |
| 5 | Enregistrer | Toast succès ; redirection Planning |
| 6 | Vérifier la grille / liste | Vacations créées pour **tous** les agents sur ce poste / période |
| 7 | Poste 24h | Un agent en jour, l’autre en nuit (selon slots) |

### A3 — Remplacement du planning existant

| # | Action | Résultat attendu |
|---|--------|------------------|
| 1 | Replanifier le **même poste** + **mêmes agents** sur une période qui chevauche | Anciennes vacations de ces agents sur ce poste (période) remplacées |
| 2 | Vérifier | Pas de doublons jour/agent/poste |

### A4 — Cas d’erreur

| # | Action | Résultat attendu |
|---|--------|------------------|
| 1 | Laisser un slot agent vide → Enregistrer | Erreur validation « Agent requis » |
| 2 | Mettre le même agent dans 2 slots | Erreur doublon |
| 3 | Agent en congé (si possible) | Ne doit **pas** apparaître dans la liste |

---

## Scénario B — Alterner le poste (rotation)

### B1 — Formulaire progressif + explications

| # | Action | Résultat attendu |
|---|--------|------------------|
| 1 | Onglet **Alterner le poste** | Description claire |
| 2 | Sans poste | Seule section Site / Poste ; message d’aide |
| 3 | Choisir un poste | Sections 2–4 apparaissent ; horaires poste en lecture seule |
| 4 | Section « Comment ça tourne ? » | Modes adaptés au poste (pas tous si inutiles) |
| 5 | Changer de mode | Nombre de slots agents change ; phrase d’exemple se met à jour |
| 6 | Cycle 1/1, 3/2… | Texte d’explication cohérent (ex. A travaille X j, B se repose…) |

### B2 — Mode « À tour de rôle » (duo) — 1/1

| # | Action | Résultat attendu |
|---|--------|------------------|
| 1 | Poste simple (idéalement 1 requis / duo) | Mode « À tour de rôle » |
| 2 | 2 agents, cycle **1/1**, qui commence = Agent A | Aperçu : A en poste un jour, B l’autre (et inverse) |
| 3 | Appliquer | Vacations créées selon l’alternance |
| 4 | Détail rotation / calendrier | Un seul agent « en poste » par jour (pas les deux en service) |

### B3 — Cycle asymétrique 3/2

| # | Action | Résultat attendu |
|---|--------|------------------|
| 1 | Cycle **3/2**, Agent A commence | Première période : A 3 j / B 2 j |
| 2 | Observer la suite de la période | Tour suivant : durées **inversées** (B 3 j / A 2 j) |

### B4 — Modes équipes / quarts / roulement (si disponibles)

| # | Action | Résultat attendu |
|---|--------|------------------|
| 1 | Poste avec effectif ≥ 2 | Modes « Deux équipes », « Tous en service », « Roulement » selon le poste |
| 2 | Équipes | Première moitié = équipe A, seconde = B |
| 3 | Quarts 24h | Répartition jour/nuit le même jour |
| 4 | Appliquer + vérifier grille | Cohérent avec la phrase d’exemple |

---

## Scénario C — Conflits & repos insuffisant

### C1 — Message d’erreur explicite

| # | Action | Résultat attendu |
|---|--------|------------------|
| 1 | Agent déjà planifié ailleurs juste avant/après le créneau (écart &lt; 11 h) | |
| 2 | Lancer Planifier ou Alterner | Erreur du type : **nom agent + matricule**, vacation à créer, vacation déjà planifiée (date, heures, site/poste), écart en heures |

### C2 — Annuler toutes les vacations bloquantes (en un clic)

| # | Action | Résultat attendu |
|---|--------|------------------|
| 1 | Sur l’alerte rouge | Bouton **« Annuler les N vacations et réappliquer »** (ou 1 seule) |
| 2 | Confirmer | Vacations bloquantes → statut **annulée** |
| 3 | Suite auto | Planning réappliqué sans re-cliquer manuellement une par une |
| 4 | Vérifier | Nouvelles vacations OK ; anciennes bloquantes annulées |

### C3 — Conflit chevauchement (même créneau)

| # | Action | Résultat attendu |
|---|--------|------------------|
| 1 | Agent déjà en vacation qui chevauche | Erreur conflit + possibilité d’annuler / réappliquer |
| 2 | Après annulation | Création possible |

---

## Scénario D — Navigation & non-régression UI

| # | Action | Résultat attendu |
|---|--------|------------------|
| 1 | Basculer Planifier ↔ Alterner (même site en query si présent) | Site conservé si `site_id` dans l’URL |
| 2 | Liens depuis Planning postes | Libellés « Planifier le poste » / « Alterner le poste » |
| 3 | Mobile | Boutons bas de page ; formulaires utilisables |
| 4 | Aperçu calendrier | Légende créneaux / déjà planifié / repos / conflit cohérente |

---

## Jeu de données suggéré (demo)

1. **Poste A** — jour 07:00–19:00, `agents_requis = 2`  
2. **Poste B** — 24h (jour + nuit), `agents_requis = 2`  
3. Agents : Kouassi, Ibrahim, + 2 autres disponibles  
4. Préparer volontairement une vacation « piège » pour C1 (fin à 07:00 le jour J sur un autre site)

---

## Checklist de validation finale

- [ ] Planifier : N agents d’un coup selon le poste  
- [ ] Pas de saisie horaires / presets semaines  
- [ ] Alterner : modes clairs + exemple dynamique  
- [ ] Cycle 1/1 et 3/2 corrects  
- [ ] Message repos avec **qui** + **quelle vacation**  
- [ ] Annulation groupée + réapplication en 1 confirmation  
- [ ] Agents en congé exclus des listes  
- [ ] Grille Planning cohérente après enregistrement  

---

## Bugs à remonter (format)

Pour chaque anomalie :

1. **Écran** (Planifier / Alterner / Planning)  
2. **Poste + agents** utilisés  
3. **Étapes** reproduites  
4. **Attendu** vs **obtenu** (capture + message d’erreur exact)  
5. **Navigateur** + date/heure  

---

*Document généré pour la campagne de test Planning postes (juillet 2026).*
