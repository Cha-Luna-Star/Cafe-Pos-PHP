CREATE OR ALTER PROCEDURE dbo.GetManagedMenuItems
AS
BEGIN
    SET NOCOUNT ON;

    IF COL_LENGTH('dbo.menu_items', 'low_stock_threshold') IS NULL
    BEGIN
        SELECT
            m.id,
            m.name,
            m.price,
            m.category_id,
            c.name AS category_name,
            m.available,
            m.stock_qty,
            10 AS low_stock_threshold,
            m.discount,
            m.has_temp_option,
            m.has_sizes,
            m.is_archived
        FROM dbo.menu_items m
        LEFT JOIN dbo.categories c
            ON m.category_id = c.id
        ORDER BY m.is_archived, m.name;
        RETURN;
    END;

    EXEC sys.sp_executesql N'SELECT
        m.id,
        m.name,
        m.price,
        m.category_id,
        c.name AS category_name,
        m.available,
        m.stock_qty,
        m.low_stock_threshold AS low_stock_threshold,
        m.discount,
        m.has_temp_option,
        m.has_sizes,
        m.is_archived
    FROM dbo.menu_items m
    LEFT JOIN dbo.categories c
        ON m.category_id = c.id
    ORDER BY m.is_archived, m.name';
END;
GO

CREATE OR ALTER PROCEDURE dbo.HasProductLowStockThreshold
AS
BEGIN
    SET NOCOUNT ON;

    SELECT CASE WHEN COL_LENGTH('dbo.menu_items', 'low_stock_threshold') IS NULL
        THEN 0 ELSE 1 END;
END;
GO

CREATE OR ALTER PROCEDURE dbo.CountMenuCategory
    @CategoryId int
AS
BEGIN
    SET NOCOUNT ON;

    SELECT COUNT(*) FROM dbo.categories WHERE id = @CategoryId;
END;
GO
