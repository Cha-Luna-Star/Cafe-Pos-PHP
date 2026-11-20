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