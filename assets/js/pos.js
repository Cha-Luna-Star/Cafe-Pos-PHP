
let cart = [];
let checkoutBusy = false;

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
        minus.textContent = "-";
        minus.setAttribute("aria-label", "Remove one " + item.name);
        minus.onclick = () => changeQuantity(index, -1);

        const quantity = document.createElement("span");
        quantity.textContent = item.quantity;

        const plus = document.createElement("button");
        plus.textContent = "+";
        plus.setAttribute("aria-label", "Add one " + item.name);
        plus.disabled = item.quantity >= 100 || remainingStock(item.id) === 0;
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

    currentTotal = discountedTotal;
    document.getElementById("cart-total").textContent = "₱" + discountedTotal.toFixed(2);
    const itemCount = cart.reduce((total, item) => total + item.quantity, 0);
    document.getElementById("cart-shortcut").textContent = "View cart (" + itemCount + ")";

    for (const button of menuButtons) {
        const id = Number(button.dataset.menuId);
        button.disabled = remainingStock(id) === 0;
        button.textContent = button.disabled
            ? (stockByItem.get(id) > 0 ? "All Stock in Cart" : "Out of Stock")
            : "Add to Cart";
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
    checkoutButton.textContent = busy ? "Placing order…" : "Place Order";
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

checkoutForm.addEventListener("submit", async event => {
    event.preventDefault();
    if (checkoutBusy || cart.length === 0) return;

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
    checkoutMessage.textContent = "";
    renderCart();
    showReceipt(result, data, submittedCart);
});

function showReceipt(result, orderData, submittedCart) {
    const modal = document.getElementById("receipt-modal");
    const money = value => `₱${Number(value).toFixed(2)}`;

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
