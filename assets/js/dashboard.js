const chartData = document.getElementById("dashboard-chart-data");

if (chartData) {
    const periods = JSON.parse(chartData.textContent);
    const moneyTicks = new Intl.NumberFormat("en-PH", {
        style: "currency", currency: "PHP", notation: "compact", maximumFractionDigits: 1
    });
    const countTicks = new Intl.NumberFormat("en-PH", {
        notation: "compact", maximumFractionDigits: 0
    });

    document.querySelectorAll("canvas[data-trend]").forEach(canvas => {
        const context = canvas.getContext("2d");
        if (!context || !periods.length) return;
        const metric = canvas.dataset.trend;
        const isSales = metric === "sales";
        const values = periods.map(period => period[metric]);
        const ticks = isSales ? moneyTicks : countTicks;
        const color = isSales ? "#76543b" : "#286555";

        function draw(width, height) {
            if (width < 100 || height < 100) return;
            const ratio = window.devicePixelRatio || 1;
            canvas.width = Math.round(width * ratio);
            canvas.height = Math.round(height * ratio);
            context.setTransform(ratio, 0, 0, ratio, 0, 0);
            context.font = "11px Arial, sans-serif";
            context.textBaseline = "middle";

            const lowest = Math.min(0, ...values);
            const highest = Math.max(1, ...values);
            const span = highest - lowest;
            const scale = 10 ** Math.floor(Math.log10(span / 4));
            const step = Math.max(isSales ? 0.01 : 1, Math.ceil(span / (4 * scale)) * scale);
            const topValue = Math.ceil(highest / step) * step;
            const bottomValue = Math.floor(lowest / step) * step;
            const left = Math.ceil(Math.max(
                context.measureText(ticks.format(topValue)).width,
                context.measureText(ticks.format(bottomValue)).width
            )) + 10;
            const right = width - 10;
            const top = 12;
            const bottom = height - 29;
            const slot = (right - left) / periods.length;
            const x = index => left + slot * (index + 0.5);
            const y = value => bottom - (bottom - top) * (value - bottomValue) / (topValue - bottomValue);

            context.textAlign = "right";
            for (let index = 0; index <= Math.round((topValue - bottomValue) / step); index++) {
                const value = bottomValue + index * step;
                const line = y(value);
                context.strokeStyle = "#e7dfd9";
                context.lineWidth = 1;
                context.beginPath();
                context.moveTo(left, line);
                context.lineTo(right, line);
                context.stroke();
                context.fillStyle = "#555";
                context.fillText(ticks.format(value), left - 5, line);
            }

            context.fillStyle = color;
            if (isSales) {
                context.strokeStyle = color;
                context.lineWidth = 2;
                context.lineJoin = "round";
                context.beginPath();
                values.forEach((value, index) => {
                    if (index === 0) context.moveTo(x(index), y(value));
                    else context.lineTo(x(index), y(value));
                });
                context.stroke();
                if (values.length <= 30) {
                    values.forEach((value, index) => {
                        context.beginPath();
                        context.arc(x(index), y(value), 2.6, 0, Math.PI * 2);
                        context.fill();
                    });
                }
            } else {
                const barWidth = Math.min(30, slot * 0.68);
                values.forEach((value, index) => {
                    context.fillRect(x(index) - barWidth / 2, y(value), barWidth, y(0) - y(value));
                });
            }

            context.fillStyle = "#555";
            context.textAlign = "center";
            const labelCount = Math.max(2, Math.min(periods.length, Math.floor((right - left) / 62)));
            const labelIndices = new Set(Array.from({ length: labelCount }, (_, index) =>
                Math.round(index * (periods.length - 1) / (labelCount - 1))
            ));
            for (const index of labelIndices) {
                const label = periods[index].label;
                const half = context.measureText(label).width / 2;
                context.fillText(label, Math.min(width - half, Math.max(half, x(index))), bottom + 15);
            }
        }

        const observer = new ResizeObserver(([entry]) => draw(entry.contentRect.width, entry.contentRect.height));
        observer.observe(canvas);
    });
}
