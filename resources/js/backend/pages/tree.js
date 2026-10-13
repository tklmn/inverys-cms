import { iconHtml } from '../ui/icons.js';


import Sortable from 'sortablejs';

// Tree management class
class PageTreeManager {
    constructor() {
        this.treeContainer = document.getElementById('page-tree-container');
        this.saveStatus = document.getElementById('save-status');
        this.csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        this.sortables = [];
        this.treeData = [];
        this.debounceTimer = null;
        this.debounceDelay = 800; // milliseconds before saving after changes

        // Initialize tree if container exists
        if (this.treeContainer) {
            this.initializeTree();
        }
    }

    // Initialize sortable functionality on all nested lists
    initializeTree() {
        // Apply to root list
        const rootList = this.treeContainer.querySelector('.page-tree-root');
        if (!rootList) return;

        // Initialize sortable on the root list
        this.initializeSortable(rootList);

        // Apply to all child lists
        const childLists = this.treeContainer.querySelectorAll('.page-children');
        childLists.forEach(list => {
            this.initializeSortable(list);
        });

        // Show autosave status indicator
        this.saveStatus.classList.remove('hidden');
    }

    // Initialize a single sortable list
    initializeSortable(list) {
        const self = this;
        const sortable = new Sortable(list, {
            group: { name: 'pages', pull: true, put: true },
            animation: 150,
            fallbackOnBody: true,
            swapThreshold: 0.65,
            ghostClass: 'page-item-ghost',
            chosenClass: 'page-item-chosen',
            dragClass: 'page-item-drag',

            // When an item is added to a new list (for nesting)
            onAdd: function(evt) {
                // If dropped on a .page-item that has no .page-children, create it
                const toList = evt.to;
                const parentItem = toList.closest('.page-item');
                if (parentItem && !toList.classList.contains('page-tree-root')) {
                    let childrenList = parentItem.querySelector(':scope > .page-children');
                    if (!childrenList) {
                        childrenList = document.createElement('ul');
                        childrenList.className = 'page-children';
                        parentItem.appendChild(childrenList);
                        // Move the dropped element into the new children list
                        childrenList.appendChild(evt.item);
                        // Re-initialize Sortable on the new list
                        self.initializeSortable(childrenList);
                    }
                }
            },

            // Called when an item is moved
            onEnd: function(evt) {
                self.handleItemMoved(evt);
            }
        });

        this.sortables.push(sortable);
        return sortable;
    }

    // Called when an item is moved
    handleItemMoved(evt) {
        // Clear previous timeout if it exists
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }

        // Debounce the save operation
        this.debounceTimer = setTimeout(() => {
            this.saveTreeStructure();
        }, this.debounceDelay);
    }

    // Build tree data from DOM structure (parent_id is determined by DOM nesting)
    buildTreeData() {
        this.treeData = [];
        const rootItems = this.treeContainer.querySelectorAll('.page-tree-root > .page-item');

        rootItems.forEach((item, index) => {
            this.processNode(item, null, index);
        });

        return this.treeData;
    }

    // Recursively process a node and its children, always using the DOM to determine parent_id
    processNode(item, parentId, order) {
        const itemData = this.getItemData(item, parentId, order);
        this.treeData.push(itemData);

        // Find direct children
        const childrenContainer = item.querySelector(':scope > .page-children');
        if (childrenContainer) {
            const children = childrenContainer.querySelectorAll(':scope > .page-item');
            children.forEach((child, idx) => {
                this.processNode(child, itemData.id, idx);
            });
        }
    }

    // Get data for a single item
    getItemData(item, parentId, order) {
        return {
            id: parseInt(item.dataset.id),
            parent_id: parentId,
            order: order
        };
    }

    // Save tree structure to server
    saveTreeStructure() {
        const treeData = this.buildTreeData();
        const saveStatus = this.saveStatus;

        // Add saving indicator
        saveStatus.innerHTML = iconHtml('loader-circle', 'text-accent animate-spin') + ' ' +
            (window.translations?.pages?.saving || 'Saving...');

        // Use the defined route or fallback to the hardcoded path
        const updateTreeUrl = window.routes?.pages?.updateTree || '/backend/pages/tree';

        fetch(updateTreeUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': this.csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ tree: treeData })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                saveStatus.innerHTML = iconHtml('circle-check', 'text-positive') + ' ' +
                    (window.translations?.pages?.saved || 'Saved');

                // Reset after 3 seconds
                setTimeout(() => {
                    saveStatus.innerHTML = iconHtml('circle-check', 'text-positive') + ' ' +
                        (window.translations?.pages?.autosave_enabled || 'Autosave enabled');
                }, 3000);
            } else {
                // Show error
                // Server text as a text node, not as HTML
                saveStatus.innerHTML = iconHtml('triangle-alert', 'text-critical') + ' ';
                saveStatus.append(data.message || window.translations?.pages?.save_error || 'Error saving');
            }
        })
        .catch(() => {
            saveStatus.innerHTML = iconHtml('triangle-alert', 'text-critical') + ' ' +
                (window.translations?.pages?.save_error || 'Error saving');
        });
    }
}

// Initialize when document is loaded
document.addEventListener('DOMContentLoaded', function() {
    const pageTree = new PageTreeManager();
});
