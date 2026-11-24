const stockDialog = document.getElementById("stock-dialog");
const stockForm = document.getElementById("stock-form");
const itemType = document.getElementById("item-type");
const stockItem = document.getElementById("stock-item");
const stockQuantity = document.getElementById("stock-quantity");
const stockMessage = document.getElementById("stock-message");
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const paginationState = {};

const allOptions = Array.from(stockItem.options).map(option => ({
    id: option.value,
    name: option.textContent,
    type: option.dataset.type
}));

function updateItems() {
    stockItem.replaceChildren();

    allOptions
        .filter(option => option.type === itemType.value)
        .forEach(option => {
            const element = document.createElement("option");
            element.value = option.id;
            element.textContent = option.name;
            stockItem.appendChild(element);
        });

    stockQuantity.step = itemType.value === "Product" ? "1" : "0.001";
    stockQuantity.min = itemType.value === "Product" ? "1" : "0.001";
}

itemType.addEventListener("change", updateItems);

document.getElementById("stock-in-btn")?.addEventListener("click", () => {
    stockForm.reset();
    stockMessage.textContent = "";
    updateItems();
    document.getElementById("received-date").value =
        new Date().toLocaleDateString("en-CA");
    stockDialog.showModal();
});

document.getElementById("cancel-stock-btn").addEventListener("click", () => {
    stockDialog.close();
});

stockForm.addEventListener("submit", async event => {
    event.preventDefault();

    const saveButton = document.getElementById("save-stock-btn");
    saveButton.disabled = true;
    stockMessage.textContent = "";

    try {
        const response = await fetch("controllers/stock_in.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                csrf_token: csrfToken,
                item_type: itemType.value,
                item_id: Number(stockItem.value),
                quantity: Number(stockQuantity.value),
                cost_per_unit: Number(document.getElementById("stock-cost").value),
                received_date: document.getElementById("received-date").value,
                expiration_date: document.getElementById("expiration-date").value,
                supplier: document.getElementById("stock-supplier").value,
                notes: document.getElementById("stock-notes").value
            })
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.message || "Unable to add stock.");
        }

        window.location.reload();
    } catch (error) {
        stockMessage.textContent = error.message;
    } finally {
        saveButton.disabled = false;
    }
});

updateItems();

function inventoryCellValue(cell) {
    return (cell.dataset.number ?? cell.dataset.value ?? cell.textContent).trim();
}

document.querySelectorAll(".sort-header").forEach(button => {
    button.addEventListener("click", () => {
        const tbody = document.getElementById(button.dataset.table);
        const column = Number(button.dataset.column);
        const type = button.dataset.type;
        const ascending = button.dataset.direction !== "asc";

        document.querySelectorAll(
            `.sort-header[data-table="${button.dataset.table}"]`
        ).forEach(header => {
            header.dataset.direction = "";
            header.closest("th").removeAttribute("aria-sort");
            header.querySelector("span").textContent = "⇅";
        });

        button.dataset.direction = ascending ? "asc" : "desc";
        button.closest("th").setAttribute("aria-sort", ascending ? "ascending" : "descending");
        button.querySelector("span").textContent = ascending ? "↑" : "↓";

        const rows = Array.from(tbody.children).filter(row =>
            button.dataset.table !== "current-stock-body" ||
            row.classList.contains("stock-row")
        );

        function getValue(row) {
            const text = inventoryCellValue(row.cells[column]);

            if (type === "number") {
                const value = text.replace(/[₱,]/g, "");
                const number = Number(value);
                return value !== "" && Number.isFinite(number) ? number : null;
            }

            if (type === "date") {
                if (!/^\d{4}-\d{2}-\d{2}$/.test(text)) return null;
                const date = new Date(text);
                return Number.isNaN(date.getTime()) || date.toISOString().slice(0, 10) !== text
                    ? null
                    : date.getTime();
            }

            return text.toLowerCase();
        }

        rows.sort((a, b) => {
            const valueA = getValue(a);
            const valueB = getValue(b);

            if (valueA === null || valueB === null) {
                if (valueA === null && valueB === null) return 0;
                return valueA === null ? 1 : -1;
            }

            let result;

            if (type === "text") {
                result = valueA.localeCompare(valueB);
            } else {
                result = valueA - valueB;
            }

            return ascending ? result : -result;
        });

        if (button.dataset.table === "current-stock-body") {
            const detailsMap = new Map();

            tbody.querySelectorAll(".stock-details").forEach(row => {
                detailsMap.set(row.dataset.parent, row);
            });

            const sortedRows = [];

            rows.forEach(row => {
                sortedRows.push(row);

                const details = detailsMap.get(row.dataset.key);

                if (details) {
                    sortedRows.push(details);
                }
            });

            tbody.replaceChildren(...sortedRows);
        } else {
            tbody.replaceChildren(...rows);
        }

        paginationState[button.dataset.table] = { page: 1 };
        paginateInventory(button.dataset.table);
    });
});



document.querySelectorAll(".stock-row").forEach(row => {
    row.addEventListener("click", () => {
        const details = document.querySelector(
            `.stock-details[data-parent="${row.dataset.key}"]`
        );

        if (row.hidden || !details) return;

        details.hidden = !details.hidden;
        row.classList.toggle("expanded", !details.hidden);
    });
});
function filterCurrentStock() {
    const search = document.getElementById("stock-search").value.trim().toLowerCase();
    const type = document.getElementById("stock-type-filter").value;
    const status = document.getElementById("stock-status-filter").value;

    document.querySelectorAll("#current-stock-body .stock-row").forEach(row => {
        const itemType = row.cells[0].textContent.trim();
        const itemName = row.cells[1].textContent.toLowerCase();
        const itemStatus = row.querySelector(".stock-status");

        const matchesSearch = itemName.includes(search);
        const matchesType = !type || itemType === type;
        const matchesStatus = !status || itemStatus?.classList.contains(status);

        const visible = matchesSearch && matchesType && matchesStatus;
        row.hidden = !visible;
        row.dataset.filterHidden = String(row.hidden);

        const details = document.querySelector(
            `.stock-details[data-parent="${row.dataset.key}"]`
        );

        if (details) {
            details.hidden = !visible || !row.classList.contains("expanded");
        }
    });
    const hasResults = Array.from(
        document.querySelectorAll("#current-stock-body .stock-row")
    ).some(row => !row.hidden);

    document.getElementById("stock-no-results").hidden = hasResults;
    paginationState["current-stock-body"] = { page: 1 };
    paginateInventory("current-stock-body");
}

function filterBatchHistory() {
    const search = document.getElementById("batch-search").value.trim().toLowerCase();
    const type = document.getElementById("batch-type-filter").value;
    const expiration = document.getElementById("batch-expiration-filter").value;
    const remainingOnly = document.getElementById("batch-remaining-only").checked;

    document.querySelectorAll("#batch-history-body tr").forEach(row => {
        const itemType = row.cells[0].textContent.trim();
        const itemName = row.cells[1].textContent.toLowerCase();
        const expirationStatus = row.querySelector(".expiration-status");

        const matchesSearch = itemName.includes(search);
        const matchesType = !type || itemType === type;
        const matchesExpiration = !expiration ||
            expirationStatus?.classList.contains(expiration);

        const matchesRemaining = !remainingOnly || row.dataset.hasStock === "true";
        row.hidden = !(matchesSearch && matchesType && matchesExpiration && matchesRemaining);
        row.dataset.filterHidden = String(row.hidden);
    });
    const hasResults = Array.from(
        document.querySelectorAll("#batch-history-body tr")
    ).some(row => !row.hidden);

    document.getElementById("batch-no-results").hidden = hasResults;
    paginationState["batch-history-body"] = { page: 1 };
    paginateInventory("batch-history-body");
}

["stock-search", "stock-type-filter", "stock-status-filter"].forEach(id => {
    const element = document.getElementById(id);
    element.addEventListener("input", filterCurrentStock);
    element.addEventListener("change", filterCurrentStock);
});

["batch-search", "batch-type-filter", "batch-expiration-filter"].forEach(id => {
    const element = document.getElementById(id);
    element.addEventListener("input", filterBatchHistory);
    element.addEventListener("change", filterBatchHistory);
});

document.getElementById("batch-remaining-only").addEventListener("change", filterBatchHistory);

document.getElementById("clear-stock-filters").addEventListener("click", () => {
    document.getElementById("stock-search").value = "";
    document.getElementById("stock-type-filter").value = "";
    document.getElementById("stock-status-filter").value = "";
    filterCurrentStock();
});

document.getElementById("clear-batch-filters").addEventListener("click", () => {
    document.getElementById("batch-search").value = "";
    document.getElementById("batch-type-filter").value = "";
    document.getElementById("batch-expiration-filter").value = "";
    document.getElementById("batch-remaining-only").checked = false;
    filterBatchHistory();
});

function filterStockMovements() {
    const search = document.getElementById("movement-search").value.trim().toLowerCase();
    const type = document.getElementById("movement-type-filter").value;
    const action = document.getElementById("movement-action-filter").value;
    const dateFrom = document.getElementById("movement-date-from").value;
    const dateTo = document.getElementById("movement-date-to").value;

    let visibleCount = 0;

    document.querySelectorAll("#movement-history-body tr").forEach(row => {
        const itemType = row.cells[0].textContent.trim();
        const itemName = row.cells[1].textContent.trim().toLowerCase();
        const movementType = row.cells[2].textContent.trim();
        const movementDate = row.cells[6].dataset.date || "";

        const matchesSearch = itemName.includes(search);
        const matchesType = !type || itemType === type;
        const matchesAction = !action || movementType === action;
        const matchesFrom = !dateFrom || (movementDate && movementDate >= dateFrom);
        const matchesTo = !dateTo || (movementDate && movementDate <= dateTo);

        const visible = matchesSearch &&
            matchesType &&
            matchesAction &&
            matchesFrom &&
            matchesTo;

        row.hidden = !visible;
        row.dataset.filterHidden = String(row.hidden);

        if (visible) {
            visibleCount++;
        }
    });

    document.getElementById("movement-no-results").hidden = visibleCount > 0;
    paginationState["movement-history-body"] = { page: 1 };
    paginateInventory("movement-history-body");
}

[
    "movement-search",
    "movement-type-filter",
    "movement-action-filter",
    "movement-date-from",
    "movement-date-to"
].forEach(id => {
    const element = document.getElementById(id);

    element.addEventListener("input", filterStockMovements);
    element.addEventListener("change", filterStockMovements);
});

document.getElementById("clear-movement-filters").addEventListener("click", () => {
    document.getElementById("movement-search").value = "";
    document.getElementById("movement-type-filter").value = "";
    document.getElementById("movement-action-filter").value = "";
    document.getElementById("movement-date-from").value = "";
    document.getElementById("movement-date-to").value = "";

    filterStockMovements();
});

filterStockMovements();

const adjustDialog = document.getElementById("adjust-stock-dialog");
const adjustForm = document.getElementById("adjust-stock-form");

if (adjustDialog && adjustForm) {
    const adjustType = document.getElementById("adjust-item-type");
    const adjustItem = document.getElementById("adjust-item");
    const adjustQuantity = document.getElementById("adjust-quantity");
    const adjustMessage = document.getElementById("adjust-message");
    const adjustSave = document.getElementById("save-adjust-btn");
    const adjustBatch = document.getElementById("adjust-batch");
    const batchContainer = document.getElementById("adjust-batch-container");
    const adjustAction = document.getElementById("adjust-action");
    const adjustMovement = document.getElementById("adjust-movement");
    const batchOptions = Array.from(adjustBatch.options).slice(1);

    function updateAdjustBatches() {
        const isRemove = adjustAction.value === "Remove";

        batchContainer.hidden = !isRemove;
        document.getElementById("adjust-movement-container").hidden = !isRemove;
        adjustBatch.required = isRemove;
        adjustQuantity.removeAttribute("max");

        const selectedBatch = adjustBatch.value;
        adjustBatch.replaceChildren(new Option("Select a batch", ""));

        if (!isRemove) return;

        batchOptions.forEach(option => {
            if (
                option.dataset.type === adjustType.value &&
                option.dataset.item === adjustItem.value &&
                (adjustMovement.value !== "Expired" || option.dataset.expirationStatus === "expired")
            ) {
                adjustBatch.appendChild(option.cloneNode(true));
            }
        });

        if (Array.from(adjustBatch.options).some(option => option.value === selectedBatch)) {
            adjustBatch.value = selectedBatch;
        }
        adjustBatch.dispatchEvent(new Event("change"));
    }

    adjustMovement.addEventListener("change", updateAdjustBatches);
    adjustItem.addEventListener("change", updateAdjustBatches);
    adjustAction.addEventListener("change", updateAdjustBatches);

    adjustBatch.addEventListener("change", () => {
        const option = adjustBatch.selectedOptions[0];

        if (option?.dataset.quantity) {
            adjustQuantity.max = option.dataset.quantity;
        } else {
            adjustQuantity.removeAttribute("max");
        }
    });

    const adjustOptions = Array.from(adjustItem.options).map(option => ({
        id: option.value,
        name: option.textContent.trim(),
        type: option.dataset.type
    }));

    function updateAdjustItems() {
        adjustItem.replaceChildren();

        adjustOptions
            .filter(option => option.type === adjustType.value)
            .forEach(option => {
                const element = document.createElement("option");
                element.value = option.id;
                element.textContent = option.name;
                adjustItem.appendChild(element);
            });

        const isProduct = adjustType.value === "Product";

        adjustQuantity.min = isProduct ? "1" : "0.001";
        adjustQuantity.step = isProduct ? "1" : "0.001";
    }

    adjustType.addEventListener("change", () => {
        updateAdjustItems();
        updateAdjustBatches();
    });

    document.getElementById("adjust-stock-btn").addEventListener("click", () => {
        adjustForm.reset();
        adjustMessage.textContent = "";
        updateAdjustItems();
        updateAdjustBatches();
        adjustDialog.showModal();
    });

    document.getElementById("cancel-adjust-btn").addEventListener("click", () => {
        adjustDialog.close();
    });

    adjustForm.addEventListener("submit", async event => {
        event.preventDefault();

        adjustSave.disabled = true;
        adjustMessage.textContent = "";

        try {
            const response = await fetch("controllers/stock_adjustment.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    csrf_token: csrfToken,
                    item_type: adjustType.value,
                    item_id: Number(adjustItem.value),
                    action: document.getElementById("adjust-action").value,
                    batch_id: adjustAction.value === "Remove"
                        ? Number(adjustBatch.value)
                        : null,
                    quantity: Number(adjustQuantity.value),
                    reason: document.getElementById("adjust-reason").value.trim(),
                    movement_type: adjustAction.value === "Remove"
                        ? adjustMovement.value
                        : "Adjustment"
                })
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || "Unable to adjust stock.");
            }

            window.location.reload();
        } catch (error) {
            adjustMessage.textContent = error.message;
        } finally {
            adjustSave.disabled = false;
        }
    });

    updateAdjustItems();
}

function quoteInventoryCsv(value, isNumber = false) {
    const safeNumber = isNumber && /^-?\d+(?:\.\d+)?$/.test(value);
    const startsWithFormula = /^[\s\u0000-\u001f\u200b-\u200f\u202a-\u202e\u2060]*[=+\-@＝＋－＠]/u.test(value);
    const text = !safeNumber && startsWithFormula ? "'" + value : value;
    return '"' + text.replace(/"/g, '""') + '"';
}

document.querySelectorAll(".export-csv-btn").forEach(button => {
    button.addEventListener("click", () => {
        const tableId = button.dataset.table;
        const tbody = document.getElementById(tableId);
        const table = tbody.closest("table");

        const headers = Array.from(table.querySelectorAll("thead > tr:first-child > th"))
            .map(th => quoteInventoryCsv(th.textContent.replace(/[⇅↑↓]/g, "").trim()));

        const rows = Array.from(tbody.querySelectorAll(":scope > tr"))
            .filter(row =>
                row.dataset.filterHidden !== "true" &&
                !row.classList.contains("stock-details")
            )
            .map(row => Array.from(row.cells, cell =>
                quoteInventoryCsv(inventoryCellValue(cell), cell.dataset.number !== undefined)
            ).join(","));

        const csvRows = [headers.join(","), ...rows];

        const blob = new Blob(["\uFEFF" + csvRows.join("\r\n")], {
            type: "text/csv;charset=utf-8;"
        });

        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");

        link.href = url;
        link.download = button.dataset.filename;
        link.click();

        URL.revokeObjectURL(url);
    });
});



function paginateInventory(tableId) {
    const tbody = document.getElementById(tableId);
    const container = document.querySelector(
        `.inventory-pagination[data-table="${tableId}"]`
    );

    if (!tbody || !container) return;

    const rows = Array.from(tbody.children).filter(
        row => !row.classList.contains("stock-details")
    );

    if (tableId === "current-stock-body") {
        tbody.querySelectorAll(".stock-details").forEach(row => {
            row.hidden = true;
        });

        tbody.querySelectorAll(".stock-row").forEach(row => {
            row.classList.remove("expanded");
        });
    }

    const pageSize = 10;

    if (!paginationState[tableId]) {
        paginationState[tableId] = { page: 1 };
    }

    const matchingRows = rows.filter(row => row.dataset.filterHidden !== "true");
    const totalPages = Math.max(1, Math.ceil(matchingRows.length / pageSize));

    paginationState[tableId].page = Math.min(
        paginationState[tableId].page,
        totalPages
    );

    const currentPage = paginationState[tableId].page;

    rows.forEach(row => {
        row.hidden = true;
    });

    matchingRows.forEach((row, index) => {
        row.hidden = index < (currentPage - 1) * pageSize ||
                     index >= currentPage * pageSize;
    });

    container.replaceChildren();

    const previous = document.createElement("button");
    previous.textContent = "Previous";
    previous.disabled = currentPage === 1;
    previous.onclick = () => {
        paginationState[tableId].page--;
        paginateInventory(tableId);
    };
    container.appendChild(previous);

    const label = document.createElement("span");
    label.textContent = `Page ${currentPage} of ${totalPages} · ${matchingRows.length} ${matchingRows.length === 1 ? "result" : "results"}`;
    container.appendChild(label);

    const next = document.createElement("button");
    next.textContent = "Next";
    next.disabled = currentPage === totalPages;
    next.onclick = () => {
        paginationState[tableId].page++;
        paginateInventory(tableId);
    };
    container.appendChild(next);
}

filterCurrentStock();
filterBatchHistory();

document.querySelectorAll(".inventory-tab").forEach(tab => {
    tab.addEventListener("click", () => {
        const target = tab.dataset.target;

        document.querySelectorAll(".inventory-tab").forEach(button => {
            button.classList.toggle("active", button === tab);
        });

        document.querySelectorAll(".inventory-section").forEach(section => {
            section.hidden = section.id !== target;
        });

        const tableMap = {
            "current-stock-section": "current-stock-body",
            "batch-history-section": "batch-history-body",
            "stock-movement-section": "movement-history-body",
            "archived-ingredients-section": "archived-ingredients-body",
            "reconciliation-section": "reconciliation-body",
            "reconciliation-history-section": "reconciliation-history-body"
        };

        paginateInventory(tableMap[target]);
    });
});

document.querySelectorAll(".summary-card[data-filter]").forEach(card => {
    function applySummaryFilter() {
        const filter = card.dataset.filter;
        const isBatch = ["expiring-soon", "expired"].includes(filter);

        if (isBatch) {
            document.querySelector(
                '[data-target="batch-history-section"]'
            ).click();

            document.getElementById("batch-search").value = "";
            document.getElementById("batch-type-filter").value = "";
            document.getElementById("batch-expiration-filter").value = filter;
            document.getElementById("batch-remaining-only").checked = true;

            filterBatchHistory();
        } else {
            document.querySelector(
                '[data-target="current-stock-section"]'
            ).click();

            document.getElementById("stock-search").value = "";
            document.getElementById("stock-type-filter").value = "";
            document.getElementById("stock-status-filter").value =
                filter === "all" ? "" : filter;

            filterCurrentStock();
        }

        document.querySelectorAll(".summary-card").forEach(item => {
            item.classList.toggle("selected", item === card);
        });
    }

    card.addEventListener("click", applySummaryFilter);

    card.addEventListener("keydown", event => {
        if (event.key === "Enter" || event.key === " ") {
            event.preventDefault();
            applySummaryFilter();
        }
    });
});

const requestedInventoryFilter = new URLSearchParams(window.location.search).get("filter");
Array.from(document.querySelectorAll(".summary-card[data-filter]"))
    .find(card => card.dataset.filter === requestedInventoryFilter)?.click();

const ingredientDialog = document.getElementById("ingredient-dialog");
const ingredientForm = document.getElementById("ingredient-form");

if (ingredientDialog && ingredientForm) {
    document.getElementById("add-ingredient-btn").addEventListener("click", () => {
        ingredientForm.reset();
        document.getElementById("ingredient-unit").disabled = false;
        document.getElementById("ingredient-id").value = "";
        document.getElementById("ingredient-dialog-title").textContent = "Add Ingredient";
        document.getElementById("ingredient-message").textContent = "";
        ingredientDialog.showModal();
    });

    document.getElementById("cancel-ingredient-btn").addEventListener("click", () => {
        ingredientDialog.close();
    });

    ingredientForm.addEventListener("submit", async event => {
        event.preventDefault();

        const message = document.getElementById("ingredient-message");
        const saveButton = document.getElementById("save-ingredient-btn");

        saveButton.disabled = true;
        message.textContent = "";

        try {
            const response = await fetch("controllers/save_ingredient.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    csrf_token: csrfToken,
                    id: Number(document.getElementById("ingredient-id").value || 0),
                    name: document.getElementById("ingredient-name").value.trim(),
                    unit: document.getElementById("ingredient-unit").value,
                    threshold: Number(document.getElementById("ingredient-threshold").value),
                    cost: Number(document.getElementById("ingredient-cost").value)
                })
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || "Unable to save ingredient.");
            }

            window.location.reload();

        } catch (error) {
            message.textContent = error.message;
        } finally {
            saveButton.disabled = false;
        }
    });
}

document.querySelectorAll(".edit-ingredient-btn").forEach(button => {
    button.addEventListener("click", event => {
        event.stopPropagation();

        ingredientForm.reset();

        document.getElementById("ingredient-dialog-title").textContent = "Edit Ingredient";
        document.getElementById("ingredient-id").value = button.dataset.id;
        document.getElementById("ingredient-name").value = button.dataset.name;

        const unit = document.getElementById("ingredient-unit");

        if (!Array.from(unit.options).some(option => option.value === button.dataset.unit)) {
            unit.add(new Option(button.dataset.unit, button.dataset.unit));
        }

        unit.value = button.dataset.unit;
        unit.disabled = true;

        document.getElementById("ingredient-threshold").value = button.dataset.threshold;
        document.getElementById("ingredient-cost").value = button.dataset.cost;
        document.getElementById("ingredient-message").textContent = "";

        ingredientDialog.showModal();
    });
});

document.querySelectorAll(".delete-ingredient-btn").forEach(button => {
    button.addEventListener("click", async event => {
        event.stopPropagation();

        const confirmed = confirm(
            `Delete ${button.dataset.name}? This cannot be undone.`
        );

        if (!confirmed) return;

        button.disabled = true;

        try {
            const response = await fetch("controllers/delete_ingredient.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    csrf_token: csrfToken,
                    id: Number(button.dataset.id)
                })
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || "Unable to delete ingredient.");
            }

            window.location.reload();

        } catch (error) {
            alert(error.message);
        } finally {
            button.disabled = false;
        }
    });
});

document.querySelectorAll(".archive-ingredient-btn").forEach(button => {
    button.addEventListener("click", async event => {
        event.stopPropagation();

        if (!confirm(`Archive ${button.dataset.name}?`)) return;

        button.disabled = true;

        try {
            const response = await fetch("controllers/archive_ingredient.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    csrf_token: csrfToken,
                    id: Number(button.dataset.id)
                })
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || "Unable to archive ingredient.");
            }

            window.location.reload();

        } catch (error) {
            alert(error.message);
        } finally {
            button.disabled = false;
        }
    });
});

document.querySelectorAll(".restore-ingredient-btn").forEach(button => {
    button.addEventListener("click", async () => {
        if (!confirm(`Restore ${button.dataset.name}?`)) return;

        button.disabled = true;

        try {
            const response = await fetch("controllers/restore_ingredient.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    csrf_token: csrfToken,
                    id: Number(button.dataset.id)
                })
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || "Unable to restore ingredient.");
            }

            window.location.reload();

        } catch (error) {
            alert(error.message);
        } finally {
            button.disabled = false;
        }
    });
});

paginateInventory("archived-ingredients-body");
paginateInventory("reconciliation-body");

function filterReconciliation() {
    const search = document.getElementById("reconciliation-search").value.toLowerCase().trim();
    const type = document.getElementById("reconciliation-type-filter").value;
    const status = document.getElementById("reconciliation-status-filter").value;

    const rows = document.querySelectorAll("#reconciliation-body tr");
    let matches = 0;

    rows.forEach(row => {
        const itemType = row.cells[0].textContent.trim();
        const itemName = row.cells[1].textContent.toLowerCase();
        const itemStatus = row.cells[5].textContent.trim();

        const visible =
            itemName.includes(search) &&
            (!type || itemType === type) &&
            (!status || itemStatus === status);

        row.dataset.filterHidden = String(!visible);

        if (visible) matches++;
    });

    document.getElementById("reconciliation-no-results").hidden = matches !== 0;

    paginationState["reconciliation-body"] = { page: 1 };
    paginateInventory("reconciliation-body");
}

if (document.getElementById("reconciliation-search")) {
    document.getElementById("reconciliation-search")
        .addEventListener("input", filterReconciliation);

    document.getElementById("reconciliation-type-filter")
        .addEventListener("change", filterReconciliation);

    document.getElementById("reconciliation-status-filter")
        .addEventListener("change", filterReconciliation);

    document.getElementById("clear-reconciliation-filters")
        .addEventListener("click", () => {
            document.getElementById("reconciliation-search").value = "";
            document.getElementById("reconciliation-type-filter").value = "";
            document.getElementById("reconciliation-status-filter").value = "";

            filterReconciliation();
        });
}

const reconcileDialog = document.getElementById("reconcile-dialog");
const reconcileForm = document.getElementById("reconcile-form");

if (reconcileDialog && reconcileForm) {
    document.querySelectorAll(".reconcile-btn").forEach(button => {
        button.addEventListener("click", () => {
            reconcileForm.reset();

            document.getElementById("reconcile-id").value = button.dataset.id;
            document.getElementById("reconcile-type").value = button.dataset.type;
            document.getElementById("reconcile-recorded").value = button.dataset.recorded;
            document.getElementById("reconcile-batch").value = button.dataset.batch;

            document.getElementById("reconcile-item-name").textContent =
                button.dataset.name;

            document.getElementById("reconcile-stock-info").textContent =
                `Recorded Stock: ${button.dataset.recorded} → Corrected Stock: ${button.dataset.batch}`;

            document.getElementById("reconcile-message").textContent = "";

            reconcileDialog.showModal();
        });
    });

    document.getElementById("cancel-reconcile-btn").addEventListener("click", () => {
        reconcileDialog.close();
    });

    const saveButton = document.getElementById("save-reconcile-btn");
    const cancelButton = document.getElementById("cancel-reconcile-btn");
    const message = document.getElementById("reconcile-message");

    reconcileDialog.addEventListener("cancel", event => {
        if (saveButton.disabled) {
            event.preventDefault();
        }
    });

    reconcileForm.addEventListener("submit", async event => {
        event.preventDefault();
        if (saveButton.disabled) {
            return;
        }

        saveButton.disabled = true;
        cancelButton.disabled = true;
        message.textContent = "Saving correction…";

        try {
            const response = await fetch("controllers/reconcile_stock.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    csrf_token: csrfToken,
                    item_id: Number(document.getElementById("reconcile-id").value),
                    item_type: document.getElementById("reconcile-type").value,
                    expected_recorded_stock: document.getElementById("reconcile-recorded").value,
                    expected_batch_stock: document.getElementById("reconcile-batch").value,
                    reason: document.getElementById("reconcile-reason").value,
                    confirmed: document.getElementById("reconcile-confirm").checked
                })
            });
            const result = await response.json();
            message.textContent = result.message;

            if (response.ok && result.success) {
                const filterIds = ["reconciliation-search", "reconciliation-type-filter", "reconciliation-status-filter"];
                const filters = filterIds.map(id => document.getElementById(id).value);
                history.replaceState({ ...history.state, reconciliationFilters: filters }, "");
                window.location.reload();
                return;
            }
        } catch {
            message.textContent = "Unable to verify the correction. Refresh to check the current stock.";
        }

        saveButton.disabled = false;
        cancelButton.disabled = false;
    });
}
if (history.state?.reconciliationFilters) {
    const { reconciliationFilters, ...state } = history.state;
    history.replaceState(state, "");
    ["reconciliation-search", "reconciliation-type-filter", "reconciliation-status-filter"]
        .forEach((id, index) => {
            document.getElementById(id).value = reconciliationFilters[index];
        });
    document.querySelector('.inventory-tab[data-target="reconciliation-section"]').click();
    filterReconciliation();
}

const correctionHistory = document.getElementById("reconciliation-history-body");

if (correctionHistory) {
    const searchInput = document.getElementById("correction-search");
    const typeFilter = document.getElementById("correction-type-filter");
    const dateFrom = document.getElementById("correction-date-from");
    const dateTo = document.getElementById("correction-date-to");
    const noResults = document.getElementById("correction-no-results");
    const filters = [searchInput, typeFilter, dateFrom, dateTo];

    function filterCorrectionHistory() {
        const search = searchInput.value.trim().toLowerCase();
        let matches = 0;

        for (const row of correctionHistory.rows) {
            const { type, date } = row.dataset;
            const matchesSearch = !search || Array.from(row.querySelectorAll(".correction-search-value"))
                .some(cell => cell.textContent.toLowerCase().includes(search));
            const visible = matchesSearch &&
                (!typeFilter.value || type === typeFilter.value) &&
                (!dateFrom.value || date >= dateFrom.value) &&
                (!dateTo.value || (date && date <= dateTo.value));

            row.dataset.filterHidden = String(!visible);
            matches += Number(Boolean(visible));
        }

        noResults.hidden = matches > 0;
        paginationState[correctionHistory.id] = { page: 1 };
        paginateInventory(correctionHistory.id);
    }

    searchInput.addEventListener("input", filterCorrectionHistory);
    [typeFilter, dateFrom, dateTo].forEach(input => {
        input.addEventListener("change", filterCorrectionHistory);
    });
    document.getElementById("clear-correction-filters").addEventListener("click", () => {
        filters.forEach(input => { input.value = ""; });
        filterCorrectionHistory();
    });
}
