<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analyseur de Facettes SEO</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/dashboard.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-inner">
            <span class="navbar-brand">Analyseur Facettes</span>
            <span class="navbar-subtitle">Qualification SEO des filtres à facettes</span>
        </div>
    </nav>

    <main class="container">
        <!-- Onglets -->
        <div class="tabs" role="tablist">
            <button class="tab active" data-tab="accueil" role="tab" aria-selected="true">Tableau de bord</button>
            <button class="tab" data-tab="resultats" role="tab" aria-selected="false">Résultats</button>
            <button class="tab" data-tab="vue-ensemble" role="tab" aria-selected="false">Vue d'ensemble</button>
            <button class="tab" data-tab="configuration" role="tab" aria-selected="false">Configuration</button>
            <button class="tab" data-tab="catalogue" role="tab" aria-selected="false">Catalogue</button>
        </div>

        <!-- TAB: Accueil -->
        <section id="tab-accueil" class="tab-panel active">
            <div class="card">
                <div class="card-header">
                    <h2>Vue synthétique</h2>
                </div>
                <div class="card-body">
                    <div class="stats-grid" id="stats-grid">
                        <div class="stat-card">
                            <div class="stat-valeur" id="stat-categories">
                                <?= count($categories ?? []) ?>
                            </div>
                            <div class="stat-label">Catégories configurées</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-valeur" id="stat-analyses">
                                <?= count($resultats_existants ?? []) ?>
                            </div>
                            <div class="stat-label">Analyses réalisées</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-valeur" id="stat-index">—</div>
                            <div class="stat-label">% Index global</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Barre de progression -->
            <div class="card" id="carte-progression" style="display: none;">
                <div class="card-header">
                    <h2>Analyse en cours</h2>
                </div>
                <div class="card-body">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progress-fill" style="width: 0%"></div>
                    </div>
                    <p class="progress-text" id="progress-text">Initialisation...</p>
                </div>
            </div>

            <!-- Lancement d'analyse -->
            <div class="card">
                <div class="card-header">
                    <h2>Lancer une analyse</h2>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="select-categorie">Catégorie</label>
                            <select id="select-categorie" class="form-control">
                                <option value="">— Sélectionner —</option>
                                <?php foreach ($categories ?? [] as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat['chemin']) ?>">
                                        <?= str_repeat('&nbsp;&nbsp;&nbsp;', $cat['profondeur']) ?><?= $cat['profondeur'] > 0 ? '└ ' : '' ?><?= ucfirst(htmlspecialchars($cat['nom'])) ?>
                                        (<?= $cat['nb_facettes'] ?> facettes)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="select-genre">Genre</label>
                            <select id="select-genre" class="form-control" disabled>
                                <option value="">— Sélectionner la catégorie —</option>
                            </select>
                        </div>
                    </div>

                    <!-- Arborescence des facettes -->
                    <div id="conteneur-arbre-facettes" style="display: none; margin-top: 1rem;">
                        <div class="arbre-entete">
                            <label class="arbre-titre">Facettes à analyser</label>
                            <div class="arbre-actions-globales">
                                <button type="button" id="btn-tout-cocher" class="btn btn-sm btn-outline">Tout</button>
                                <button type="button" id="btn-tout-decocher" class="btn btn-sm btn-outline">Rien</button>
                            </div>
                        </div>
                        <div id="arbre-facettes" class="arbre-facettes"></div>
                        <div class="arbre-pied">
                            <span id="compteur-selection" class="compteur-selection"></span>
                            <div style="display: flex; gap: 0.5rem;">
                                <button id="btn-decouvrir" class="btn btn-outline btn-sm" disabled>
                                    Découvrir
                                </button>
                                <button id="btn-analyser" class="btn btn-primary" disabled>
                                    Lancer l'analyse
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Suggestions de facettes -->
                    <div id="conteneur-suggestions" style="display: none; margin-top: 1rem;">
                        <div class="card" style="margin-bottom: 0;">
                            <div class="card-header">
                                <h2>Facettes suggérées</h2>
                                <button id="btn-fermer-suggestions" class="btn btn-sm btn-outline">&times;</button>
                            </div>
                            <div class="card-body" id="liste-suggestions"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analyses existantes -->
            <?php if (!empty($resultats_existants)): ?>
            <div class="card">
                <div class="card-header">
                    <h2>Analyses disponibles</h2>
                </div>
                <div class="card-body">
                    <div class="liste-analyses">
                        <?php foreach ($resultats_existants as $r): ?>
                            <button class="btn btn-outline btn-analyse-existante"
                                    data-categorie="<?= htmlspecialchars($r['categorie']) ?>"
                                    data-genre="<?= htmlspecialchars($r['genre']) ?>">
                                <?= htmlspecialchars($r['categorie']) ?> / <?= htmlspecialchars($r['genre']) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </section>

        <!-- TAB: Résultats -->
        <section id="tab-resultats" class="tab-panel">
            <div class="card">
                <div class="card-header">
                    <h2>Résultats d'analyse</h2>
                    <div class="card-actions" id="export-actions" style="display: none;">
                        <button class="btn btn-outline btn-sm" data-export="csv">CSV</button>
                        <button class="btn btn-outline btn-sm" data-export="json">JSON</button>
                        <button class="btn btn-outline btn-sm" data-export="html">HTML</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="res-categorie">Catégorie</label>
                            <select id="res-categorie" class="form-control">
                                <option value="">— Sélectionner —</option>
                                <?php foreach ($categories ?? [] as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat['chemin']) ?>">
                                        <?= str_repeat('&nbsp;&nbsp;&nbsp;', $cat['profondeur']) ?><?= $cat['profondeur'] > 0 ? '└ ' : '' ?><?= ucfirst(htmlspecialchars($cat['nom'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="res-genre">Genre</label>
                            <select id="res-genre" class="form-control" disabled>
                                <option value="">—</option>
                            </select>
                        </div>
                        <div class="form-group form-group-btn">
                            <button id="btn-charger-resultats" class="btn btn-primary" disabled>
                                Charger
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Simulateur de seuils -->
            <div id="simulateur-seuils" style="display: none;">
                <div class="simulateur-panneau">
                    <h3>Simulateur de seuils</h3>
                    <div class="simulateur-grid">
                        <div class="simulateur-groupe">
                            <label>Poids Volume</label>
                            <input type="range" min="0" max="100" value="35" data-sim="poids_volume">
                            <span class="simulateur-valeur">35</span>
                        </div>
                        <div class="simulateur-groupe">
                            <label>Poids Suggest</label>
                            <input type="range" min="0" max="100" value="25" data-sim="poids_suggest">
                            <span class="simulateur-valeur">25</span>
                        </div>
                        <div class="simulateur-groupe">
                            <label>Poids CPC</label>
                            <input type="range" min="0" max="100" value="20" data-sim="poids_cpc">
                            <span class="simulateur-valeur">20</span>
                        </div>
                        <div class="simulateur-groupe">
                            <label>Poids KD</label>
                            <input type="range" min="0" max="100" value="20" data-sim="poids_kd">
                            <span class="simulateur-valeur">20</span>
                        </div>
                        <div class="simulateur-groupe">
                            <label>Seuil INDEX</label>
                            <input type="range" min="0" max="100" value="55" data-sim="seuil_index">
                            <span class="simulateur-valeur">55</span>
                        </div>
                        <div class="simulateur-groupe">
                            <label>Plafond Volume</label>
                            <input type="range" min="500" max="20000" step="500" value="5000" data-sim="plafond_volume">
                            <span class="simulateur-valeur">5000</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtres par zone -->
            <div id="filtres-zone" class="filtres-zone" style="display: none;">
                <button class="filtre-zone-btn actif" data-zone="tous">Tous</button>
                <button class="filtre-zone-btn" data-zone="QUICK_WIN">Quick Win</button>
                <button class="filtre-zone-btn" data-zone="FORT_POTENTIEL">Fort potentiel</button>
                <button class="filtre-zone-btn" data-zone="NICHE">Niche</button>
                <button class="filtre-zone-btn" data-zone="SURVEILLER">Surveiller</button>
                <button class="filtre-zone-btn" data-zone="IGNORER">Ignorer</button>
            </div>

            <!-- Alertes cannibalisation -->
            <div id="carte-cannibalisation" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <h2>Alertes de cannibalisation</h2>
                    </div>
                    <div class="card-body" id="liste-cannibalisation"></div>
                </div>
            </div>

            <!-- Niveau 1 -->
            <div class="card" id="carte-niveau1" style="display: none;">
                <div class="card-header">
                    <h2>Niveau 1 — Facettes simples</h2>
                </div>
                <div class="card-body">
                    <table class="table sortable" id="table-simples">
                        <thead>
                            <tr>
                                <th data-sort="string">Type</th>
                                <th data-sort="string">Décision</th>
                                <th data-sort="number">Score</th>
                                <th data-sort="string">Zone</th>
                                <th data-sort="number">Volume total</th>
                                <th data-sort="number">Volume médian</th>
                                <th data-sort="number">Taux Suggest</th>
                                <th data-sort="number">CPC moyen</th>
                                <th data-sort="number">KD moyen</th>
                                <th data-sort="string">Tendance</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- Niveau 2 -->
            <div class="card" id="carte-niveau2" style="display: none;">
                <div class="card-header">
                    <h2>Niveau 2 — Combinaisons</h2>
                </div>
                <div class="card-body">
                    <table class="table sortable" id="table-combinaisons">
                        <thead>
                            <tr>
                                <th data-sort="string">Combinaison</th>
                                <th data-sort="string">Décision</th>
                                <th data-sort="number">Score</th>
                                <th data-sort="string">Zone</th>
                                <th data-sort="number">Volume total</th>
                                <th data-sort="number">Volume médian</th>
                                <th data-sort="number">Taux Suggest</th>
                                <th data-sort="number">CPC moyen</th>
                                <th data-sort="number">KD moyen</th>
                                <th data-sort="string">Tendance</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- Graphiques -->
            <div class="card" id="carte-graphiques" style="display: none;">
                <div class="card-header">
                    <h2>Visualisations</h2>
                </div>
                <div class="card-body">
                    <div class="graphiques-conteneur">
                        <div class="graphique-card">
                            <h3>Comparatif par score</h3>
                            <div id="graphique-barres"></div>
                        </div>
                        <div class="graphique-card">
                            <h3>Matrice Volume × KD</h3>
                            <div id="graphique-scatter"></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- TAB: Vue d'ensemble -->
        <section id="tab-vue-ensemble" class="tab-panel">
            <div class="card">
                <div class="card-header">
                    <h2>Vue d'ensemble — Comparaison cross-catégories</h2>
                </div>
                <div class="card-body">
                    <div id="vue-ensemble-contenu">
                        <p style="color: #999; font-style: italic;">Chargement...</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- TAB: Configuration -->
        <section id="tab-configuration" class="tab-panel">
            <!-- Seuils -->
            <div class="card">
                <div class="card-header">
                    <h2>Seuils de qualification</h2>
                </div>
                <div class="card-body">
                    <h3>Facettes simples</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="seuil-s-volume-total">Volume total min</label>
                            <input type="number" id="seuil-s-volume-total" class="form-control" data-seuil="simple.volume_total_min">
                        </div>
                        <div class="form-group">
                            <label for="seuil-s-volume-median">Volume médian min</label>
                            <input type="number" id="seuil-s-volume-median" class="form-control" data-seuil="simple.volume_median_min">
                        </div>
                        <div class="form-group">
                            <label for="seuil-s-taux-suggest">Taux Suggest min</label>
                            <input type="number" id="seuil-s-taux-suggest" class="form-control" step="0.01" data-seuil="simple.taux_suggest_min">
                        </div>
                        <div class="form-group">
                            <label for="seuil-s-cpc">CPC moyen min</label>
                            <input type="number" id="seuil-s-cpc" class="form-control" step="0.01" data-seuil="simple.cpc_moyen_min">
                        </div>
                    </div>

                    <h3 style="margin-top: 1.5rem;">Combinaisons</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="seuil-c-volume-total">Volume total min</label>
                            <input type="number" id="seuil-c-volume-total" class="form-control" data-seuil="combinaison.volume_total_min">
                        </div>
                        <div class="form-group">
                            <label for="seuil-c-volume-median">Volume médian min</label>
                            <input type="number" id="seuil-c-volume-median" class="form-control" data-seuil="combinaison.volume_median_min">
                        </div>
                        <div class="form-group">
                            <label for="seuil-c-taux-suggest">Taux Suggest min</label>
                            <input type="number" id="seuil-c-taux-suggest" class="form-control" step="0.01" data-seuil="combinaison.taux_suggest_min">
                        </div>
                        <div class="form-group">
                            <label for="seuil-c-cpc">CPC moyen min</label>
                            <input type="number" id="seuil-c-cpc" class="form-control" step="0.01" data-seuil="combinaison.cpc_moyen_min">
                        </div>
                    </div>

                    <button id="btn-sauver-seuils" class="btn btn-primary" style="margin-top: 1rem;">
                        Sauvegarder les seuils
                    </button>
                    <span id="seuils-statut" class="statut-message"></span>
                </div>
            </div>

            <!-- API SEMrush -->
            <div class="card">
                <div class="card-header">
                    <h2>Statut API SEMrush</h2>
                </div>
                <div class="card-body">
                    <button id="btn-test-semrush" class="btn btn-outline">
                        Tester la connexion
                    </button>
                    <span id="semrush-statut" class="statut-message"></span>
                </div>
            </div>

            <!-- Cache -->
            <div class="card">
                <div class="card-header">
                    <h2>Gestion du cache</h2>
                </div>
                <div class="card-body">
                    <div class="cache-info">
                        <span id="cache-taille">Chargement...</span>
                    </div>
                    <div class="form-row" style="margin-top: 1rem;">
                        <div class="form-group">
                            <label for="cache-prefixe">Préfixe (vide = tout purger)</label>
                            <input type="text" id="cache-prefixe" class="form-control" placeholder="Ex: semrush, suggest, resultats">
                        </div>
                        <div class="form-group form-group-btn">
                            <button id="btn-purger-cache" class="btn btn-danger">
                                Purger le cache
                            </button>
                        </div>
                    </div>
                    <span id="cache-statut" class="statut-message"></span>
                </div>
            </div>
        </section>
        <!-- TAB: Catalogue -->
        <section id="tab-catalogue" class="tab-panel">
            <div class="card">
                <div class="card-header">
                    <h2>Éditeur de catalogue</h2>
                    <div class="card-actions">
                        <button id="btn-sauver-catalogue" class="btn btn-primary" disabled>
                            Sauvegarder
                        </button>
                        <span id="catalogue-statut" class="statut-message"></span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="catalogue-editeur">
                        <!-- Panneau gauche : catégories -->
                        <div class="catalogue-panneau">
                            <div class="panneau-entete">
                                <h3>Catégories</h3>
                                <div class="panneau-actions">
                                    <button class="btn btn-sm btn-outline" id="btn-cat-ajouter" title="Ajouter à la racine">+</button>
                                    <button class="btn btn-sm btn-outline" id="btn-sous-cat-ajouter" title="Ajouter sous-catégorie" disabled>+&#8615;</button>
                                    <button class="btn btn-sm btn-outline" id="btn-cat-renommer" title="Renommer" disabled>&#9998;</button>
                                    <button class="btn btn-sm btn-danger btn-sm" id="btn-cat-supprimer" title="Supprimer" disabled>&times;</button>
                                </div>
                            </div>
                            <div class="liste-elements" id="liste-categories"></div>
                        </div>

                        <!-- Panneau droit : détail de la catégorie sélectionnée -->
                        <div class="catalogue-panneau catalogue-panneau-detail" id="panneau-detail-categorie">
                            <p class="detail-placeholder">Sélectionnez une catégorie pour voir son détail.</p>

                            <!-- Genres -->
                            <div class="detail-section" id="section-genres" style="display: none;">
                                <div class="panneau-entete">
                                    <h3>Genres</h3>
                                    <div class="panneau-actions">
                                        <button class="btn btn-sm btn-outline" id="btn-genre-ajouter" title="Ajouter">+</button>
                                        <button class="btn btn-sm btn-danger btn-sm" id="btn-genre-supprimer" title="Supprimer" disabled>&times;</button>
                                    </div>
                                </div>
                                <div class="liste-elements" id="liste-genres"></div>
                            </div>

                            <!-- Types de facettes -->
                            <div class="detail-section" id="section-types" style="display: none;">
                                <div class="panneau-entete">
                                    <h3>Types de facettes</h3>
                                    <div class="panneau-actions">
                                        <button class="btn btn-sm btn-outline" id="btn-type-ajouter" title="Ajouter">+</button>
                                        <button class="btn btn-sm btn-outline" id="btn-type-renommer" title="Renommer" disabled>&#9998;</button>
                                        <button class="btn btn-sm btn-danger btn-sm" id="btn-type-supprimer" title="Supprimer" disabled>&times;</button>
                                    </div>
                                </div>
                                <div class="liste-elements" id="liste-types"></div>
                            </div>

                            <!-- Valeurs de la facette sélectionnée -->
                            <div class="detail-section" id="section-valeurs" style="display: none;">
                                <div class="panneau-entete">
                                    <h3 id="titre-valeurs">Valeurs</h3>
                                    <div class="panneau-actions">
                                        <button class="btn btn-sm btn-outline" id="btn-valeur-ajouter" title="Ajouter">+</button>
                                        <button class="btn btn-sm btn-danger btn-sm" id="btn-valeur-supprimer" title="Supprimer" disabled>&times;</button>
                                    </div>
                                </div>
                                <div class="liste-elements" id="liste-valeurs"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sélection des facettes par combinaison -->
            <div class="card">
                <div class="card-header">
                    <h2>Sélection des facettes pour l'analyse</h2>
                    <div class="card-actions">
                        <span id="selections-statut" class="statut-message"></span>
                        <button id="btn-sauver-selections" class="btn btn-primary btn-sm">
                            Sauvegarder
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="selecteur-facettes">
                        <div class="selecteur-facettes-filtres">
                            <div class="form-group">
                                <label for="sel-facettes-categorie">Catégorie</label>
                                <select id="sel-facettes-categorie" class="form-control">
                                    <option value="">— Sélectionner —</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="sel-facettes-genre">Genre</label>
                                <select id="sel-facettes-genre" class="form-control" disabled>
                                    <option value="">— Sélectionner —</option>
                                </select>
                            </div>
                        </div>
                        <div id="selecteur-facettes-contenu" class="selecteur-facettes-contenu">
                            <p style="color: #999; font-style: italic; padding: 1rem;">Sélectionnez une catégorie et un genre pour configurer les facettes.</p>
                        </div>
                        <div class="selecteur-facettes-pied">
                            <span id="selecteur-compteur-global" class="compteur-selection"></span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Données du catalogue injectées pour le JS -->
    <script>
        window.CATALOGUE_MAP = {};
        <?php foreach ($categories ?? [] as $cat): ?>
            window.CATALOGUE_MAP[<?= json_encode($cat['chemin']) ?>] = <?= json_encode($cat['genres']) ?>;
        <?php endforeach; ?>
        window.CATALOGUE_COMPLET = <?= json_encode($catalogue_complet ?? [], JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <script src="/js/graphiques.js"></script>
    <script src="/js/dashboard.js"></script>
</body>
</html>
