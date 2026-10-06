/*
 * Point d'entrée JavaScript du backoffice.
 *
 * Inclus via importmap('backoffice') dans base_backoffice.html.twig.
 */
import './styles/base.css';
import './styles/backoffice.css';
import './emoji_picker.js';

import './rank_choice.js';
import './catalog_table.js';
import './catalog_mutations.js';
import './catalog_batch.js';
import './backoffice_theme.js';

// En navigation horizontale, la catégorie ouverte reste visible à l’arrivée.
document.querySelectorAll('.bo-sidenav').forEach((nav) => {
    const active = nav.querySelector('[aria-current="page"]');
    if (!active) return;
    new ResizeObserver(() => {
        if (nav.scrollWidth <= nav.clientWidth) return;
        const item = active.getBoundingClientRect();
        const box = nav.getBoundingClientRect();
        nav.scrollLeft += item.left - box.left - (nav.clientWidth - item.width) / 2;
    }).observe(nav);
});
