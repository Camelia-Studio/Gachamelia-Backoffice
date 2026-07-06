import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['source', 'input', 'image', 'glyph', 'status'];
    static values = {
        default: String,
    };

    connect() {
        this.update();
    }

    update() {
        const value = this.inputTarget.value.trim() || this.defaultValue;
        const customEmoji = value.match(/^<(a?):([A-Za-z0-9_]{2,32}):(\d{17,22})>$/);

        if (customEmoji) {
            const animated = customEmoji[1] === 'a';
            const emojiId = customEmoji[3];
            const extension = animated ? 'gif' : 'webp';

            this.imageTarget.src = `https://cdn.discordapp.com/emojis/${emojiId}.${extension}?size=64&quality=lossless`;
            this.imageTarget.classList.remove('hidden');
            this.glyphTarget.classList.add('hidden');
            this.statusTarget.textContent = `${this.sourceLabel()} prêt pour Discord.`;

            return;
        }

        this.imageTarget.removeAttribute('src');
        this.imageTarget.classList.add('hidden');
        this.glyphTarget.textContent = value;
        this.glyphTarget.classList.remove('hidden');
        this.statusTarget.textContent = `${this.sourceLabel()} utilisé dans les fiches.`;
    }

    imageLoaded() {
        this.statusTarget.textContent = `${this.sourceLabel()} affiché dans la prévisualisation.`;
    }

    imageError() {
        this.imageTarget.removeAttribute('src');
        this.imageTarget.classList.add('hidden');
        this.glyphTarget.textContent = this.inputTarget.value.trim() || this.defaultValue;
        this.glyphTarget.classList.remove('hidden');
        this.statusTarget.textContent = 'Emoji indisponible, aperçu texte conservé.';
    }

    sourceLabel() {
        const labels = {
            unicode: 'Emoji standard',
            bot: 'Emoji du bot',
            server: 'Emoji serveur',
        };

        return labels[this.sourceTarget.value] || labels.unicode;
    }
}
