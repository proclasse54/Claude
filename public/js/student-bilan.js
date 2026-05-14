(function () {
    const STUDENT_ID = parseInt(document.getElementById('studentBilanData').dataset.studentId);

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
    loadBilan();

    function loadBilan() {
        const from = inputFrom.value || '';
        const to   = inputTo.value   || '';
        let url = '/api/students/' + STUDENT_ID + '/bilan';
        const params = [];
        if (from) params.push('from=' + encodeURIComponent(from));
        if (to)   params.push('to='   + encodeURIComponent(to));
        if (params.length) url += '?' + params.join('&');

        tagBarsEl.innerHTML  = '<div class="bilan-loading"><div class="spinner"></div><br>Chargement\u2026</div>';
        timelineEl.innerHTML = '';
        kpiSessions.textContent  = '\u2014';
        kpiSessionsO.textContent = '\u2014';
        kpiTotalObs.textContent  = '\u2014';

        fetch(url)
            .then(r => r.json())
            .then(data => renderBilan(data))
            .catch(() => {
                tagBarsEl.innerHTML = '<div class="bilan-empty">Erreur lors du chargement des donn\u00e9es.</div>';
            });
    }

    function renderBilan(data) {
        kpiSessions.textContent  = data.stats.total_sessions;
        kpiSessionsO.textContent = data.stats.sessions_with_obs;
        kpiTotalObs.textContent  = data.stats.total_observations;

        if (!data.tag_counts || data.tag_counts.length === 0) {
            tagBarsEl.innerHTML = '<div class="bilan-empty">Aucune observation sur cette p\u00e9riode.</div>';
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

        if (!data.timeline || data.timeline.length === 0) {
            timelineEl.innerHTML = '<div class="bilan-empty">Aucune s\u00e9ance avec observation sur cette p\u00e9riode.</div>';
            return;
        }

        const reversed = [...data.timeline].reverse();
        timelineEl.innerHTML = reversed.map(sess => {
            const dateStr = formatDate(sess.date);
            const timeStr = sess.time_start ? sess.time_start.substring(0, 5) : '';
            const subject = sess.subject ? `\u2014 ${escHtml(sess.subject)}` : '';

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
                    <span class="session-date">${dateStr}${timeStr ? ' \u00e0 ' + timeStr : ''}</span>
                    <span class="session-subject">${subject}</span>
                </div>
                <div class="timeline-obs">${chips}</div>
            </div>`;
        }).join('');
    }

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
