import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['source', 'value', 'image', 'glyph', 'status', 'option', 'search'];
    static values = {
        default: String,
    };

    connect() {
        this.selectCurrentOption();
        this.updatePreview();
    }

    choose(event) {
        const option = event.currentTarget;

        this.sourceTarget.value = option.dataset.emojiSource || 'unicode';
        this.valueTarget.value = option.dataset.emojiValue || this.defaultValue;
        this.markSelectedOption(option);
        this.updatePreview(option);
    }

    filter() {
        const query = this.searchTarget.value.trim().toLowerCase();

        this.optionTargets.forEach((option) => {
            const name = (option.dataset.emojiName || '').toLowerCase();
            const value = (option.dataset.emojiValue || '').toLowerCase();
            option.classList.toggle('hidden', query !== '' && !name.includes(query) && !value.includes(query));
        });
    }

    imageLoaded() {
        this.statusTarget.textContent = `${this.sourceLabel()} affiché dans la prévisualisation.`;
    }

    imageError() {
        this.imageTarget.removeAttribute('src');
        this.imageTarget.classList.add('hidden');
        this.glyphTarget.textContent = this.valueTarget.value || this.defaultValue;
        this.glyphTarget.classList.remove('hidden');
        this.statusTarget.textContent = 'Emoji indisponible, aperçu texte conservé.';
    }

    selectCurrentOption() {
        const selected = this.optionTargets.find((option) => {
            return option.dataset.emojiSource === this.sourceTarget.value
                && option.dataset.emojiValue === this.valueTarget.value;
        }) || this.optionTargets.find((option) => option.dataset.emojiValue === this.defaultValue) || this.optionTargets[0];

        if (!selected) {
            return;
        }

        this.sourceTarget.value = selected.dataset.emojiSource || 'unicode';
        this.valueTarget.value = selected.dataset.emojiValue || this.defaultValue;
        this.markSelectedOption(selected);
    }

    markSelectedOption(selectedOption) {
        this.optionTargets.forEach((option) => {
            option.dataset.selected = option === selectedOption ? 'true' : 'false';
        });
    }

    updatePreview(selectedOption = null) {
        const option = selectedOption || this.optionTargets.find((candidate) => candidate.dataset.selected === 'true');
        const value = this.valueTarget.value || this.defaultValue;
        const customEmoji = value.match(/^<(a?):([A-Za-z0-9_]{2,32}):(\d{17,22})>$/);
        const cdnUrl = option?.dataset.emojiCdnUrl || this.cdnUrlFromMarkup(customEmoji);

        if (cdnUrl) {
            this.imageTarget.src = cdnUrl;
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

    cdnUrlFromMarkup(customEmoji) {
        if (!customEmoji) {
            return null;
        }

        const extension = customEmoji[1] === 'a' ? 'gif' : 'webp';

        return `https://cdn.discordapp.com/emojis/${customEmoji[3]}.${extension}?size=64&quality=lossless`;
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
