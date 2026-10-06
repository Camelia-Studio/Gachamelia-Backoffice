import { initCatalogTables } from './catalog_table.js';
import { initEmojiPickers } from './emoji_picker.js';
import { initRankChoices } from './rank_choice.js';

let busy = false;
export function acquireCatalogMutation() {
    if (busy) return false;
    busy = true;
    return true;
}
export function releaseCatalogMutation() { busy = false; }
const cellValues = (row) => JSON.stringify(Array.from(row.querySelectorAll('td[data-column]')).map((cell) => cell.dataset.filterValue));

export function refreshCatalogue(fresh, editedRow = null) {
    const panel = document.querySelector('[data-catalog-scope]');
    const incoming = fresh.querySelector(`[data-catalog-scope="${CSS.escape(panel.dataset.catalogScope)}"]`);
    if (!incoming) throw new Error('La session ou le catalogue a changé. Recharge la page avant de réessayer.');
    const table = panel.querySelector('[data-catalog-table]');
    const oldRows = new Map(Array.from(table.querySelectorAll('[data-catalog-row]')).map((row) => [row.dataset.catalogRow, row]));
    const incomingRows = new Map(Array.from(incoming.querySelectorAll('[data-catalog-row]')).map((row) => [row.dataset.catalogRow, row]));
    oldRows.forEach((row, key) => { if (!incomingRows.has(key)) row.remove(); });
    incomingRows.forEach((row, key) => {
        const existing = oldRows.get(key);
        if (!existing) {
            table.tBodies[0].insertBefore(row.cloneNode(true), table.querySelector('[data-catalog-no-match]'));
        } else if (key === editedRow || cellValues(existing) !== cellValues(row)) {
            const replacement = row.cloneNode(true);
            if (existing.querySelector('details[open]')) replacement.querySelector('details')?.setAttribute('open', '');
            existing.replaceWith(replacement);
        }
    });
    table.querySelector('[data-catalog-empty]')?.remove();
    if (incomingRows.size === 0) {
        const empty = incoming.querySelector('[data-catalog-empty]');
        if (empty) table.tBodies[0].insertBefore(empty.cloneNode(true), table.querySelector('[data-catalog-no-match]'));
    }
    panel.querySelector('[data-catalog-count]').textContent = incoming.querySelector('[data-catalog-count]').textContent;
    const validation = document.querySelector('[data-testid="catalog-validation"]');
    const newValidation = fresh.querySelector('[data-testid="catalog-validation"]');
    if (validation && newValidation && validation.outerHTML !== newValidation.outerHTML) validation.replaceWith(newValidation.cloneNode(true));
    document.querySelectorAll('.bo-sidenav a[href]').forEach((link) => {
        const other = Array.from(fresh.querySelectorAll('.bo-sidenav a[href]')).find((item) => item.getAttribute('href') === link.getAttribute('href'));
        const count = link.querySelector('.bo-sidenav-count');
        if (count && other) count.textContent = other.querySelector('.bo-sidenav-count')?.textContent || count.textContent;
    });
    initEmojiPickers();
    initRankChoices();
    initCatalogTables();
}

document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!form.matches('form[data-catalog-mutation]')) return;
    event.preventDefault();
    const panel = document.querySelector('[data-catalog-scope]');
    const feedback = panel.querySelector('[data-catalog-operation-status]');
    if (!acquireCatalogMutation()) return;
    const scroll = { x: window.scrollX, y: window.scrollY, left: panel.querySelector('.bo-table-wrap').scrollLeft };
    feedback.hidden = false;
    feedback.classList.remove('bo-flash--error');
    feedback.textContent = 'Enregistrement en cours…';
    const payload = new FormData(form);
    const buttons = Array.from(form.querySelectorAll('button[type="submit"]'));
    buttons.forEach((button) => { button.disabled = true; });
    form.setAttribute('aria-busy', 'true');
    const editedRow = form.closest('[data-catalog-row]')?.dataset.catalogRow || null;
    const focusName = document.activeElement?.getAttribute('name');
    try {
        const response = await fetch(form.action, { method: 'POST', body: payload, credentials: 'same-origin', headers: { 'X-Gachamelia-Catalog': '1', 'X-Requested-With': 'XMLHttpRequest' } });
        const fresh = new DOMParser().parseFromString(await response.text(), 'text/html');
        const error = fresh.querySelector('[data-testid="flash-error"]');
        if (error) throw new Error(error.textContent.trim());
        if (!response.ok) throw new Error('L’enregistrement a été refusé. Vérifie la saisie et recharge la page si ta session a expiré.');
        refreshCatalogue(fresh, editedRow);
        if (!editedRow && form.id !== 'catalog-settings') {
            const rank = form.querySelector('[data-rank-choice]');
            const selected = rank?.value;
            form.reset();
            if (rank) rank.value = selected;
        }
        feedback.textContent = fresh.querySelector('[data-testid="flash-success"]')?.textContent.trim() || (event.submitter?.textContent.includes('Supprimer') ? 'Suppression effectuée.' : 'Enregistrement effectué.');
        if (editedRow && focusName) {
            panel.querySelector(`[data-catalog-row="${CSS.escape(editedRow)}"] [name="${CSS.escape(focusName)}"]`)?.focus({ preventScroll: true });
        }
    } catch (error) {
        feedback.classList.add('bo-flash--error');
        feedback.textContent = error instanceof TypeError ? 'L’enregistrement n’a pas pu être confirmé. Recharge la page pour vérifier le catalogue avant de réessayer.' : error.message;
    } finally {
        buttons.forEach((button) => { button.disabled = false; });
        form.removeAttribute('aria-busy');
        releaseCatalogMutation();
        requestAnimationFrame(() => {
            panel.querySelector('.bo-table-wrap').scrollLeft = scroll.left;
            window.scrollTo(scroll.x, scroll.y);
        });
    }
});
