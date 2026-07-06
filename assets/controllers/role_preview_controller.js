import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'image', 'placeholder', 'status'];

    connect() {
        this.update();
    }

    update() {
        const url = this.inputTarget.value.trim();

        if (!url) {
            this.showPlaceholder('Aucune image renseignée pour le moment.');

            return;
        }

        this.imageTarget.src = url;
        this.imageTarget.classList.remove('hidden');
        this.placeholderTarget.classList.add('hidden');
        this.statusTarget.textContent = 'Chargement de l’aperçu.';
    }

    imageLoaded() {
        this.statusTarget.textContent = 'Image affichée dans la prévisualisation.';
    }

    imageError() {
        this.showPlaceholder('Image indisponible, placeholder conservé.');
    }

    showPlaceholder(message) {
        this.imageTarget.removeAttribute('src');
        this.imageTarget.classList.add('hidden');
        this.placeholderTarget.classList.remove('hidden');
        this.statusTarget.textContent = message;
    }
}
