# CLAUDE.md — Analyseur de Facettes SEO

## Vue d'ensemble

**Analyseur de Facettes** est un outil de qualification SEO des facettes e-commerce. Il analyse les combinaisons catégorie/genre/attribut pour déterminer lesquelles méritent d'être indexées (pages de listing) en se basant sur les volumes de recherche Semrush, la présence dans Google Suggest, et un scoring multi-critères. L'outil détecte aussi les risques de cannibalisation entre facettes.

---

## Architecture

Application **PHP PSR-4** (namespace `Facettes\`) avec CLI + interface web, routeur custom, et APIs Semrush/Google Suggest.

```
facettes/
├── composer.json                # Autoload PSR-4 Facettes\ → src/
├── .env                         # Clé API Semrush (SEMRUSH_API_KEY)
├── .env.example
├── run.php                      # Point d'entrée CLI
├── config/
│   ├── config.php               # Configuration (seuils, scoring, rate limiting)
│   ├── catalogue.php            # Définition du catalogue produit
│   └── catalogue.json           # Catalogue au format JSON
├── src/
│   ├── Analysis/
│   │   ├── CannibalisationDetecteur.php   # Détection cannibalisation (Jaccard)
│   │   ├── Decision.php                    # Décision indexer/noindex
│   │   ├── FacetQualifier.php             # Qualification des facettes
│   │   ├── FacetteDecouvreur.php          # Découverte automatique de facettes
│   │   ├── MetricsAggregator.php          # Agrégation des métriques
│   │   └── TendanceCalculateur.php        # Calcul de tendances
│   ├── Api/
│   │   ├── GoogleSuggestClient.php        # Client Google Suggest
│   │   └── SemrushClient.php              # Client API Semrush
│   ├── Catalogue/
│   │   ├── CatalogueParser.php            # Parsing du catalogue produit
│   │   ├── CombinationEngine.php          # Génération des combinaisons facettes
│   │   └── SelectionsManager.php          # Gestion des sélections utilisateur
│   ├── Export/
│   │   ├── CsvExporter.php                # Export CSV
│   │   ├── HtmlExporter.php               # Export rapport HTML
│   │   ├── JsonExporter.php               # Export JSON
│   │   └── RecommandationGenerateur.php   # Génération de recommandations
│   ├── Infrastructure/
│   │   ├── Cache.php                      # Cache fichier (TTL configurable)
│   │   ├── EnvLoader.php                  # Chargement .env custom
│   │   ├── HistoriqueManager.php          # Historique des analyses
│   │   ├── Logger.php                     # Logger fichier
│   │   ├── NiveauLog.php                  # Enum niveaux de log
│   │   └── RateLimiter.php                # Rate limiting API
│   └── Web/
│       ├── Controller.php                 # Contrôleur web (dashboard + API)
│       └── Router.php                     # Routeur HTTP minimaliste
├── public/
│   ├── index.php               # Front controller web
│   ├── css/dashboard.css       # Styles dashboard
│   └── js/
│       ├── dashboard.js        # JS principal dashboard
│       └── graphiques.js       # Graphiques Chart.js
├── templates/
│   └── dashboard.php           # Template dashboard
├── cache/                      # Cache des réponses API
├── exports/                    # Fichiers exportés
├── logs/                       # Logs applicatifs
├── module.json                 # Métadonnées plugin plateforme
└── boot.php                    # Bootstrap plateforme
```

---

## Modes d'utilisation

### CLI (`run.php`)

```bash
php run.php                                        # Analyse toutes les catégories
php run.php robes femme                            # Catégorie + genre spécifique
php run.php "vetements > pantalons > shorts" femme # Catégorie hiérarchique
php run.php --export=json robes femme              # Avec export
```

### Web (dashboard)

L'interface web est un SPA-like avec un dashboard et des endpoints API JSON.

#### Routes pages

| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/` | Dashboard principal |

#### Routes API

| Méthode | Route | Description |
|---------|-------|-------------|
| POST | `/api/analyser` | Lancer une analyse |
| GET | `/api/resultats` | Récupérer les résultats |
| GET | `/api/exporter` | Exporter les données |
| GET | `/api/catalogue` | Obtenir le catalogue |
| POST | `/api/catalogue` | Mettre à jour le catalogue |
| GET | `/api/configuration` | Lire la configuration |
| POST | `/api/configuration` | Modifier la configuration |
| GET | `/api/cache/info` | Infos sur le cache |
| POST | `/api/cache/purger` | Purger le cache |
| GET | `/api/test-semrush` | Tester la connexion Semrush |
| GET | `/api/vue-ensemble` | Vue d'ensemble globale |
| POST | `/api/decouvrir-facettes` | Découverte automatique |
| GET | `/api/selections` | Lire les sélections |
| POST | `/api/selections` | Modifier les sélections |

---

## Configuration (`config/config.php`)

### Seuils de qualification

- **Facettes simples** : volume total ≥ 500, volume médian ≥ 50, taux suggest ≥ 30%, CPC moyen ≥ 0.20€
- **Combinaisons** : volume total ≥ 200, volume médian ≥ 20, taux suggest ≥ 15%, CPC moyen ≥ 0.10€

### Scoring (pondération)

- Volume : 35% | Suggest : 25% | CPC : 20% | KD : 20%
- Score ≥ 55 = indexer | 30-55 = à évaluer | < 30 = noindex

### Cannibalisation

- Coefficient Jaccard ≥ 0.6 = risque de cannibalisation

---

## Intégration plateforme

- **Display mode** : `iframe` — application autonome dans un iframe
- **Quota** : `api_call` — contrôle fin par le plugin (appels Semrush API)
- **Env keys** : `SEMRUSH_API_KEY`
- **boot.php** : charge `vendor/autoload.php` + `.env`
- L'interface web fonctionne en standalone ou dans le contexte iframe de la plateforme

---

## Dépendances

### Composer (`composer.json`)

| Package | Version | Usage |
|---------|---------|-------|
| PHP | >=8.2 | Requis |

Pas de dépendance Composer tierce — tout est implémenté en PHP pur (clients API, cache, logger, routeur).

### Autoload PSR-4

```
Facettes\ → src/
```

---

## Conventions

- Code en **français** (variables, fonctions, classes, commentaires, interface)
- Namespace **PSR-4** : `Facettes\Analysis`, `Facettes\Api`, `Facettes\Catalogue`, `Facettes\Export`, `Facettes\Infrastructure`, `Facettes\Web`
- `declare(strict_types=1)` dans tous les fichiers
- Routeur custom (`Facettes\Web\Router`) avec support paramètres nommés `{param}`
- Pattern format requête configurable : `{categorie} {genre} {facettes}`
- Cache fichier avec TTL (7 jours par défaut)
- Rate limiting intégré pour les API externes
