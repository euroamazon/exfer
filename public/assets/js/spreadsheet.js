/**
 * ExFer — Module Tableur (Spreadsheet)
 * Gestion de la saisie inline des articles d'inventaire
 *
 * Fonctionnalités :
 * - Édition inline double-clic
 * - Autosave (debounce 1s)
 * - Copier/coller (Ctrl+C / Ctrl+V)
 * - Ajout de ligne (rouleau ou séquence)
 * - Saisie manuelle du code
 * - Duplication (×1 et ×N)
 * - Upload photo avec Vision IA
 * - Suppression avec confirmation
 * - Filtrage
 */

'use strict';

// ─── Config ───────────────────────────────────────────────────────────────────
const CFG = window.SPREADSHEET_CONFIG || {};
const BASE_URL = `/api/campaigns/${CFG.campaignId}/locations/${CFG.locationId}`;

// ─── État ─────────────────────────────────────────────────────────────────────
let currentItemId    = null;  // Pour la modal duplication/photo
let pendingManualCode = null; // Code manuel en attente de confirmation

// ─── Initialisation ───────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    initEditableTable();
    initToolbar();
    initValidation();
    initSearch();
    initCopyPaste();
});

// ─── Tableau éditable ─────────────────────────────────────────────────────────

function initEditableTable() {
    const tbody = document.getElementById('inventory-tbody');
    if (!tbody) return;

    tbody.addEventListener('dblclick', e => {
        const span = e.target.closest('.editable');
        if (!span || span.classList.contains('editing')) return;
        startEdit(span);
    });
}

function startEdit(span) {
    const type    = span.dataset.type || 'text';
    const options = span.dataset.options ? JSON.parse(span.dataset.options) : [];
    const value   = span.textContent.trim();

    span.classList.add('editing');

    let input;

    if (type === 'select' && options.length > 0) {
        input = document.createElement('select');
        input.className = 'form-select form-select-sm';
        input.innerHTML = '<option value="">—</option>';
        options.forEach(opt => {
            const o = document.createElement('option');
            o.value   = opt.value;
            o.text    = opt.label;
            o.selected = opt.value === value || opt.label === value;
            input.appendChild(o);
        });
    } else if (type === 'boolean') {
        input = document.createElement('select');
        input.className = 'form-select form-select-sm';
        input.innerHTML = '<option value="">—</option><option value="1">Oui</option><option value="0">Non</option>';
        input.value = value === 'Oui' ? '1' : value === 'Non' ? '0' : '';
    } else if (type === 'date') {
        input = document.createElement('input');
        input.type  = 'date';
        input.className = 'form-control form-control-sm';
        input.value = value;
    } else if (type === 'number') {
        input = document.createElement('input');
        input.type  = 'number';
        input.className = 'form-control form-control-sm';
        input.value = value;
    } else {
        input = document.createElement('input');
        input.type  = 'text';
        input.className = 'form-control form-control-sm';
        input.value = value;
    }

    const oldContent = span.innerHTML;
    span.innerHTML = '';
    span.appendChild(input);
    input.focus();
    input.select?.();

    const commitEdit = () => finishEdit(span, input, oldContent);
    const cancelEdit = () => { span.innerHTML = oldContent; span.classList.remove('editing'); };

    input.addEventListener('blur', commitEdit);
    input.addEventListener('keydown', e => {
        if (e.key === 'Enter')  { e.preventDefault(); commitEdit(); }
        if (e.key === 'Escape') { e.preventDefault(); cancelEdit(); }
        if (e.key === 'Tab')    { commitEdit(); } // Tab : passer à la prochaine cellule
    });
}

const autosaveTimers = {};

function finishEdit(span, input, oldContent) {
    const newValue = input.value;
    const display  = getDisplayValue(span.dataset.type, newValue, span.dataset.options);

    span.innerHTML    = escapeHtml(display);
    span.textContent  = display || '';
    span.classList.remove('editing');

    // Identifier l'article
    const row    = span.closest('tr');
    const itemId = row?.dataset.itemId;
    if (!itemId) return;

    // Préparer la mise à jour
    const field   = span.dataset.field;
    let payload   = {};

    if (field?.startsWith('attr_')) {
        const colKey = span.dataset.colKey;
        payload = { attributes: { [colKey]: newValue } };
    } else {
        payload = { [field]: newValue };
    }

    // Autosave avec debounce
    clearTimeout(autosaveTimers[itemId]);
    showAutosave(true);

    autosaveTimers[itemId] = setTimeout(async () => {
        try {
            await apiFetch(`/api/items/${itemId}/update`, {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            showAutosave(false);
        } catch (err) {
            showToast(err.message, 'danger');
            span.textContent = oldContent;
            showAutosave(false);
        }
    }, 800);
}

function getDisplayValue(type, value, optionsJson) {
    if (!value) return '';
    if (type === 'boolean') return value === '1' ? 'Oui' : 'Non';
    if ((type === 'select' || type === 'multi_select') && optionsJson) {
        try {
            const opts = JSON.parse(optionsJson);
            const found = opts.find(o => o.value === value);
            return found ? found.label : value;
        } catch { return value; }
    }
    return value;
}

function showAutosave(loading) {
    const ind = document.getElementById('autosave-indicator');
    if (!ind) return;
    ind.style.display = loading ? 'block' : 'none';
}

// ─── Barre d'outils ───────────────────────────────────────────────────────────

function initToolbar() {
    // Ajouter un article (attribution automatique du code)
    document.getElementById('btn-add-row')?.addEventListener('click', () => addItem(null));

    // Saisie manuelle du code
    document.getElementById('btn-add-manual')?.addEventListener('click', () => {
        document.getElementById('manual-code-input').value = '';
        document.getElementById('manual-code-warning').style.display = 'none';
        new bootstrap.Modal('#manualCodeModal').show();
    });

    // Vérification code manuel
    document.getElementById('manual-code-input')?.addEventListener('input', e => {
        const val      = e.target.value;
        const expected = CFG.suggestedCode;
        const warning  = document.getElementById('manual-code-warning');
        if (val && expected && val !== expected) {
            warning.style.display = 'block';
        } else {
            warning.style.display = 'none';
        }
    });

    // Confirmer le code manuel
    document.getElementById('btn-confirm-manual-code')?.addEventListener('click', () => {
        const code = document.getElementById('manual-code-input').value.trim();
        if (!code) { showToast('Veuillez saisir un code.', 'warning'); return; }
        bootstrap.Modal.getInstance('#manualCodeModal')?.hide();
        addItem(code);
    });

    // Validation du local
    document.getElementById('btn-validate-location')?.addEventListener('click', async function() {
        if (!confirm('Êtes-vous sûr de vouloir valider ce local ? Les contrôles qualité seront exécutés.')) return;
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Validation...';

        try {
            const result = await apiFetch(this.dataset.url, { method: 'POST' });
            showToast('Local validé avec succès.', 'success');
            setTimeout(() => location.reload(), 1500);
        } catch (err) {
            showToast('Validation refusée: ' + err.message, 'danger');
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-check-circle me-1"></i>Valider le local';
        }
    });

    // Duplication
    const tbody = document.getElementById('inventory-tbody');
    tbody?.addEventListener('click', e => {
        const btn = e.target.closest('.btn-duplicate');
        if (!btn) return;
        currentItemId = btn.dataset.itemId;
        document.getElementById('dup-count').value = 1;
        new bootstrap.Modal('#duplicateModal').show();
    });

    document.getElementById('btn-confirm-duplicate')?.addEventListener('click', async () => {
        if (!currentItemId) return;
        const count = parseInt(document.getElementById('dup-count').value) || 1;
        bootstrap.Modal.getInstance('#duplicateModal')?.hide();

        try {
            const result = await apiFetch(`/api/items/${currentItemId}/duplicate`, {
                method: 'POST',
                body: JSON.stringify({ count }),
            });

            result.data.items.forEach(item => appendItemRow(item));
            updateNextCode(result.data.next_code);
            updateCount(result.data.items.length);
            showToast(`${count} article(s) dupliqué(s).`, 'success');
        } catch (err) {
            showToast(err.message, 'danger');
        }
    });

    // Suppression
    tbody?.addEventListener('click', e => {
        const btn = e.target.closest('.btn-delete');
        if (!btn) return;
        if (!confirm('Supprimer cet article ? Cette action est irréversible.')) return;
        deleteItem(btn.dataset.itemId);
    });

    // Photos
    tbody?.addEventListener('click', e => {
        const btn = e.target.closest('.btn-photo');
        if (!btn) return;
        currentItemId = btn.dataset.itemId;
        openPhotoModal(currentItemId);
    });

    // Photo upload
    document.getElementById('photo-input')?.addEventListener('change', async e => {
        const file = e.target.files[0];
        if (!file || !currentItemId) return;

        const formData = new FormData();
        formData.append('photo', file);

        try {
            const result = await fetch(`/api/items/${currentItemId}/photos`, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': CSRF_TOKEN },
                body: formData,
            });
            const data = await result.json();

            if (!result.ok) throw new Error(data.message || 'Erreur upload');

            // Ajouter la photo dans la modal
            appendPhotoToModal(data.data.photo);

            if (data.data.mismatch) {
                showToast('Attention : l\'image ne correspond pas à la désignation (anomalie créée).', 'warning');
            } else {
                showToast('Photo ajoutée.', 'success');
            }

            // Mettre à jour le compteur de photos dans la ligne
            updatePhotoButton(currentItemId);

        } catch (err) {
            showToast(err.message, 'danger');
        }

        e.target.value = '';
    });
}

// ─── Ajout d'article ──────────────────────────────────────────────────────────

async function addItem(manualCode = null) {
    const payload = {};
    if (manualCode) {
        payload.code_immo = manualCode;
    }

    try {
        const result = await apiFetch(`${BASE_URL}/items`, {
            method: 'POST',
            body: JSON.stringify(payload),
        });

        appendItemRow(result.data.item);
        updateNextCode(result.data.next_code);
        updateCount(1);

        if (CFG.suggestedCode) {
            CFG.suggestedCode = result.data.next_code;
        }

    } catch (err) {
        showToast(err.message, 'danger');
    }
}

function appendItemRow(item) {
    const tbody = document.getElementById('inventory-tbody');
    if (!tbody) return;

    const attrs  = item.attributes || {};
    const photos = item.photos_json || [];

    const cols = CFG.columns || [];
    let attrCells = cols.map(col => {
        const val = attrs[col.key] || '';
        const disp = Array.isArray(val) ? val.join(', ') : val;
        return `<td><span class="editable" data-field="attr_${col.key}" data-col-key="${col.key}"
            data-type="${col.type}" data-options='${JSON.stringify(col.options || [])}'>
            ${escapeHtml(disp)}</span></td>`;
    }).join('');

    const tr = document.createElement('tr');
    tr.dataset.itemId = item.id;
    tr.className = 'item-row row-new';
    tr.innerHTML = `
        <td class="sticky-col fw-mono">
            <span class="editable" data-field="code_immo" data-type="text">${escapeHtml(item.code_immo)}</span>
        </td>
        <td class="text-muted small">${escapeHtml(item.code_local || '')}</td>
        <td><span class="editable" data-field="designation" data-type="text">${escapeHtml(item.designation || '')}</span></td>
        <td><span class="editable" data-field="serial_number" data-type="text">${escapeHtml(item.serial_number || '')}</span></td>
        ${attrCells}
        <td>
            <button class="btn btn-sm btn-outline-secondary btn-photo" data-item-id="${item.id}" title="0 photo(s)">
                <i class="bi bi-camera"></i>
            </button>
        </td>
        <td><span class="badge bg-secondary">Brouillon</span></td>
        <td>
            <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-primary btn-duplicate" data-item-id="${item.id}" title="Dupliquer">
                    <i class="bi bi-copy"></i>
                </button>
                <button class="btn btn-outline-danger btn-delete" data-item-id="${item.id}" title="Supprimer">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </td>`;

    tbody.appendChild(tr);

    // Scroll vers la nouvelle ligne
    tr.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// ─── Suppression ──────────────────────────────────────────────────────────────

async function deleteItem(itemId) {
    try {
        await apiFetch(`/api/items/${itemId}/delete`, { method: 'POST' });

        const row = document.querySelector(`tr[data-item-id="${itemId}"]`);
        if (row) {
            row.style.transition = 'opacity .3s';
            row.style.opacity = '0';
            setTimeout(() => row.remove(), 300);
        }

        updateCount(-1);
        showToast('Article supprimé.', 'success');
    } catch (err) {
        showToast(err.message, 'danger');
    }
}

// ─── Photos ───────────────────────────────────────────────────────────────────

async function openPhotoModal(itemId) {
    const modal = new bootstrap.Modal('#photoModal');
    const body  = document.getElementById('photo-modal-body');

    body.innerHTML = '<div class="text-center py-3"><div class="spinner-border"></div></div>';
    modal.show();

    try {
        const result = await apiFetch(`${BASE_URL}/items`);
        const item   = result.data.items.find(i => String(i.id) === String(itemId));
        const photos = item?.photos_json || [];

        if (photos.length === 0) {
            body.innerHTML = '<p class="text-center text-muted py-3"><i class="bi bi-camera fs-1 d-block mb-2"></i>Aucune photo</p>';
        } else {
            body.innerHTML = `<div class="photo-grid">${photos.map(p => renderPhoto(p, itemId)).join('')}</div>`;
        }
    } catch {
        body.innerHTML = '<p class="text-danger text-center">Erreur de chargement.</p>';
    }
}

function renderPhoto(photo, itemId) {
    return `<div class="photo-item" data-photo-id="${photo.id}">
        <img src="/storage/uploads/${photo.path}" alt="Photo" loading="lazy">
        ${photo.vision_label ? `<span class="badge bg-dark vision-badge position-absolute top-0 start-0 m-1" title="Vision IA">${escapeHtml(photo.vision_label)} ${Math.round((photo.vision_confidence || 0) * 100)}%</span>` : ''}
        <div class="photo-overlay">
            <button class="btn btn-sm btn-light" onclick="deletePhoto('${itemId}', '${photo.id}')">
                <i class="bi bi-trash text-danger"></i>
            </button>
        </div>
    </div>`;
}

function appendPhotoToModal(photo) {
    const grid = document.querySelector('#photo-modal-body .photo-grid');
    if (!grid) {
        document.getElementById('photo-modal-body').innerHTML = `<div class="photo-grid">${renderPhoto(photo, currentItemId)}</div>`;
    } else {
        grid.insertAdjacentHTML('beforeend', renderPhoto(photo, currentItemId));
    }
}

async function deletePhoto(itemId, photoId) {
    if (!confirm('Supprimer cette photo ?')) return;
    try {
        await apiFetch(`/api/items/${itemId}/photos/${photoId}/delete`, { method: 'POST' });
        document.querySelector(`.photo-item[data-photo-id="${photoId}"]`)?.remove();
        updatePhotoButton(itemId);
        showToast('Photo supprimée.', 'success');
    } catch (err) {
        showToast(err.message, 'danger');
    }
}

function updatePhotoButton(itemId) {
    // Recharger le compteur de photos en dehors du modal n'est pas implémenté ici
    // On pourrait faire un appel API, mais pour l'UX on incrémente/décrémente
}

// ─── Copier / Coller ──────────────────────────────────────────────────────────

function initCopyPaste() {
    let clipboard = null;

    document.addEventListener('keydown', e => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'c') {
            const active = document.activeElement;
            const row = active?.closest('tr.item-row');
            if (row && !active.closest('.editable.editing')) {
                clipboard = row.dataset.itemId;
            }
        }

        if ((e.ctrlKey || e.metaKey) && e.key === 'v') {
            if (clipboard) {
                e.preventDefault();
                currentItemId = clipboard;
                document.getElementById('dup-count').value = 1;
                new bootstrap.Modal('#duplicateModal').show();
            }
        }

        if (e.key === 'Delete' || e.key === 'Backspace') {
            const active = document.activeElement;
            const row = active?.closest('tr.item-row');
            if (row && active.tagName !== 'INPUT' && active.tagName !== 'TEXTAREA') {
                if (confirm('Supprimer cet article ?')) {
                    deleteItem(row.dataset.itemId);
                }
            }
        }
    });
}

// ─── Validation locale ────────────────────────────────────────────────────────

function initValidation() {
    // La validation est gérée par le bouton btn-validate-location dans initToolbar
}

// ─── Recherche / Filtre ───────────────────────────────────────────────────────

function initSearch() {
    const searchInput  = document.getElementById('search-items');
    const filterStatus = document.getElementById('filter-status');

    const applyFilter = () => {
        const term   = searchInput?.value.toLowerCase() || '';
        const status = filterStatus?.value || '';

        document.querySelectorAll('#inventory-tbody .item-row').forEach(row => {
            const text    = row.textContent.toLowerCase();
            const badgeEl = row.querySelector('.badge');
            const rowStatus = badgeEl?.textContent.trim();

            const matchTerm   = !term   || text.includes(term);
            const matchStatus = !status || rowStatus?.includes(status);

            row.style.display = (matchTerm && matchStatus) ? '' : 'none';
        });
    };

    searchInput?.addEventListener('input', applyFilter);
    filterStatus?.addEventListener('change', applyFilter);
}

// ─── Utilitaires ──────────────────────────────────────────────────────────────

function updateNextCode(nextCode) {
    if (!nextCode) return;
    CFG.suggestedCode = nextCode;

    const badge = document.querySelector('.badge.bg-light.text-dark strong');
    if (badge) badge.textContent = nextCode;

    const rollBadge = document.querySelector('.badge.bg-success');
    if (rollBadge && CFG.labelRollEnabled) {
        rollBadge.textContent = `Rouleau: ${nextCode}`;
    }

    document.getElementById('suggested-code-display').value = nextCode;
}

function updateCount(delta) {
    const countEl = document.getElementById('items-count');
    if (!countEl) return;
    const match = countEl.textContent.match(/\d+/);
    const count = (parseInt(match?.[0] || 0)) + delta;
    countEl.textContent = `${count} article(s)`;
}
