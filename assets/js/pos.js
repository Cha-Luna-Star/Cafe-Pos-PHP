
let cart = [];
let checkoutBusy = false;
const money = value => "\u20b1" + Number(value).toFixed(2);

const menuButtons = Array.from(document.querySelectorAll("[data-menu-id]"));
const stockByItem = new Map(menuButtons.map(button => [Number(button.dataset.menuId), Number(button.dataset.stock)]));

function remainingStock(id) {
    const inCart = cart.reduce((total, item) => total + (item.id === id ? item.quantity : 0), 0);
    return Math.max(0, (stockByItem.get(id) ?? 0) - inCart);
}

const menuSearch = document.getElementById("pos-menu-search");
const clearMenuSearch = document.getElementById("clear-pos-menu-search");
const categoryButtons = Array.from(document.querySelectorAll("[data-menu-category]"));
const menuCards = Array.from(document.querySelectorAll(".menu-card"));
const menuEmpty = document.getElementById("pos-menu-empty");
const menuResultCount = document.getElementById("pos-menu-result-count");
let selectedMenuCategory = "all";

function normalizeMenuSearch(value) {
    return value.normalize("NFD").replace(/\p{M}/gu, "").toLowerCase();
}

function filterMenuCards() {
    const terms = normalizeMenuSearch(menuSearch.value).trim().split(/\s+/).filter(Boolean);
    let matches = 0;
    for (const card of menuCards) {
        const name = normalizeMenuSearch(card.dataset.menuName);
        const inCategory = selectedMenuCategory === "all" || card.dataset.category === selectedMenuCategory;
        card.hidden = !inCategory || !terms.every(term => name.includes(term));
        if (!card.hidden) matches++;
    }

    menuEmpty.hidden = matches !== 0;
    menuResultCount.textContent = matches + (matches === 1 ? " item" : " items");
    clearMenuSearch.disabled = menuSearch.value === "";
}

menuSearch.addEventListener("input", filterMenuCards);
clearMenuSearch.addEventListener("click", () => {
    menuSearch.value = "";
    filterMenuCards();
    menuSearch.focus();
});
for (const button of categoryButtons) {
    button.addEventListener("click", () => {
        selectedMenuCategory = button.dataset.menuCategory;
        for (const category of categoryButtons) {
            category.setAttribute("aria-pressed", String(category === button));
        }
        filterMenuCards();
    });
}
filterMenuCards();

function addToCart(item) {
    if (checkoutBusy || remainingStock(item.id) <= 0) return;
    const notice = document.getElementById("pos-menu-notice");
    notice.hidden = true;

    if (item.is_addon) {
        const hasMainItem = cart.some(entry => !entry.is_addon);

        if (!hasMainItem) {
            notice.textContent = "Add a regular menu item before selecting an add-on.";
            notice.hidden = false;
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
        if (existing.quantity >= 100) return;
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
    const item = cart[index];
    if (checkoutBusy || !item || (amount > 0 && (item.quantity >= 100 || remainingStock(item.id) <= 0))) return;

    item.quantity += amount;

    if (cart[index].quantity <= 0) {
        cart.splice(index, 1);
    }

    renderCart();
}

function renderCart() {
    const container = document.getElementById("cart-items");
    container.replaceChildren();

    let total = 0;

    document.getElementById("cart-empty").hidden = cart.length !== 0;

    cart.forEach((item, index) => {
        const subtotal = item.price * item.quantity;
        total += subtotal;

        const row = document.createElement("div");
        row.className = "cart-item";

        const heading = document.createElement("div");
        heading.className = "cart-item-heading";
        const details = document.createElement("strong");
        details.textContent = item.name;
        const remove = document.createElement("button");
        remove.type = "button";
        remove.className = "ui-button ui-button-sm";
        remove.dataset.tone = "danger";
        remove.textContent = "Remove";
        remove.setAttribute("aria-label", "Remove " + item.name + " from cart");
        remove.addEventListener("click", () => changeQuantity(index, -item.quantity));
        heading.append(details, remove);

        const optionsRow = document.createElement("div");
        optionsRow.className = "cart-options";

        if (item.sizes && item.sizes.length > 0) {
            const sizeSelect = document.createElement("select");
            sizeSelect.className = "ui-field";
            sizeSelect.setAttribute("aria-label", item.name + " size");

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

                if (duplicate && duplicate.quantity + item.quantity <= 100) {
                    duplicate.quantity += item.quantity;
                    cart.splice(cart.indexOf(item), 1);
                }

                renderCart();
            });

            optionsRow.appendChild(sizeSelect);
        }

        if (item.temperature !== null && item.temperature !== undefined) {
            const temperatureSelect = document.createElement("select");
            temperatureSelect.className = "ui-field";
            temperatureSelect.setAttribute("aria-label", item.name + " temperature");

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

                if (duplicate && duplicate.quantity + item.quantity <= 100) {
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
        minus.className = "ui-button ui-button-sm";
        minus.dataset.tone = "danger";
        minus.textContent = "-";
        minus.setAttribute("aria-label", "Remove one " + item.name);
        minus.onclick = () => changeQuantity(index, -1);

        const quantity = document.createElement("span");
        quantity.textContent = item.quantity;

        const plus = document.createElement("button");
        plus.className = "ui-button ui-button-sm";
        plus.dataset.tone = "positive";
        plus.textContent = "+";
        plus.setAttribute("aria-label", "Add one " + item.name);
        plus.disabled = item.quantity >= 100 || remainingStock(item.id) === 0;
        plus.onclick = () => changeQuantity(index, 1);

        controls.append(minus, quantity, plus);

        row.appendChild(heading);

        if (optionsRow.children.length > 0) {
            row.appendChild(optionsRow);
        }

        const bottom = document.createElement("div");
        bottom.className = "cart-item-bottom";
        bottom.append(controls, price);
        row.appendChild(bottom);
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

    const subtotal = Math.round(total * 100) / 100;
    const discountedTotal = Math.round(subtotal * (1 - discountRate) * 100) / 100;
    document.getElementById("cart-subtotal").textContent = money(subtotal);
    document.getElementById("cart-discount").textContent = money(subtotal - discountedTotal);
    document.getElementById("cart-tax").textContent = money(discountedTotal - discountedTotal / 1.12);

    currentTotal = discountedTotal;
    document.getElementById("cart-total").textContent = money(discountedTotal);
    const itemCount = cart.reduce((total, item) => total + item.quantity, 0);
    document.getElementById("cart-count").textContent = itemCount;
    clearCartButton.disabled = !cart.length || checkoutBusy;
    document.getElementById("cart-shortcut").textContent = "View cart (" + itemCount + ")";

    for (const button of menuButtons) {
        const id = Number(button.dataset.menuId);
        const inStock = stockByItem.get(id) > 0;
        button.disabled = remainingStock(id) === 0;
        button.textContent = !inStock ? "Out of stock"
            : button.disabled ? "All stock in cart" : "Add to Cart";
        const badge = button.closest(".menu-card").querySelector(".menu-card-status");
        badge.dataset.tone = inStock ? "positive" : "danger";
        badge.textContent = inStock ? "Available" : "Out of stock";
    }
    checkoutButton.disabled = cart.length === 0 || checkoutBusy;
    updateCashSummary();
}

const checkoutForm = document.getElementById("checkout-form");
const paymentMethod = document.getElementById("payment-method");
const cashFields = document.getElementById("cash-fields");
const referenceFields = document.getElementById("reference-fields");
const cashAmount = document.getElementById("amount-tendered");
const refNumber = document.getElementById("ref-number");
const cashSummary = document.getElementById("cash-summary");
const checkoutMessage = document.getElementById("checkout-message");
const checkoutButton = document.getElementById("checkout-btn");
const clearCartButton = document.getElementById("clear-cart");
const checkoutConfirm = document.getElementById("checkout-confirm");
const confirmButton = document.getElementById("checkout-confirm-button");
const menuSection = document.querySelector(".menu-section");
const cartItems = document.getElementById("cart-items");
let currentTotal = 0;

function updateCashSummary() {
    const cash = paymentMethod.value === "Cash";
    cashFields.hidden = !cash;
    referenceFields.hidden = cash;
    cashAmount.required = cash;
    refNumber.required = !cash;
    cashAmount.disabled = !cash || checkoutBusy;
    refNumber.disabled = cash || checkoutBusy;

    const received = cashAmount.valueAsNumber;
    const short = cash && cart.length > 0 && Number.isFinite(received) &&
        Math.round(received * 100) < Math.round(currentTotal * 100);
    cashAmount.setCustomValidity(short ? "Amount tendered is below the order total." : "");
    cashSummary.dataset.short = String(short);

    if (!cash || cart.length === 0 || !Number.isFinite(received)) {
        cashSummary.textContent = "";
        return;
    }

    const difference = Math.abs(Math.round(received * 100) - Math.round(currentTotal * 100)) / 100;
    cashSummary.textContent = short
        ? "Still due: ₱" + difference.toFixed(2)
        : "Change: ₱" + difference.toFixed(2);
}

function setCheckoutBusy(busy) {
    checkoutBusy = busy;
    menuSection.inert = busy;
    cartItems.inert = busy;
    checkoutButton.textContent = busy ? "Placing order..." : "Checkout";
    clearCartButton.disabled = busy || !cart.length;
    checkoutButton.disabled = busy || cart.length === 0;
    for (const field of checkoutForm.querySelectorAll("select")) {
        field.disabled = busy;
    }
    updateCashSummary();
}

paymentMethod.addEventListener("change", updateCashSummary);
cashAmount.addEventListener("input", updateCashSummary);
document.getElementById("discount-type").addEventListener("change", renderCart);
renderCart();

function showCheckoutError(message, field) {
    checkoutMessage.dataset.error = "true";
    checkoutMessage.textContent = message;
    if (field) {
        field.setAttribute("aria-invalid", "true");
        field.focus();
    }
}

function clearCheckoutError() {
    checkoutMessage.textContent = "";
    cashAmount.removeAttribute("aria-invalid");
    refNumber.removeAttribute("aria-invalid");
}

checkoutForm.addEventListener("input", clearCheckoutError);
clearCartButton.addEventListener("click", () => {
    if (checkoutBusy) return;
    cart = [];
    checkoutForm.reset();
    clearCheckoutError();
    renderCart();
    menuSearch.focus();
});

checkoutForm.addEventListener("submit", event => {
    event.preventDefault();
    if (checkoutBusy || !cart.length || checkoutConfirm.open) return;
    clearCheckoutError();
    const field = paymentMethod.value === "Cash" ? cashAmount : refNumber;
    if (field === refNumber && !refNumber.value.trim()) {
        showCheckoutError("Enter a payment reference number.", field);
        return;
    }
    if (!field.validity.valid) {
        showCheckoutError(field === cashAmount && !cashAmount.value
            ? "Enter the amount tendered." : field.validationMessage, field);
        return;
    }

    document.getElementById("review-order-type").textContent = document.getElementById("order-type").value;
    document.getElementById("review-payment").textContent = paymentMethod.value;
    document.getElementById("review-total").textContent = money(currentTotal);
    checkoutConfirm.showModal();
});

document.getElementById("checkout-back").addEventListener("click", () => checkoutConfirm.close());
confirmButton.addEventListener("click", async () => {
    if (!checkoutConfirm.open || checkoutBusy || !cart.length) return;
    checkoutConfirm.close();
    const submittedCart = cart;
    const paidCash = paymentMethod.value === "Cash";
    const data = {
        items: submittedCart.map(item => ({
            id: item.id,
            quantity: item.quantity,
            size: item.size ?? "Regular",
            temperature: item.temperature
        })),
        order_type: document.getElementById("order-type").value,
        discount_type: document.getElementById("discount-type").value,
        payment_method: paymentMethod.value,
        amount_tendered: paidCash ? cashAmount.valueAsNumber : 0,
        ref_number: paidCash ? "" : refNumber.value.trim()
    };

    setCheckoutBusy(true);
    checkoutMessage.dataset.error = "false";
    checkoutMessage.textContent = "Placing order…";

    let result;
    try {
        const response = await fetch("controllers/checkout.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-Token": document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(data)
        });

        result = await response.json();
        if (!response.ok || !result?.success) {
            checkoutMessage.dataset.error = "true";
            checkoutMessage.textContent = result?.error || "The order could not be placed.";
            return;
        }
    } catch {
        checkoutMessage.dataset.error = "true";
        checkoutMessage.textContent = "Could not confirm the order. Check Order History before trying again.";
        return;
    } finally {
        setCheckoutBusy(false);
    }

    for (const item of submittedCart) {
        stockByItem.set(item.id, Math.max(0, (stockByItem.get(item.id) ?? 0) - item.quantity));
    }
    cart = [];
    checkoutForm.reset();
    clearCheckoutError();
    renderCart();
    showReceipt(result, data, submittedCart);
});

function showReceipt(result, orderData, submittedCart) {
    const modal = document.getElementById("receipt-modal");

    const subtotal = submittedCart.reduce(
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

    submittedCart.forEach(item => {
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
