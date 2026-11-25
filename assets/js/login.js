{
    const form = document.getElementById("login-form");
    const username = document.getElementById("login-username");
    const password = document.getElementById("login-password");
    const toggle = document.getElementById("login-password-toggle");
    const submit = document.getElementById("login-submit");
    const status = document.getElementById("login-status");
    const credentialsError = document.getElementById("login-error");
    let submitting = false;

    function showFieldError(field, message) {
        const error = document.getElementById(field.id + "-error");
        error.textContent = message;
        error.hidden = !message;
        field.toggleAttribute("aria-invalid", Boolean(message));
        if (message) field.setAttribute("aria-invalid", "true");
    }

    function clearCredentialsError() {
        if (!credentialsError || credentialsError.hidden) return;
        credentialsError.hidden = true;
        for (const field of [username, password]) {
            field.removeAttribute("aria-invalid");
            field.setAttribute("aria-describedby", field.id + "-error");
        }
    }

    toggle.hidden = false;
    toggle.addEventListener("click", () => {
        const show = password.type === "password";
        password.type = show ? "text" : "password";
        toggle.setAttribute("aria-label", show ? "Hide password" : "Show password");
        toggle.toggleAttribute("data-password-visible", show);
    });

    form.noValidate = true;
    for (const field of [username, password]) {
        field.addEventListener("input", () => {
            clearCredentialsError();
            showFieldError(field, "");
        });
    }

    form.addEventListener("submit", event => {
        if (submitting) {
            event.preventDefault();
            return;
        }

        clearCredentialsError();
        const missingUsername = !username.value.trim();
        const missingPassword = !password.value;
        showFieldError(username, missingUsername ? "Enter your username." : "");
        showFieldError(password, missingPassword ? "Enter your password." : "");
        if (missingUsername || missingPassword) {
            event.preventDefault();
            (missingUsername ? username : password).focus();
            return;
        }

        submitting = true;
        submit.disabled = true;
        submit.textContent = "Signing in…";
        status.textContent = "Signing in. Please wait.";
    });

    window.addEventListener("pageshow", () => {
        submitting = false;
        submit.disabled = false;
        submit.textContent = "Sign In";
        status.textContent = "";
    });
}
