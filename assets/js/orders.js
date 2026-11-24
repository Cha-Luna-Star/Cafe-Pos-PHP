document.addEventListener("DOMContentLoaded", () => {
    const statusDialog = document.getElementById("status-dialog");
    const confirmMessage = document.getElementById("status-confirm-message");
    const statusError = document.getElementById("status-error");
    const confirmButton = document.getElementById("confirm-status-action");
    const closeButton = document.getElementById("close-status-dialog");
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    if (!statusDialog || !confirmButton || !closeButton || !csrfToken) {
        console.error("Order status elements are missing.");
        return;
    }

    let selectedOrder = null;
    let selectedAction = null;

    document.querySelectorAll(".complete-btn, .cancel-btn").forEach(button => {
        button.addEventListener("click", event => {
            event.preventDefault();

            selectedOrder = Number(button.dataset.order);
            selectedAction = button.dataset.action;

            confirmMessage.textContent =
                `Are you sure you want to ${selectedAction.toLowerCase()} order #${selectedOrder}?`;

            statusError.textContent = "";
            statusDialog.showModal();
        });
    });

    closeButton.addEventListener("click", () => {
        statusDialog.close();
    });

    confirmButton.addEventListener("click", async () => {
        if (!selectedOrder || !selectedAction) return;

        confirmButton.disabled = true;
        statusError.textContent = "";

        try {
            const response = await fetch("controllers/update_order_status.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    csrf_token: csrfToken,
                    order_id: selectedOrder,
                    action: selectedAction
                })
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || "Unable to update order.");
            }

            window.location.reload();

        } catch (error) {
            statusError.textContent = error.message;
        } finally {
            confirmButton.disabled = false;
        }
    });
    const searchInput = document.getElementById("order-search");
    const statusFilter = document.getElementById("status-filter");
    const paymentFilter = document.getElementById("payment-filter");
    const dateFilter = document.getElementById("date-filter");
    const clearFilters = document.getElementById("clear-filters");
    const ordersTable = document.getElementById("orders-table");
    const tableBody = ordersTable.querySelector("tbody");
    const rows = Array.from(tableBody.querySelectorAll("tr"))
        .filter(row => row.cells.length === 8);

    function filterOrders() {
        const search = searchInput.value.trim().toLowerCase();
        const status = statusFilter.value.toLowerCase();
        const payment = paymentFilter.value.toLowerCase();
        const date = dateFilter.value;

        rows.forEach(row => {
            const orderId = row.cells[0].textContent.trim().toLowerCase();
            const orderDate = row.cells[1].textContent.trim();
            const cashier = row.cells[2].textContent.trim().toLowerCase();
            const orderPayment = row.cells[4].textContent.trim().toLowerCase();
            const orderStatus = row.cells[6].textContent.trim().toLowerCase();

            const matchesSearch = orderId.includes(search) || cashier.includes(search);
            const matchesStatus = !status || orderStatus === status;
            const matchesPayment = !payment || orderPayment === payment;
            const matchesDate = !date || row.dataset.date === date;
            row.hidden = !(matchesSearch && matchesStatus && matchesPayment && matchesDate);
        });
    }

    [searchInput, statusFilter, paymentFilter, dateFilter].forEach(element => {
        element.addEventListener("input", filterOrders);
    });

    clearFilters.addEventListener("click", () => {
        searchInput.value = "";
        statusFilter.value = "";
        paymentFilter.value = "";
        dateFilter.value = "";
        filterOrders();
    });

    const requestedStatus = new URLSearchParams(window.location.search).get("status");
    if (Array.from(statusFilter.options).some(option => option.value === requestedStatus)) {
        statusFilter.value = requestedStatus;
        filterOrders();
    }

    let sortColumn = -1;
    let sortAscending = true;

    ordersTable.querySelectorAll("thead th[data-sort]").forEach((header, index) => {
        header.addEventListener("click", () => {
            sortAscending = sortColumn === index ? !sortAscending : true;
            sortColumn = index;

            const type = header.dataset.sort;

            rows.sort((a, b) => {
                let valueA = a.cells[index].textContent.trim();
                let valueB = b.cells[index].textContent.trim();

                if (type === "number") {
                    valueA = Number(valueA.replace(/[^\d.-]/g, ""));
                    valueB = Number(valueB.replace(/[^\d.-]/g, ""));
                    return sortAscending ? valueA - valueB : valueB - valueA;
                }

                if (type === "date") {
                    valueA = Number(a.cells[index].dataset.timestamp || 0);
                    valueB = Number(b.cells[index].dataset.timestamp || 0);
                    return sortAscending ? valueA - valueB : valueB - valueA;
                }

                const result = valueA.localeCompare(valueB);
                return sortAscending ? result : -result;
            });

            rows.forEach(row => tableBody.appendChild(row));

            ordersTable.querySelectorAll("thead th").forEach(th => {
                th.classList.remove("sort-asc", "sort-desc");
            });

            header.classList.add(sortAscending ? "sort-asc" : "sort-desc");
        });
    });
});