-- Requires inventory_read_procedures.sql for vw_InventoryBatches.
CREATE OR ALTER PROCEDURE dbo.GetDashboardToday
    @StartDate date, @StopDate date
AS
BEGIN
    SET NOCOUNT ON;

    SELECT COUNT_BIG(*) AS orders, COUNT_BIG(CASE WHEN status = 'Completed' THEN 1 END) AS completed, COUNT_BIG(CASE WHEN status = 'Pending' THEN 1 END) AS pending, COUNT_BIG(CASE WHEN status = 'Cancelled' THEN 1 END) AS cancelled,
        SUM(CASE WHEN status = 'Completed' THEN CAST(COALESCE(total, 0) AS decimal(19,2)) ELSE 0 END) AS sales
    FROM dbo.orders
    WHERE created_at >= @StartDate AND created_at < @StopDate;
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetDashboardPeriods
    @StopDate date, @StartDate date, @Grouping varchar(5)
AS
BEGIN
    SET NOCOUNT ON;

    SELECT CASE @Grouping WHEN 'hour' THEN CONVERT(varchar(10), DATEPART(hour, created_at)) WHEN 'month' THEN CONVERT(char(7), created_at, 23) ELSE CONVERT(char(10), created_at, 23) END AS period, COUNT_BIG(*) AS orders, COUNT_BIG(CASE WHEN status = 'Completed' THEN 1 END) AS completed, COUNT_BIG(CASE WHEN status = 'Pending' THEN 1 END) AS pending, COUNT_BIG(CASE WHEN status = 'Cancelled' THEN 1 END) AS cancelled,
            SUM(CASE WHEN status = 'Completed' THEN CAST(COALESCE(total, 0) AS decimal(19,2)) ELSE 0 END) AS sales
        FROM dbo.orders
        WHERE created_at < @StopDate AND (@StartDate IS NULL OR created_at >= @StartDate)
        GROUP BY CASE @Grouping WHEN 'hour' THEN CONVERT(varchar(10), DATEPART(hour, created_at)) WHEN 'month' THEN CONVERT(char(7), created_at, 23) ELSE CONVERT(char(10), created_at, 23) END
        ORDER BY period;
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetDashboardRecentOrders
AS
BEGIN
    SET NOCOUNT ON;

    SELECT TOP (6) id, status, total, payment_method, CONVERT(char(16), created_at, 120) AS placed_at
    FROM dbo.orders
    ORDER BY created_at DESC, id DESC;
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetDashboardPendingOrders
AS
BEGIN
    SET NOCOUNT ON;

    SELECT TOP (3) id, total, COUNT_BIG(*) OVER () AS total_pending
    FROM dbo.orders
    WHERE status = 'Pending'
    ORDER BY created_at ASC, id ASC;
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetDashboardStock
AS
BEGIN
    SET NOCOUNT ON;

    IF COL_LENGTH('dbo.menu_items', 'low_stock_threshold') IS NULL
    BEGIN
        WITH active_stock AS (
        SELECT COALESCE(stock_qty, 0) AS stock_qty, low_stock_threshold AS threshold
        FROM dbo.ingredients WHERE is_archived = 0
        UNION ALL
        SELECT COALESCE(stock_qty, 0), 10
        FROM dbo.menu_items WHERE is_archived = 0
    )
    SELECT
        COUNT_BIG(CASE WHEN stock_qty > 0 AND stock_qty <= threshold THEN 1 END) AS low_items,
        COUNT_BIG(CASE WHEN stock_qty <= 0 THEN 1 END) AS out_of_stock
    FROM active_stock;
        RETURN;
    END;

    EXEC sys.sp_executesql N'WITH active_stock AS (
        SELECT COALESCE(stock_qty, 0) AS stock_qty, low_stock_threshold AS threshold
        FROM dbo.ingredients WHERE is_archived = 0
        UNION ALL
        SELECT COALESCE(stock_qty, 0), low_stock_threshold
        FROM dbo.menu_items WHERE is_archived = 0
    )
    SELECT
        COUNT_BIG(CASE WHEN stock_qty > 0 AND stock_qty <= threshold THEN 1 END) AS low_items,
        COUNT_BIG(CASE WHEN stock_qty <= 0 THEN 1 END) AS out_of_stock
    FROM active_stock';
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetDashboardExpirationGroups
AS
BEGIN
    SET NOCOUNT ON;

    SELECT expiration_date, COUNT_BIG(*) AS batch_count
    FROM dbo.vw_InventoryBatches
    WHERE quantity > 0 AND expiration_date IS NOT NULL
    GROUP BY expiration_date;
END;
GO
