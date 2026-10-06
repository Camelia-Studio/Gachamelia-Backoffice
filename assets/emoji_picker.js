/*
 * Sélecteur d'emoji des écrans de configuration.
 *
 * Chaque formulaire `[data-emoji-picker]` (valeur par défaut dans `data-emoji-picker-default`)
 * contient les champs cachés source/valeur, une recherche, la grille d'options et la prévisualisation.
 */
const CUSTOM_EMOJI = /^<(a?):([A-Za-z0-9_]{2,32}):(\d{17,22})>$/;

const SOURCE_LABELS = {
    unicode: 'Emoji standard',
    bot: 'Emoji du bot',
    server: 'Emoji serveur',
};

function initEmojiPicker(root) {
    const defaultValue = root.dataset.emojiPickerDefault || '';
    const sourceField = root.querySelector('[data-emoji-field="source"]');
    const valueField = root.querySelector('[data-emoji-field="value"]');
    const search = root.querySelector('[data-emoji-search]');
    const image = root.querySelector('[data-emoji-image]');
    const glyph = root.querySelector('[data-emoji-glyph]');
    const status = root.querySelector('[data-emoji-status]');
    const options = Array.from(root.querySelectorAll('[data-emoji-option]'));

    if (!sourceField || !valueField || !image || !glyph || !status) {
        return;
    }

    function sourceLabel() {
        return SOURCE_LABELS[sourceField.value] || SOURCE_LABELS.unicode;
    }

    function cdnUrlFromMarkup(customEmoji) {
        if (!customEmoji) {
            return null;
        }

        const extension = customEmoji[1] === 'a' ? 'gif' : 'webp';

        return 'https://cdn.discordapp.com/emojis/' + customEmoji[3] + '.' + extension + '?size=64&quality=lossless';
    }

    function markSelected(selectedOption) {
        options.forEach(function (option) {
            option.dataset.selected = option === selectedOption ? 'true' : 'false';
        });
    }

    function showGlyph(value) {
        image.removeAttribute('src');
        image.classList.add('hidden');
        glyph.textContent = value;
        glyph.classList.remove('hidden');
    }

    function updatePreview(selectedOption) {
        const option = selectedOption || options.find(function (candidate) {
            return candidate.dataset.selected === 'true';
        });
        const value = valueField.value || defaultValue;
        const cdnUrl = (option && option.dataset.emojiCdnUrl) || cdnUrlFromMarkup(value.match(CUSTOM_EMOJI));

        if (cdnUrl) {
            image.src = cdnUrl;
            image.classList.remove('hidden');
            glyph.classList.add('hidden');
            status.textContent = sourceLabel() + ' prêt pour Discord.';

            return;
        }

        showGlyph(value);
        status.textContent = sourceLabel() + ' utilisé dans les fiches.';
    }

    function selectCurrentOption() {
        const selected = options.find(function (option) {
            return option.dataset.emojiSource === sourceField.value
                && option.dataset.emojiValue === valueField.value;
        }) || options.find(function (option) {
            return option.dataset.emojiValue === defaultValue;
        }) || options[0];

        if (!selected) {
            return;
        }

        sourceField.value = selected.dataset.emojiSource || 'unicode';
        valueField.value = selected.dataset.emojiValue || defaultValue;
        markSelected(selected);
    }

    options.forEach(function (option) {
        option.addEventListener('click', function () {
            sourceField.value = option.dataset.emojiSource || 'unicode';
            valueField.value = option.dataset.emojiValue || defaultValue;
            markSelected(option);
            updatePreview(option);
        });
    });

    if (search) {
        search.addEventListener('input', function () {
            const query = search.value.trim().toLowerCase();

            options.forEach(function (option) {
                const name = (option.dataset.emojiName || '').toLowerCase();
                const value = (option.dataset.emojiValue || '').toLowerCase();

                option.classList.toggle('hidden', query !== '' && !name.includes(query) && !value.includes(query));
            });
        });
    }

    image.addEventListener('load', function () {
        status.textContent = sourceLabel() + ' affiché dans la prévisualisation.';
    });

    image.addEventListener('error', function () {
        showGlyph(valueField.value || defaultValue);
        status.textContent = 'Emoji indisponible, aperçu texte conservé.';
    });

    selectCurrentOption();
    updatePreview();
}

document.querySelectorAll('[data-emoji-picker]').forEach(initEmojiPicker);
