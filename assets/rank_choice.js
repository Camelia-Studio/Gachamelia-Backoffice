export function initRankChoices(root = document) {
    root.querySelectorAll('[data-rank-choice]').forEach((select) => {
        if (select.dataset.rankChoiceReady) return;
        select.dataset.rankChoiceReady = 'true';
        const key = `gachamelia.rank.${select.dataset.rankChoice}`;
        try {
            const saved = localStorage.getItem(key);
            if (saved && Array.from(select.options).some((option) => option.value === saved)) {
                select.value = saved;
            } else if (saved) {
                localStorage.removeItem(key);
            }
        } catch { /* Le choix reste disponible si le navigateur bloque le stockage. */ }
        const remember = () => {
            try {
                if (select.value) localStorage.setItem(key, select.value);
            } catch { /* La saisie fonctionne aussi sans stockage local. */ }
        };
        select.addEventListener('change', remember);
        select.form?.addEventListener('submit', remember);
    });
}

initRankChoices();
