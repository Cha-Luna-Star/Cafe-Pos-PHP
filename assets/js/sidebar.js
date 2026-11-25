{
    const sidebar = document.getElementById("app-sidebar");
    const toggle = document.getElementById("sidebar-toggle");
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

    function renderSidebar() {
        const hidden = mobile.matches && !mobileOpen;
        const collapsed = !mobile.matches && desktopCollapsed;
        if (hidden && sidebar.contains(document.activeElement)) {
            toggle.focus();
        }
        layout.toggleAttribute("data-sidebar-hidden", hidden);
        layout.toggleAttribute("data-sidebar-collapsed", collapsed);
        toggle.setAttribute("aria-expanded", String(!hidden && !collapsed));
        const label = mobile.matches
            ? (hidden ? "Show sidebar" : "Hide sidebar")
            : (collapsed ? "Expand sidebar" : "Collapse sidebar");
        toggle.setAttribute("aria-label", label);
        toggle.title = label;
    }

    toggle.hidden = false;
    toggle.addEventListener("click", () => {
        if (mobile.matches) {
            mobileOpen = !mobileOpen;
        } else {
            desktopCollapsed = !desktopCollapsed;
            try {
                window.localStorage.setItem(preferenceKey, String(desktopCollapsed));
            } catch {
                // Keep the preference for this page when storage is unavailable.
            }
        }
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
