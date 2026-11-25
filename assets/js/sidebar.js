const sidebar = document.querySelector(".sidebar");
const toggle = document.getElementById("sidebar-toggle");

if (sidebar && toggle) {
    const links = document.getElementById("sidebar-links");
    const compact = window.matchMedia("(max-width: 850px)");

    function setExpanded(expanded) {
        sidebar.dataset.collapsed = String(!expanded);
        toggle.setAttribute("aria-expanded", String(expanded));
    }

    function syncLayout() {
        if (compact.matches && links.contains(document.activeElement)) {
            toggle.focus();
        }
        setExpanded(!compact.matches);
    }

    toggle.hidden = false;
    toggle.addEventListener("click", () => {
        setExpanded(toggle.getAttribute("aria-expanded") !== "true");
    });
    sidebar.addEventListener("keydown", event => {
        if (event.key === "Escape" && compact.matches && toggle.getAttribute("aria-expanded") === "true") {
            setExpanded(false);
            toggle.focus();
            event.preventDefault();
        }
    });
    compact.addEventListener("change", syncLayout);
    syncLayout();
}
