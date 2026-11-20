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
});