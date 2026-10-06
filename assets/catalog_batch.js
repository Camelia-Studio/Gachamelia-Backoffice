import { initEmojiPickers } from './emoji_picker.js';
import { initRankChoices } from './rank_choice.js';
import { acquireCatalogMutation, releaseCatalogMutation, refreshCatalogue } from './catalog_mutations.js';

const SOURCE_LABELS = { unicode: 'Standard', bot: 'Bot', server: 'Serveur' };

function initBatch(panel) {
    const form = panel.querySelector('[data-catalog-batch]');
    if (!form || form.dataset.batchReady) return;
    form.dataset.batchReady = 'true';
    const drafts = panel.querySelector('[data-catalog-new-rows]');
    const template = panel.querySelector('[data-catalog-new-row-template]');
    const add = form.querySelector('[data-batch-add]');
    const status = form.querySelector('[data-batch-status]');
    const defaultRank = panel.querySelector('[data-batch-rank-default]');
    const rows = () => Array.from(drafts.querySelectorAll('[data-catalog-new-row]'));
    let saving = false;
    let committed = false;

    function rankDefault(row) {
        const rank = row.querySelector('[data-row-field="rang"]');
        if (rank && !rank.value && defaultRank?.value) rank.value = defaultRank.selectedOptions[0].textContent;
    }

    function prepare() {
        rows().forEach((row, index) => {
            row.querySelector('[data-batch-row-label]').textContent = `Ligne ${index + 1}`;
            row.querySelector('[data-batch-error]').id = `batch-error-${index}`;
            row.querySelectorAll('[data-row-field]').forEach((field) => {
                field.name = `rows[${index}][${field.dataset.rowField}]`;
                field.setAttribute('form', form.id);
                field.setAttribute('aria-label', `${field.getAttribute('aria-label')?.replace(/ de la (nouvelle ligne|ligne \d+)$/, '') || field.dataset.rowField} de la ligne ${index + 1}`);
            });
            row.querySelector('[data-batch-remove]').disabled = saving || committed || rows().length === 1;
            rankDefault(row);
            const source = row.querySelector('[data-batch-emoji-source]');
            if (source) source.textContent = SOURCE_LABELS[row.querySelector('[data-emoji-field="source"]').value];
        });
        add.disabled = saving || committed || rows().length >= 100;
        form.querySelector('[data-batch-count]').textContent = `${rows().length} ligne${rows().length > 1 ? 's' : ''} à enregistrer · 100 maximum`;
        initEmojiPickers(panel);
    }

    function append(focus = true) {
        if (saving || committed || rows().length >= 100) return;
        drafts.append(template.content.cloneNode(true));
        prepare();
        if (focus) rows().at(-1).querySelector('input:not([type="hidden"]), select, textarea')?.focus({ preventScroll: true });
    }

    function clearErrors() {
        rows().forEach((row) => {
            const feedback = row.querySelector('[data-batch-error]');
            feedback.textContent = '';
            feedback.hidden = true;
            row.querySelectorAll('[aria-invalid]').forEach((field) => {
                field.removeAttribute('aria-invalid');
                field.removeAttribute('aria-describedby');
            });
        });
        status.hidden = true;
        status.classList.remove('bo-batch-error');
    }

    add.addEventListener('click', () => append());
    drafts.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-batch-remove]');
        if (!remove || saving || committed || rows().length === 1) return;
        const row = remove.closest('[data-catalog-new-row]');
        const index = rows().indexOf(row);
        row.remove();
        clearErrors();
        prepare();
        rows()[Math.min(index, rows().length - 1)].querySelector('[data-batch-remove]')?.focus({ preventScroll: true });
    });
    defaultRank?.addEventListener('change', () => rows().forEach((row) => {
        const rank = row.querySelector('[data-row-field="rang"]');
        if (rank && !row.querySelector('[data-row-field="message"]').value.trim()) {
            rank.value = defaultRank.value ? defaultRank.selectedOptions[0].textContent : '';
        }
    }));
    drafts.addEventListener('click', (event) => {
        const option = event.target.closest('[data-emoji-option]');
        if (option) {
            const row = option.closest('[data-catalog-new-row]');
            row.querySelector('[data-batch-emoji-source]').textContent = SOURCE_LABELS[option.dataset.emojiSource];
            row.querySelector('[data-batch-emoji-label]').textContent = option.dataset.emojiSource === 'unicode' ? option.dataset.emojiValue : option.dataset.emojiName;
        }
    });
    initRankChoices(panel);
    prepare();

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (saving || committed || !acquireCatalogMutation()) return;
        prepare();
        clearErrors();
        const payload = new FormData(form);
        const scroll = { x: window.scrollX, y: window.scrollY, left: panel.querySelector('.bo-table-wrap').scrollLeft };
        saving = true;
        const controls = Array.from(form.elements).concat(Array.from(drafts.querySelectorAll('button, input, select, textarea')));
        const disabled = new Map(controls.map((control) => [control, control.disabled]));
        controls.forEach((control) => { control.disabled = true; });
        if (defaultRank) defaultRank.disabled = true;
        form.setAttribute('aria-busy', 'true');
        status.hidden = false;
        status.textContent = 'Vérification et enregistrement du lot…';
        try {
            const response = await fetch(form.action, { method: 'POST', body: payload, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } });
            if (!response.headers.get('Content-Type')?.includes('application/json')) throw new Error('La session a expiré ou le serveur a refusé la requête. Recharge la page avant de réessayer.');
            const result = await response.json();
            if (!response.ok) {
                if (!Array.isArray(result.errors)) throw new Error('Le lot n’a pas pu être enregistré. Recharge la page et vérifie le catalogue.');
                const globalErrors = [];
                result.errors.forEach((error) => {
                    const row = rows()[error.line - 1];
                    if (!error.line || !row) { globalErrors.push(error.message); return; }
                    const feedback = row.querySelector('[data-batch-error]');
                    feedback.hidden = false;
                    feedback.textContent += `${feedback.textContent ? ' ' : ''}${error.column ? error.column + ' : ' : ''}${error.message}`;
                    const field = error.column && row.querySelector(`[data-row-field="${CSS.escape(error.column)}"]`);
                    if (field) { field.setAttribute('aria-invalid', 'true'); field.setAttribute('aria-describedby', feedback.id); }
                });
                status.classList.add('bo-batch-error');
                status.textContent = ['Aucune ligne enregistrée. Corrige les erreurs du lot.', ...globalErrors].join(' ');
                return;
            }
            committed = true;
            const url = new URL(result.url, window.location.href);
            if (url.origin !== window.location.origin) throw new Error('Le catalogue a changé.');
            const refresh = await fetch(url, { credentials: 'same-origin', headers: { 'X-Gachamelia-Catalog': '1' } });
            if (!refresh.ok) throw new Error('Actualisation indisponible.');
            refreshCatalogue(new DOMParser().parseFromString(await refresh.text(), 'text/html'));
            rows().forEach((row) => row.remove());
            committed = false;
            saving = false;
            append(false);
            status.textContent = `${result.created} nouvelle${result.created > 1 ? 's' : ''} entrée${result.created > 1 ? 's' : ''} enregistrée${result.created > 1 ? 's' : ''}.`;
        } catch (error) {
            status.classList.add('bo-batch-error');
            status.textContent = committed
                ? 'Le lot a été enregistré, mais l’affichage n’a pas pu être actualisé. Recharge la page pour le voir.'
                : (error instanceof TypeError ? 'L’enregistrement n’a pas pu être confirmé. Recharge la page pour vérifier le catalogue avant de réessayer.' : error.message);
        } finally {
            saving = false;
            disabled.forEach((value, control) => { control.disabled = committed || value; });
            if (defaultRank) defaultRank.disabled = false;
            form.removeAttribute('aria-busy');
            prepare();
            releaseCatalogMutation();
            requestAnimationFrame(() => {
                panel.querySelector('.bo-table-wrap').scrollLeft = scroll.left;
                window.scrollTo(scroll.x, scroll.y);
            });
        }
    });
}

document.querySelectorAll('[data-catalog-scope]').forEach(initBatch);
