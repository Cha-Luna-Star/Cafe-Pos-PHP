{
    const sidebar = document.getElementById("app-sidebar");
    const collapseToggle = document.getElementById("sidebar-collapse");
    const mobileToggle = document.getElementById("sidebar-menu-toggle");
    const mobile = window.matchMedia("(max-width: 850px)");
    const layout = document.documentElement;
    const preferenceKey = "cafe-pos-sidebar-collapsed";
    let desktopCollapsed = false;
    let mobileOpen = false;

    try {
        desktopCollapsed = (window.localStorage.getItem(preferenceKey)
            ?? window.localStorage.getItem("cafe-pos-sidebar-hidden")) === "true";
    } catch {
        // The sidebar still works when browser storage is unavailable.
    }

    function updateToggle(button, expanded, label) {
        button.setAttribute("aria-expanded", String(expanded));
        button.setAttribute("aria-label", label);
        button.title = label;
    }

    function renderSidebar() {
        const hidden = mobile.matches && !mobileOpen;
        const collapsed = !mobile.matches && desktopCollapsed;
        if (hidden && sidebar.contains(document.activeElement)) {
            mobileToggle.focus();
        }
        layout.toggleAttribute("data-sidebar-hidden", hidden);
        layout.toggleAttribute("data-sidebar-collapsed", collapsed);
        updateToggle(collapseToggle, !desktopCollapsed, desktopCollapsed ? "Expand sidebar" : "Collapse sidebar");
        updateToggle(mobileToggle, mobileOpen, mobileOpen ? "Hide sidebar" : "Show sidebar");
    }

    collapseToggle.hidden = false;
    mobileToggle.hidden = false;

    collapseToggle.addEventListener("click", () => {
        desktopCollapsed = !desktopCollapsed;
        try {
            window.localStorage.setItem(preferenceKey, String(desktopCollapsed));
        } catch {
            // Keep the preference for this page when storage is unavailable.
        }
        renderSidebar();
    });

    mobileToggle.addEventListener("click", () => {
        mobileOpen = !mobileOpen;
        renderSidebar();
    });

    sidebar.addEventListener("keydown", event => {
        if (event.key === "Escape" && mobile.matches && mobileOpen) {
            mobileOpen = false;
            renderSidebar();
            event.preventDefault();
        }
    });

    mobile.addEventListener("change", () => {
        mobileOpen = false;
        renderSidebar();
    });
    renderSidebar();
}
