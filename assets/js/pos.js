
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
    let selectedSize = null;
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
        Number(item.price) + adjustment - Number(item.discount)
    );

    const existing = cart.find(
        entry =>
            entry.id === item.id &&
            entry.size === selectedSize
    );

    if (existing) {
        existing.quantity++;
    } else {
        cart.push({
            id: item.id,
            name: item.name,
            size: selectedSize,
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

    document.getElementById("cart-total").textContent =
        `₱${total.toFixed(2)}`;

    document.getElementById("checkout-btn").disabled =
        cart.length === 0;
}
