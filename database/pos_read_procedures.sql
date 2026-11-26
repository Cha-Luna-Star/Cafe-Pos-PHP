CREATE OR ALTER PROCEDURE dbo.GetMenuCategories
AS
BEGIN
    SET NOCOUNT ON;

    SELECT id, name
    FROM dbo.categories
    ORDER BY name;
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetPosMenu
    @StockDate date
AS
BEGIN
    SET NOCOUNT ON;

    SELECT m.id, m.name, m.price, m.category_id, m.discount, m.has_temp_option, m.has_sizes,
    CASE
    WHEN COALESCE(m.stock_qty, 0) <= 0 THEN 0
    WHEN m.stock_qty < batches.available_qty THEN m.stock_qty
    ELSE batches.available_qty
    END AS sellable_quantity
    FROM dbo.menu_items m
    OUTER APPLY (
    SELECT COALESCE(SUM(CAST(quantity AS bigint)), 0) AS available_qty
    FROM dbo.product_batches
    WHERE menu_item_id = m.id AND quantity > 0
    AND (expiration_date IS NULL OR expiration_date >= CONVERT(date, @StockDate, 23))
    ) batches
    WHERE m.is_archived = 0 AND m.available = 1
    ORDER BY m.name;
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetSizeModifiers
AS
BEGIN
    SET NOCOUNT ON;

    SELECT id, name, price_adjustment, menu_item_id
    FROM dbo.size_modifiers
    ORDER BY price_adjustment;
END;
GO
