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
                `Are you sure you want to ${selectedAction === "Completed" ? "complete" : "cancel"} order #${selectedOrder}?`;

            statusError.textContent = "";
            statusDialog.showModal();
        });
    });

    closeButton.addEventListener("click", () => {
        statusDialog.close();
    });

    confirmButton.addEventListener("click", async () => {
        if (confirmButton.disabled || !selectedOrder || !selectedAction) return;

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

            history.replaceState({
                ...history.state,
                ordersAfterSave: {
                    filters: filters.map(field => field.value),
                    sortColumn,
                    sortAscending,
                    message: result.message || "Order updated."
                }
            }, "");
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
    const filters = [searchInput, statusFilter, paymentFilter, dateFilter];
    const empty = document.getElementById("orders-empty");
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
            const cashier = row.cells[2].textContent.trim().toLowerCase();
            const orderPayment = row.cells[4].textContent.trim().toLowerCase();
            const orderStatus = row.cells[6].textContent.trim().toLowerCase();

            const matchesSearch = orderId.includes(search) || cashier.includes(search);
            const matchesStatus = !status || orderStatus === status;
            const matchesPayment = !payment || orderPayment === payment;
            const matchesDate = !date || row.dataset.date === date;
            row.hidden = !(matchesSearch && matchesStatus && matchesPayment && matchesDate);
        });
        empty.hidden = rows.some(row => !row.hidden);
    }

    filters.forEach(element => {
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

    let sortColumn = 1;
    let sortAscending = false;

    const headings = ordersTable.querySelectorAll("thead .sort-header");
    headings.forEach((header, index) => {
        updateSortIndicator(header, header.dataset.direction);
        header.addEventListener("click", () => {
            sortAscending = sortColumn === index ? !sortAscending : true;
            sortColumn = index;

            const type = header.dataset.type;

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

            rows.forEach(row => tableBody.insertBefore(row, empty));

            for (const heading of headings) {
                updateSortIndicator(heading);
                heading.closest("th").removeAttribute("aria-sort");
            }
            updateSortIndicator(header, sortAscending ? "asc" : "desc");
            header.closest("th").setAttribute("aria-sort", sortAscending ? "ascending" : "descending");
            ordersTable.closest(".orders-table-wrapper").scrollTo({ top: 0 });
        });
    });
    if (history.state?.ordersAfterSave) {
        const { ordersAfterSave, ...state } = history.state;
        history.replaceState(state, "");
        filters.forEach((field, index) => {
            field.value = ordersAfterSave.filters[index];
        });
        const heading = headings[ordersAfterSave.sortColumn];
        if (heading) {
            if (sortColumn !== ordersAfterSave.sortColumn) heading.click();
            if (sortAscending !== ordersAfterSave.sortAscending) heading.click();
        }

        const notice = document.getElementById("order-update-notice");
        notice.hidden = false;
        notice.textContent = ordersAfterSave.message;
    }
    filterOrders();
});