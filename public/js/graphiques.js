/**
 * Graphiques SVG inline pour l'analyseur de facettes SEO.
 * Pas de dépendance externe — SVG pur.
 */
window.GraphiquesFacettes = (function () {
    'use strict';

    const COULEURS_ZONES = {
        QUICK_WIN: '#2e7d32',
        FORT_POTENTIEL: '#1565c0',
        NICHE: '#f9a825',
        SURVEILLER: '#ef6c00',
        IGNORER: '#757575',
    };

    /**
     * Génère un graphique en barres horizontales comparant les scores.
     * @param {HTMLElement} conteneur
     * @param {Array<{nom: string, score: number, zone: string}>} donnees
     */
    function barresHorizontales(conteneur, donnees) {
        if (!conteneur || !donnees || donnees.length === 0) return;

        // Trier par score décroissant
        const triees = [...donnees].sort((a, b) => b.score - a.score);
        const max = 100;
        const hauteurLigne = 32;
        const margeGauche = 160;
        const largeurBarre = 400;
        const largeurTotale = margeGauche + largeurBarre + 60;
        const hauteurTotale = triees.length * hauteurLigne + 20;

        let svg = `<svg class="graphique-svg" viewBox="0 0 ${largeurTotale} ${hauteurTotale}" xmlns="http://www.w3.org/2000/svg">`;

        triees.forEach((item, i) => {
            const y = i * hauteurLigne + 10;
            const largeur = (item.score / max) * largeurBarre;
            const couleur = COULEURS_ZONES[item.zone] || '#757575';
            const nomTronque = item.nom.length > 22 ? item.nom.substring(0, 20) + '...' : item.nom;

            // Label
            svg += `<text x="${margeGauche - 8}" y="${y + 18}" text-anchor="end" font-size="12" font-family="Poppins, sans-serif" fill="#333">${escSvg(nomTronque)}</text>`;

            // Barre fond
            svg += `<rect x="${margeGauche}" y="${y + 4}" width="${largeurBarre}" height="20" rx="3" fill="#e2e8f0"/>`;

            // Barre valeur
            svg += `<rect x="${margeGauche}" y="${y + 4}" width="${largeur}" height="20" rx="3" fill="${couleur}"/>`;

            // Score
            svg += `<text x="${margeGauche + largeur + 6}" y="${y + 18}" font-size="12" font-weight="700" font-family="Poppins, sans-serif" fill="${couleur}">${item.score.toFixed(1)}</text>`;
        });

        svg += '</svg>';
        conteneur.innerHTML = svg;
    }

    /**
     * Génère un scatter plot Volume × KD (matrice de priorisation).
     * @param {HTMLElement} conteneur
     * @param {Array<{nom: string, volume: number, kd: number, zone: string, score: number}>} donnees
     */
    function scatter(conteneur, donnees) {
        if (!conteneur || !donnees || donnees.length === 0) return;

        const marge = { haut: 30, bas: 50, gauche: 60, droite: 30 };
        const largeur = 500;
        const hauteur = 350;
        const zoneW = largeur - marge.gauche - marge.droite;
        const zoneH = hauteur - marge.haut - marge.bas;

        const maxVol = Math.max(...donnees.map(d => d.volume), 1);
        const maxKd = 100;

        let svg = `<svg class="graphique-svg" viewBox="0 0 ${largeur} ${hauteur}" xmlns="http://www.w3.org/2000/svg">`;

        // Quadrants de fond (seuils KD=40, Volume=50% du max)
        const seuilKdY = marge.haut + zoneH * (1 - 40 / maxKd);
        const midVolX = marge.gauche + zoneW * 0.5;

        // Quadrant Quick Win (bas-droite : volume haut, KD bas)
        svg += `<rect x="${midVolX}" y="${seuilKdY}" width="${zoneW * 0.5}" height="${marge.haut + zoneH - seuilKdY}" fill="rgba(46,125,50,0.05)"/>`;

        // Axes
        svg += `<line x1="${marge.gauche}" y1="${marge.haut}" x2="${marge.gauche}" y2="${marge.haut + zoneH}" stroke="#ccc" stroke-width="1"/>`;
        svg += `<line x1="${marge.gauche}" y1="${marge.haut + zoneH}" x2="${marge.gauche + zoneW}" y2="${marge.haut + zoneH}" stroke="#ccc" stroke-width="1"/>`;

        // Labels axes
        svg += `<text x="${marge.gauche + zoneW / 2}" y="${hauteur - 8}" text-anchor="middle" font-size="12" font-family="Poppins, sans-serif" fill="#666">Volume de recherche</text>`;
        svg += `<text x="15" y="${marge.haut + zoneH / 2}" text-anchor="middle" font-size="12" font-family="Poppins, sans-serif" fill="#666" transform="rotate(-90, 15, ${marge.haut + zoneH / 2})">Keyword Difficulty</text>`;

        // Graduation volume
        for (let i = 0; i <= 4; i++) {
            const x = marge.gauche + (zoneW / 4) * i;
            const val = Math.round((maxVol / 4) * i);
            svg += `<text x="${x}" y="${marge.haut + zoneH + 18}" text-anchor="middle" font-size="10" fill="#999">${val}</text>`;
            svg += `<line x1="${x}" y1="${marge.haut}" x2="${x}" y2="${marge.haut + zoneH}" stroke="#f1f5f9" stroke-width="1"/>`;
        }

        // Graduation KD
        for (let i = 0; i <= 5; i++) {
            const y = marge.haut + zoneH - (zoneH / 5) * i;
            const val = i * 20;
            svg += `<text x="${marge.gauche - 8}" y="${y + 4}" text-anchor="end" font-size="10" fill="#999">${val}</text>`;
            svg += `<line x1="${marge.gauche}" y1="${y}" x2="${marge.gauche + zoneW}" y2="${y}" stroke="#f1f5f9" stroke-width="1"/>`;
        }

        // Points
        donnees.forEach(d => {
            const x = marge.gauche + (d.volume / maxVol) * zoneW;
            const y = marge.haut + zoneH - (Math.min(d.kd, maxKd) / maxKd) * zoneH;
            const rayon = Math.max(5, Math.min(15, d.score / 8));
            const couleur = COULEURS_ZONES[d.zone] || '#757575';

            svg += `<circle cx="${x}" cy="${y}" r="${rayon}" fill="${couleur}" opacity="0.7" stroke="#fff" stroke-width="1.5"/>`;
            svg += `<title>${escSvg(d.nom)} — Vol: ${d.volume}, KD: ${d.kd}, Score: ${d.score}</title>`;
        });

        svg += '</svg>';
        conteneur.innerHTML = svg;
    }

    /**
     * Génère un graphique radar pour une facette individuelle.
     * @param {HTMLElement} conteneur
     * @param {object} metriques {volume_total, volume_median, taux_suggest, cpc_moyen, kd_moyen}
     * @param {object} plafonds {volume, cpc, kd}
     */
    function radar(conteneur, metriques, plafonds) {
        if (!conteneur || !metriques) return;

        const taille = 200;
        const centre = taille / 2;
        const rayon = 70;
        const axes = [
            { label: 'Volume', valeur: Math.min(metriques.volume_total / (plafonds?.volume || 5000), 1) },
            { label: 'Médiane', valeur: Math.min(metriques.volume_median / 200, 1) },
            { label: 'Suggest', valeur: metriques.taux_suggest },
            { label: 'CPC', valeur: Math.min(metriques.cpc_moyen / (plafonds?.cpc || 3), 1) },
            { label: 'KD inv.', valeur: 1 - Math.min(metriques.kd_moyen / (plafonds?.kd || 100), 1) },
        ];

        const nbAxes = axes.length;
        const angleStep = (2 * Math.PI) / nbAxes;

        let svg = `<svg class="graphique-svg" viewBox="0 0 ${taille} ${taille}" xmlns="http://www.w3.org/2000/svg">`;

        // Grilles concentriques
        [0.25, 0.5, 0.75, 1.0].forEach(pct => {
            const points = [];
            for (let i = 0; i < nbAxes; i++) {
                const angle = i * angleStep - Math.PI / 2;
                points.push(`${centre + rayon * pct * Math.cos(angle)},${centre + rayon * pct * Math.sin(angle)}`);
            }
            svg += `<polygon points="${points.join(' ')}" fill="none" stroke="#e2e8f0" stroke-width="0.5"/>`;
        });

        // Axes
        for (let i = 0; i < nbAxes; i++) {
            const angle = i * angleStep - Math.PI / 2;
            const x2 = centre + rayon * Math.cos(angle);
            const y2 = centre + rayon * Math.sin(angle);
            svg += `<line x1="${centre}" y1="${centre}" x2="${x2}" y2="${y2}" stroke="#ccc" stroke-width="0.5"/>`;

            // Labels
            const lx = centre + (rayon + 18) * Math.cos(angle);
            const ly = centre + (rayon + 18) * Math.sin(angle);
            svg += `<text x="${lx}" y="${ly + 4}" text-anchor="middle" font-size="9" font-family="Poppins, sans-serif" fill="#666">${axes[i].label}</text>`;
        }

        // Polygone de données
        const dataPoints = [];
        for (let i = 0; i < nbAxes; i++) {
            const angle = i * angleStep - Math.PI / 2;
            const val = Math.max(0, Math.min(1, axes[i].valeur));
            dataPoints.push(`${centre + rayon * val * Math.cos(angle)},${centre + rayon * val * Math.sin(angle)}`);
        }
        svg += `<polygon points="${dataPoints.join(' ')}" fill="rgba(0, 76, 76, 0.15)" stroke="#004c4c" stroke-width="1.5"/>`;

        // Points
        for (let i = 0; i < nbAxes; i++) {
            const angle = i * angleStep - Math.PI / 2;
            const val = Math.max(0, Math.min(1, axes[i].valeur));
            const px = centre + rayon * val * Math.cos(angle);
            const py = centre + rayon * val * Math.sin(angle);
            svg += `<circle cx="${px}" cy="${py}" r="3" fill="#004c4c"/>`;
        }

        svg += '</svg>';
        conteneur.innerHTML = svg;
    }

    function escSvg(str) {
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    return { barresHorizontales, scatter, radar };
})();
