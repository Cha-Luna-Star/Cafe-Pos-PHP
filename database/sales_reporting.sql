-- Apply before deploying the Sales Reports PHP changes.
CREATE OR ALTER VIEW dbo.vw_CompletedOrders
AS
    SELECT id, created_at, payment_method, total
    FROM dbo.orders
    WHERE status = 'Completed';
GO

CREATE OR ALTER PROCEDURE dbo.GetSalesReportTotals
    @From datetime2(7),
    @To datetime2(7)
AS
BEGIN
    SET NOCOUNT ON;

    WITH completed_sales AS (
        SELECT
            CAST(created_at AS date) AS sale_date,
            COALESCE(NULLIF(TRIM(payment_method), ''), 'Unspecified') AS payment_method,
            CAST(total AS decimal(19,2)) AS total
        FROM dbo.vw_CompletedOrders
        WHERE created_at >= @From AND created_at <= @To
    )
    SELECT
        CONVERT(char(10), sale_date, 23) AS sale_date,
        payment_method,
        COUNT_BIG(*) AS order_count,
        COALESCE(SUM(total), 0) AS sales
    FROM completed_sales
    GROUP BY GROUPING SETS ((), (sale_date), (payment_method))
    ORDER BY sale_date DESC, sales DESC, payment_method;
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetBestSellingMenuItems
    @From datetime2(7),
    @To datetime2(7)
AS
BEGIN
    SET NOCOUNT ON;

    WITH item_totals AS (
        SELECT oi.menu_item_id, SUM(CAST(oi.quantity AS bigint)) AS units_sold,
            SUM(CAST(oi.subtotal AS decimal(19,2))) AS revenue
        FROM dbo.order_items oi
        JOIN dbo.vw_CompletedOrders o ON o.id = oi.order_id
        WHERE created_at >= @From AND created_at <= @To
        GROUP BY oi.menu_item_id
    )
    SELECT TOP (10)
        COALESCE(m.name, CONCAT('Item #', t.menu_item_id)) AS item_name,
        t.units_sold, t.revenue
    FROM item_totals t
    LEFT JOIN dbo.menu_items m ON m.id = t.menu_item_id
    ORDER BY t.units_sold DESC, item_name, t.menu_item_id;
END;
GO
