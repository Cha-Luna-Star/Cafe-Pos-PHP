{
    const sidebar = document.getElementById("app-sidebar");
    const toggle = document.getElementById("sidebar-toggle");
    const compact = window.matchMedia("(max-width: 850px)");
    const preferenceKey = "cafe-pos-sidebar-hidden";
    let desktopHidden = false;

    try {
        desktopHidden = window.localStorage.getItem(preferenceKey) === "true";
    } catch {
        // The sidebar still works when browser storage is unavailable.
    }

    function setSidebarHidden(hidden) {
        if (hidden && sidebar.contains(document.activeElement)) {
            toggle.focus();
        }
        document.documentElement.toggleAttribute("data-sidebar-hidden", hidden);
        toggle.setAttribute("aria-expanded", String(!hidden));
        const label = hidden ? "Show sidebar" : "Hide sidebar";
        toggle.setAttribute("aria-label", label);
        toggle.title = label;
    }

    function syncLayout() {
        setSidebarHidden(compact.matches || desktopHidden);
    }

    toggle.hidden = false;
    toggle.addEventListener("click", () => {
        const hidden = toggle.getAttribute("aria-expanded") === "true";
        setSidebarHidden(hidden);
        if (!compact.matches) {
            desktopHidden = hidden;
            try {
                window.localStorage.setItem(preferenceKey, String(hidden));
            } catch {
                // Keep the preference for this page when storage is unavailable.
            }
        }
    });

    sidebar.addEventListener("keydown", event => {
        if (event.key === "Escape") {
            toggle.click();
            event.preventDefault();
        }
    });

    compact.addEventListener("change", syncLayout);
    syncLayout();
}
