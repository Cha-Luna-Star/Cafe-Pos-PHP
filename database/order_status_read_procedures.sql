CREATE OR ALTER PROCEDURE dbo.GetOrderStatusForUpdate
    @OrderId int
AS
BEGIN
    SET NOCOUNT ON;

    SELECT status
    FROM dbo.orders WITH (UPDLOCK, HOLDLOCK)
    WHERE id = @OrderId;
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetOrderSaleMovements
    @Reference nvarchar(500)
AS
BEGIN
    SET NOCOUNT ON;

    SELECT item_type, item_id, batch_id, quantity
    FROM dbo.stock_movements
    WHERE movement_type = 'Sale'
    AND remarks = @Reference
    ORDER BY id;
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetOrderProductTotals
    @OrderId int
AS
BEGIN
    SET NOCOUNT ON;

    SELECT menu_item_id, SUM(quantity) AS quantity
    FROM dbo.order_items
    WHERE order_id = @OrderId
    GROUP BY menu_item_id;
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetOrderIngredientTotals
    @OrderId int
AS
BEGIN
    SET NOCOUNT ON;

    SELECT ingredient_id, SUM(quantity_used) AS quantity
    FROM dbo.ingredient_usage
    WHERE order_id = @OrderId
    GROUP BY ingredient_id;
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetProductForOrderRestore
    @ItemId int
AS
BEGIN
    SET NOCOUNT ON;

    SELECT id
    FROM dbo.menu_items WITH (UPDLOCK, HOLDLOCK)
    WHERE id = @ItemId AND stock_qty >= 0;
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetIngredientForOrderRestore
    @ItemId int
AS
BEGIN
    SET NOCOUNT ON;

    SELECT is_archived, stock_qty
    FROM dbo.ingredients WITH (UPDLOCK, HOLDLOCK)
    WHERE id = @ItemId;
END;
GO
