/**
 * Side navigation behaviour: show/hide on mobile, collapse on the desktop,
 * expand and collapse subgroups. The state lives on
 * <body data-nav> or the respective group button and is remembered in
 * localStorage.
 */

const KEY_COLLAPSED = 'nav-collapsed';
const KEY_GROUPS = 'nav-open-groups'; function readGroups() {
    try {
        return new Set(JSON.parse(localStorage.getItem(KEY_GROUPS) || '[]')); } catch {
        return new Set();
    }
}

function writeGroups(set) {
    try {
        localStorage.setItem(KEY_GROUPS, JSON.stringify([...set]));
    } catch {
        /* private mode — the state then only applies to this page */
    }
}

function setCollapsed(collapsed) {
    document.body.dataset.nav = collapsed ? 'collapsed' : 'expanded'; try {
        localStorage.setItem(KEY_COLLAPSED, collapsed ? '1' : '0'); } catch {
        /* see above */
    }
}

function toggleGroup(button, open) {
    const target = document.getElementById(button.getAttribute('aria-controls')); if (!target) return;

    button.setAttribute('aria-expanded', open ? 'true' : 'false'); target.toggleAttribute('data-open', open); const groups = readGroups();
    open ? groups.add(button.dataset.group) : groups.delete(button.dataset.group);
    writeGroups(groups);
}

document.addEventListener('DOMContentLoaded', () => { // Restore stored open groups; the group of the current
    // page is already rendered open on the server.
    const stored = readGroups();

    document.querySelectorAll('[data-nav-group]').forEach((button) => { if (stored.has(button.dataset.group) && button.getAttribute('aria-expanded') !== 'true') { toggleGroup(button, true);
        }
    });
});

document.addEventListener('click', (event) => { const groupButton = event.target.closest('[data-nav-group]'); if (groupButton) {
        event.preventDefault();
        toggleGroup(groupButton, groupButton.getAttribute('aria-expanded') !== 'true'); return;
    }

    if (event.target.closest('[data-nav-collapse]')) { setCollapsed(document.body.dataset.nav !== 'collapsed'); return;
    }

    if (event.target.closest('[data-nav-open]')) { document.body.dataset.navMobile = 'open'; return;
    }

    if (event.target.closest('[data-nav-close]')) { delete document.body.dataset.navMobile;
    }
});

document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && document.body.dataset.navMobile) { delete document.body.dataset.navMobile;
    }
});
