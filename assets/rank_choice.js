function initChoices(root, kind) {
    root.querySelectorAll(`[data-${kind}-choice]`).forEach((select) => {
        const ready = `${kind}ChoiceReady`;
        if (select.dataset[ready]) return;
        select.dataset[ready] = 'true';
        const key = `gachamelia.${kind}.${select.dataset[`${kind}Choice`]}`;
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
                else localStorage.removeItem(key);
            } catch { /* La saisie fonctionne aussi sans stockage local. */ }
        };
        select.addEventListener('change', remember);
        select.form?.addEventListener('submit', remember);
    });
}

export function initRankChoices(root = document) { initChoices(root, 'rank'); }
export function initRoleChoices(root = document) { initChoices(root, 'role'); }

initRankChoices();
initRoleChoices();
