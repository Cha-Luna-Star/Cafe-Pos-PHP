
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
    let temperature = null;

    if (item.has_temp_option) {
        const choice = prompt(
            "Choose temperature:\n1. Hot\n2. Iced"
        );

        if (choice === null) return;

        if (choice === "1") {
            temperature = "Hot";
        } else if (choice === "2") {
            temperature = "Iced";
        } else {
            alert("Invalid temperature.");
            return;
        }
    }
    let adjustment = 0;

    if (item.has_sizes && item.sizes.length > 0) {
        const options = item.sizes.map(
            (size, index) =>
                `${index + 1}. ${size.name} (+₱${Number(size.price_adjustment).toFixed(2)})`
        ).join("\n");

        const choice = prompt(
            `Choose a size:\n${options}`
        );

        if (choice === null) return;

        const index = Number(choice) - 1;

        if (!Number.isInteger(index) ||
            index < 0 ||
            index >= item.sizes.length) {
            alert("Invalid size.");
            return;
        }

        selectedSize = item.sizes[index].name;
        adjustment = Number(item.sizes[index].price_adjustment);
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
        details.textContent =
            `${item.name}${item.size ? " (" + item.size + ")" : ""}`;

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
        row.append(details, price, controls);
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
        cart.length === 0;
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

        checkoutMessage.textContent =
            `Order #${result.order_id} completed. Total: ₱${Number(result.total).toFixed(2)}. Change: ₱${Number(result.change).toFixed(2)}`;

        cart = [];
        renderCart();
        checkoutForm.reset();
        paymentMethod.dispatchEvent(new Event("change"));

    } catch (error) {
        checkoutMessage.textContent = error.message;
    } finally {
        checkoutBusy = false;
        checkoutButton.disabled = cart.length === 0;
    }
});

document.getElementById("discount-type").addEventListener(
    "change",
    renderCart
);
