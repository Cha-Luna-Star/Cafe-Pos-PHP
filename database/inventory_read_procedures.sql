CREATE OR ALTER PROCEDURE dbo.GetActiveIngredients
AS
BEGIN
    SET NOCOUNT ON;

    SELECT id, name, unit, stock_qty,
           low_stock_threshold, cost_per_unit
    FROM dbo.ingredients
    WHERE is_archived = 0
    ORDER BY name;
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetArchivedIngredients
AS
BEGIN
    SET NOCOUNT ON;

    SELECT id, name, unit, low_stock_threshold, cost_per_unit
    FROM dbo.ingredients
    WHERE is_archived = 1
    ORDER BY name;
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetInventoryProducts
AS
BEGIN
    SET NOCOUNT ON;

    IF COL_LENGTH('dbo.menu_items', 'low_stock_threshold') IS NULL
    BEGIN
        SELECT id, name, stock_qty, 10 AS low_stock_threshold
        FROM dbo.menu_items
        WHERE is_archived = 0
        ORDER BY name;
        RETURN;
    END;

    EXEC sys.sp_executesql N'SELECT id, name, stock_qty, low_stock_threshold AS low_stock_threshold
    FROM dbo.menu_items
    WHERE is_archived = 0
    ORDER BY name';
END;
GO

CREATE OR ALTER VIEW dbo.vw_InventoryBatches
AS
    SELECT
        'Ingredient' AS item_type,
        b.ingredient_id AS item_id,
        b.id,
        i.name AS item_name,
        i.is_archived,
        b.quantity,
        b.cost_per_unit,
        CASE
            WHEN b.quantity = 0 THEN 0
            WHEN TRY_CONVERT(decimal(18,3), b.quantity) > 0
                AND TRY_CONVERT(decimal(18,6), b.cost_per_unit) > 0
            THEN ROUND(TRY_CONVERT(decimal(18,3), b.quantity) *
                TRY_CONVERT(decimal(18,6), b.cost_per_unit), 2)
        END AS remaining_value,
        b.received_date,
        b.expiration_date,
        b.supplier
    FROM dbo.inventory_batches b
    JOIN dbo.ingredients i ON b.ingredient_id = i.id

    UNION ALL

    SELECT
        'Product' AS item_type,
        b.menu_item_id AS item_id,
        b.id,
        m.name AS item_name,
        m.is_archived,
        b.quantity,
        b.cost_per_unit,
        CASE
            WHEN b.quantity = 0 THEN 0
            WHEN TRY_CONVERT(decimal(18,3), b.quantity) > 0
                AND TRY_CONVERT(decimal(18,6), b.cost_per_unit) > 0
            THEN ROUND(TRY_CONVERT(decimal(18,3), b.quantity) *
                TRY_CONVERT(decimal(18,6), b.cost_per_unit), 2)
        END AS remaining_value,
        CONVERT(NVARCHAR(30), b.received_date, 23),
        CONVERT(NVARCHAR(30), b.expiration_date, 23),
        b.supplier
    FROM dbo.product_batches b
    JOIN dbo.menu_items m ON b.menu_item_id = m.id;
GO

CREATE OR ALTER PROCEDURE dbo.GetInventoryBatches
AS
BEGIN
    SET NOCOUNT ON;

    SELECT item_type, item_id, id, item_name, is_archived, quantity,
        cost_per_unit, remaining_value, received_date, expiration_date, supplier
    FROM dbo.vw_InventoryBatches;
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetInventoryMovements
AS
BEGIN
    SET NOCOUNT ON;

    SELECT
        sm.id,
        sm.item_type,
        CASE
            WHEN sm.item_type = 'Ingredient' THEN i.name
            WHEN sm.item_type = 'Product' THEN m.name
        END AS item_name,
        sm.movement_type,
        sm.quantity,
        sm.batch_id,
        sm.remarks,
        sm.created_at,
        CONVERT(varchar(27), sm.created_at, 126) AS sort_timestamp,
        u.username
    FROM dbo.stock_movements sm
    LEFT JOIN dbo.ingredients i
        ON sm.item_type = 'Ingredient' AND sm.item_id = i.id
    LEFT JOIN dbo.menu_items m
        ON sm.item_type = 'Product' AND sm.item_id = m.id
    LEFT JOIN dbo.users u
        ON sm.user_id = u.id
    ORDER BY sm.created_at DESC, sm.id DESC;
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetStockReconciliation
AS
BEGIN
    SET NOCOUNT ON;

    SELECT
        'Ingredient' AS item_type,
        i.id,
        i.name,
        TRY_CONVERT(decimal(18,3), i.stock_qty) AS recorded_stock,
        TRY_CONVERT(decimal(18,3), COALESCE(b.batch_stock, 0)) AS batch_stock,
        TRY_CONVERT(decimal(18,3), i.stock_qty) - TRY_CONVERT(decimal(18,3), COALESCE(b.batch_stock, 0)) AS difference
    FROM dbo.ingredients i
    LEFT JOIN (
        SELECT ingredient_id, SUM(TRY_CONVERT(decimal(18,3), quantity)) AS batch_stock
        FROM dbo.inventory_batches
        GROUP BY ingredient_id
    ) b ON i.id = b.ingredient_id
    WHERE i.is_archived = 0

    UNION ALL

    SELECT
        'Product' AS item_type,
        m.id,
        m.name,
        TRY_CONVERT(decimal(18,3), m.stock_qty) AS recorded_stock,
        TRY_CONVERT(decimal(18,3), COALESCE(b.batch_stock, 0)) AS batch_stock,
        TRY_CONVERT(decimal(18,3), m.stock_qty) - TRY_CONVERT(decimal(18,3), COALESCE(b.batch_stock, 0)) AS difference
    FROM dbo.menu_items m
    LEFT JOIN (
        SELECT menu_item_id, SUM(TRY_CONVERT(decimal(18,3), quantity)) AS batch_stock
        FROM dbo.product_batches
        GROUP BY menu_item_id
    ) b ON m.id = b.menu_item_id
    WHERE m.is_archived = 0

    ORDER BY name;
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetReconciliationHistory
AS
BEGIN
    SET NOCOUNT ON;

    SELECT
        r.item_type,
        r.item_id,
        COALESCE(i.name, m.name) AS item_name,
        r.previous_stock,
        r.corrected_stock,
        r.reason,
        r.user_id,
        u.username,
        CONVERT(varchar(19), r.created_at, 120) AS corrected_at
    FROM dbo.stock_reconciliations r
    LEFT JOIN dbo.ingredients i
        ON r.item_type = 'Ingredient' AND r.item_id = i.id
    LEFT JOIN dbo.menu_items m
        ON r.item_type = 'Product' AND r.item_id = m.id
    LEFT JOIN dbo.users u ON r.user_id = u.id
    ORDER BY r.created_at DESC, r.id DESC;
END;
GO
