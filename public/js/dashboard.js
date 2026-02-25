/**
 * Dashboard Analyseur de Facettes SEO
 * JS vanilla — onglets, tri, fetch, accordéons, graphiques, simulateur
 * Supporte les catégories hiérarchiques multi-niveaux
 */
(function () {
    'use strict';

    // === Utilitaire : résoudre un chemin hiérarchique dans CATALOGUE_COMPLET ===
    function resoudreNoeudJS(chemin) {
        if (!chemin || !window.CATALOGUE_COMPLET) return null;

        const segments = chemin.split('>').map(s => s.trim());
        let noeud = window.CATALOGUE_COMPLET[segments[0]];

        if (!noeud) return null;

        for (let i = 1; i < segments.length; i++) {
            if (!noeud.sous_categories || !noeud.sous_categories[segments[i]]) return null;
            noeud = noeud.sous_categories[segments[i]];
        }

        return noeud;
    }

    /**
     * Collecte récursivement tous les chemins du catalogue.
     */
    function collecterCheminsCatalogue(noeuds, prefixe) {
        const resultats = [];

        for (const [nom, noeud] of Object.entries(noeuds)) {
            if (!noeud || !noeud.genres) continue;

            const chemin = prefixe ? prefixe + ' > ' + nom : nom;
            const profondeur = chemin.split('>').length - 1;

            resultats.push({
                chemin,
                nom,
                profondeur,
                genres: noeud.genres,
                nbFacettes: noeud.facettes ? Object.keys(noeud.facettes).length : 0,
            });

            if (noeud.sous_categories) {
                resultats.push(...collecterCheminsCatalogue(noeud.sous_categories, chemin));
            }
        }

        return resultats;
    }

    // === Données brutes stockées pour le simulateur ===
    let donneesResultatsBrutes = null;
    let filtreZoneActif = 'tous';

    // Paramètres du simulateur (valeurs par défaut)
    const simParams = {
        poids_volume: 35,
        poids_suggest: 25,
        poids_cpc: 20,
        poids_kd: 20,
        seuil_index: 55,
        plafond_volume: 5000,
        plafond_cpc: 3.0,
    };

    // === Onglets ===
    const tabs = document.querySelectorAll('.tab');
    const panels = document.querySelectorAll('.tab-panel');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => { t.classList.remove('active'); t.setAttribute('aria-selected', 'false'); });
            panels.forEach(p => p.classList.remove('active'));
            tab.classList.add('active');
            tab.setAttribute('aria-selected', 'true');
            const panel = document.getElementById('tab-' + tab.dataset.tab);
            if (panel) panel.classList.add('active');

            // Charger la vue d'ensemble au clic sur l'onglet
            if (tab.dataset.tab === 'vue-ensemble') {
                chargerVueEnsemble();
            }
        });
    });

    // === Sélecteurs catégorie/genre — Accueil ===
    const selectCategorie = document.getElementById('select-categorie');
    const selectGenre = document.getElementById('select-genre');
    const btnAnalyser = document.getElementById('btn-analyser');

    if (selectCategorie) {
        selectCategorie.addEventListener('change', () => {
            remplirGenres(selectCategorie, selectGenre, null);
            construireArbreFacettes(selectCategorie.value);
            mettreAJourBtnDecouvrir();
        });
    }

    // === Sélecteurs catégorie/genre — Résultats ===
    const resCategorie = document.getElementById('res-categorie');
    const resGenre = document.getElementById('res-genre');
    const btnCharger = document.getElementById('btn-charger-resultats');

    if (resCategorie) {
        resCategorie.addEventListener('change', () => {
            remplirGenres(resCategorie, resGenre, btnCharger);
        });
    }

    function remplirGenres(selectCat, selectGen, btn) {
        const categorie = selectCat.value;
        selectGen.innerHTML = '<option value="">—</option>';

        if (!categorie || !window.CATALOGUE_MAP[categorie]) {
            selectGen.disabled = true;
            if (btn) btn.disabled = true;
            return;
        }

        const genres = window.CATALOGUE_MAP[categorie];
        genres.forEach(g => {
            const opt = document.createElement('option');
            opt.value = g;
            opt.textContent = g.charAt(0).toUpperCase() + g.slice(1);
            selectGen.appendChild(opt);
        });

        selectGen.disabled = false;
        selectGen.addEventListener('change', () => {
            if (btn) btn.disabled = !selectGen.value;
            if (selectGen === selectGenre && typeof mettreAJourCompteur === 'function') {
                mettreAJourCompteur();
            }
            mettreAJourBtnDecouvrir();
        }, { once: false });
    }

    // === Arborescence des facettes ===
    const conteneurArbre = document.getElementById('conteneur-arbre-facettes');
    const arbreFacettes = document.getElementById('arbre-facettes');
    const compteurSelection = document.getElementById('compteur-selection');
    const btnToutCocher = document.getElementById('btn-tout-cocher');
    const btnToutDecocher = document.getElementById('btn-tout-decocher');

    function construireArbreFacettes(categorie) {
        if (!arbreFacettes || !conteneurArbre) return;

        const noeud = resoudreNoeudJS(categorie);
        if (!categorie || !noeud) {
            conteneurArbre.style.display = 'none';
            arbreFacettes.innerHTML = '';
            return;
        }

        const facettes = noeud.facettes;
        arbreFacettes.innerHTML = '';

        for (const [type, valeurs] of Object.entries(facettes)) {
            const noeudEl = document.createElement('div');
            noeudEl.className = 'arbre-noeud';

            const entete = document.createElement('div');
            entete.className = 'arbre-noeud-entete';

            const toggle = document.createElement('span');
            toggle.className = 'arbre-toggle';
            toggle.textContent = '\u25B6';

            const cbType = document.createElement('input');
            cbType.type = 'checkbox';
            cbType.className = 'arbre-checkbox';
            cbType.checked = true;
            cbType.dataset.type = type;

            const label = document.createElement('span');
            label.className = 'arbre-label';
            label.textContent = type;

            const compteur = document.createElement('span');
            compteur.className = 'arbre-compteur-valeurs';
            compteur.textContent = '(' + valeurs.length + ' val.)';

            entete.appendChild(toggle);
            entete.appendChild(cbType);
            entete.appendChild(label);
            entete.appendChild(compteur);

            const feuilles = document.createElement('div');
            feuilles.className = 'arbre-feuilles';

            valeurs.forEach(valeur => {
                const feuille = document.createElement('div');
                feuille.className = 'arbre-feuille';

                const cbValeur = document.createElement('input');
                cbValeur.type = 'checkbox';
                cbValeur.className = 'arbre-checkbox';
                cbValeur.checked = true;
                cbValeur.dataset.type = type;
                cbValeur.dataset.valeur = valeur;

                const lblValeur = document.createElement('label');
                lblValeur.textContent = valeur;
                lblValeur.addEventListener('click', () => {
                    cbValeur.checked = !cbValeur.checked;
                    cbValeur.dispatchEvent(new Event('change'));
                });

                feuille.appendChild(cbValeur);
                feuille.appendChild(lblValeur);
                feuilles.appendChild(feuille);

                cbValeur.addEventListener('change', () => {
                    mettreAJourCheckboxParent(cbType, feuilles);
                    mettreAJourCompteur();
                });
            });

            noeudEl.appendChild(entete);
            noeudEl.appendChild(feuilles);
            arbreFacettes.appendChild(noeudEl);

            const toggleHandler = (e) => {
                if (e.target === cbType) return;
                feuilles.classList.toggle('ouvert');
                toggle.classList.toggle('ouvert');
            };
            toggle.addEventListener('click', toggleHandler);
            label.addEventListener('click', toggleHandler);

            cbType.addEventListener('change', () => {
                const cbs = feuilles.querySelectorAll('.arbre-checkbox');
                cbs.forEach(cb => { cb.checked = cbType.checked; });
                cbType.indeterminate = false;
                mettreAJourCompteur();
            });
        }

        conteneurArbre.style.display = 'block';
        mettreAJourCompteur();
    }

    function mettreAJourCheckboxParent(cbParent, conteneurFeuilles) {
        const cbs = conteneurFeuilles.querySelectorAll('.arbre-checkbox');
        const total = cbs.length;
        let cochees = 0;

        cbs.forEach(cb => { if (cb.checked) cochees++; });

        if (cochees === 0) {
            cbParent.checked = false;
            cbParent.indeterminate = false;
        } else if (cochees === total) {
            cbParent.checked = true;
            cbParent.indeterminate = false;
        } else {
            cbParent.checked = false;
            cbParent.indeterminate = true;
        }
    }

    function mettreAJourCompteur() {
        if (!arbreFacettes || !compteurSelection) return;

        const facettesSelectionnees = collecterFacettesSelectionnees();
        const nbTypes = Object.keys(facettesSelectionnees).length;
        let nbValeurs = 0;
        for (const valeurs of Object.values(facettesSelectionnees)) {
            nbValeurs += valeurs.length;
        }

        compteurSelection.textContent = nbTypes + ' type' + (nbTypes > 1 ? 's' : '') +
            ' \u00B7 ' + nbValeurs + ' valeur' + (nbValeurs > 1 ? 's' : '') + ' s\u00E9lectionn\u00E9e' + (nbValeurs > 1 ? 's' : '');

        const btnLancer = document.getElementById('btn-analyser');
        if (btnLancer) {
            const genre = selectGenre ? selectGenre.value : '';
            btnLancer.disabled = nbValeurs === 0 || !genre;
        }
    }

    function collecterFacettesSelectionnees() {
        if (!arbreFacettes) return {};

        const resultat = {};
        const cbValeurs = arbreFacettes.querySelectorAll('.arbre-checkbox[data-valeur]');

        cbValeurs.forEach(cb => {
            if (cb.checked) {
                const type = cb.dataset.type;
                const valeur = cb.dataset.valeur;
                if (!resultat[type]) resultat[type] = [];
                resultat[type].push(valeur);
            }
        });

        return resultat;
    }

    // Boutons globaux Tout / Rien
    if (btnToutCocher) {
        btnToutCocher.addEventListener('click', () => {
            if (!arbreFacettes) return;
            arbreFacettes.querySelectorAll('.arbre-checkbox').forEach(cb => {
                cb.checked = true;
                cb.indeterminate = false;
            });
            mettreAJourCompteur();
        });
    }

    if (btnToutDecocher) {
        btnToutDecocher.addEventListener('click', () => {
            if (!arbreFacettes) return;
            arbreFacettes.querySelectorAll('.arbre-checkbox').forEach(cb => {
                cb.checked = false;
                cb.indeterminate = false;
            });
            mettreAJourCompteur();
        });
    }

    // === Bouton Découvrir ===
    function mettreAJourBtnDecouvrir() {
        const btn = document.getElementById('btn-decouvrir');
        if (btn) {
            const cat = selectCategorie ? selectCategorie.value : '';
            const gen = selectGenre ? selectGenre.value : '';
            btn.disabled = !cat || !gen;
        }
    }

    const btnDecouvrir = document.getElementById('btn-decouvrir');
    if (btnDecouvrir) {
        btnDecouvrir.addEventListener('click', async () => {
            const categorie = selectCategorie.value;
            const genre = selectGenre.value;
            if (!categorie || !genre) return;

            btnDecouvrir.disabled = true;
            btnDecouvrir.innerHTML = '<span class="spinner"></span>Recherche...';

            try {
                const response = await fetch('/api/decouvrir-facettes', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ categorie, genre }),
                });

                const data = await response.json();

                if (!response.ok) throw new Error(data.erreur || 'Erreur');

                afficherSuggestions(data.donnees || []);
            } catch (err) {
                const conteneur = document.getElementById('liste-suggestions');
                if (conteneur) conteneur.innerHTML = '<p style="color: #c62828;">Erreur : ' + escHtml(err.message) + '</p>';
                document.getElementById('conteneur-suggestions').style.display = 'block';
            } finally {
                btnDecouvrir.disabled = false;
                btnDecouvrir.textContent = 'D\u00E9couvrir';
                mettreAJourBtnDecouvrir();
            }
        });
    }

    function afficherSuggestions(suggestions) {
        const conteneur = document.getElementById('conteneur-suggestions');
        const liste = document.getElementById('liste-suggestions');
        if (!conteneur || !liste) return;

        if (suggestions.length === 0) {
            liste.innerHTML = '<p style="color: #666; font-style: italic;">Aucune nouvelle facette d\u00E9couverte.</p>';
        } else {
            let html = '';
            suggestions.forEach(s => {
                html += `<div class="suggestion-item">
                    <span class="suggestion-texte">${escHtml(s.suggestion)}</span>
                    <span class="suggestion-source">via "${escHtml(s.source)}"</span>
                </div>`;
            });
            liste.innerHTML = html;
        }

        conteneur.style.display = 'block';
    }

    const btnFermerSuggestions = document.getElementById('btn-fermer-suggestions');
    if (btnFermerSuggestions) {
        btnFermerSuggestions.addEventListener('click', () => {
            document.getElementById('conteneur-suggestions').style.display = 'none';
        });
    }

    // === Lancer l'analyse ===
    document.addEventListener('click', async (e) => {
        const btnAnalyserEl = e.target.closest('#btn-analyser');
        if (!btnAnalyserEl || btnAnalyserEl.disabled) return;

        const categorie = selectCategorie.value;
        const genre = selectGenre.value;
        const facettes = collecterFacettesSelectionnees();

        if (!categorie || !genre || Object.keys(facettes).length === 0) return;

        btnAnalyserEl.disabled = true;
        btnAnalyserEl.innerHTML = '<span class="spinner"></span>Analyse en cours...';

        const carteProgression = document.getElementById('carte-progression');
        const progressFill = document.getElementById('progress-fill');
        const progressText = document.getElementById('progress-text');

        if (carteProgression) {
            carteProgression.style.display = 'block';
            progressFill.style.width = '10%';
            progressText.textContent = 'Envoi de la requ\u00EAte...';
        }

        try {
            progressFill.style.width = '30%';
            progressText.textContent = 'Analyse des facettes simples et combinaisons...';

            const response = await fetch('/api/analyser', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ categorie, genre, facettes }),
            });

            progressFill.style.width = '90%';
            progressText.textContent = 'R\u00E9ception des r\u00E9sultats...';

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.erreur || 'Erreur inconnue');
            }

            progressFill.style.width = '100%';
            progressText.textContent = 'Analyse termin\u00E9e !';

            setTimeout(() => {
                carteProgression.style.display = 'none';
                afficherResultats(data.donnees, categorie, genre);
                document.querySelector('[data-tab="resultats"]').click();
            }, 500);

        } catch (err) {
            if (progressText) progressText.textContent = 'Erreur : ' + err.message;
            if (progressFill) { progressFill.style.width = '100%'; progressFill.style.background = '#c62828'; }
        } finally {
            btnAnalyserEl.disabled = false;
            btnAnalyserEl.textContent = 'Lancer l\'analyse';
            mettreAJourCompteur();
        }
    });

    // === Charger résultats existants ===
    if (btnCharger) {
        btnCharger.addEventListener('click', async () => {
            const categorie = resCategorie.value;
            const genre = resGenre.value;

            if (!categorie || !genre) return;

            btnCharger.disabled = true;
            btnCharger.innerHTML = '<span class="spinner"></span>Chargement...';

            try {
                const response = await fetch(`/api/resultats?categorie=${encodeURIComponent(categorie)}&genre=${encodeURIComponent(genre)}`);
                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.erreur || 'Aucun r\u00E9sultat trouv\u00E9');
                }

                afficherResultats(data.donnees, categorie, genre);

            } catch (err) {
                const conteneurCannibalisation = document.getElementById('carte-cannibalisation');
                if (conteneurCannibalisation) conteneurCannibalisation.style.display = 'none';
                // Afficher l'erreur proprement
                const carteN1 = document.getElementById('carte-niveau1');
                if (carteN1) carteN1.style.display = 'none';
                const carteN2 = document.getElementById('carte-niveau2');
                if (carteN2) carteN2.style.display = 'none';
            } finally {
                btnCharger.disabled = false;
                btnCharger.textContent = 'Charger';
            }
        });
    }

    // === Boutons d'analyses existantes (accueil) ===
    document.querySelectorAll('.btn-analyse-existante').forEach(btn => {
        btn.addEventListener('click', async () => {
            const categorie = btn.dataset.categorie;
            const genre = btn.dataset.genre;

            try {
                const response = await fetch(`/api/resultats?categorie=${encodeURIComponent(categorie)}&genre=${encodeURIComponent(genre)}`);
                const data = await response.json();

                if (!response.ok) throw new Error(data.erreur);

                afficherResultats(data.donnees, categorie, genre);
                document.querySelector('[data-tab="resultats"]').click();
            } catch (err) {
                // Silencieux
            }
        });
    });

    // === Afficher les résultats dans les tableaux ===
    let categorieActuelle = '';
    let genreActuel = '';

    function afficherResultats(resultats, categorie, genre) {
        categorieActuelle = categorie;
        genreActuel = genre;
        donneesResultatsBrutes = resultats;

        const carteN1 = document.getElementById('carte-niveau1');
        const carteN2 = document.getElementById('carte-niveau2');
        const exportActions = document.getElementById('export-actions');
        const filtresZone = document.getElementById('filtres-zone');
        const simulateur = document.getElementById('simulateur-seuils');

        // Niveau 1
        remplirTableau('table-simples', resultats.facettes_simples, 'simple');
        if (carteN1) carteN1.style.display = 'block';

        // Niveau 2
        remplirTableau('table-combinaisons', resultats.combinaisons, 'combinaison');
        if (carteN2) carteN2.style.display = 'block';

        if (exportActions) exportActions.style.display = 'flex';
        if (filtresZone) filtresZone.style.display = 'flex';
        if (simulateur) simulateur.style.display = 'block';

        // Cannibalisation
        afficherCannibalisation(resultats.cannibalisation || []);

        // Graphiques
        afficherGraphiques(resultats);
    }

    function remplirTableau(tableId, donnees, type) {
        const tbody = document.querySelector('#' + tableId + ' tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        if (!donnees) return;

        for (const [nom, d] of Object.entries(donnees)) {
            // Filtrage par zone
            if (filtreZoneActif !== 'tous' && (d.zone || 'IGNORER') !== filtreZoneActif) {
                continue;
            }

            const tr = creerLigneResultat(nom, d, type);
            tbody.appendChild(tr);

            const trDetail = creerLigneDetail(d.detail_valeurs, type);
            tbody.appendChild(trDetail);

            tr.classList.add('expandable');
            tr.addEventListener('click', () => {
                trDetail.classList.toggle('visible');
            });
        }
    }

    function creerLigneResultat(nom, donnees, type) {
        const tr = document.createElement('tr');
        tr.className = donnees.decision;
        tr.dataset.zone = donnees.zone || 'IGNORER';
        const badgeClasse = donnees.decision === 'index' ? 'badge-index' : 'badge-noindex';
        const tauxPct = (donnees.metriques.taux_suggest * 100).toFixed(1);
        const zone = donnees.zone || 'IGNORER';
        const zoneClasse = 'badge-zone badge-zone-' + zone.toLowerCase();

        // Score bar
        const scoreContinu = donnees.score_continu || 0;
        const scoreCouleur = scoreContinu >= 55 ? '#2e7d32' : scoreContinu >= 30 ? '#ef6c00' : '#c62828';

        // Tendance
        let tendanceHtml = '';
        if (donnees.tendance) {
            const tendVolume = donnees.tendance.volume || {};
            const icone = tendVolume.icone || '';
            const variation = tendVolume.variation || 0;
            let classTendance = 'tendance-stable';
            if (icone === '\u2191' || icone === '\u2197') classTendance = 'tendance-up';
            else if (icone === '\u2193' || icone === '\u2198') classTendance = 'tendance-down';
            else if (icone === 'nouveau') classTendance = 'tendance-nouveau';

            if (icone === 'nouveau') {
                tendanceHtml = '<span class="tendance tendance-nouveau">NEW</span>';
            } else {
                const signe = variation >= 0 ? '+' : '';
                tendanceHtml = `<span class="tendance ${classTendance}">${icone} ${signe}${variation.toFixed(0)}%</span>`;
            }
        }

        tr.innerHTML = `
            <td><strong>${escHtml(nom)}</strong></td>
            <td><span class="badge ${badgeClasse}">${donnees.decision}</span></td>
            <td>
                <div class="score-bar">
                    <div class="score-bar-track"><div class="score-bar-fill" style="width: ${scoreContinu}%; background: ${scoreCouleur}"></div></div>
                    <span class="score-bar-label">${scoreContinu.toFixed(1)}</span>
                </div>
            </td>
            <td><span class="${zoneClasse}">${zone.replace('_', ' ')}</span></td>
            <td>${formatNombre(donnees.metriques.volume_total)}</td>
            <td>${donnees.metriques.volume_median}</td>
            <td>${tauxPct}%</td>
            <td>${donnees.metriques.cpc_moyen.toFixed(2)}\u00A0\u20AC</td>
            <td>${donnees.metriques.kd_moyen}</td>
            <td>${tendanceHtml}</td>
        `;

        return tr;
    }

    function creerLigneDetail(detailValeurs, type) {
        const trDetail = document.createElement('tr');
        trDetail.className = 'detail-row';

        let tableHtml = '<td colspan="10"><table class="detail-table"><thead><tr>';

        if (type === 'simple') {
            tableHtml += '<th>Valeur</th>';
        } else {
            tableHtml += '<th>Valeurs</th>';
        }
        tableHtml += '<th>Requ\u00EAte</th><th>Volume</th><th>Suggest</th><th>CPC</th><th>KD</th>';
        if (detailValeurs.some(d => d.statut)) {
            tableHtml += '<th>Statut</th>';
        }
        tableHtml += '</tr></thead><tbody>';

        for (const d of detailValeurs) {
            const suggestTxt = d.suggest ? '\u2713' : '\u2717';
            const suggestColor = d.suggest ? 'color: #2e7d32' : 'color: #c62828';
            const valeur = type === 'simple' ? escHtml(d.valeur) : escHtml(d.valeurs.join(' + '));

            tableHtml += `<tr>
                <td>${valeur}</td>
                <td><em>${escHtml(d.requete)}</em></td>
                <td>${formatNombre(d.volume)}</td>
                <td style="${suggestColor}; font-weight: 700">${suggestTxt}</td>
                <td>${d.cpc.toFixed(2)}\u00A0\u20AC</td>
                <td>${d.kd}</td>`;
            if (d.statut) {
                tableHtml += `<td><span style="color: #999">${escHtml(d.statut)}</span></td>`;
            }
            tableHtml += '</tr>';
        }

        tableHtml += '</tbody></table></td>';
        trDetail.innerHTML = tableHtml;
        return trDetail;
    }

    // === Cannibalisation ===
    function afficherCannibalisation(alertes) {
        const carte = document.getElementById('carte-cannibalisation');
        const liste = document.getElementById('liste-cannibalisation');
        if (!carte || !liste) return;

        if (!alertes || alertes.length === 0) {
            carte.style.display = 'none';
            return;
        }

        let html = '';
        alertes.forEach(a => {
            const pct = (a.similarite * 100).toFixed(0);
            html += `<div class="alerte-cannibalisation">
                <p class="alerte-cannibalisation-titre">\u26A0 ${escHtml(a.facette_a)} \u2194 ${escHtml(a.facette_b)} \u2014 Similarit\u00E9 : ${pct}%</p>
                <p class="alerte-cannibalisation-detail">${escHtml(a.recommandation)}</p>
            </div>`;
        });

        liste.innerHTML = html;
        carte.style.display = 'block';
    }

    // === Graphiques ===
    function afficherGraphiques(resultats) {
        const carteGraphiques = document.getElementById('carte-graphiques');
        if (!carteGraphiques) return;

        const toutesLesFacettes = {
            ...resultats.facettes_simples,
            ...resultats.combinaisons,
        };

        if (Object.keys(toutesLesFacettes).length === 0) {
            carteGraphiques.style.display = 'none';
            return;
        }

        carteGraphiques.style.display = 'block';

        // Barres horizontales
        const donneesBarres = Object.entries(toutesLesFacettes).map(([nom, d]) => ({
            nom,
            score: d.score_continu || 0,
            zone: d.zone || 'IGNORER',
        }));

        if (window.GraphiquesFacettes) {
            window.GraphiquesFacettes.barresHorizontales(
                document.getElementById('graphique-barres'),
                donneesBarres
            );

            // Scatter Volume × KD
            const donneesScatter = Object.entries(toutesLesFacettes).map(([nom, d]) => ({
                nom,
                volume: d.metriques.volume_total,
                kd: d.metriques.kd_moyen,
                zone: d.zone || 'IGNORER',
                score: d.score_continu || 0,
            }));

            window.GraphiquesFacettes.scatter(
                document.getElementById('graphique-scatter'),
                donneesScatter
            );
        }
    }

    // === Filtrage par zone ===
    document.querySelectorAll('.filtre-zone-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.filtre-zone-btn').forEach(b => b.classList.remove('actif'));
            btn.classList.add('actif');
            filtreZoneActif = btn.dataset.zone;

            if (donneesResultatsBrutes) {
                remplirTableau('table-simples', donneesResultatsBrutes.facettes_simples, 'simple');
                remplirTableau('table-combinaisons', donneesResultatsBrutes.combinaisons, 'combinaison');
            }
        });
    });

    // === Simulateur de seuils ===
    document.querySelectorAll('[data-sim]').forEach(input => {
        const valeurSpan = input.parentElement.querySelector('.simulateur-valeur');

        input.addEventListener('input', () => {
            const val = parseFloat(input.value);
            if (valeurSpan) valeurSpan.textContent = val;
            simParams[input.dataset.sim] = val;

            // Recalculer et réafficher
            if (donneesResultatsBrutes) {
                recalculerScores(donneesResultatsBrutes);
                remplirTableau('table-simples', donneesResultatsBrutes.facettes_simples, 'simple');
                remplirTableau('table-combinaisons', donneesResultatsBrutes.combinaisons, 'combinaison');
                afficherGraphiques(donneesResultatsBrutes);
            }
        });
    });

    function recalculerScores(resultats) {
        const sommePoids = simParams.poids_volume + simParams.poids_suggest + simParams.poids_cpc + simParams.poids_kd;
        if (sommePoids <= 0) return;

        const plafondVol = simParams.plafond_volume || 5000;
        const plafondCpc = simParams.plafond_cpc || 3.0;
        const seuilIndex = simParams.seuil_index;

        for (const niveau of ['facettes_simples', 'combinaisons']) {
            if (!resultats[niveau]) continue;

            for (const [, donnees] of Object.entries(resultats[niveau])) {
                const m = donnees.metriques;

                const scoreVol = Math.min(m.volume_total / plafondVol, 1.0) * simParams.poids_volume;
                const scoreSug = m.taux_suggest * simParams.poids_suggest;
                const scoreCpc = Math.min(m.cpc_moyen / plafondCpc, 1.0) * simParams.poids_cpc;
                const scoreKd = (1.0 - Math.min(m.kd_moyen / 100, 1.0)) * simParams.poids_kd;

                const scoreTotal = (scoreVol + scoreSug + scoreCpc + scoreKd) * (100 / sommePoids);
                const score = Math.max(0, Math.min(100, scoreTotal));

                donnees.score_continu = Math.round(score * 10) / 10;
                donnees.score = score.toFixed(1) + '/100';
                donnees.decision = score >= seuilIndex ? 'index' : 'noindex';
                donnees.zone = determinerZone(score, m.kd_moyen);
            }
        }
    }

    function determinerZone(score, kd) {
        if (score >= 55 && kd < 40) return 'QUICK_WIN';
        if (score >= 55) return 'FORT_POTENTIEL';
        if (score >= 30 && kd < 30) return 'NICHE';
        if (score >= 30) return 'SURVEILLER';
        return 'IGNORER';
    }

    // === Export ===
    document.querySelectorAll('[data-export]').forEach(btn => {
        btn.addEventListener('click', () => {
            const format = btn.dataset.export;
            if (!categorieActuelle || !genreActuel) return;

            const url = `/api/exporter?categorie=${encodeURIComponent(categorieActuelle)}&genre=${encodeURIComponent(genreActuel)}&format=${format}`;
            window.location.href = url;
        });
    });

    // === Tri des tableaux ===
    document.querySelectorAll('.table.sortable thead th').forEach(th => {
        th.addEventListener('click', () => {
            const table = th.closest('table');
            const tbody = table.querySelector('tbody');
            const index = Array.from(th.parentNode.children).indexOf(th);
            const type = th.dataset.sort || 'string';

            const estAsc = th.classList.contains('sort-asc');
            table.querySelectorAll('th').forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
            th.classList.add(estAsc ? 'sort-desc' : 'sort-asc');

            const direction = estAsc ? -1 : 1;

            const lignes = [];
            const trs = Array.from(tbody.querySelectorAll('tr'));
            for (let i = 0; i < trs.length; i++) {
                if (trs[i].classList.contains('detail-row')) continue;
                const detail = trs[i + 1] && trs[i + 1].classList.contains('detail-row') ? trs[i + 1] : null;
                lignes.push({ principal: trs[i], detail });
            }

            lignes.sort((a, b) => {
                const cellA = a.principal.children[index]?.textContent.trim() || '';
                const cellB = b.principal.children[index]?.textContent.trim() || '';

                if (type === 'number') {
                    const numA = parseFloat(cellA.replace(/[^0-9.,-]/g, '').replace(',', '.')) || 0;
                    const numB = parseFloat(cellB.replace(/[^0-9.,-]/g, '').replace(',', '.')) || 0;
                    return (numA - numB) * direction;
                }

                return cellA.localeCompare(cellB, 'fr') * direction;
            });

            lignes.forEach(l => {
                tbody.appendChild(l.principal);
                if (l.detail) tbody.appendChild(l.detail);
            });
        });
    });

    // === Vue d'ensemble ===
    async function chargerVueEnsemble() {
        const conteneur = document.getElementById('vue-ensemble-contenu');
        if (!conteneur) return;

        conteneur.innerHTML = '<p style="color: #999; font-style: italic;"><span class="spinner"></span> Chargement...</p>';

        try {
            const response = await fetch('/api/vue-ensemble');
            const data = await response.json();

            if (!data.donnees || data.donnees.length === 0) {
                conteneur.innerHTML = '<p style="color: #999; font-style: italic;">Aucune analyse disponible. Lancez des analyses depuis le tableau de bord.</p>';
                return;
            }

            let html = '<table class="table"><thead><tr>';
            html += '<th>Cat\u00E9gorie</th><th>Genre</th><th>Facettes</th><th>INDEX</th><th>NOINDEX</th>';
            html += '<th>Score moyen</th><th>Volume total</th><th>Meilleur Quick Win</th>';
            html += '</tr></thead><tbody>';

            for (const item of data.donnees) {
                const scoreColor = item.score_moyen >= 55 ? '#2e7d32' : item.score_moyen >= 30 ? '#ef6c00' : '#c62828';
                html += `<tr>
                    <td>${escHtml(item.categorie)}</td>
                    <td>${escHtml(item.genre)}</td>
                    <td>${item.nb_facettes}</td>
                    <td style="color: #2e7d32; font-weight: 700;">${item.nb_index}</td>
                    <td style="color: #c62828; font-weight: 700;">${item.nb_noindex}</td>
                    <td class="score-cell" style="color: ${scoreColor}">${item.score_moyen}/100</td>
                    <td>${formatNombre(item.volume_total)}</td>
                    <td>${item.meilleur_quick_win ? escHtml(item.meilleur_quick_win) : '\u2014'}</td>
                </tr>`;
            }

            html += '</tbody></table>';
            conteneur.innerHTML = html;
        } catch (err) {
            conteneur.innerHTML = '<p style="color: #c62828;">Erreur : ' + escHtml(err.message) + '</p>';
        }
    }

    // === Configuration — Charger les seuils ===
    async function chargerConfiguration() {
        try {
            const response = await fetch('/api/configuration');
            const data = await response.json();

            if (data.donnees && data.donnees.seuils) {
                const seuils = data.donnees.seuils;
                document.querySelectorAll('[data-seuil]').forEach(input => {
                    const [niveau, cle] = input.dataset.seuil.split('.');
                    if (seuils[niveau] && seuils[niveau][cle] !== undefined) {
                        input.value = seuils[niveau][cle];
                    }
                });
            }

            // Synchroniser les paramètres du simulateur
            if (data.donnees && data.donnees.scoring) {
                const scoring = data.donnees.scoring;
                if (scoring.poids) {
                    simParams.poids_volume = scoring.poids.volume || 35;
                    simParams.poids_suggest = scoring.poids.suggest || 25;
                    simParams.poids_cpc = scoring.poids.cpc || 20;
                    simParams.poids_kd = scoring.poids.kd || 20;
                }
                if (scoring.plafonds) {
                    simParams.plafond_volume = scoring.plafonds.volume || 5000;
                    simParams.plafond_cpc = scoring.plafonds.cpc || 3.0;
                }
                simParams.seuil_index = scoring.seuil_index || 55;

                // Mettre à jour les sliders
                document.querySelectorAll('[data-sim]').forEach(input => {
                    if (simParams[input.dataset.sim] !== undefined) {
                        input.value = simParams[input.dataset.sim];
                        const valeurSpan = input.parentElement.querySelector('.simulateur-valeur');
                        if (valeurSpan) valeurSpan.textContent = simParams[input.dataset.sim];
                    }
                });
            }
        } catch (err) {
            console.error('Erreur chargement config :', err);
        }
    }

    chargerConfiguration();

    // === Sauver seuils ===
    const btnSauveSeuils = document.getElementById('btn-sauver-seuils');
    const seuilsStatut = document.getElementById('seuils-statut');

    if (btnSauveSeuils) {
        btnSauveSeuils.addEventListener('click', async () => {
            const seuils = { simple: {}, combinaison: {} };

            document.querySelectorAll('[data-seuil]').forEach(input => {
                const [niveau, cle] = input.dataset.seuil.split('.');
                seuils[niveau][cle] = parseFloat(input.value) || 0;
            });

            try {
                const response = await fetch('/api/configuration', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ seuils }),
                });

                const data = await response.json();

                if (seuilsStatut) {
                    seuilsStatut.textContent = data.message || 'Sauvegard\u00E9';
                    seuilsStatut.className = 'statut-message ok';
                    setTimeout(() => { seuilsStatut.textContent = ''; }, 3000);
                }
            } catch (err) {
                if (seuilsStatut) {
                    seuilsStatut.textContent = 'Erreur : ' + err.message;
                    seuilsStatut.className = 'statut-message erreur';
                }
            }
        });
    }

    // === Test SEMrush ===
    const btnTestSemrush = document.getElementById('btn-test-semrush');
    const semrushStatut = document.getElementById('semrush-statut');

    if (btnTestSemrush) {
        btnTestSemrush.addEventListener('click', async () => {
            btnTestSemrush.disabled = true;
            btnTestSemrush.innerHTML = '<span class="spinner"></span>Test...';

            try {
                const response = await fetch('/api/test-semrush');
                const data = await response.json();

                if (semrushStatut) {
                    semrushStatut.textContent = data.message;
                    semrushStatut.className = 'statut-message ' + (data.statut === 'ok' ? 'ok' : 'erreur');
                }
            } catch (err) {
                if (semrushStatut) {
                    semrushStatut.textContent = 'Erreur de connexion';
                    semrushStatut.className = 'statut-message erreur';
                }
            } finally {
                btnTestSemrush.disabled = false;
                btnTestSemrush.textContent = 'Tester la connexion';
            }
        });
    }

    // === Cache ===
    async function chargerInfoCache() {
        try {
            const response = await fetch('/api/cache/info');
            const data = await response.json();
            const el = document.getElementById('cache-taille');
            if (el && data.donnees) {
                el.textContent = `${data.donnees.nombre_entrees} entr\u00E9es \u2014 ${data.donnees.taille_lisible}`;
            }
        } catch (err) {
            // Silencieux
        }
    }

    chargerInfoCache();

    const btnPurger = document.getElementById('btn-purger-cache');
    const cacheStatut = document.getElementById('cache-statut');

    if (btnPurger) {
        btnPurger.addEventListener('click', async () => {
            const prefixe = document.getElementById('cache-prefixe')?.value || '';

            try {
                const response = await fetch('/api/cache/purger', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ prefixe }),
                });
                const data = await response.json();

                if (cacheStatut) {
                    cacheStatut.textContent = data.message;
                    cacheStatut.className = 'statut-message ok';
                    setTimeout(() => { cacheStatut.textContent = ''; }, 3000);
                }

                chargerInfoCache();
            } catch (err) {
                if (cacheStatut) {
                    cacheStatut.textContent = 'Erreur : ' + err.message;
                    cacheStatut.className = 'statut-message erreur';
                }
            }
        });
    }

    // === Éditeur de catalogue ===
    const catalogueData = JSON.parse(JSON.stringify(window.CATALOGUE_COMPLET || {}));
    let cheminSelectionne = null;
    let typeSelectionne = null;
    let genreSelectionne = null;
    let catalogueModifie = false;

    const listeCategories = document.getElementById('liste-categories');
    const listeGenres = document.getElementById('liste-genres');
    const listeTypes = document.getElementById('liste-types');
    const listeValeurs = document.getElementById('liste-valeurs');
    const panneauDetail = document.getElementById('panneau-detail-categorie');
    const sectionGenres = document.getElementById('section-genres');
    const sectionTypes = document.getElementById('section-types');
    const sectionValeurs = document.getElementById('section-valeurs');
    const btnSauverCatalogue = document.getElementById('btn-sauver-catalogue');
    const catalogueStatut = document.getElementById('catalogue-statut');

    function marquerModifie() {
        catalogueModifie = true;
        if (btnSauverCatalogue) btnSauverCatalogue.disabled = false;
    }

    /**
     * Affiche une zone de saisie inline (textarea ou input) dans un conteneur.
     * Remplace les prompt() pour une meilleure UX.
     *
     * @param {HTMLElement} conteneur - Élément parent où insérer la saisie
     * @param {Object} options
     * @param {boolean} options.multiligne - true = textarea (plusieurs valeurs), false = input
     * @param {string} options.placeholder - Texte indicatif
     * @param {function} options.onValider - Callback appelé avec le texte saisi
     * @param {function} [options.onAnnuler] - Callback optionnel à l'annulation
     * @param {string} [options.valeurInitiale] - Valeur pré-remplie (pour les renommages)
     */
    function afficherSaisieInline(conteneur, options) {
        // Empêcher les saisies multiples
        const existante = conteneur.querySelector('.saisie-inline');
        if (existante) existante.remove();

        const wrapper = document.createElement('div');
        wrapper.className = 'saisie-inline';

        const champ = options.multiligne
            ? document.createElement('textarea')
            : document.createElement('input');

        if (options.multiligne) {
            champ.rows = 3;
        } else {
            champ.type = 'text';
        }

        champ.className = 'form-control';
        champ.placeholder = options.placeholder || '';
        if (options.valeurInitiale) champ.value = options.valeurInitiale;

        const actions = document.createElement('div');
        actions.className = 'saisie-inline-actions';

        const btnValider = document.createElement('button');
        btnValider.type = 'button';
        btnValider.className = 'btn btn-sm btn-primary';
        btnValider.textContent = 'Valider';

        const btnAnnuler = document.createElement('button');
        btnAnnuler.type = 'button';
        btnAnnuler.className = 'btn btn-sm btn-outline';
        btnAnnuler.textContent = 'Annuler';

        actions.appendChild(btnValider);
        actions.appendChild(btnAnnuler);
        wrapper.appendChild(champ);
        wrapper.appendChild(actions);
        conteneur.appendChild(wrapper);

        champ.focus();

        function valider() {
            const texte = champ.value.trim();
            wrapper.remove();
            if (texte) options.onValider(texte);
        }

        function annuler() {
            wrapper.remove();
            if (options.onAnnuler) options.onAnnuler();
        }

        btnValider.addEventListener('click', valider);
        btnAnnuler.addEventListener('click', annuler);

        champ.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                annuler();
            }
            // Entrée valide seulement pour les inputs (pas les textareas)
            if (!options.multiligne && e.key === 'Enter') {
                e.preventDefault();
                valider();
            }
        });
    }

    function resoudreNoeudEdition(chemin) {
        if (!chemin) return null;

        const segments = chemin.split('>').map(s => s.trim());

        if (segments.length === 1) {
            if (!catalogueData[segments[0]]) return null;
            return { parent: catalogueData, cle: segments[0], noeud: catalogueData[segments[0]] };
        }

        let parent = catalogueData;
        let noeud = catalogueData[segments[0]];
        let cle = segments[0];

        if (!noeud) return null;

        for (let i = 1; i < segments.length; i++) {
            if (!noeud.sous_categories || !noeud.sous_categories[segments[i]]) return null;
            parent = noeud.sous_categories;
            cle = segments[i];
            noeud = noeud.sous_categories[segments[i]];
        }

        return { parent, cle, noeud };
    }

    function estSousCategorie(chemin) {
        return chemin && chemin.includes('>');
    }

    function obtenirNoeudParent(chemin) {
        if (!chemin) return null;
        const segments = chemin.split('>').map(s => s.trim());
        if (segments.length <= 1) return null;
        const cheminParent = segments.slice(0, -1).join(' > ');
        const resolution = resoudreNoeudEdition(cheminParent);
        return resolution ? resolution.noeud : null;
    }

    function construireArbreEditeur(noeuds, prefixe, conteneur, profondeur) {
        for (const [nom, noeud] of Object.entries(noeuds)) {
            if (!noeud || !noeud.genres) continue;

            const chemin = prefixe ? prefixe + ' > ' + nom : nom;
            const aDesSousCategories = noeud.sous_categories && Object.keys(noeud.sous_categories).length > 0;

            const elementArbre = document.createElement('div');
            elementArbre.className = 'element-arbre';

            const ligne = document.createElement('div');
            ligne.className = 'element-arbre-ligne' + (chemin === cheminSelectionne ? ' actif' : '');
            ligne.style.paddingLeft = (profondeur * 20 + 8) + 'px';

            const toggleBtn = document.createElement('span');
            toggleBtn.className = 'arbre-editeur-toggle' + (aDesSousCategories ? '' : ' invisible');
            toggleBtn.textContent = '\u25B6';

            const labelEl = document.createElement('span');
            labelEl.className = 'element-arbre-label';
            labelEl.textContent = nom;

            const nbFacettes = noeud.facettes ? Object.keys(noeud.facettes).length : 0;
            const badge = document.createElement('span');
            badge.className = 'element-arbre-badge';
            badge.textContent = nbFacettes + 'f';

            ligne.appendChild(toggleBtn);
            ligne.appendChild(labelEl);
            ligne.appendChild(badge);

            elementArbre.appendChild(ligne);

            const sousConteneur = document.createElement('div');
            sousConteneur.className = 'sous-categories-editeur';

            if (aDesSousCategories) {
                construireArbreEditeur(noeud.sous_categories, chemin, sousConteneur, profondeur + 1);
            }

            elementArbre.appendChild(sousConteneur);

            labelEl.addEventListener('click', (e) => {
                e.stopPropagation();
                selectionnerCategorie(chemin);
            });

            toggleBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                if (!aDesSousCategories) return;
                sousConteneur.classList.toggle('ouvert');
                toggleBtn.classList.toggle('ouvert');
            });

            if (cheminSelectionne && cheminSelectionne.startsWith(chemin + ' > ')) {
                sousConteneur.classList.add('ouvert');
                toggleBtn.classList.add('ouvert');
            }

            conteneur.appendChild(elementArbre);
        }
    }

    function rendreCategories() {
        if (!listeCategories) return;
        listeCategories.innerHTML = '';
        construireArbreEditeur(catalogueData, '', listeCategories, 0);

        const btnRenommer = document.getElementById('btn-cat-renommer');
        const btnSupprimer = document.getElementById('btn-cat-supprimer');
        const btnSousCatAjouter = document.getElementById('btn-sous-cat-ajouter');
        if (btnRenommer) btnRenommer.disabled = !cheminSelectionne;
        if (btnSupprimer) btnSupprimer.disabled = !cheminSelectionne;
        if (btnSousCatAjouter) btnSousCatAjouter.disabled = !cheminSelectionne;
    }

    function selectionnerCategorie(chemin) {
        cheminSelectionne = chemin;
        typeSelectionne = null;
        genreSelectionne = null;
        rendreCategories();
        rendreDetail();
    }

    function rendreDetail() {
        const placeholder = panneauDetail?.querySelector('.detail-placeholder');
        const resolution = cheminSelectionne ? resoudreNoeudEdition(cheminSelectionne) : null;

        if (!cheminSelectionne || !resolution) {
            if (placeholder) placeholder.style.display = 'block';
            if (sectionGenres) sectionGenres.style.display = 'none';
            if (sectionTypes) sectionTypes.style.display = 'none';
            if (sectionValeurs) sectionValeurs.style.display = 'none';
            return;
        }

        if (placeholder) placeholder.style.display = 'none';

        if (estSousCategorie(cheminSelectionne)) {
            rendreDetailSousCategorie(resolution);
        } else {
            rendreDetailRacine(resolution);
        }
    }

    function rendreDetailRacine(resolution) {
        const cat = resolution.noeud;

        // Afficher les boutons CRUD
        masquerAfficherBoutonsCrud(true);

        // Restaurer les titres par défaut
        const titreGenres = sectionGenres?.querySelector('.panneau-entete h3');
        if (titreGenres) titreGenres.textContent = 'Genres';
        const titreTypesEl = sectionTypes?.querySelector('.panneau-entete h3');
        if (titreTypesEl) titreTypesEl.textContent = 'Types de facettes';

        // Restaurer les classes des listes
        if (listeGenres) listeGenres.className = 'liste-elements';
        if (listeTypes) listeTypes.className = 'liste-elements';
        if (listeValeurs) listeValeurs.className = 'liste-elements';

        if (sectionGenres) sectionGenres.style.display = 'block';
        remplirListe(listeGenres, cat.genres, genreSelectionne, selectionnerGenre);
        const btnGenreSuppr = document.getElementById('btn-genre-supprimer');
        if (btnGenreSuppr) btnGenreSuppr.disabled = !genreSelectionne;

        if (sectionTypes) sectionTypes.style.display = 'block';
        const types = Object.keys(cat.facettes);
        remplirListe(listeTypes, types, typeSelectionne, selectionnerType);
        const btnTypeRenommer = document.getElementById('btn-type-renommer');
        const btnTypeSuppr = document.getElementById('btn-type-supprimer');
        if (btnTypeRenommer) btnTypeRenommer.disabled = !typeSelectionne;
        if (btnTypeSuppr) btnTypeSuppr.disabled = !typeSelectionne;

        rendreValeurs();
    }

    function masquerAfficherBoutonsCrud(afficher) {
        const ids = [
            'btn-genre-ajouter', 'btn-genre-supprimer',
            'btn-type-ajouter', 'btn-type-renommer', 'btn-type-supprimer',
            'btn-valeur-ajouter', 'btn-valeur-supprimer',
        ];
        ids.forEach(id => {
            const btn = document.getElementById(id);
            if (btn) btn.style.display = afficher ? '' : 'none';
        });
    }

    function rendreDetailSousCategorie(resolution) {
        const cat = resolution.noeud;
        const parent = obtenirNoeudParent(cheminSelectionne);

        if (!parent) {
            rendreDetailRacine(resolution);
            return;
        }

        // Masquer les boutons CRUD
        masquerAfficherBoutonsCrud(false);

        // Nom du parent pour les labels
        const segments = cheminSelectionne.split('>').map(s => s.trim());
        const nomParent = segments.slice(0, -1).join(' > ');

        // === Genres (checkboxes héritées du parent) ===
        if (sectionGenres) sectionGenres.style.display = 'block';
        const titreGenres = sectionGenres?.querySelector('.panneau-entete h3');
        if (titreGenres) titreGenres.textContent = 'Genres (hérités de "' + nomParent + '")';

        if (listeGenres) {
            listeGenres.innerHTML = '';
            listeGenres.className = 'heritage-liste';

            parent.genres.forEach(genre => {
                const item = document.createElement('label');
                item.className = 'heritage-item';

                const cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.className = 'arbre-checkbox';
                cb.checked = cat.genres.includes(genre);

                cb.addEventListener('change', () => {
                    if (cb.checked) {
                        if (!cat.genres.includes(genre)) {
                            cat.genres.push(genre);
                        }
                    } else {
                        const idx = cat.genres.indexOf(genre);
                        if (idx > -1) cat.genres.splice(idx, 1);
                    }
                    marquerModifie();
                });

                const span = document.createElement('span');
                span.textContent = genre;

                item.appendChild(cb);
                item.appendChild(span);
                listeGenres.appendChild(item);
            });
        }

        // === Types de facettes (checkboxes héritées du parent) ===
        if (sectionTypes) sectionTypes.style.display = 'block';
        const titreTypes = sectionTypes?.querySelector('.panneau-entete h3');
        if (titreTypes) titreTypes.textContent = 'Types de facettes (hérités de "' + nomParent + '")';

        if (listeTypes) {
            listeTypes.innerHTML = '';
            listeTypes.className = 'heritage-liste';

            const typesParent = Object.keys(parent.facettes);
            typesParent.forEach(type => {
                const item = document.createElement('label');
                item.className = 'heritage-item' + (type === typeSelectionne ? ' actif' : '');

                const cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.className = 'arbre-checkbox';
                cb.checked = !!cat.facettes[type];

                cb.addEventListener('change', (e) => {
                    e.stopPropagation();
                    if (cb.checked) {
                        if (!cat.facettes[type]) {
                            cat.facettes[type] = [...parent.facettes[type]];
                        }
                    } else {
                        delete cat.facettes[type];
                        if (typeSelectionne === type) {
                            typeSelectionne = null;
                        }
                    }
                    marquerModifie();
                    rendreDetailSousCategorie(resoudreNoeudEdition(cheminSelectionne));
                });

                const span = document.createElement('span');
                span.className = 'heritage-item-label';
                span.textContent = type;

                span.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (cat.facettes[type]) {
                        typeSelectionne = type;
                        rendreDetailSousCategorie(resoudreNoeudEdition(cheminSelectionne));
                    }
                });

                item.appendChild(cb);
                item.appendChild(span);
                listeTypes.appendChild(item);
            });
        }

        // === Valeurs (checkboxes héritées du parent) ===
        if (!typeSelectionne || !cat.facettes[typeSelectionne]) {
            if (sectionValeurs) sectionValeurs.style.display = 'none';
            return;
        }

        if (sectionValeurs) sectionValeurs.style.display = 'block';
        const titreValeurs = document.getElementById('titre-valeurs');
        if (titreValeurs) titreValeurs.textContent = 'Valeurs de "' + typeSelectionne + '" (héritées de "' + nomParent + '")';

        if (listeValeurs) {
            listeValeurs.innerHTML = '';
            listeValeurs.className = 'heritage-liste';

            const valeursParent = parent.facettes[typeSelectionne] || [];
            const valeursEnfant = cat.facettes[typeSelectionne] || [];

            valeursParent.forEach(valeur => {
                const item = document.createElement('label');
                item.className = 'heritage-item';

                const cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.className = 'arbre-checkbox';
                cb.checked = valeursEnfant.includes(valeur);

                cb.addEventListener('change', () => {
                    if (cb.checked) {
                        if (!cat.facettes[typeSelectionne].includes(valeur)) {
                            cat.facettes[typeSelectionne].push(valeur);
                        }
                    } else {
                        const idx = cat.facettes[typeSelectionne].indexOf(valeur);
                        if (idx > -1) cat.facettes[typeSelectionne].splice(idx, 1);
                    }
                    marquerModifie();
                });

                const span = document.createElement('span');
                span.textContent = valeur;

                item.appendChild(cb);
                item.appendChild(span);
                listeValeurs.appendChild(item);
            });
        }
    }

    function remplirListe(conteneur, elements, selectionne, onSelect) {
        if (!conteneur) return;
        conteneur.innerHTML = '';

        elements.forEach(texte => {
            const div = document.createElement('div');
            div.className = 'element-liste' + (texte === selectionne ? ' actif' : '');
            div.textContent = texte;
            div.addEventListener('click', () => onSelect(texte));
            conteneur.appendChild(div);
        });
    }

    function selectionnerGenre(nom) {
        genreSelectionne = (genreSelectionne === nom) ? null : nom;
        rendreDetail();
    }

    function selectionnerType(nom) {
        typeSelectionne = nom;
        rendreDetail();
    }

    let valeurSelectionnee = null;

    function rendreValeurs() {
        if (!typeSelectionne || !cheminSelectionne) {
            if (sectionValeurs) sectionValeurs.style.display = 'none';
            return;
        }

        const resolution = resoudreNoeudEdition(cheminSelectionne);
        if (!resolution || !resolution.noeud.facettes[typeSelectionne]) {
            if (sectionValeurs) sectionValeurs.style.display = 'none';
            return;
        }

        if (sectionValeurs) sectionValeurs.style.display = 'block';
        const titreValeurs = document.getElementById('titre-valeurs');
        if (titreValeurs) titreValeurs.textContent = 'Valeurs de "' + typeSelectionne + '"';

        const valeurs = resolution.noeud.facettes[typeSelectionne];
        valeurSelectionnee = null;

        remplirListe(listeValeurs, valeurs, null, (val) => {
            valeurSelectionnee = (valeurSelectionnee === val) ? null : val;
            remplirListe(listeValeurs, valeurs, valeurSelectionnee, selectionnerValeur);
            const btnValSuppr = document.getElementById('btn-valeur-supprimer');
            if (btnValSuppr) btnValSuppr.disabled = !valeurSelectionnee;
        });

        const btnValSuppr = document.getElementById('btn-valeur-supprimer');
        if (btnValSuppr) btnValSuppr.disabled = true;
    }

    function selectionnerValeur(val) {
        const resolution = resoudreNoeudEdition(cheminSelectionne);
        if (!resolution) return;

        valeurSelectionnee = (valeurSelectionnee === val) ? null : val;
        const valeurs = resolution.noeud.facettes[typeSelectionne];
        remplirListe(listeValeurs, valeurs, valeurSelectionnee, selectionnerValeur);
        const btnValSuppr = document.getElementById('btn-valeur-supprimer');
        if (btnValSuppr) btnValSuppr.disabled = !valeurSelectionnee;
    }

    // Bouton suppression valeur
    const btnValeurSupprimer = document.getElementById('btn-valeur-supprimer');
    if (btnValeurSupprimer) {
        btnValeurSupprimer.addEventListener('click', () => {
            if (!valeurSelectionnee || !cheminSelectionne || !typeSelectionne) return;
            if (!confirm('Supprimer la valeur "' + valeurSelectionnee + '" ?')) return;

            const resolution = resoudreNoeudEdition(cheminSelectionne);
            if (!resolution) return;

            const idx = resolution.noeud.facettes[typeSelectionne].indexOf(valeurSelectionnee);
            if (idx > -1) {
                resolution.noeud.facettes[typeSelectionne].splice(idx, 1);
                valeurSelectionnee = null;
                marquerModifie();
                rendreValeurs();
            }
        });
    }

    // Bouton ajout valeur (textarea multiligne)
    const btnValeurAjouter = document.getElementById('btn-valeur-ajouter');
    if (btnValeurAjouter) {
        btnValeurAjouter.addEventListener('click', () => {
            if (!cheminSelectionne || !typeSelectionne) return;

            const conteneur = document.getElementById('liste-valeurs');
            if (!conteneur) return;

            afficherSaisieInline(conteneur, {
                multiligne: true,
                placeholder: 'Une valeur par ligne\u2026',
                onValider: (texte) => {
                    const resolution = resoudreNoeudEdition(cheminSelectionne);
                    if (!resolution) return;

                    const lignes = texte.split('\n')
                        .map(l => l.trim().toLowerCase())
                        .filter(l => l && !resolution.noeud.facettes[typeSelectionne].includes(l));

                    if (lignes.length === 0) return;

                    const uniques = [...new Set(lignes)];
                    resolution.noeud.facettes[typeSelectionne].push(...uniques);
                    marquerModifie();
                    rendreValeurs();
                },
            });
        });
    }

    // CRUD Catégories
    const btnCatAjouter = document.getElementById('btn-cat-ajouter');
    const btnCatRenommer = document.getElementById('btn-cat-renommer');
    const btnCatSupprimer = document.getElementById('btn-cat-supprimer');

    if (btnCatAjouter) {
        btnCatAjouter.addEventListener('click', () => {
            afficherSaisieInline(listeCategories, {
                multiligne: false,
                placeholder: 'Nom de la catégorie\u2026',
                onValider: (texte) => {
                    const nomNettoye = texte.trim().toLowerCase();
                    if (!nomNettoye || catalogueData[nomNettoye]) return;

                    catalogueData[nomNettoye] = { genres: ['femme'], facettes: { type: ['valeur'] } };
                    cheminSelectionne = nomNettoye;
                    typeSelectionne = null;
                    marquerModifie();
                    rendreCategories();
                    rendreDetail();
                },
            });
        });
    }

    const btnSousCatAjouter = document.getElementById('btn-sous-cat-ajouter');
    if (btnSousCatAjouter) {
        btnSousCatAjouter.addEventListener('click', () => {
            if (!cheminSelectionne) return;

            const resolution = resoudreNoeudEdition(cheminSelectionne);
            if (!resolution) return;

            afficherSaisieInline(listeCategories, {
                multiligne: false,
                placeholder: 'Nom de la sous-catégorie\u2026',
                onValider: (texte) => {
                    const nomNettoye = texte.trim().toLowerCase();
                    if (!nomNettoye) return;

                    const res = resoudreNoeudEdition(cheminSelectionne);
                    if (!res) return;

                    if (!res.noeud.sous_categories) {
                        res.noeud.sous_categories = {};
                    }

                    if (res.noeud.sous_categories[nomNettoye]) return;

                    res.noeud.sous_categories[nomNettoye] = {
                        genres: [...res.noeud.genres],
                        facettes: JSON.parse(JSON.stringify(res.noeud.facettes)),
                    };

                    cheminSelectionne = cheminSelectionne + ' > ' + nomNettoye;
                    typeSelectionne = null;
                    marquerModifie();
                    rendreCategories();
                    rendreDetail();
                },
            });
        });
    }

    if (btnCatRenommer) {
        btnCatRenommer.addEventListener('click', () => {
            if (!cheminSelectionne) return;

            const resolution = resoudreNoeudEdition(cheminSelectionne);
            if (!resolution) return;

            afficherSaisieInline(listeCategories, {
                multiligne: false,
                placeholder: 'Nouveau nom\u2026',
                valeurInitiale: resolution.cle,
                onValider: (texte) => {
                    const nomNettoye = texte.trim().toLowerCase();
                    if (!nomNettoye || nomNettoye === resolution.cle) return;
                    if (resolution.parent[nomNettoye]) return;

                    resolution.parent[nomNettoye] = resolution.parent[resolution.cle];
                    delete resolution.parent[resolution.cle];

                    const segments = cheminSelectionne.split('>').map(s => s.trim());
                    segments[segments.length - 1] = nomNettoye;
                    cheminSelectionne = segments.join(' > ');

                    typeSelectionne = null;
                    marquerModifie();
                    rendreCategories();
                    rendreDetail();
                },
            });
        });
    }

    if (btnCatSupprimer) {
        btnCatSupprimer.addEventListener('click', () => {
            if (!cheminSelectionne) return;

            const resolution = resoudreNoeudEdition(cheminSelectionne);
            if (!resolution) return;

            if (!confirm('Supprimer "' + resolution.cle + '" et tout son contenu ?')) return;

            delete resolution.parent[resolution.cle];
            cheminSelectionne = null;
            typeSelectionne = null;
            marquerModifie();
            rendreCategories();
            rendreDetail();
        });
    }

    // CRUD Genres
    const btnGenreAjouter = document.getElementById('btn-genre-ajouter');
    const btnGenreSupprimer = document.getElementById('btn-genre-supprimer');

    if (btnGenreAjouter) {
        btnGenreAjouter.addEventListener('click', () => {
            if (!cheminSelectionne) return;

            const conteneur = document.getElementById('liste-genres');
            if (!conteneur) return;

            afficherSaisieInline(conteneur, {
                multiligne: true,
                placeholder: 'Un genre par ligne\u2026',
                onValider: (texte) => {
                    const resolution = resoudreNoeudEdition(cheminSelectionne);
                    if (!resolution) return;

                    const lignes = texte.split('\n')
                        .map(l => l.trim().toLowerCase())
                        .filter(l => l && !resolution.noeud.genres.includes(l));

                    if (lignes.length === 0) return;

                    const uniques = [...new Set(lignes)];
                    resolution.noeud.genres.push(...uniques);
                    marquerModifie();
                    rendreDetail();
                },
            });
        });
    }

    if (btnGenreSupprimer) {
        btnGenreSupprimer.addEventListener('click', () => {
            if (!cheminSelectionne || !genreSelectionne) return;

            const resolution = resoudreNoeudEdition(cheminSelectionne);
            if (!resolution) return;

            if (resolution.noeud.genres.length <= 1) return;

            if (!confirm('Supprimer le genre "' + genreSelectionne + '" ?')) return;

            const idx = resolution.noeud.genres.indexOf(genreSelectionne);
            if (idx > -1) {
                resolution.noeud.genres.splice(idx, 1);
                genreSelectionne = null;
                marquerModifie();
                rendreDetail();
            }
        });
    }

    // CRUD Types de facettes
    const btnTypeAjouter = document.getElementById('btn-type-ajouter');
    const btnTypeRenommer = document.getElementById('btn-type-renommer');
    const btnTypeSupprimer = document.getElementById('btn-type-supprimer');

    if (btnTypeAjouter) {
        btnTypeAjouter.addEventListener('click', () => {
            if (!cheminSelectionne) return;

            const conteneur = document.getElementById('liste-types');
            if (!conteneur) return;

            afficherSaisieInline(conteneur, {
                multiligne: true,
                placeholder: 'Un type par ligne\u2026',
                onValider: (texte) => {
                    const resolution = resoudreNoeudEdition(cheminSelectionne);
                    if (!resolution) return;

                    const lignes = texte.split('\n')
                        .map(l => l.trim().toLowerCase())
                        .filter(l => l && !resolution.noeud.facettes[l]);

                    if (lignes.length === 0) return;

                    const uniques = [...new Set(lignes)];
                    uniques.forEach(nom => {
                        resolution.noeud.facettes[nom] = ['valeur'];
                    });
                    typeSelectionne = uniques[uniques.length - 1];
                    marquerModifie();
                    rendreDetail();
                },
            });
        });
    }

    if (btnTypeRenommer) {
        btnTypeRenommer.addEventListener('click', () => {
            if (!cheminSelectionne || !typeSelectionne) return;

            const conteneur = document.getElementById('liste-types');
            if (!conteneur) return;

            afficherSaisieInline(conteneur, {
                multiligne: false,
                placeholder: 'Nouveau nom\u2026',
                valeurInitiale: typeSelectionne,
                onValider: (texte) => {
                    const nomNettoye = texte.trim().toLowerCase();
                    if (!nomNettoye || nomNettoye === typeSelectionne) return;

                    const resolution = resoudreNoeudEdition(cheminSelectionne);
                    if (!resolution) return;

                    if (resolution.noeud.facettes[nomNettoye]) return;

                    resolution.noeud.facettes[nomNettoye] = resolution.noeud.facettes[typeSelectionne];
                    delete resolution.noeud.facettes[typeSelectionne];
                    typeSelectionne = nomNettoye;
                    marquerModifie();
                    rendreDetail();
                },
            });
        });
    }

    if (btnTypeSupprimer) {
        btnTypeSupprimer.addEventListener('click', () => {
            if (!cheminSelectionne || !typeSelectionne) return;

            const resolution = resoudreNoeudEdition(cheminSelectionne);
            if (!resolution) return;

            if (Object.keys(resolution.noeud.facettes).length <= 1) return;

            if (!confirm('Supprimer le type "' + typeSelectionne + '" et toutes ses valeurs ?')) return;

            delete resolution.noeud.facettes[typeSelectionne];
            typeSelectionne = null;
            marquerModifie();
            rendreDetail();
        });
    }

    // Sauvegarde catalogue
    if (btnSauverCatalogue) {
        btnSauverCatalogue.addEventListener('click', async () => {
            btnSauverCatalogue.disabled = true;
            btnSauverCatalogue.innerHTML = '<span class="spinner"></span>Sauvegarde...';

            try {
                const response = await fetch('/api/catalogue', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ catalogue: catalogueData }),
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.erreur || 'Erreur de sauvegarde');
                }

                catalogueModifie = false;

                syncSelecteursCatalogue();

                if (catalogueStatut) {
                    catalogueStatut.textContent = data.message || 'Sauvegard\u00E9';
                    catalogueStatut.className = 'statut-message ok';
                    setTimeout(() => { catalogueStatut.textContent = ''; }, 3000);
                }
            } catch (err) {
                if (catalogueStatut) {
                    catalogueStatut.textContent = 'Erreur : ' + err.message;
                    catalogueStatut.className = 'statut-message erreur';
                }
            } finally {
                btnSauverCatalogue.disabled = !catalogueModifie;
                btnSauverCatalogue.textContent = 'Sauvegarder';
            }
        });
    }

    function syncSelecteursCatalogue() {
        window.CATALOGUE_COMPLET = JSON.parse(JSON.stringify(catalogueData));
        window.CATALOGUE_MAP = {};

        const chemins = collecterCheminsCatalogue(catalogueData, '');
        for (const entry of chemins) {
            window.CATALOGUE_MAP[entry.chemin] = entry.genres;
        }

        const selects = [
            document.getElementById('select-categorie'),
            document.getElementById('res-categorie'),
        ];

        selects.forEach(sel => {
            if (!sel) return;
            const valeurActuelle = sel.value;
            sel.innerHTML = '<option value="">\u2014 S\u00E9lectionner \u2014</option>';

            for (const entry of chemins) {
                const opt = document.createElement('option');
                opt.value = entry.chemin;
                const indent = '\u00A0\u00A0\u00A0'.repeat(entry.profondeur);
                const prefix = entry.profondeur > 0 ? '\u2514 ' : '';
                opt.innerHTML = indent + prefix + entry.nom.charAt(0).toUpperCase() + entry.nom.slice(1) +
                    ' (' + entry.nbFacettes + ' facettes)';
                sel.appendChild(opt);
            }

            if (valeurActuelle && window.CATALOGUE_MAP[valeurActuelle]) {
                sel.value = valeurActuelle;
            }
        });

        const statCat = document.getElementById('stat-categories');
        if (statCat) statCat.textContent = chemins.length;
    }

    // Alerte navigateur si modifications non sauvegardées
    window.addEventListener('beforeunload', (e) => {
        if (catalogueModifie) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    // === Sélecteur de facettes par combinaison ===
    let selectionsData = {};

    async function chargerSelections() {
        try {
            const response = await fetch('/api/selections');
            const data = await response.json();
            if (data.donnees) {
                selectionsData = data.donnees;
            }
        } catch (err) {
            // Silencieux : si le fichier n'existe pas, on utilise un objet vide
        }
    }

    async function sauvegarderSelections() {
        const btnSauver = document.getElementById('btn-sauver-selections');
        const statut = document.getElementById('selections-statut');

        if (btnSauver) {
            btnSauver.disabled = true;
            btnSauver.innerHTML = '<span class="spinner"></span>Sauvegarde...';
        }

        try {
            const response = await fetch('/api/selections', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ selections: selectionsData }),
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.erreur || 'Erreur de sauvegarde');
            }

            if (statut) {
                statut.textContent = data.message || 'Sauvegardé';
                statut.className = 'statut-message ok';
                setTimeout(() => { statut.textContent = ''; }, 3000);
            }
        } catch (err) {
            if (statut) {
                statut.textContent = 'Erreur : ' + err.message;
                statut.className = 'statut-message erreur';
            }
        } finally {
            if (btnSauver) {
                btnSauver.disabled = false;
                btnSauver.textContent = 'Sauvegarder';
            }
        }
    }

    function rendreSelecteurFacettes() {
        const conteneur = document.getElementById('selecteur-facettes-contenu');
        const selectCat = document.getElementById('sel-facettes-categorie');
        const selectGenre = document.getElementById('sel-facettes-genre');

        if (!conteneur || !selectCat || !selectGenre) return;

        const categorie = selectCat.value;
        const genre = selectGenre.value;

        conteneur.innerHTML = '';

        if (!categorie || !genre) {
            conteneur.innerHTML = '<p style="color: #999; font-style: italic; padding: 1rem;">Sélectionnez une catégorie et un genre pour configurer les facettes.</p>';
            return;
        }

        const noeud = resoudreNoeudJS(categorie);
        if (!noeud || !noeud.facettes) {
            conteneur.innerHTML = '<p style="color: #999; font-style: italic; padding: 1rem;">Aucune facette trouvée.</p>';
            return;
        }

        // Récupérer les sélections existantes ou tout cocher par défaut
        const selectionsCombinaison = selectionsData[categorie]?.[genre] || null;

        let totalValeurs = 0;
        let totalSelectionnees = 0;

        for (const [type, valeurs] of Object.entries(noeud.facettes)) {
            const valeursSelectionnees = selectionsCombinaison?.[type] || [...valeurs];

            const noeudType = document.createElement('div');
            noeudType.className = 'selecteur-facettes-type';

            const nbSel = valeurs.filter(v => valeursSelectionnees.includes(v)).length;
            totalValeurs += valeurs.length;
            totalSelectionnees += nbSel;

            // En-tête du type
            const entete = document.createElement('div');
            entete.className = 'selecteur-facettes-type-entete';

            const toggle = document.createElement('span');
            toggle.className = 'arbre-toggle ouvert';
            toggle.textContent = '\u25B6';

            const label = document.createElement('span');
            label.className = 'selecteur-facettes-type-label';
            label.textContent = type;

            const compteur = document.createElement('span');
            compteur.className = 'selecteur-facettes-type-compteur';
            compteur.textContent = '(' + nbSel + '/' + valeurs.length + ' sélectionnées)';
            compteur.dataset.type = type;

            const actionsType = document.createElement('div');
            actionsType.className = 'selecteur-facettes-type-actions';

            const btnTout = document.createElement('button');
            btnTout.type = 'button';
            btnTout.className = 'btn btn-sm btn-outline';
            btnTout.textContent = 'Tout';
            btnTout.addEventListener('click', (e) => {
                e.stopPropagation();
                basculerToutType(categorie, genre, type, true);
            });

            const btnRien = document.createElement('button');
            btnRien.type = 'button';
            btnRien.className = 'btn btn-sm btn-outline';
            btnRien.textContent = 'Rien';
            btnRien.addEventListener('click', (e) => {
                e.stopPropagation();
                basculerToutType(categorie, genre, type, false);
            });

            actionsType.appendChild(btnTout);
            actionsType.appendChild(btnRien);

            entete.appendChild(toggle);
            entete.appendChild(label);
            entete.appendChild(compteur);
            entete.appendChild(actionsType);

            // Liste des valeurs (checkboxes)
            const listeValeurs = document.createElement('div');
            listeValeurs.className = 'selecteur-facettes-valeurs ouvert';
            listeValeurs.dataset.type = type;

            for (const valeur of valeurs) {
                const estCochee = valeursSelectionnees.includes(valeur);

                const feuille = document.createElement('div');
                feuille.className = 'arbre-feuille';

                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'arbre-checkbox';
                checkbox.checked = estCochee;
                checkbox.id = 'sel-' + type + '-' + valeur;
                checkbox.dataset.type = type;
                checkbox.dataset.valeur = valeur;

                checkbox.addEventListener('change', () => {
                    mettreAJourSelectionValeur(categorie, genre, type, valeur, checkbox.checked);
                    mettreAJourCompteurs(categorie, genre);
                });

                const labelVal = document.createElement('label');
                labelVal.htmlFor = checkbox.id;
                labelVal.textContent = valeur;

                feuille.appendChild(checkbox);
                feuille.appendChild(labelVal);
                listeValeurs.appendChild(feuille);
            }

            // Toggle ouverture/fermeture
            entete.addEventListener('click', () => {
                listeValeurs.classList.toggle('ouvert');
                toggle.classList.toggle('ouvert');
            });

            noeudType.appendChild(entete);
            noeudType.appendChild(listeValeurs);
            conteneur.appendChild(noeudType);
        }

        // Compteur global
        const compteurGlobal = document.getElementById('selecteur-compteur-global');
        if (compteurGlobal) {
            compteurGlobal.textContent = totalSelectionnees + '/' + totalValeurs + ' valeurs sélectionnées';
        }
    }

    function mettreAJourSelectionValeur(categorie, genre, type, valeur, estCochee) {
        if (!selectionsData[categorie]) selectionsData[categorie] = {};
        if (!selectionsData[categorie][genre]) {
            // Initialiser avec toutes les valeurs cochées
            const noeud = resoudreNoeudJS(categorie);
            if (!noeud) return;
            selectionsData[categorie][genre] = {};
            for (const [t, vals] of Object.entries(noeud.facettes)) {
                selectionsData[categorie][genre][t] = [...vals];
            }
        }

        if (!selectionsData[categorie][genre][type]) {
            selectionsData[categorie][genre][type] = [];
        }

        const liste = selectionsData[categorie][genre][type];
        const idx = liste.indexOf(valeur);

        if (estCochee && idx === -1) {
            liste.push(valeur);
        } else if (!estCochee && idx > -1) {
            liste.splice(idx, 1);
        }
    }

    function basculerToutType(categorie, genre, type, etat) {
        const noeud = resoudreNoeudJS(categorie);
        if (!noeud || !noeud.facettes[type]) return;

        if (!selectionsData[categorie]) selectionsData[categorie] = {};
        if (!selectionsData[categorie][genre]) {
            selectionsData[categorie][genre] = {};
            for (const [t, vals] of Object.entries(noeud.facettes)) {
                selectionsData[categorie][genre][t] = [...vals];
            }
        }

        selectionsData[categorie][genre][type] = etat ? [...noeud.facettes[type]] : [];

        // Mettre à jour les checkboxes visuellement
        const conteneur = document.getElementById('selecteur-facettes-contenu');
        if (conteneur) {
            const checkboxes = conteneur.querySelectorAll('input[data-type="' + type + '"]');
            checkboxes.forEach(cb => { cb.checked = etat; });
        }

        mettreAJourCompteurs(categorie, genre);
    }

    function mettreAJourCompteurs(categorie, genre) {
        const noeud = resoudreNoeudJS(categorie);
        if (!noeud) return;

        const selectionsCombinaison = selectionsData[categorie]?.[genre] || null;
        let totalValeurs = 0;
        let totalSelectionnees = 0;

        for (const [type, valeurs] of Object.entries(noeud.facettes)) {
            const valeursSelectionnees = selectionsCombinaison?.[type] || [...valeurs];
            const nbSel = valeurs.filter(v => valeursSelectionnees.includes(v)).length;
            totalValeurs += valeurs.length;
            totalSelectionnees += nbSel;

            // Mettre à jour le compteur du type
            const compteur = document.querySelector('[data-type="' + type + '"].selecteur-facettes-type-compteur');
            if (compteur) {
                compteur.textContent = '(' + nbSel + '/' + valeurs.length + ' sélectionnées)';
            }
        }

        const compteurGlobal = document.getElementById('selecteur-compteur-global');
        if (compteurGlobal) {
            compteurGlobal.textContent = totalSelectionnees + '/' + totalValeurs + ' valeurs sélectionnées';
        }
    }

    // Initialiser le sélecteur : remplir les dropdowns et écouter les changements
    function initSelecteurFacettes() {
        const selectCat = document.getElementById('sel-facettes-categorie');
        const selectGenre = document.getElementById('sel-facettes-genre');

        if (!selectCat || !selectGenre) return;

        // Remplir les catégories
        const chemins = collecterCheminsCatalogue(catalogueData, '');
        selectCat.innerHTML = '<option value="">— Sélectionner —</option>';
        for (const entry of chemins) {
            const opt = document.createElement('option');
            opt.value = entry.chemin;
            const indent = '\u00A0\u00A0\u00A0'.repeat(entry.profondeur);
            const prefix = entry.profondeur > 0 ? '\u2514 ' : '';
            opt.textContent = indent + prefix + entry.nom;
            selectCat.appendChild(opt);
        }

        selectCat.addEventListener('change', () => {
            const cat = selectCat.value;
            selectGenre.innerHTML = '<option value="">— Sélectionner —</option>';
            selectGenre.disabled = !cat;

            if (cat) {
                const noeud = resoudreNoeudJS(cat);
                if (noeud && noeud.genres) {
                    noeud.genres.forEach(g => {
                        const opt = document.createElement('option');
                        opt.value = g;
                        opt.textContent = g;
                        selectGenre.appendChild(opt);
                    });
                }
            }

            rendreSelecteurFacettes();
        });

        selectGenre.addEventListener('change', () => {
            rendreSelecteurFacettes();
        });

        // Bouton sauvegarder
        const btnSauver = document.getElementById('btn-sauver-selections');
        if (btnSauver) {
            btnSauver.addEventListener('click', sauvegarderSelections);
        }
    }

    // Charger les sélections puis initialiser
    chargerSelections().then(() => {
        initSelecteurFacettes();
    });

    // Rendu initial
    rendreCategories();

    // === Utilitaires ===
    function escHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function formatNombre(n) {
        return new Intl.NumberFormat('fr-FR').format(n);
    }
})();
