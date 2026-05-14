<?php
// views/student_bilan.php
// Vue bilan élève — synthèse des observations pour le conseil de classe
// Données disponibles : $student (array), $studentId (int)
// Les observations sont chargées dynamiquement via /api/students/{id}/bilan
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bilan — <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></title>
    <link rel="stylesheet" href="/css/app.css">
    <style>
        /* ── Bilan élève ─────────────────────────────────── */
        .bilan-header {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            padding: 1.5rem 0 1rem;
            border-bottom: 1px solid var(--color-border, #e0e0e0);
            margin-bottom: 1.5rem;
        }
        .bilan-photo {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            object-fit: cover;
            background: var(--color-surface-offset, #f0f0f0);
            flex-shrink: 0;
        }
        .bilan-photo-placeholder {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: var(--color-surface-offset, #e8e8e8);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            flex-shrink: 0;
        }
        .bilan-identity h1 {
            font-size: 1.4rem;
            font-weight: 700;
            margin: 0 0 .2rem;
        }
        .bilan-identity .class-badge {
            display: inline-block;
            background: var(--color-primary, #01696f);
            color: #fff;
            font-size: .75rem;
            padding: .15rem .55rem;
            border-radius: 999px;
        }
        .bilan-meta {
            display: flex;
            flex-wrap: wrap;
            gap: .4rem .8rem;
            margin-top: .4rem;
            font-size: .82rem;
            color: var(--color-text-muted, #666);
        }
        .bilan-meta span.badge-alert {
            background: #fdecea;
            color: #b71c1c;
            border-radius: 4px;
            padding: .1rem .4rem;
            font-weight: 600;
        }

        /* ── Filtre période ──────────────────────────────── */
        .period-filter {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .6rem 1rem;
            background: var(--color-surface, #f9f9f9);
            border: 1px solid var(--color-border, #e0e0e0);
            border-radius: 8px;
            padding: .75rem 1rem;
            margin-bottom: 1.5rem;
        }
        .period-filter label { font-size: .85rem; font-weight: 600; }
        .period-filter input[type=date] {
            padding: .3rem .5rem;
            border: 1px solid var(--color-border, #ccc);
            border-radius: 5px;
            font-size: .85rem;
        }
        .period-filter .btn-filter {
            padding: .35rem .9rem;
            background: var(--color-primary, #01696f);
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: .85rem;
            font-weight: 600;
        }
        .period-filter .btn-filter:hover { opacity: .85; }
        .period-shortcuts { display: flex; gap: .4rem; }
        .period-shortcuts button {
            font-size: .78rem;
            padding: .25rem .6rem;
            border: 1px solid var(--color-border, #ccc);
            background: #fff;
            border-radius: 4px;
            cursor: pointer;
        }
        .period-shortcuts button:hover { background: var(--color-primary, #01696f); color: #fff; }

        /* ── Stats KPI ───────────────────────────────────── */
        .kpi-row {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        .kpi-card {
            flex: 1;
            min-width: 120px;
            background: var(--color-surface, #f9f9f9);
            border: 1px solid var(--color-border, #e0e0e0);
            border-radius: 8px;
            padding: .9rem 1rem;
            text-align: center;
        }
        .kpi-card .kpi-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--color-primary, #01696f);
            line-height: 1.1;
        }
        .kpi-card .kpi-label {
            font-size: .78rem;
            color: var(--color-text-muted, #666);
            margin-top: .2rem;
        }

        /* ── Tags fréquence ──────────────────────────────── */
        .section-title {
            font-size: 1rem;
            font-weight: 700;
            margin: 0 0 .75rem;
            padding-bottom: .4rem;
            border-bottom: 2px solid var(--color-primary, #01696f);
            display: inline-block;
        }
        .tag-bars { display: flex; flex-direction: column; gap: .5rem; margin-bottom: 1.5rem; }
        .tag-bar-row {
            display: flex;
            align-items: center;
            gap: .6rem;
        }
        .tag-bar-label {
            min-width: 140px;
            font-size: .85rem;
            display: flex;
            align-items: center;
            gap: .35rem;
        }
        .tag-bar-track {
            flex: 1;
            height: 14px;
            background: #eee;
            border-radius: 7px;
            overflow: hidden;
        }
        .tag-bar-fill {
            height: 100%;
            border-radius: 7px;
            transition: width .4s ease;
        }
        .tag-bar-count {
            min-width: 24px;
            text-align: right;
            font-size: .82rem;
            font-weight: 700;
            color: var(--color-text-muted, #666);
        }

        /* ── Timeline ────────────────────────────────────── */
        .timeline { display: flex; flex-direction: column; gap: .8rem; }
        .timeline-item {
            border: 1px solid var(--color-border, #e0e0e0);
            border-radius: 8px;
            overflow: hidden;
        }
        .timeline-item-header {
            display: flex;
            align-items: center;
            gap: .6rem;
            padding: .5rem .85rem;
            background: var(--color-surface, #f5f5f5);
            font-size: .83rem;
            font-weight: 600;
            border-bottom: 1px solid var(--color-border, #e0e0e0);
        }
        .timeline-item-header .session-date {
            color: var(--color-primary, #01696f);
        }
        .timeline-item-header .session-subject {
            color: var(--color-text-muted, #666);
            font-weight: 400;
        }
        .timeline-obs {
            padding: .5rem .85rem;
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
        }
        .obs-chip {
            display: inline-flex;
            align-items: center;
            gap: .25rem;
            font-size: .8rem;
            padding: .2rem .55rem;
            border-radius: 999px;
            color: #fff;
            font-weight: 500;
        }
        .obs-note {
            font-size: .78rem;
            color: var(--color-text-muted, #666);
            width: 100%;
            padding: .1rem 0 .2rem .2rem;
            font-style: italic;
        }

        /* ── Loader / état vide ───────────────────────────── */
        .bilan-loading, .bilan-empty {
            text-align: center;
            padding: 2.5rem 1rem;
            color: var(--color-text-muted, #888);
            font-size: .95rem;
        }
        .bilan-loading .spinner {
            display: inline-block;
            width: 28px; height: 28px;
            border: 3px solid #e0e0e0;
            border-top-color: var(--color-primary, #01696f);
            border-radius: 50%;
            animation: spin .7s linear infinite;
            margin-bottom: .6rem;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Retour ──────────────────────────────────────── */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            font-size: .85rem;
            color: var(--color-primary, #01696f);
            text-decoration: none;
            margin-bottom: .75rem;
        }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
<?php if (file_exists(ROOT . '/views/partials/navbar.php')) require ROOT . '/views/partials/navbar.php'; ?>

<main class="container" style="max-width:860px;margin:0 auto;padding:1rem 1.25rem 3rem;">

    <a href="/classes/<?= $student['class_id'] ?>" class="back-link">
        &#8592; <?= htmlspecialchars($student['class_name']) ?>
    </a>

    <!-- En-tête élève -->
    <div class="bilan-header">
        <?php
            // Construction du chemin photo (même convention que PhotoController)
            $photoUrl = '/photo?student_id=' . (int)$studentId;                      
        ?>
        <img src="<?= htmlspecialchars($photoUrl) ?>" alt="Photo"
             class="bilan-photo"
             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
        <div class="bilan-photo-placeholder" style="display:none">👤</div>

        <div class="bilan-identity">
            <h1><?= htmlspecialchars($student['last_name']) ?> <?= htmlspecialchars($student['first_name_usage'] ?: $student['first_name']) ?></h1>
            <span class="class-badge"><?= htmlspecialchars($student['class_name']) ?></span>
            <div class="bilan-meta">
                <?php if ($student['is_repeating']): ?>
                    <span class="badge-alert">Redoublant</span>
                <?php endif; ?>
                <?php if (!empty($student['support_project'])): ?>
                    <span class="badge-alert">PAP/PAI : <?= htmlspecialchars($student['support_project']) ?></span>
                <?php endif; ?>
                <?php if (!empty($student['allergies'])): ?>
                    <span class="badge-alert">Allergies : <?= htmlspecialchars($student['allergies']) ?></span>
                <?php endif; ?>
                <?php if (!empty($student['options'])): ?>
                    <span>Options : <?= htmlspecialchars($student['options']) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Filtre période -->
    <div class="period-filter">
        <label for="date-from">Du</label>
        <input type="date" id="date-from" name="from">
        <label for="date-to">au</label>
        <input type="date" id="date-to" name="to">
        <button class="btn-filter" id="btn-apply-filter">Afficher</button>
        <div class="period-shortcuts">
            <button data-period="t1">T1</button>
            <button data-period="t2">T2</button>
            <button data-period="t3">T3</button>
            <button data-period="all">Tout</button>
        </div>
    </div>

    <!-- KPI -->
    <div class="kpi-row" id="kpi-row">
        <div class="kpi-card"><div class="kpi-value" id="kpi-sessions">—</div><div class="kpi-label">Séances</div></div>
        <div class="kpi-card"><div class="kpi-value" id="kpi-sessions-obs">—</div><div class="kpi-label">Séances avec obs.</div></div>
        <div class="kpi-card"><div class="kpi-value" id="kpi-total-obs">—</div><div class="kpi-label">Observations totales</div></div>
    </div>

    <!-- Fréquence des tags -->
    <p class="section-title">Fréquence des observations</p>
    <div class="tag-bars" id="tag-bars">
        <div class="bilan-loading"><div class="spinner"></div><br>Chargement…</div>
    </div>

    <!-- Chronologie -->
    <p class="section-title">Chronologie séance par séance</p>
    <div class="timeline" id="timeline">
        <!-- Alimenté par JS -->
    </div>

</main>

<script nonce="<?= htmlspecialchars($cspNonce ?? '') ?>">
(function () {
    const STUDENT_ID = <?= $studentId ?>;

    // ── Raccourcis trimestres (dates approximatives Education Nationale) ──
    const YEAR = new Date().getFullYear();
    const PERIODS = {
        t1:  { from: (YEAR - 1) + '-09-02', to: (YEAR - 1) + '-12-20' },
        t2:  { from: YEAR + '-01-06',        to: YEAR + '-03-21' },
        t3:  { from: YEAR + '-03-31',        to: YEAR + '-07-05' },
        all: { from: '', to: '' },
    };

    const inputFrom    = document.getElementById('date-from');
    const inputTo      = document.getElementById('date-to');
    const btnFilter    = document.getElementById('btn-apply-filter');
    const tagBarsEl    = document.getElementById('tag-bars');
    const timelineEl   = document.getElementById('timeline');
    const kpiSessions  = document.getElementById('kpi-sessions');
    const kpiSessionsO = document.getElementById('kpi-sessions-obs');
    const kpiTotalObs  = document.getElementById('kpi-total-obs');

    // ── Raccourcis période ────────────────────────────────
    document.querySelectorAll('.period-shortcuts button').forEach(btn => {
        btn.addEventListener('click', () => {
            const p = PERIODS[btn.dataset.period];
            if (!p) return;
            inputFrom.value = p.from;
            inputTo.value   = p.to;
            loadBilan();
        });
    });

    btnFilter.addEventListener('click', loadBilan);

    // ── Chargement initial (tout) ─────────────────────────
    loadBilan();

    function loadBilan() {
        const from = inputFrom.value || '';
        const to   = inputTo.value   || '';
        let url = '/api/students/' + STUDENT_ID + '/bilan';
        const params = [];
        if (from) params.push('from=' + encodeURIComponent(from));
        if (to)   params.push('to='   + encodeURIComponent(to));
        if (params.length) url += '?' + params.join('&');

        // État chargement
        tagBarsEl.innerHTML  = '<div class="bilan-loading"><div class="spinner"></div><br>Chargement…</div>';
        timelineEl.innerHTML = '';
        kpiSessions.textContent  = '—';
        kpiSessionsO.textContent = '—';
        kpiTotalObs.textContent  = '—';

        fetch(url)
            .then(r => r.json())
            .then(data => renderBilan(data))
            .catch(() => {
                tagBarsEl.innerHTML = '<div class="bilan-empty">Erreur lors du chargement des données.</div>';
            });
    }

    function renderBilan(data) {
        // ── KPI ───────────────────────────────────────────
        kpiSessions.textContent  = data.stats.total_sessions;
        kpiSessionsO.textContent = data.stats.sessions_with_obs;
        kpiTotalObs.textContent  = data.stats.total_observations;

        // ── Tag bars ──────────────────────────────────────
        if (!data.tag_counts || data.tag_counts.length === 0) {
            tagBarsEl.innerHTML = '<div class="bilan-empty">Aucune observation sur cette période.</div>';
        } else {
            const maxCount = data.tag_counts[0].count || 1;
            tagBarsEl.innerHTML = data.tag_counts.map(t => {
                const pct = Math.round((t.count / maxCount) * 100);
                return `
                <div class="tag-bar-row">
                    <div class="tag-bar-label">${escHtml(t.icon || '')} ${escHtml(t.tag)}</div>
                    <div class="tag-bar-track">
                        <div class="tag-bar-fill" style="width:${pct}%;background:${escHtml(t.color)}"></div>
                    </div>
                    <div class="tag-bar-count">${t.count}</div>
                </div>`;
            }).join('');
        }

        // ── Timeline ──────────────────────────────────────
        if (!data.timeline || data.timeline.length === 0) {
            timelineEl.innerHTML = '<div class="bilan-empty">Aucune séance avec observation sur cette période.</div>';
            return;
        }

        // Afficher en ordre antéchronologique (plus récent en haut)
        const reversed = [...data.timeline].reverse();
        timelineEl.innerHTML = reversed.map(sess => {
            const dateStr = formatDate(sess.date);
            const timeStr = sess.time_start ? sess.time_start.substring(0, 5) : '';
            const subject = sess.subject ? `— ${escHtml(sess.subject)}` : '';

            const chips = sess.observations.map(obs => {
                const noteHtml = obs.note
                    ? `<div class="obs-note">&ldquo;${escHtml(obs.note)}&rdquo;</div>`
                    : '';
                return `<div style="width:100%;display:flex;flex-wrap:wrap;align-items:center;gap:.3rem;margin-bottom:.15rem;">
                    <span class="obs-chip" style="background:${escHtml(obs.color)}">
                        ${escHtml(obs.icon || '')} ${escHtml(obs.tag)}
                    </span>
                    ${noteHtml}
                </div>`;
            }).join('');

            return `
            <div class="timeline-item">
                <div class="timeline-item-header">
                    <span class="session-date">${dateStr}${timeStr ? ' à ' + timeStr : ''}</span>
                    <span class="session-subject">${subject}</span>
                </div>
                <div class="timeline-obs">${chips}</div>
            </div>`;
        }).join('');
    }

    // ── Utilitaires ───────────────────────────────────────
    function escHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        const [y, m, d] = dateStr.split('-');
        return d + '/' + m + '/' + y;
    }

})();
</script>

</body>
</html>
