const stockDialog = document.getElementById("stock-dialog");
const stockForm = document.getElementById("stock-form");
const itemType = document.getElementById("item-type");
const stockItem = document.getElementById("stock-item");
const stockQuantity = document.getElementById("stock-quantity");
const stockMessage = document.getElementById("stock-message");
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

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
            header.querySelector("span").textContent = "⇅";
        });

        button.dataset.direction = ascending ? "asc" : "desc";
        button.querySelector("span").textContent = ascending ? "↑" : "↓";

        const rows = Array.from(tbody.children).filter(row =>
            button.dataset.table !== "current-stock-body" ||
            row.classList.contains("stock-row")
        );

        function getValue(row) {
            const text = row.cells[column].textContent.trim();

            if (type === "number") {
                return Number(text.replace(/[^0-9.-]/g, "")) || 0;
            }

            if (type === "date") {
                const date = Date.parse(text);
                return Number.isNaN(date) ? null : date;
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
}

function filterBatchHistory() {
    const search = document.getElementById("batch-search").value.trim().toLowerCase();
    const type = document.getElementById("batch-type-filter").value;
    const expiration = document.getElementById("batch-expiration-filter").value;

    document.querySelectorAll("#batch-history-body tr").forEach(row => {
        const itemType = row.cells[0].textContent.trim();
        const itemName = row.cells[1].textContent.toLowerCase();
        const expirationStatus = row.querySelector(".expiration-status");

        const matchesSearch = itemName.includes(search);
        const matchesType = !type || itemType === type;
        const matchesExpiration = !expiration ||
            expirationStatus?.classList.contains(expiration);

        row.hidden = !(matchesSearch && matchesType && matchesExpiration);
    });
    const hasResults = Array.from(
        document.querySelectorAll("#batch-history-body tr")
    ).some(row => !row.hidden);

    document.getElementById("batch-no-results").hidden = hasResults;
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
    filterBatchHistory();
});