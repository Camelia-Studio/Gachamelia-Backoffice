/*
 * Point d'entrée JavaScript de la vitrine publique.
 *
 * Inclus via importmap('app') dans base.html.twig.
 */
import './styles/base.css';
import './styles/app.css';

/*
 * Menu mobile : tiroir latéral ouvert par le bouton burger.
 * Fermé par la croix, le fond, la touche Échap ou un clic sur un lien.
 */
(function () {
    const root = document.querySelector('[data-mobile-menu]');

    if (!root) {
        return;
    }

    const toggle = root.querySelector('[data-mobile-menu-toggle]');
    const panel = root.querySelector('[data-mobile-menu-panel]');
    const backdrop = root.querySelector('[data-mobile-menu-backdrop]');

    function isOpen() {
        return panel.classList.contains('is-open');
    }

    function open() {
        panel.classList.add('is-open');
        backdrop.classList.add('is-open');
        panel.removeAttribute('inert');
        panel.setAttribute('aria-hidden', 'false');
        toggle.setAttribute('aria-expanded', 'true');
        document.body.classList.add('has-open-menu');
    }

    function close() {
        panel.classList.remove('is-open');
        backdrop.classList.remove('is-open');
        panel.setAttribute('inert', '');
        panel.setAttribute('aria-hidden', 'true');
        toggle.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('has-open-menu');
    }

    /* Défilement vers l'ancre après la fermeture du tiroir, puis mise à jour de l'URL. */
    function navigate(event) {
        const hash = event.currentTarget.hash;
        const target = hash ? document.getElementById(hash.slice(1)) : null;
        const wasOpen = isOpen();

        event.preventDefault();
        close();

        if (!target) {
            return;
        }

        window.setTimeout(function () {
            target.scrollIntoView({
                block: 'start',
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
            });
            window.history.pushState(null, '', hash);
        }, wasOpen ? 180 : 0);
    }

    toggle.addEventListener('click', open);
    backdrop.addEventListener('click', close);
    root.querySelectorAll('[data-mobile-menu-close]').forEach(function (element) {
        element.addEventListener('click', close);
    });
    root.querySelectorAll('[data-mobile-menu-link]').forEach(function (link) {
        link.addEventListener('click', navigate);
    });
    window.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            close();
        }
    });

    close();
})();
