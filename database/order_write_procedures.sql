CREATE OR ALTER PROCEDURE dbo.CreateCheckoutOrder
    @OrderType nvarchar(30),
    @Total float,
    @UserId int,
    @Payment nvarchar(100),
    @Tendered float,
    @Change float,
    @Tax float,
    @Reference nvarchar(100)
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO dbo.orders
    (
        order_type, status, total, created_by,
        created_at, payment_method, amount_tendered,
        change_amount, tax, ref_number
    )
    OUTPUT INSERTED.id
    VALUES (@OrderType, 'Pending', @Total, @UserId, SYSDATETIME(), @Payment, @Tendered, @Change, @Tax, @Reference);
END;
GO

CREATE OR ALTER PROCEDURE dbo.CreateCheckoutOrderItem
    @OrderId int,
    @ItemId int,
    @Quantity int,
    @Size nvarchar(50),
    @Subtotal float
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO dbo.order_items
    (order_id, menu_item_id, quantity, size, subtotal)
    VALUES (@OrderId, @ItemId, @Quantity, @Size, @Subtotal);
END;
GO

CREATE OR ALTER PROCEDURE dbo.DeductCheckoutProduct
    @Quantity int,
    @ItemId int,
    @Minimum int
AS
BEGIN
    SET NOCOUNT OFF;

    UPDATE dbo.menu_items
    SET stock_qty = stock_qty - @Quantity
    WHERE id = @ItemId AND stock_qty >= @Minimum;
END;
GO

CREATE OR ALTER PROCEDURE dbo.DeductCheckoutProductBatch
    @Quantity int,
    @BatchId int,
    @Minimum int
AS
BEGIN
    SET NOCOUNT OFF;

    UPDATE dbo.product_batches
    SET quantity = quantity - @Quantity
    WHERE id = @BatchId
    AND quantity >= @Minimum;
END;
GO

CREATE OR ALTER PROCEDURE dbo.WriteProductSaleMovement
    @ItemId int,
    @BatchId int,
    @Quantity decimal(12,3),
    @Remarks nvarchar(500),
    @UserId int
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO dbo.stock_movements
    (item_type, item_id, batch_id, movement_type,
    quantity, remarks, user_id)
    VALUES ('Product', @ItemId, @BatchId, 'Sale', @Quantity, @Remarks, @UserId);
END;
GO

CREATE OR ALTER PROCEDURE dbo.DeductCheckoutIngredient
    @ZeroThreshold float,
    @Quantity float,
    @ItemId int,
    @Minimum float
AS
BEGIN
    SET NOCOUNT OFF;

    UPDATE dbo.ingredients
    SET stock_qty = CASE WHEN stock_qty <= @ZeroThreshold THEN 0 ELSE stock_qty - @Quantity END
    WHERE id = @ItemId AND stock_qty + 0.000001 >= @Minimum;
END;
GO

CREATE OR ALTER PROCEDURE dbo.WriteCheckoutIngredientUsage
    @ItemId int,
    @Quantity float,
    @OrderId int
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO dbo.ingredient_usage
    (ingredient_id, quantity_used, order_id, created_at)
    VALUES (@ItemId, @Quantity, @OrderId, SYSDATETIME());
END;
GO

CREATE OR ALTER PROCEDURE dbo.DeductCheckoutIngredientBatch
    @Quantity float,
    @BatchId int
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE dbo.inventory_batches
    SET quantity = quantity - @Quantity
    WHERE id = @BatchId;
END;
GO

CREATE OR ALTER PROCEDURE dbo.WriteIngredientSaleMovement
    @ItemId int,
    @BatchId int,
    @Quantity decimal(12,3),
    @Remarks nvarchar(500),
    @UserId int
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO dbo.stock_movements
    (item_type, item_id, batch_id, movement_type,
    quantity, remarks, user_id)
    VALUES ('Ingredient', @ItemId, @BatchId, 'Sale', @Quantity, @Remarks, @UserId);
END;
GO

CREATE OR ALTER PROCEDURE dbo.RestoreOrderBatchIngredient
    @Quantity float,
    @BatchId int,
    @ItemId int
AS
BEGIN
    SET NOCOUNT OFF;

    UPDATE dbo.inventory_batches
    SET quantity = quantity + @Quantity
    WHERE id = @BatchId AND ingredient_id = @ItemId AND quantity >= 0;
END;
GO

CREATE OR ALTER PROCEDURE dbo.RestoreOrderBatchProduct
    @Quantity float,
    @BatchId int,
    @ItemId int
AS
BEGIN
    SET NOCOUNT OFF;

    UPDATE dbo.product_batches
    SET quantity = quantity + @Quantity
    WHERE id = @BatchId AND menu_item_id = @ItemId AND quantity >= 0;
END;
GO

CREATE OR ALTER PROCEDURE dbo.RestoreOrderStockIngredient
    @Quantity float,
    @ItemId int
AS
BEGIN
    SET NOCOUNT OFF;

    UPDATE dbo.ingredients
    SET stock_qty = stock_qty + @Quantity
    WHERE id = @ItemId;
END;
GO

CREATE OR ALTER PROCEDURE dbo.RestoreOrderStockProduct
    @Quantity float,
    @ItemId int
AS
BEGIN
    SET NOCOUNT OFF;

    UPDATE dbo.menu_items
    SET stock_qty = stock_qty + @Quantity
    WHERE id = @ItemId;
END;
GO

CREATE OR ALTER PROCEDURE dbo.WriteCancellationMovement
    @Type nvarchar(20),
    @ItemId int,
    @BatchId int,
    @Quantity decimal(12,3),
    @Remarks nvarchar(500),
    @UserId int
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO dbo.stock_movements
    (item_type, item_id, batch_id, movement_type,
    quantity, remarks, user_id)
    VALUES (@Type, @ItemId, @BatchId, 'Cancellation', @Quantity, @Remarks, @UserId);
END;
GO

CREATE OR ALTER PROCEDURE dbo.SetPendingOrderStatus
    @Status nvarchar(30),
    @OrderId int
AS
BEGIN
    SET NOCOUNT OFF;

    UPDATE dbo.orders
    SET status = @Status
    WHERE id = @OrderId AND status = 'Pending';
END;
GO
