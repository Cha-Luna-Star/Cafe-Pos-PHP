const resetDialog = document.getElementById("staff-reset-dialog");

if (resetDialog) {
    const form = resetDialog.querySelector("form");
    const accountId = document.getElementById("reset-staff-id");
    const accountName = document.getElementById("reset-staff-name");
    const password = document.getElementById("reset-staff-password");
    const error = document.getElementById("reset-staff-error");
    const retryId = resetDialog.dataset.retryId;
    let trigger;

    function openReset(button, retry = false) {
        form.reset();
        trigger = button;
        accountId.value = button.dataset.staffId;
        accountName.textContent = button.dataset.staffName;
        error.hidden = !retry;
        resetDialog.showModal();
        password.focus();
    }

    const buttons = document.querySelectorAll("[data-reset-staff]");
    for (const button of buttons) {
        button.addEventListener("click", () => openReset(button));
    }

    document.getElementById("cancel-staff-reset").addEventListener("click", () => resetDialog.close());
    resetDialog.addEventListener("close", () => {
        form.reset();
        trigger?.focus();
    });

    const retryButton = Array.from(buttons).find(button => button.dataset.staffId === retryId);
    if (retryButton) {
        openReset(retryButton, true);
    }
}
