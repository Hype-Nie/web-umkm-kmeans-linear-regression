// Global custom confirm dialog
function showCustomConfirm(message, onConfirm) {
    let overlay = document.getElementById('app-confirm-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'app-confirm-overlay';
        overlay.className = 'app-confirm-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.innerHTML = `
            <div class="app-confirm-backdrop"></div>
            <div class="app-confirm-card">
                <div class="app-confirm-icon-circle">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                </div>
                <div class="app-confirm-text">
                    <h3 class="app-confirm-title">Konfirmasi Tindakan</h3>
                    <p class="app-confirm-desc" id="app-confirm-message"></p>
                </div>
                <div class="app-confirm-actions">
                    <button type="button" class="btn" id="app-confirm-btn-cancel">Batal</button>
                    <button type="button" class="btn btn-danger" id="app-confirm-btn-proceed">Ya, Lanjutkan</button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);

        const cancelBtn = overlay.querySelector('#app-confirm-btn-cancel');
        const backdrop = overlay.querySelector('.app-confirm-backdrop');
        const close = () => {
            overlay.classList.remove('active');
        };

        cancelBtn.addEventListener('click', close);
        backdrop.addEventListener('click', close);
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && overlay.classList.contains('active')) close();
        });
    }

    const msgEl = overlay.querySelector('#app-confirm-message');
    if (msgEl) {
        msgEl.textContent = message || 'Apakah Anda yakin ingin melanjutkan tindakan ini?';
    }

    const proceedBtn = overlay.querySelector('#app-confirm-btn-proceed');
    const newProceedBtn = proceedBtn.cloneNode(true);
    proceedBtn.parentNode.replaceChild(newProceedBtn, proceedBtn);

    newProceedBtn.addEventListener('click', () => {
        overlay.classList.remove('active');
        if (typeof onConfirm === 'function') onConfirm();
    });

    overlay.classList.add('active');
}

// Global initialization of interactive components
function initApp(root = document) {
    // Password visibility toggles
    root.querySelectorAll('[data-password]').forEach(button => {
        if (button._hasPasswordToggle) return;
        button._hasPasswordToggle = true;
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.password);
            if (!input) return;
            const visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            button.setAttribute('aria-label', visible ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
            button.setAttribute('aria-pressed', String(visible));
        });
    });

    // Form confirm modals (Custom Themed Dialog)
    root.querySelectorAll('form[data-confirm]').forEach(form => {
        if (form._hasConfirm) return;
        form._hasConfirm = true;
        form.addEventListener('submit', e => {
            if (form._skipCustomConfirm) {
                form._skipCustomConfirm = false;
                return;
            }
            e.preventDefault();
            showCustomConfirm(form.dataset.confirm, () => {
                form._skipCustomConfirm = true;
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            });
        });
    });

    // Drag and drop zone
    const zone = root.querySelector('[data-dropzone]');
    const file = root.querySelector('#upload-file');
    if (zone && file && !zone._hasDropzone) {
        zone._hasDropzone = true;
        ['dragenter', 'dragover'].forEach(name => zone.addEventListener(name, e => {
            e.preventDefault();
            zone.classList.add('dragover');
        }));
        ['dragleave', 'drop'].forEach(name => zone.addEventListener(name, e => {
            e.preventDefault();
            zone.classList.remove('dragover');
        }));
        zone.addEventListener('drop', e => {
            if (e.dataTransfer.files.length === 1) {
                file.files = e.dataTransfer.files;
                file.dispatchEvent(new Event('change'));
            }
        });
        file.addEventListener('change', () => {
            const label = root.querySelector('[data-file-name]');
            if (label) {
                label.textContent = file.files[0]?.name || 'Belum ada file dipilih';
            }
        });
    }

    // Dynamic price x quantity calculator
    const qty = root.querySelector('input[data-quantity], [data-quantity]');
    const price = root.querySelector('input[data-unit-price], input[data-price], input[name="harga_satuan_jual"]');
    const total = root.querySelector('input[data-total], [data-total]');
    function updateTotal() {
        if (!qty || !price || !total) return;
        const raw = price.value;
        if (!raw || !/^\d+(\.\d{1,2})?$/.test(raw) || !qty.value || !/^\d+$/.test(qty.value) || BigInt(qty.value) === 0n) {
            total.value = '0';
            return;
        }
        const [whole, fraction = ''] = raw.split('.');
        const cents = BigInt(whole) * 100n + BigInt(fraction.padEnd(2, '0'));
        const result = cents * BigInt(qty.value);
        if (result === 0n) {
            total.value = '0';
            return;
        }
        total.value = (result / 100n).toString() + '.' + (result % 100n).toString().padStart(2, '0');
    }
    if (qty && price) {
        [qty, price].forEach(input => {
            if (!input._hasTotalCalc) {
                input._hasTotalCalc = true;
                input.addEventListener('input', updateTotal);
            }
        });
        updateTotal();
    }

    // Product selector auto-filling price
    const productPriceSelect = root.querySelector('[data-product-price]');
    if (productPriceSelect && !productPriceSelect._hasProductPrice) {
        productPriceSelect._hasProductPrice = true;
        productPriceSelect.addEventListener('change', e => {
            const option = e.target.selectedOptions[0];
            if (price) {
                if (option && option.dataset && option.dataset.price) {
                    price.value = option.dataset.price;
                } else {
                    price.value = '';
                }
                updateTotal();
            }
        });
    }

    // Print buttons & chart print sync
    root.querySelectorAll('[data-print]').forEach(button => {
        if (button._hasPrint) return;
        button._hasPrint = true;
        button.addEventListener('click', () => {
            if (typeof Chart !== 'undefined' && Chart.instances) {
                Object.values(Chart.instances).forEach(chart => {
                    try { chart.resize(); } catch (e) {}
                });
            }
            window.print();
        });
    });

    if (!window._hasBeforePrintHook) {
        window._hasBeforePrintHook = true;
        window.addEventListener('beforeprint', () => {
            if (typeof Chart !== 'undefined' && Chart.instances) {
                Object.values(Chart.instances).forEach(chart => {
                    try { chart.resize(); } catch (e) {}
                });
            }
        });
    }

    // Zoom buttons for report paper
    root.querySelectorAll('[data-zoom]').forEach(button => {
        if (button._hasZoom) return;
        button._hasZoom = true;
        button.addEventListener('click', () => {
            const paper = document.querySelector('.paper');
            if (!paper) return;
            const current = Number(paper.style.zoom || 1);
            paper.style.zoom = Math.min(1.5, Math.max(0.6, current + Number(button.dataset.zoom)));
        });
    });

    // Form submit locker (prevent double-submitting)
    root.querySelectorAll('form[data-submit-lock]').forEach(form => {
        if (form._hasSubmitLock) return;
        form._hasSubmitLock = true;
        form.addEventListener('submit', () => {
            if (form.checkValidity()) {
                form.querySelectorAll('button[type="submit"]').forEach(button => {
                    button.disabled = true;
                    button.textContent = 'Memproses…';
                });
            }
        });
    });

    // Chart.js renderers
    root.querySelectorAll('canvas[data-chart]').forEach(canvas => {
        const payload = document.getElementById(canvas.dataset.chart);
        if (!payload) return;
        if (typeof Chart === 'undefined') {
            const note = document.createElement('p');
            note.className = 'muted';
            note.textContent = 'Grafik belum dimuat. Periksa koneksi internet; data tetap tersedia pada tabel.';
            canvas.after(note);
            return;
        }
        // Destroy existing chart on this canvas if previously instantiated
        const existingChart = Chart.getChart(canvas);
        if (existingChart) {
            existingChart.destroy();
        }
        try {
            const config = JSON.parse(payload.textContent);
            if (canvas.dataset.chart === 'forecast-chart') {
                config.options = config.options || {};
                config.options.plugins = config.options.plugins || {};
                config.options.plugins.tooltip = config.options.plugins.tooltip || {};
                config.options.plugins.tooltip.callbacks = {
                    label: function(context) {
                        const val = context.parsed.y;
                        if (val === null || val === undefined) return '';
                        return (context.dataset.label || '') + ': ' + val + ' unit';
                    }
                };
            }
            new Chart(canvas, config);
        } catch (e) {
            console.error('Error rendering chart:', e);
        }
    });

    // Initialize searchable select components
    initSearchableSelects(root);
}

// Searchable select component for responsive dropdowns with live search
function initSearchableSelects(root = document) {
    root.querySelectorAll('select[data-searchable], select#produk, select#id_barang').forEach(select => {
        if (select._hasSearchableSelect || select.closest('.searchable-select-wrap')) return;
        select._hasSearchableSelect = true;
        select.setAttribute('data-searchable', 'true');

        // Build wrapper
        const wrap = document.createElement('div');
        wrap.className = 'searchable-select-wrap';
        if (select.id) wrap.setAttribute('data-select-id', select.id);

        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(select);

        // Required attribute handling to prevent invisible element validation issues
        if (select.hasAttribute('required')) {
            select.removeAttribute('required');
            select._isRequired = true;
        }

        // Trigger button
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'searchable-select-trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');

        const label = document.createElement('span');
        label.className = 'searchable-select-label';

        const arrow = document.createElement('span');
        arrow.className = 'searchable-select-arrow';
        arrow.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>`;

        trigger.appendChild(label);
        trigger.appendChild(arrow);
        wrap.appendChild(trigger);

        // Dropdown panel
        const dropdown = document.createElement('div');
        dropdown.className = 'searchable-select-dropdown';
        dropdown.setAttribute('role', 'listbox');

        // Search input
        const searchPlaceholder = select.getAttribute('data-search-placeholder')
            || (select.id === 'kategori' ? 'Cari kategori...' : (select.id === 'produk' || select.id === 'id_barang' ? 'Ketik nama / kode produk...' : 'Ketik untuk mencari...'));
        const searchBox = document.createElement('div');
        searchBox.className = 'searchable-select-search-box';
        searchBox.innerHTML = `
            <svg class="searchable-select-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="text" class="searchable-select-search-input" placeholder="${searchPlaceholder}" autocomplete="off">
            <button type="button" class="searchable-select-clear-btn" aria-label="Hapus pencarian">&times;</button>
        `;
        dropdown.appendChild(searchBox);

        const searchInput = searchBox.querySelector('.searchable-select-search-input');
        const clearBtn = searchBox.querySelector('.searchable-select-clear-btn');

        // Options container
        const optionsList = document.createElement('div');
        optionsList.className = 'searchable-select-options';
        dropdown.appendChild(optionsList);

        const emptyMsg = document.createElement('div');
        emptyMsg.className = 'searchable-select-empty';
        emptyMsg.textContent = 'Tidak ditemukan pilihan yang cocok';
        emptyMsg.style.display = 'none';
        optionsList.appendChild(emptyMsg);

        wrap.appendChild(dropdown);

        // Populate options from native select
        function renderOptions() {
            const oldOpts = optionsList.querySelectorAll('.searchable-select-option');
            oldOpts.forEach(o => o.remove());

            const selectedOption = select.selectedOptions[0] || select.options[0];
            label.textContent = selectedOption ? selectedOption.textContent.trim() : (select.getAttribute('placeholder') || 'Pilih...');

            Array.from(select.options).forEach(opt => {
                const optEl = document.createElement('div');
                optEl.className = 'searchable-select-option' + (opt.selected ? ' selected' : '');
                optEl.setAttribute('role', 'option');
                optEl.setAttribute('data-value', opt.value);
                optEl.textContent = opt.textContent.trim();

                optEl.addEventListener('click', (e) => {
                    e.stopPropagation();
                    select.value = opt.value;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    updateSelectedState();
                    closeDropdown();
                    trigger.focus();
                });

                optionsList.insertBefore(optEl, emptyMsg);
            });
        }

        function updateSelectedState() {
            const selectedVal = select.value;
            const currentSelected = select.selectedOptions[0];
            label.textContent = currentSelected ? currentSelected.textContent.trim() : (select.getAttribute('placeholder') || 'Pilih...');

            trigger.style.borderColor = '';
            trigger.style.boxShadow = '';

            optionsList.querySelectorAll('.searchable-select-option').forEach(optEl => {
                if (optEl.getAttribute('data-value') === selectedVal) {
                    optEl.classList.add('selected');
                } else {
                    optEl.classList.remove('selected');
                }
            });
        }

        renderOptions();

        // Search filtering
        function filterOptions(query) {
            const q = query.trim().toLowerCase();
            let visibleCount = 0;
            optionsList.querySelectorAll('.searchable-select-option').forEach(optEl => {
                const text = optEl.textContent.toLowerCase();
                if (!q || text.includes(q)) {
                    optEl.style.display = '';
                    visibleCount++;
                } else {
                    optEl.style.display = 'none';
                }
                optEl.classList.remove('highlighted');
            });

            emptyMsg.style.display = visibleCount === 0 ? 'block' : 'none';
            clearBtn.style.display = q ? 'block' : 'none';
        }

        searchInput.addEventListener('input', () => {
            filterOptions(searchInput.value);
        });

        clearBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            searchInput.value = '';
            filterOptions('');
            searchInput.focus();
        });

        // Open/Close
        function openDropdown() {
            document.querySelectorAll('.searchable-select-wrap.active').forEach(w => {
                if (w !== wrap) {
                    w.classList.remove('active');
                    w.querySelector('.searchable-select-trigger')?.setAttribute('aria-expanded', 'false');
                }
            });

            wrap.classList.add('active');
            trigger.setAttribute('aria-expanded', 'true');
            searchInput.value = '';
            filterOptions('');
            setTimeout(() => searchInput.focus(), 50);

            const sel = optionsList.querySelector('.searchable-select-option.selected');
            if (sel) {
                sel.scrollIntoView({ block: 'nearest' });
            }
        }

        function closeDropdown() {
            wrap.classList.remove('active');
            trigger.setAttribute('aria-expanded', 'false');
        }

        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            if (wrap.classList.contains('active')) {
                closeDropdown();
            } else {
                openDropdown();
            }
        });

        select.addEventListener('change', () => {
            updateSelectedState();
        });

        // Form submit validation for required selects
        select.form?.addEventListener('submit', (e) => {
            if (select._isRequired && !select.value) {
                e.preventDefault();
                e.stopPropagation();
                trigger.focus();
                trigger.style.borderColor = '#ef4444';
                trigger.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.2)';
                openDropdown();
            }
        });

        // Keyboard navigation in search input
        searchInput.addEventListener('keydown', (e) => {
            const visibleOptions = Array.from(optionsList.querySelectorAll('.searchable-select-option:not([style*="display: none"])'));
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                const currentIndex = visibleOptions.findIndex(o => o.classList.contains('highlighted'));
                const nextIndex = currentIndex < visibleOptions.length - 1 ? currentIndex + 1 : 0;
                visibleOptions.forEach(o => o.classList.remove('highlighted'));
                if (visibleOptions[nextIndex]) {
                    visibleOptions[nextIndex].classList.add('highlighted');
                    visibleOptions[nextIndex].scrollIntoView({ block: 'nearest' });
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                const currentIndex = visibleOptions.findIndex(o => o.classList.contains('highlighted'));
                const prevIndex = currentIndex > 0 ? currentIndex - 1 : visibleOptions.length - 1;
                visibleOptions.forEach(o => o.classList.remove('highlighted'));
                if (visibleOptions[prevIndex]) {
                    visibleOptions[prevIndex].classList.add('highlighted');
                    visibleOptions[prevIndex].scrollIntoView({ block: 'nearest' });
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                const highlighted = optionsList.querySelector('.searchable-select-option.highlighted:not([style*="display: none"])');
                const firstVisible = visibleOptions[0];
                if (highlighted) {
                    highlighted.click();
                } else if (firstVisible) {
                    firstVisible.click();
                }
            } else if (e.key === 'Escape') {
                closeDropdown();
                trigger.focus();
            }
        });
    });

    if (!document._hasSearchableSelectGlobalEvents) {
        document._hasSearchableSelectGlobalEvents = true;
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.searchable-select-wrap')) {
                document.querySelectorAll('.searchable-select-wrap.active').forEach(w => {
                    w.classList.remove('active');
                    w.querySelector('.searchable-select-trigger')?.setAttribute('aria-expanded', 'false');
                });
            }
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.searchable-select-wrap.active').forEach(w => {
                    w.classList.remove('active');
                    w.querySelector('.searchable-select-trigger')?.setAttribute('aria-expanded', 'false');
                });
            }
        });
    }
}

// Top loading indicator
let progressBar = null;
function getProgressBar() {
    if (!progressBar) {
        progressBar = document.createElement('div');
        progressBar.className = 'top-progress-bar';
        document.body.appendChild(progressBar);
    }
    return progressBar;
}

function startProgress() {
    const bar = getProgressBar();
    bar.style.width = '0%';
    bar.style.opacity = '1';
    requestAnimationFrame(() => {
        bar.style.width = '65%';
    });
}

function finishProgress() {
    const bar = getProgressBar();
    bar.style.width = '100%';
    setTimeout(() => {
        bar.style.opacity = '0';
        setTimeout(() => {
            bar.style.width = '0%';
        }, 150);
    }, 80);
}

// In-Memory Page Cache & Hover Prefetching
const pageCache = new Map();
const CACHE_TTL_MS = 60 * 1000; // 60 seconds

function prefetchPage(url) {
    try {
        const targetUrl = new URL(url, window.location.href);
        if (targetUrl.origin !== window.location.origin) return;
        if (pageCache.has(targetUrl.href)) {
            const item = pageCache.get(targetUrl.href);
            if (Date.now() - item.timestamp < CACHE_TTL_MS) return;
        }

        fetch(targetUrl.href, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-SPK-Transition': 'true'
            }
        }).then(res => {
            const ct = res.headers.get('content-type') || '';
            if (res.ok && ct.includes('text/html')) {
                return res.text();
            }
            return null;
        }).then(html => {
            if (html) {
                pageCache.set(targetUrl.href, { html, timestamp: Date.now() });
            }
        }).catch(() => {});
    } catch (e) {}
}

// Seamless soft navigation engine (no white flash / instant cache load)
let isNavigating = false;

async function loadPage(url, push = true, options = {}) {
    if (isNavigating) return;
    const targetUrl = new URL(url, window.location.href);

    const {
        scrollMode = 'top',
        scrollTargetSelector = null,
        savedScrollY = window.scrollY,
    } = options;

    // If identical URL, just smooth scroll
    if (targetUrl.pathname === window.location.pathname && targetUrl.search === window.location.search) {
        if (targetUrl.hash) {
            const el = document.querySelector(targetUrl.hash);
            if (el) el.scrollIntoView({ behavior: 'smooth' });
        } else if (scrollMode === 'preserve') {
            window.scrollTo({ top: savedScrollY, behavior: 'instant' });
        } else {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        return;
    }

    isNavigating = true;

    const main = document.querySelector('.main');
    if (main) {
        main.classList.add('page-transitioning');
    }

    try {
        let html = null;
        const cached = pageCache.get(targetUrl.href);
        if (cached && (Date.now() - cached.timestamp < CACHE_TTL_MS)) {
            html = cached.html;
        } else {
            startProgress();
            const response = await fetch(targetUrl.href, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-SPK-Transition': 'true'
                }
            });

            const contentType = response.headers.get('content-type') || '';
            if (!response.ok || !contentType.includes('text/html')) {
                window.location.href = targetUrl.href;
                return;
            }

            html = await response.text();
            pageCache.set(targetUrl.href, { html, timestamp: Date.now() });
        }

        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');

        const newMain = doc.querySelector('.main');
        if (!newMain || !main) {
            window.location.href = targetUrl.href;
            return;
        }

        // Update document title
        if (doc.title) {
            document.title = doc.title;
        }

        // Update sidebar nav items & active classes
        const newNav = doc.querySelector('.sidebar nav');
        const currentNav = document.querySelector('.sidebar nav');
        if (newNav && currentNav) {
            currentNav.innerHTML = newNav.innerHTML;
        }

        // Close mobile drawer if open
        document.querySelector('.sidebar')?.classList.remove('open');

        // Swap main content without flickering fixed header or sidebar
        main.innerHTML = newMain.innerHTML;

        // Update browser URL & history
        if (push) {
            window.history.pushState({ path: targetUrl.href }, '', targetUrl.href);
        }

        // Re-initialize charts and interactive elements in new content
        initApp(main);

        // Smart scroll positioning
        if (targetUrl.hash) {
            const el = document.querySelector(targetUrl.hash);
            if (el) {
                el.scrollIntoView({ behavior: 'smooth' });
            }
        } else if (scrollMode === 'preserve') {
            window.scrollTo({ top: savedScrollY, behavior: 'instant' });
        } else if (scrollMode === 'table') {
            let targetEl = null;
            if (typeof scrollTargetSelector === 'string') {
                targetEl = document.querySelector(scrollTargetSelector);
            } else if (scrollTargetSelector && scrollTargetSelector.type === 'heading') {
                const headings = document.querySelectorAll('h1, h2, h3, .card-head');
                for (const h of headings) {
                    if (h.textContent.trim() === scrollTargetSelector.text) {
                        targetEl = h.closest('.card') || h;
                        break;
                    }
                }
            }
            if (!targetEl) {
                targetEl = document.querySelector('.table-wrap, .recommendations, .table');
            }

            if (targetEl) {
                const rect = targetEl.getBoundingClientRect();
                const headerOffset = 70; // fixed header height + breathing room
                const targetY = window.scrollY + rect.top - headerOffset;

                // If table is not already comfortably visible, scroll smoothly to it
                if (rect.top < 40 || rect.top > window.innerHeight * 0.45) {
                    window.scrollTo({ top: Math.max(0, targetY), behavior: 'smooth' });
                }
            } else {
                window.scrollTo({ top: savedScrollY, behavior: 'instant' });
            }
        } else {
            // Default top for top-level menu navigation
            window.scrollTo(0, 0);
        }

    } catch (err) {
        window.location.href = targetUrl.href;
    } finally {
        if (main) {
            main.classList.remove('page-transitioning');
        }
        finishProgress();
        isNavigating = false;
    }
}

// Initial DOM Setup
document.addEventListener('DOMContentLoaded', () => {
    // Restore scroll position after POST/PATCH mutations (e.g. status updates)
    try {
        const rawScroll = sessionStorage.getItem('spk_restore_scroll');
        if (rawScroll) {
            sessionStorage.removeItem('spk_restore_scroll');
            const data = JSON.parse(rawScroll);
            if (data && data.path === window.location.pathname && (Date.now() - data.time < 15000)) {
                requestAnimationFrame(() => {
                    window.scrollTo({ top: data.y, behavior: 'instant' });
                });
            }
        }
    } catch (e) {}

    // Mobile menu toggle
    document.querySelector('[data-menu]')?.addEventListener('click', () => {
        document.querySelector('.sidebar')?.classList.toggle('open');
    });

    // Initialize all components on initial page load
    initApp(document);

    // Prefetch on hover/touch for instant 0ms page opening
    document.addEventListener('pointerenter', e => {
        const link = e.target.closest('a');
        if (!link) return;
        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) return;
        const url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin) return;
        if (url.pathname.includes('/export') || url.pathname.includes('/template') || url.pathname.includes('/dataset') || url.searchParams.has('format')) return;
        prefetchPage(url.href);
    }, { passive: true });

    // Intercept internal link clicks for smooth seamless navigation
    document.addEventListener('click', e => {
        const link = e.target.closest('a');
        if (!link) return;

        // Skip modified clicks (new window/tab)
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
            return;
        }

        // Skip downloads and blank targets
        if (link.hasAttribute('download') || link.target === '_blank') {
            return;
        }

        // Skip non-HTTP links
        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) {
            return;
        }

        // Skip external origins
        const url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin) {
            return;
        }

        // Skip export / file download endpoints
        if (url.pathname.includes('/export') || url.pathname.includes('/template') || url.pathname.includes('/dataset') || url.searchParams.has('format')) {
            return;
        }

        // Skip if inside forms or specifically opted out
        if (link.hasAttribute('data-no-pjax') || link.hasAttribute('data-print')) {
            return;
        }

        // Only intercept app links
        if (!link.closest('.app-top, .sidebar, .main, .page-head, .chips, .filters, .empty, .pagination, .pagination-wrap')) {
            return;
        }

        const isPagination = Boolean(link.closest('.pagination, .pagination-wrap') || url.searchParams.has('page'));
        const isChips = Boolean(link.closest('.chips'));
        const isSidebarOrHeader = Boolean(link.closest('.sidebar, .app-top'));

        let scrollMode = 'top';
        let scrollTargetSelector = null;
        const savedScrollY = window.scrollY;

        if (isPagination) {
            scrollMode = 'table';
            const card = link.closest('.card, .recommendations, section');
            if (card) {
                if (card.id) {
                    scrollTargetSelector = '#' + card.id;
                } else {
                    const h = card.querySelector('h1, h2, h3, .card-head');
                    if (h && h.textContent.trim()) {
                        scrollTargetSelector = { type: 'heading', text: h.textContent.trim() };
                    } else {
                        scrollTargetSelector = '.table-wrap, .recommendations, .table';
                    }
                }
            }
        } else if (isChips) {
            scrollMode = 'preserve';
        } else if (!isSidebarOrHeader && (url.pathname === window.location.pathname)) {
            // Same page link / tab filter
            scrollMode = 'preserve';
        }

        e.preventDefault();
        loadPage(url.href, true, { scrollMode, scrollTargetSelector, savedScrollY });
    });

    // Invalidate cache on mutations (POST, PUT, PATCH, DELETE) and preserve scroll
    document.addEventListener('submit', e => {
        const form = e.target;
        if (form.method.toLowerCase() !== 'get') {
            try {
                sessionStorage.setItem('spk_restore_scroll', JSON.stringify({
                    y: window.scrollY,
                    path: window.location.pathname,
                    time: Date.now()
                }));
            } catch (err) {}
            pageCache.clear();
            return;
        }
        if (form.target === '_blank' || form.hasAttribute('download')) return;

        const action = form.action || window.location.href;
        const url = new URL(action, window.location.href);
        if (url.origin !== window.location.origin) return;

        // Skip export forms
        if (url.pathname.includes('/export')) return;

        const formData = new FormData(form);
        const searchParams = new URLSearchParams();
        for (const [key, value] of formData.entries()) {
            if (value !== '') {
                searchParams.append(key, value);
            }
        }
        url.search = searchParams.toString();

        e.preventDefault();

        // Determine scrollMode for form submission
        const isFilterForm = Boolean(form.closest('.filters') || form.classList.contains('filters'));
        let scrollMode = isFilterForm ? 'preserve' : 'top';
        let scrollTargetSelector = null;
        const card = form.closest('.card');
        if (card) {
            const h = card.querySelector('h1, h2, h3');
            if (h) {
                scrollMode = 'table';
                scrollTargetSelector = { type: 'heading', text: h.textContent.trim() };
            }
        }

        loadPage(url.href, true, { scrollMode, scrollTargetSelector, savedScrollY: window.scrollY });
    });

    // Handle browser back and forward navigation
    window.addEventListener('popstate', () => {
        loadPage(window.location.href, false);
    });
});
