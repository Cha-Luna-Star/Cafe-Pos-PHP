const dialog = document.getElementById("item-dialog");
const form = document.getElementById("item-form");
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

const itemId = document.getElementById("item-id");
const itemName = document.getElementById("item-name");
const itemPrice = document.getElementById("item-price");
const itemThreshold = document.getElementById("item-low-stock-threshold");
const itemCategory = document.getElementById("item-category");
const itemAvailable = document.getElementById("item-available");
const itemTemperature = document.getElementById("item-temperature");
const tempOptionRow = document.getElementById("temp-option-row");
const itemMessage = document.getElementById("item-message");

function updateCategoryOptions() {
    const isDrink = ["1", "2"].includes(itemCategory.value);
    tempOptionRow.hidden = !isDrink;

    if (!isDrink) {
        itemTemperature.checked = false;
    }
}

itemCategory.addEventListener("change", updateCategoryOptions);

document.getElementById("add-item-btn").addEventListener("click", () => {
    form.reset();
    itemId.value = "";
    itemMessage.textContent = "";
    document.getElementById("dialog-title").textContent = "Add Item";
    updateCategoryOptions();
    dialog.showModal();
});

document.querySelectorAll(".edit-btn").forEach(button => {
    button.addEventListener("click", () => {
        const item = JSON.parse(button.dataset.item);

        itemId.value = item.id;
        itemName.value = item.name;
        itemPrice.value = item.price;
        itemThreshold.value = item.low_stock_threshold;
        itemCategory.value = item.category_id;
        itemAvailable.checked = item.available === 1;
        itemTemperature.checked = item.has_temp_option === 1;
        itemMessage.textContent = "";

        document.getElementById("dialog-title").textContent = "Edit Item";
        updateCategoryOptions();
        dialog.showModal();
    });
});

document.getElementById("cancel-item-btn").addEventListener("click", () => {
    dialog.close();
});

form.addEventListener("submit", async event => {
    event.preventDefault();

    const saveButton = document.getElementById("save-item-btn");
    saveButton.disabled = true;
    itemMessage.textContent = "";

    try {
        const response = await fetch("controllers/save_menu_item.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                csrf_token: csrfToken,
                id: Number(itemId.value),
                name: itemName.value.trim(),
                price: Number(itemPrice.value),
                low_stock_threshold: itemThreshold.disabled ? undefined : itemThreshold.valueAsNumber,
                category_id: Number(itemCategory.value),
                available: itemAvailable.checked,
                has_temp_option: itemTemperature.checked
            })
        });

        const responseText = await response.text();
        console.log("Server response:", responseText);

        let result;

        try {
            result = JSON.parse(responseText);
        } catch {
            throw new Error("PHP error. Check the browser console.");
        }

        if (!response.ok || !result.success) {
            throw new Error(result.message || "Unable to save item.");
        }

        window.location.reload();
    } catch (error) {
        itemMessage.textContent = error.message;
    } finally {
        saveButton.disabled = false;
    }
});

const confirmDialog = document.getElementById("confirm-dialog");
const confirmTitle = document.getElementById("confirm-title");
const confirmDescription = document.getElementById("confirm-description");
const confirmSubmit = document.getElementById("confirm-submit");
const confirmCancel = document.getElementById("confirm-cancel");

let pendingAction = null;
let pendingButton = null;

document.querySelectorAll(".archive-btn").forEach(button => {
    button.addEventListener("click", () => {
        pendingAction = button.dataset.action;
        pendingButton = button;

        const itemName = button.closest("tr").querySelector("td").textContent.trim();

        const isArchive = pendingAction === "archive";

        confirmTitle.textContent = isArchive
            ? "Archive Item"
            : "Restore Item";

        confirmDescription.textContent = isArchive
            ? `Are you sure you want to archive "${itemName}"? This item will no longer appear in the POS menu.`
            : `Are you sure you want to restore "${itemName}"?`;

        confirmSubmit.textContent = isArchive
            ? "Archive Item"
            : "Restore Item";

        confirmSubmit.dataset.tone = isArchive ? "warning" : "positive";

        confirmDialog.showModal();
    });
});

confirmCancel.addEventListener("click", () => {
    confirmDialog.close();
});

confirmSubmit.addEventListener("click", async () => {
    if (!pendingAction || !pendingButton) return;

    confirmSubmit.disabled = true;

    try {
        const response = await fetch("controllers/menu_item_status.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                csrf_token: csrfToken,
                id: Number(pendingButton.dataset.id),
                action: pendingAction
            })
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.message || "Unable to update item.");
        }

        window.location.reload();
    } catch (error) {
        alert(error.message);
    } finally {
        confirmSubmit.disabled = false;
    }
});

const menuTable = document.getElementById("menu-table");
const menuRows = Array.from(menuTable.querySelectorAll("tr[data-name]"));
const emptyMenu = document.getElementById("menu-empty");
const menuSearch = document.getElementById("menu-search");
const categoryFilter = document.getElementById("category-filter");
const statusFilter = document.getElementById("status-filter");

function filterMenu() {
    const keyword = menuSearch.value.toLowerCase().trim();
    const category = categoryFilter.value;
    const status = statusFilter.value;

    for (const row of menuRows) {
        row.hidden = !row.dataset.name.includes(keyword) ||
            (category !== "all" && row.dataset.category !== category) ||
            (status !== "all" && row.dataset.status !== status);
    }
    emptyMenu.hidden = menuRows.some(row => !row.hidden);
}

menuSearch.addEventListener("input", filterMenu);
categoryFilter.addEventListener("change", filterMenu);
statusFilter.addEventListener("change", filterMenu);

const menuHeadings = menuTable.closest("table").querySelectorAll(".sort-header");
menuHeadings.forEach((heading, index) => {
    updateSortIndicator(heading);
    heading.addEventListener("click", () => {
        const ascending = heading.dataset.direction !== "asc";
        for (const button of menuHeadings) {
            updateSortIndicator(button);
            button.closest("th").removeAttribute("aria-sort");
        }
        updateSortIndicator(heading, ascending ? "asc" : "desc");
        heading.closest("th").setAttribute("aria-sort", ascending ? "ascending" : "descending");

        menuRows.sort((a, b) => {
            const left = a.cells[index];
            const right = b.cells[index];
            const comparison = heading.dataset.type === "number"
                ? Number(left.dataset.number ?? left.textContent.trim()) - Number(right.dataset.number ?? right.textContent.trim())
                : left.textContent.trim().localeCompare(right.textContent.trim(), undefined, { sensitivity: "base" });
            return ascending ? comparison : -comparison;
        });
        for (const row of menuRows) {
            menuTable.insertBefore(row, emptyMenu);
        }
        menuTable.closest(".table-wrapper").scrollTo({ top: 0 });
    });
});
filterMenu();
