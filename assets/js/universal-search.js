/**
 * IPManager Pro - Universal Search System
 */

const searchModal = document.getElementById('search-modal');
const searchInput = document.getElementById('search-input');
const searchResults = document.getElementById('search-results');
let debounceTimer;
let selectedIndex = -1;
let currentResults = [];

// Hotkeys
document.addEventListener('keydown', (e) => {
    // Cmd+K or Ctrl+K
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        openSearch();
    }
    
    // ESC
    if (e.key === 'Escape' && searchModal && searchModal.style.display !== 'none') {
        closeSearch();
    }

    // Navigation
    if (searchModal && searchModal.style.display !== 'none') {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            navigateResults(1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            navigateResults(-1);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (selectedIndex >= 0 && currentResults[selectedIndex]) {
                selectResult(currentResults[selectedIndex]);
            }
        }
    }
});

function openSearch() {
    searchModal.style.display = 'flex';
    searchInput.focus();
    searchInput.value = '';
    renderInitial();
    if (window.lucide) lucide.createIcons();
}

function closeSearch() {
    searchModal.style.display = 'none';
    searchInput.blur();
}

function renderInitial() {
    searchResults.innerHTML = '<div class="search-empty">Type at least 2 characters to search...</div>';
    selectedIndex = -1;
}

searchInput.addEventListener('input', (e) => {
    clearTimeout(debounceTimer);
    const q = e.target.value.trim();
    
    if (q.length < 2) {
        renderInitial();
        return;
    }

    debounceTimer = setTimeout(() => {
        performSearch(q);
    }, 300);
});

async function performSearch(q) {
    searchResults.innerHTML = '<div class="search-empty"><i data-lucide="loader" class="spin"></i> Searching...</div>';
    if (window.lucide) lucide.createIcons();

    try {
        const response = await fetch(`api/universal-search.php?q=${encodeURIComponent(q)}`);
        const data = await response.json();
        currentResults = data;
        renderResults(data);
    } catch (err) {
        searchResults.innerHTML = '<div class="search-empty text-danger">Error fetching results.</div>';
    }
}

function renderResults(data) {
    if (data.length === 0) {
        searchResults.innerHTML = '<div class="search-empty">No results found matching your query.</div>';
        return;
    }

    let html = '';
    data.forEach((item, index) => {
        const icon = getIcon(item.type);
        html += `
            <div class="search-item" data-index="${index}" onclick="selectResult(currentResults[${index}])">
                <i data-lucide="${icon}"></i>
                <div class="search-item-info">
                    <span class="search-item-title">${item.title || item.subnet + '/' + item.mask}</span>
                    <span class="search-item-subtitle">${item.subtitle || item.description || ''}</span>
                </div>
                <span class="search-item-type">${item.type}</span>
            </div>
        `;
    });
    searchResults.innerHTML = html;
    selectedIndex = 0;
    updateSelection();
    if (window.lucide) lucide.createIcons();
}

function getIcon(type) {
    switch(type) {
        case 'asset': return 'server';
        case 'subnet': return 'layers';
        case 'switch': return 'vibrate';
        default: return 'hash';
    }
}

function navigateResults(dir) {
    const items = searchResults.querySelectorAll('.search-item');
    if (items.length === 0) return;

    selectedIndex += dir;
    if (selectedIndex < 0) selectedIndex = items.length - 1;
    if (selectedIndex >= items.length) selectedIndex = 0;

    updateSelection();
}

function updateSelection() {
    const items = searchResults.querySelectorAll('.search-item');
    items.forEach((item, idx) => {
        if (idx === selectedIndex) {
            item.classList.add('selected');
            item.scrollIntoView({ block: 'nearest' });
        } else {
            item.classList.remove('selected');
        }
    });
}

function selectResult(item) {
    let url = '';
    switch(item.type) {
        case 'asset': url = 'server-assets'; break;
        case 'subnet': url = `subnet-details?id=${item.id}`; break;
        case 'switch': url = `switch-details?id=${item.id}`; break;
    }
    
    if (url) {
        closeSearch();
        window.location.href = url;
    }
}

// Close on outside click
searchModal.addEventListener('click', (e) => {
    if (e.target === searchModal) closeSearch();
});
