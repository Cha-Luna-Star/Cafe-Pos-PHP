const sortIndicators = {
    text: { idle: "A–Z", asc: "A–Z", desc: "Z–A", first: "A to Z", last: "Z to A" },
    date: { idle: "Oldest", asc: "Oldest", desc: "Newest", first: "oldest first", last: "newest first" },
    number: { idle: "⇅", asc: "↑", desc: "↓", first: "lowest first", last: "highest first" }
};

function updateSortIndicator(button, direction = "idle") {
    const indicators = sortIndicators[button.dataset.type];
    const label = button.querySelector(".sort-label").textContent;
    button.dataset.direction = direction;
    button.querySelector(".sort-indicator").textContent = indicators[direction];
    button.title = "Sort by " + label + ", " + (direction === "asc" ? indicators.last : indicators.first);
}
