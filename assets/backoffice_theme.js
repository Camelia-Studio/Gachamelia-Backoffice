const selector = document.querySelector('[data-backoffice-theme-choice]');
const root = document.documentElement;
const valid = (value) => ['light', 'dark', 'system'].includes(value) ? value : 'system';

if (selector) {
    selector.value = valid(root.dataset.backofficeTheme);
    selector.addEventListener('change', () => {
        const theme = valid(selector.value);
        root.dataset.backofficeTheme = theme;
        try { localStorage.setItem('gachamelia.theme', theme); } catch { /* Le choix fonctionne aussi sans stockage. */ }
    });
    window.addEventListener('storage', (event) => {
        if (event.key !== 'gachamelia.theme') return;
        selector.value = valid(event.newValue);
        root.dataset.backofficeTheme = selector.value;
    });
}
