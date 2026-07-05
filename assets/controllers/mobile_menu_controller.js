import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['button', 'panel', 'backdrop'];

    connect() {
        this.close();
    }

    open() {
        this.panelTarget.classList.remove('translate-x-full');
        this.panelTarget.classList.add('translate-x-0');
        this.backdropTarget.classList.remove('pointer-events-none', 'opacity-0');
        this.backdropTarget.classList.add('opacity-100');
        this.panelTarget.removeAttribute('inert');
        this.panelTarget.setAttribute('aria-hidden', 'false');
        this.buttonTarget.setAttribute('aria-expanded', 'true');
        document.body.classList.add('overflow-hidden');
    }

    close() {
        this.panelTarget.classList.add('translate-x-full');
        this.panelTarget.classList.remove('translate-x-0');
        this.backdropTarget.classList.add('pointer-events-none', 'opacity-0');
        this.backdropTarget.classList.remove('opacity-100');
        this.panelTarget.setAttribute('inert', '');
        this.panelTarget.setAttribute('aria-hidden', 'true');
        this.buttonTarget.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('overflow-hidden');
    }

    navigate(event) {
        event.preventDefault();

        const hash = event.currentTarget.hash;
        const target = hash ? document.getElementById(hash.slice(1)) : null;
        const wasOpen = this.panelTarget.getAttribute('aria-hidden') === 'false';

        this.close();

        if (!target) {
            return;
        }

        window.setTimeout(() => {
            target.scrollIntoView({
                block: 'start',
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
            });
            window.history.pushState(null, '', hash);
        }, wasOpen ? 180 : 0);
    }
}
