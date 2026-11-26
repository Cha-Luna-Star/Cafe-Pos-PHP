CREATE OR ALTER PROCEDURE dbo.GetReconciliationIngredientStock
    @ItemId int
AS
BEGIN
    SET NOCOUNT ON;

        SELECT TRY_CONVERT(decimal(18,3), stock_qty) AS recorded_stock, is_archived,
            CASE WHEN stock_qty >= 0 THEN 1 ELSE 0 END AS valid_stock
        FROM dbo.ingredients WITH (UPDLOCK, HOLDLOCK)
        WHERE id = @ItemId;
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetReconciliationProductStock
    @ItemId int
AS
BEGIN
    SET NOCOUNT ON;

        SELECT TRY_CONVERT(decimal(18,3), stock_qty) AS recorded_stock, is_archived,
            CASE WHEN stock_qty >= 0 THEN 1 ELSE 0 END AS valid_stock
        FROM dbo.menu_items WITH (UPDLOCK, HOLDLOCK)
        WHERE id = @ItemId;
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetReconciliationIngredientBatches
    @ItemId int
AS
BEGIN
    SET NOCOUNT ON;

        SELECT TRY_CONVERT(decimal(18,3), COALESCE(SUM(TRY_CONVERT(decimal(18,3), quantity)), 0)) AS batch_stock,
            COUNT_BIG(*) - COUNT_BIG(CASE WHEN quantity >= 0
                AND TRY_CONVERT(decimal(18,3), quantity) IS NOT NULL
                AND ABS(quantity - ROUND(quantity, 3)) <= 0.00000001 THEN 1 END) AS invalid_batches
        FROM dbo.inventory_batches WITH (UPDLOCK, HOLDLOCK)
        WHERE ingredient_id = @ItemId;
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetReconciliationProductBatches
    @ItemId int
AS
BEGIN
    SET NOCOUNT ON;

        SELECT TRY_CONVERT(decimal(18,3), COALESCE(SUM(TRY_CONVERT(decimal(18,3), quantity)), 0)) AS batch_stock,
            COUNT_BIG(*) - COUNT_BIG(CASE WHEN quantity >= 0
                AND TRY_CONVERT(decimal(18,3), quantity) IS NOT NULL
                AND ABS(quantity - ROUND(quantity, 3)) <= 0.00000001 THEN 1 END) AS invalid_batches
        FROM dbo.product_batches WITH (UPDLOCK, HOLDLOCK)
        WHERE menu_item_id = @ItemId;
END;
GO
