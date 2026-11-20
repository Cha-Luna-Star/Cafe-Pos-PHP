
let cart = [];

function filterCategory(categoryId) {
    document.querySelectorAll(".menu-card").forEach(card => {
        card.style.display =
            categoryId === "all" ||
            Number(card.dataset.category) === categoryId
                ? "block"
                : "none";
    });
}

function addToCart(item) {
    if (item.is_addon) {
        const hasMainItem = cart.some(entry => !entry.is_addon);

        if (!hasMainItem) {
            alert("Add a regular menu item before selecting an add-on.");
            return;
        }
    }

    let selectedSize = "Regular";

    let temperature = item.has_temp_option ? "Hot" : null;

    let adjustment = 0;

    if (item.has_sizes && item.sizes.length > 0) {
        const defaultSize =
            item.sizes.find(size => size.name === "Medium") ??
            item.sizes[0];

        selectedSize = defaultSize.name;
        adjustment = Number(defaultSize.price_adjustment);
    }

    const unitPrice = Math.max(
        0,
        Number(item.price) + adjustment
    );

    const existing = cart.find(
        entry =>
            entry.id === item.id &&
            entry.size === selectedSize &&
            entry.temperature === temperature
    );

    if (existing) {
        existing.quantity++;
    } else {
        cart.push({
            id: item.id,
            name: item.name,
            size: selectedSize,
            sizes: item.sizes,
            basePrice: Number(item.price),
            temperature: temperature,
            is_addon: item.is_addon,
            price: unitPrice,
            quantity: 1
        });
    }

    renderCart();
}

function changeQuantity(index, amount) {
    cart[index].quantity += amount;

    if (cart[index].quantity <= 0) {
        cart.splice(index, 1);
    }

    renderCart();
}

function renderCart() {
    const container = document.getElementById("cart-items");
    container.replaceChildren();

    let total = 0;

    if (cart.length === 0) {
        const empty = document.createElement("p");
        empty.textContent = "No items added.";
        container.appendChild(empty);
    }

    cart.forEach((item, index) => {
        const subtotal = item.price * item.quantity;
        total += subtotal;

        const row = document.createElement("div");
        row.className = "cart-item";

        const details = document.createElement("div");
        details.textContent = item.name;

        const optionsRow = document.createElement("div");
        optionsRow.className = "cart-options";
        optionsRow.style.display = "flex";
        optionsRow.style.gap = "10px";
        optionsRow.style.margin = "10px 0";

        if (item.sizes && item.sizes.length > 0) {
            const sizeSelect = document.createElement("select");
            sizeSelect.className = "size-select";
            sizeSelect.style.flex = "1";
            sizeSelect.style.width = "0";
            sizeSelect.style.minWidth = "0";

            item.sizes.forEach(size => {
                const option = document.createElement("option");
                option.value = size.name;
                option.textContent =
                    `${size.name} (${Number(size.price_adjustment) >= 0 ? "+" : ""}₱${Number(size.price_adjustment).toFixed(2)})`;
                option.selected = item.size === size.name;
                sizeSelect.appendChild(option);
            });

            sizeSelect.addEventListener("change", () => {
                const selected = item.sizes.find(
                    size => size.name === sizeSelect.value
                );

                if (!selected) return;

                item.size = selected.name;
                item.price = Math.max(
                    0,
                    item.basePrice + Number(selected.price_adjustment)
                );

                const duplicate = cart.find(entry =>
                    entry !== item &&
                    entry.id === item.id &&
                    entry.size === item.size &&
                    entry.temperature === item.temperature
                );

                if (duplicate) {
                    duplicate.quantity += item.quantity;
                    cart.splice(cart.indexOf(item), 1);
                }

                renderCart();
            });

            optionsRow.appendChild(sizeSelect);
        }

        if (item.temperature !== null && item.temperature !== undefined) {
            const temperatureSelect = document.createElement("select");
            temperatureSelect.className = "temperature-select";
            temperatureSelect.style.flex = "1";
            temperatureSelect.style.width = "0";
            temperatureSelect.style.minWidth = "0";

            ["Hot", "Iced"].forEach(temp => {
                const option = document.createElement("option");
                option.value = temp;
                option.textContent = temp;
                option.selected = item.temperature === temp;
                temperatureSelect.appendChild(option);
            });

            temperatureSelect.addEventListener("change", () => {
                item.temperature = temperatureSelect.value;

                const duplicate = cart.find(entry =>
                    entry !== item &&
                    entry.id === item.id &&
                    entry.size === item.size &&
                    entry.temperature === item.temperature
                );

                if (duplicate) {
                    duplicate.quantity += item.quantity;
                    cart.splice(cart.indexOf(item), 1);
                }

                renderCart();
            });

            optionsRow.appendChild(temperatureSelect);
        }

        const price = document.createElement("p");
        price.textContent = `₱${subtotal.toFixed(2)}`;

        const controls = document.createElement("div");
        controls.className = "quantity-controls";

        const minus = document.createElement("button");
        minus.textContent = "-";
        minus.onclick = () => changeQuantity(index, -1);

        const quantity = document.createElement("span");
        quantity.textContent = item.quantity;

        const plus = document.createElement("button");
        plus.textContent = "+";
        plus.onclick = () => changeQuantity(index, 1);

        controls.append(minus, quantity, plus);

        row.appendChild(details);

        if (optionsRow.children.length > 0) {
            row.appendChild(optionsRow);
        }

        row.append(price, controls);
        container.appendChild(row);
    });

    const discountRates = {
        None: 0,
        PWD: 0.20,
        Student: 0.10,
        "Senior Citizen": 0.20
    };

    const discountType = document.getElementById("discount-type").value;
    const discountRate = discountRates[discountType] ?? 0;

    const discountedTotal = Math.round(
        total * (1 - discountRate) * 100
    ) / 100;

    document.getElementById("cart-total").textContent =
        `₱${discountedTotal.toFixed(2)}`;

    document.getElementById("checkout-btn").disabled =
        cart.length === 0 || checkoutBusy;
}

const checkoutForm = document.getElementById("checkout-form");
const paymentMethod = document.getElementById("payment-method");
const cashFields = document.getElementById("cash-fields");
const referenceFields = document.getElementById("reference-fields");
const checkoutMessage = document.getElementById("checkout-message");
const checkoutButton = document.getElementById("checkout-btn");

let checkoutBusy = false;

paymentMethod.addEventListener("change", () => {
    const isCash = paymentMethod.value === "Cash";

    cashFields.hidden = !isCash;
    referenceFields.hidden = isCash;

    document.getElementById("amount-tendered").required = isCash;
    document.getElementById("ref-number").required = !isCash;
});

paymentMethod.dispatchEvent(new Event("change"));

checkoutForm.addEventListener("submit", async (event) => {
    event.preventDefault();

    if (checkoutBusy || cart.length === 0) {
        return;
    }

    checkoutBusy = true;
    checkoutButton.disabled = true;
    checkoutMessage.textContent = "Processing checkout...";

    const data = {
        items: cart.map(item => ({
            id: item.id,
            quantity: item.quantity,
            size: item.size ?? "Regular",
            temperature: item.temperature
        })),
        order_type: document.getElementById("order-type").value,
        discount_type: document.getElementById("discount-type").value,
        payment_method: paymentMethod.value,
        amount_tendered: document.getElementById("amount-tendered").value,
        ref_number: document.getElementById("ref-number").value
    };

    try {
        const response = await fetch("controllers/checkout.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-Token": document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.error || "Checkout failed.");
        }

        showReceipt(result, data);

        cart = [];
        checkoutForm.reset();
        paymentMethod.dispatchEvent(new Event("change"));
        checkoutMessage.textContent = "";
        renderCart();

    } catch (error) {
        checkoutMessage.textContent = error.message;
    } finally {
        checkoutBusy = false;
        renderCart();
    }
});

document.getElementById("discount-type").addEventListener(
    "change",
    renderCart
);


function showReceipt(result, orderData) {
    const modal = document.getElementById("receipt-modal");
    const money = value => `₱${Number(value).toFixed(2)}`;

    const subtotal = cart.reduce(
        (sum, item) => sum + item.price * item.quantity,
        0
    );

    const total = Number(result.total);
    const discount = subtotal - total;

    document.getElementById("receipt-order").textContent =
        result.order_id;

    document.getElementById("receipt-date").textContent =
        new Date().toLocaleString();

    document.getElementById("receipt-payment").textContent =
        orderData.payment_method;

    document.getElementById("receipt-subtotal").textContent =
        money(subtotal);

    document.getElementById("receipt-discount").textContent =
        money(discount);

    document.getElementById("receipt-tax").textContent =
        money(result.tax);

    document.getElementById("receipt-total").textContent =
        money(total);

    document.getElementById("receipt-paid").textContent =
        money(
            orderData.payment_method === "Cash"
                ? orderData.amount_tendered
                : total
        );

    document.getElementById("receipt-change").textContent =
        money(result.change);

    const itemsContainer = document.getElementById("receipt-items");
    itemsContainer.replaceChildren();

    cart.forEach(item => {
        const row = document.createElement("p");

        row.textContent =
            `${item.quantity}x ${item.name} (${item.size}${item.temperature ? ", " + item.temperature : ""}) - ${money(item.price * item.quantity)}`;

        itemsContainer.appendChild(row);
    });

    modal.showModal();
}

function closeReceipt() {
    document.getElementById("receipt-modal").close();
    document.getElementById("checkout-message").textContent = "";
}
