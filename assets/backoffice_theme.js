const toggle = document.querySelector('[data-backoffice-theme-toggle]');
const system = document.querySelector('[data-backoffice-theme-system]');
const root = document.documentElement;
const preference = window.matchMedia('(prefers-color-scheme: dark)');
const valid = (value) => ['light', 'dark', 'system'].includes(value) ? value : 'system';
let changing = false;

if (toggle && system) {
    const isDark = () => root.dataset.backofficeTheme === 'dark' || (root.dataset.backofficeTheme === 'system' && preference.matches);
    function syncControls() {
        const dark = isDark();
        toggle.dataset.dark = String(dark);
        toggle.setAttribute('aria-label', dark ? 'Activer le thème clair' : 'Activer le thème sombre');
        toggle.title = dark ? 'Activer le thème clair' : 'Activer le thème sombre';
        system.setAttribute('aria-pressed', String(root.dataset.backofficeTheme === 'system'));
        toggle.disabled = system.disabled = changing;
    }
    function applyTheme(theme, remember = true) {
        root.dataset.backofficeTheme = valid(theme);
        if (remember) {
            try { localStorage.setItem('gachamelia.theme', root.dataset.backofficeTheme); } catch { /* Le choix fonctionne aussi sans stockage. */ }
        }
        syncControls();
    }
    function changeTheme(theme, origin) {
        if (changing || theme === root.dataset.backofficeTheme) return;
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !document.startViewTransition || (theme === 'system' && isDark() === preference.matches)) {
            applyTheme(theme);
            return;
        }
        const rect = origin.getBoundingClientRect();
        const x = rect.left + rect.width / 2;
        const y = rect.top + rect.height / 2;
        const radius = Math.hypot(Math.max(x, innerWidth - x), Math.max(y, innerHeight - y));
        changing = true;
        syncControls();
        const transition = document.startViewTransition(() => applyTheme(theme));
        transition.ready.then(() => root.animate({
            clipPath: [`circle(0px at ${x}px ${y}px)`, `circle(${radius}px at ${x}px ${y}px)`],
        }, { duration: 450, easing: 'cubic-bezier(0.16, 1, 0.3, 1)', pseudoElement: '::view-transition-new(root)' })).catch(() => { /* Le thème s’applique aussi si l’animation est interrompue. */ });
        transition.finished.finally(() => { changing = false; syncControls(); }).catch(() => {});
    }
    toggle.addEventListener('click', () => changeTheme(isDark() ? 'light' : 'dark', toggle));
    system.addEventListener('click', () => changeTheme('system', system));
    preference.addEventListener('change', syncControls);
    window.addEventListener('storage', (event) => {
        if (event.key === 'gachamelia.theme' || event.key === null) applyTheme(event.newValue, false);
    });
    syncControls();
}
