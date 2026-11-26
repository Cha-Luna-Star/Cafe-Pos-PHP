CREATE OR ALTER PROCEDURE dbo.GetAdjustmentIngredient
    @ItemId int
AS
BEGIN
    SET NOCOUNT ON;

    SELECT name, stock_qty
    FROM dbo.ingredients WITH (UPDLOCK, HOLDLOCK)
    WHERE id = @ItemId AND is_archived = 0;
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetAdjustmentIngredientBatch
    @ItemId int,
    @BatchId int
AS
BEGIN
    SET NOCOUNT ON;

    SELECT id, quantity, CONVERT(nvarchar(4000), expiration_date, 23) AS expiration_date
    FROM dbo.inventory_batches WITH (UPDLOCK, HOLDLOCK)
    WHERE ingredient_id = @ItemId AND id = @BatchId AND quantity > 0;
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetAdjustmentProduct
    @ItemId int
AS
BEGIN
    SET NOCOUNT ON;

    SELECT name, stock_qty
    FROM dbo.menu_items WITH (UPDLOCK, HOLDLOCK)
    WHERE id = @ItemId AND is_archived = 0;
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetAdjustmentProductBatch
    @ItemId int,
    @BatchId int
AS
BEGIN
    SET NOCOUNT ON;

    SELECT id, quantity, CONVERT(nvarchar(4000), expiration_date, 23) AS expiration_date
    FROM dbo.product_batches WITH (UPDLOCK, HOLDLOCK)
    WHERE menu_item_id = @ItemId AND id = @BatchId AND quantity > 0;
END;
GO
