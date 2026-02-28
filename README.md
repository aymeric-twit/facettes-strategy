# Analyseur de Facettes

> **EN** -- E-commerce facet qualification tool: analyzes category/attribute/gender combinations using SEMrush volumes, Google Suggest presence, and multi-criteria scoring to decide which faceted pages deserve indexation.

---

## Description

**Analyseur de Facettes** est un outil de qualification SEO pour les facettes e-commerce. Il determine quelles combinaisons categorie/genre/attribut meritent d'etre indexees en pages de listing, en s'appuyant sur :

- Les **volumes de recherche SEMrush** pour quantifier la demande.
- La **presence dans Google Suggest** pour valider l'intention utilisateur.
- Un **scoring multi-criteres** pondere pour produire une decision automatisee (indexer / a evaluer / noindex).
- Une **detection de cannibalisation** entre facettes via le coefficient de Jaccard.

L'outil fonctionne en **mode CLI** (analyse en batch) et en **mode web** (dashboard interactif avec API JSON).

---

## Fonctionnalites

- **Definition du catalogue** : categories hierarchiques, genres, attributs (tailles, couleurs, matieres, etc.) via fichier de configuration PHP ou JSON.
- **Generation de combinaisons** : produit toutes les combinaisons pertinentes categorie x genre x attribut.
- **Enrichissement SEMrush** : recuperation des volumes de recherche, CPC et Keyword Difficulty pour chaque combinaison.
- **Validation Google Suggest** : verifie la presence de chaque requete dans les suggestions Google.
- **Scoring multi-criteres** : ponderation configurable (Volume 35%, Suggest 25%, CPC 20%, KD 20%).
- **Decision d'indexation** : score >= 55 = indexer, 30-55 = a evaluer, < 30 = noindex.
- **Detection de cannibalisation** : coefficient de Jaccard >= 0.6 entre facettes proches.
- **Calcul de tendances** : evolution des metriques dans le temps.
- **Decouverte automatique** : identification de nouvelles facettes potentielles via Google Suggest.
- **Exports** : CSV, JSON, rapport HTML complet.
- **Generation de recommandations** : synthese actionnable pour chaque facette analysee.
- **Gestion des selections** : sauvegarde des categories/genres selectionnes par l'utilisateur.
- **Cache fichier** : mise en cache des reponses API avec TTL configurable (7 jours par defaut).
- **Rate limiting** : respect des limites des API externes.
- **Historique** : conservation des analyses precedentes.
- **Mode CLI** : execution en ligne de commande via `run.php`.
- **Dashboard web** : interface avec graphiques Chart.js et endpoints API JSON.

---

## Prerequis

- **PHP >= 8.2** avec extensions `curl`, `json`, `mbstring`
- **Composer** pour l'autoloading PSR-4
- Une **cle API SEMrush** valide
- Acces reseau vers `api.semrush.com` et `suggestqueries.google.com`

---

## Installation

```bash
cd /home/aymeric/projects/facettes
cp .env.example .env
composer install
```

Pour le developpement local en standalone :

```bash
php -S localhost:8080 -t public/
```

Pour l'integration dans la plateforme SEO, installer via l'interface d'administration (admin > plugins > installer via Git).

---

## Configuration

### Variable d'environnement

Copier `.env.example` vers `.env` et renseigner la cle API :

```
SEMRUSH_API_KEY=votre_cle_api_semrush_ici
```

La cle est chargee par `EnvLoader` au demarrage. En contexte plateforme, la variable est propagee depuis le `.env` global via `boot.php`.

### Catalogue produit

Le catalogue se definit dans `config/catalogue.php` ou `config/catalogue.json`. Il contient :

- Les **categories** (hierarchie avec separateur `>`).
- Les **genres** (homme, femme, enfant, mixte, etc.).
- Les **attributs** par type (tailles, couleurs, matieres, etc.).

### Seuils et scoring

La configuration complete se trouve dans `config/config.php` :

- Seuils de qualification (volumes, taux suggest, CPC) pour facettes simples et combinaisons.
- Ponderation du scoring (volume, suggest, CPC, KD).
- Seuils de decision (indexer >= 55, evaluer 30-55, noindex < 30).
- Seuil de cannibalisation (Jaccard >= 0.6).
- Parametres de rate limiting et TTL du cache.

---

## Utilisation

### Mode web (dashboard)

Lancer le serveur local ou acceder via la plateforme :

```bash
php -S localhost:8080 -t public/
```

Le dashboard propose :

- La selection des categories et genres a analyser.
- Le lancement de l'analyse avec suivi de progression.
- La visualisation des resultats avec graphiques (Chart.js).
- La consultation des recommandations et des risques de cannibalisation.
- L'export des resultats en CSV, JSON ou rapport HTML.

#### Endpoints API

| Methode | Route                    | Description                         |
|---------|--------------------------|-------------------------------------|
| POST    | `/api/analyser`          | Lancer une analyse                  |
| GET     | `/api/resultats`         | Recuperer les resultats             |
| GET     | `/api/exporter`          | Exporter les donnees                |
| GET     | `/api/catalogue`         | Obtenir le catalogue                |
| POST    | `/api/catalogue`         | Mettre a jour le catalogue          |
| GET     | `/api/configuration`     | Lire la configuration               |
| POST    | `/api/configuration`     | Modifier la configuration           |
| GET     | `/api/cache/info`        | Informations sur le cache           |
| POST    | `/api/cache/purger`      | Purger le cache                     |
| GET     | `/api/test-semrush`      | Tester la connexion SEMrush         |
| GET     | `/api/vue-ensemble`      | Vue d'ensemble globale              |
| POST    | `/api/decouvrir-facettes`| Decouverte automatique de facettes  |
| GET     | `/api/selections`        | Lire les selections                 |
| POST    | `/api/selections`        | Modifier les selections             |

### Mode CLI

```bash
# Analyser toutes les categories du catalogue
php run.php

# Analyser une categorie et un genre specifiques
php run.php robes femme

# Categorie hierarchique
php run.php "vetements > pantalons > shorts" femme

# Avec export JSON
php run.php --export=json robes femme
```

---

## Stack technique

| Composant        | Technologie                                       |
|------------------|---------------------------------------------------|
| Langage          | PHP >= 8.2, `strict_types` partout                |
| Autoloading      | Composer PSR-4 (`Facettes\` -> `src/`)            |
| Dependances      | Aucune librairie tierce (PHP pur)                  |
| APIs externes    | SEMrush API, Google Suggest                       |
| Frontend         | HTML/CSS/JS, Chart.js (CDN), Bootstrap 5 (CDN)   |
| Cache            | Fichier local avec TTL (7 jours)                   |
| Integration      | Plugin iframe pour la plateforme SEO              |
| Quota            | `api_call` -- 500 appels/mois (defaut)            |

---

## Architecture

Le code source suit une organisation en couches avec namespace PSR-4 `Facettes\` :

- **`Analysis/`** -- Logique metier de qualification des facettes.
  - `FacetQualifier` -- Orchestration de l'analyse complete d'une facette.
  - `MetricsAggregator` -- Agregation des metriques (volumes, CPC, KD).
  - `Decision` -- Application des seuils et calcul du score final.
  - `CannibalisationDetecteur` -- Detection de cannibalisation via coefficient de Jaccard.
  - `TendanceCalculateur` -- Calcul des tendances d'evolution.
  - `FacetteDecouvreur` -- Decouverte automatique de nouvelles facettes via Suggest.

- **`Api/`** -- Clients pour les APIs externes.
  - `SemrushClient` -- Volumes, CPC, Keyword Difficulty.
  - `GoogleSuggestClient` -- Validation de presence dans les suggestions.

- **`Catalogue/`** -- Gestion du catalogue produit et generation des combinaisons.
  - `CatalogueParser` -- Lecture et parsing du catalogue (PHP/JSON).
  - `CombinationEngine` -- Generation des combinaisons categorie x genre x attribut.
  - `SelectionsManager` -- Sauvegarde des selections utilisateur.

- **`Export/`** -- Generation des exports et recommandations.
  - `CsvExporter` -- Export au format CSV.
  - `JsonExporter` -- Export au format JSON.
  - `HtmlExporter` -- Rapport HTML complet avec graphiques.
  - `RecommandationGenerateur` -- Synthese des recommandations SEO.

- **`Infrastructure/`** -- Services transverses.
  - `Cache` -- Cache fichier avec TTL configurable.
  - `RateLimiter` -- Limitation du debit des appels API.
  - `Logger` -- Journalisation dans des fichiers (avec `NiveauLog` enum).
  - `EnvLoader` -- Chargement des variables d'environnement.
  - `HistoriqueManager` -- Persistance de l'historique des analyses.

- **`Web/`** -- Couche HTTP.
  - `Router` -- Routeur HTTP minimaliste avec parametres nommes.
  - `Controller` -- Controleur unique gerant le dashboard et les endpoints API.

---

## Structure du projet

```
facettes/
├── module.json                 # Metadonnees plugin plateforme
├── boot.php                    # Bootstrap plateforme (autoload + env)
├── composer.json               # Autoload PSR-4 Facettes\ -> src/
├── run.php                     # Point d'entree CLI
├── .env.example                # Template variable d'environnement
├── .gitignore
├── config/
│   ├── config.php              # Configuration (seuils, scoring, rate limiting)
│   ├── catalogue.php           # Definition du catalogue produit (PHP)
│   └── catalogue.json          # Definition du catalogue produit (JSON)
├── src/
│   ├── Analysis/
│   │   ├── CannibalisationDetecteur.php
│   │   ├── Decision.php
│   │   ├── FacetQualifier.php
│   │   ├── FacetteDecouvreur.php
│   │   ├── MetricsAggregator.php
│   │   └── TendanceCalculateur.php
│   ├── Api/
│   │   ├── GoogleSuggestClient.php
│   │   └── SemrushClient.php
│   ├── Catalogue/
│   │   ├── CatalogueParser.php
│   │   ├── CombinationEngine.php
│   │   └── SelectionsManager.php
│   ├── Export/
│   │   ├── CsvExporter.php
│   │   ├── HtmlExporter.php
│   │   ├── JsonExporter.php
│   │   └── RecommandationGenerateur.php
│   ├── Infrastructure/
│   │   ├── Cache.php
│   │   ├── EnvLoader.php
│   │   ├── HistoriqueManager.php
│   │   ├── Logger.php
│   │   ├── NiveauLog.php
│   │   └── RateLimiter.php
│   └── Web/
│       ├── Controller.php
│       └── Router.php
├── public/
│   ├── index.php               # Front controller web
│   ├── css/
│   │   └── dashboard.css
│   └── js/
│       ├── dashboard.js
│       └── graphiques.js
├── templates/
│   └── dashboard.php           # Template dashboard
├── cache/                      # Cache des reponses API
├── data/                       # Donnees persistantes
├── exports/                    # Fichiers exportes
├── logs/                       # Logs applicatifs
└── vendor/                     # Dependances Composer (autoload)
```
