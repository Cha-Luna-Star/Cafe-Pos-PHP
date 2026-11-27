CREATE OR ALTER PROCEDURE dbo.CreateIngredientDelivery
    @ItemId int,
    @Quantity float,
    @Cost float,
    @Received nvarchar(50),
    @Expiration nvarchar(50),
    @Supplier nvarchar(150),
    @Notes nvarchar(500)
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO dbo.inventory_batches
    (ingredient_id, quantity, cost_per_unit,
     received_date, expiration_date, supplier, notes, created_at)
    OUTPUT INSERTED.id
    VALUES (@ItemId, @Quantity, @Cost, @Received, @Expiration, @Supplier, @Notes, SYSDATETIME());
END;
GO

CREATE OR ALTER PROCEDURE dbo.CreateProductDelivery
    @ItemId int,
    @Quantity int,
    @Cost decimal(10,2),
    @Received date,
    @Expiration date,
    @Supplier nvarchar(150),
    @Notes nvarchar(500)
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO dbo.product_batches
    (menu_item_id, quantity, cost_per_unit,
     received_date, expiration_date, supplier, notes)
    OUTPUT INSERTED.id
    VALUES (@ItemId, @Quantity, @Cost, @Received, @Expiration, @Supplier, @Notes);
END;
GO

CREATE OR ALTER PROCEDURE dbo.AddRecordedStockIngredient
    @Quantity float,
    @ItemId int
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE dbo.ingredients
    SET stock_qty = COALESCE(stock_qty, 0) + @Quantity
    WHERE id = @ItemId;
END;
GO

CREATE OR ALTER PROCEDURE dbo.AddRecordedStockProduct
    @Quantity float,
    @ItemId int
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE dbo.menu_items
    SET stock_qty = COALESCE(stock_qty, 0) + @Quantity
    WHERE id = @ItemId;
END;
GO

CREATE OR ALTER PROCEDURE dbo.WriteDeliveryMovement
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
    VALUES (@Type, @ItemId, @BatchId, 'Stock In', @Quantity, @Remarks, @UserId);
END;
GO

CREATE OR ALTER PROCEDURE dbo.CreateAdjustmentBatchIngredient
    @ItemId int,
    @Quantity float,
    @Notes nvarchar(max)
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO dbo.inventory_batches
    (ingredient_id, quantity, cost_per_unit, received_date, notes)
    OUTPUT INSERTED.id
    VALUES (@ItemId, @Quantity, 0, CAST(GETDATE() AS DATE), @Notes);
END;
GO

CREATE OR ALTER PROCEDURE dbo.CreateAdjustmentBatchProduct
    @ItemId int,
    @Quantity float,
    @Notes nvarchar(max)
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO dbo.product_batches
    (menu_item_id, quantity, cost_per_unit, received_date, notes)
    OUTPUT INSERTED.id
    VALUES (@ItemId, @Quantity, 0, CAST(GETDATE() AS DATE), @Notes);
END;
GO

CREATE OR ALTER PROCEDURE dbo.WriteAdditionMovement
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
    (item_type, item_id, batch_id, movement_type, quantity, remarks, user_id)
    VALUES (@Type, @ItemId, @BatchId, 'Adjustment', @Quantity, @Remarks, @UserId);
END;
GO

CREATE OR ALTER PROCEDURE dbo.DeductAdjustmentBatchIngredient
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

CREATE OR ALTER PROCEDURE dbo.DeductAdjustmentBatchProduct
    @Quantity float,
    @BatchId int
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE dbo.product_batches
    SET quantity = quantity - @Quantity
    WHERE id = @BatchId;
END;
GO

CREATE OR ALTER PROCEDURE dbo.WriteAdjustmentMovement
    @Type nvarchar(20),
    @ItemId int,
    @BatchId int,
    @Movement nvarchar(30),
    @Quantity decimal(12,3),
    @Remarks nvarchar(500),
    @UserId int
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO dbo.stock_movements
    (item_type, item_id, batch_id, movement_type, quantity, remarks, user_id)
    VALUES (@Type, @ItemId, @BatchId, @Movement, @Quantity, @Remarks, @UserId);
END;
GO

CREATE OR ALTER PROCEDURE dbo.DeductRecordedStockIngredient
    @Quantity float,
    @ItemId int
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE dbo.ingredients
    SET stock_qty = stock_qty - @Quantity
    WHERE id = @ItemId;
END;
GO

CREATE OR ALTER PROCEDURE dbo.DeductRecordedStockProduct
    @Quantity float,
    @ItemId int
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE dbo.menu_items
    SET stock_qty = stock_qty - @Quantity
    WHERE id = @ItemId;
END;
GO

CREATE OR ALTER PROCEDURE dbo.SetReconciledStockIngredient
    @Quantity decimal(18,3),
    @ItemId int
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE dbo.ingredients
    SET stock_qty = CAST(@Quantity AS decimal(18,3))
    WHERE id = @ItemId;
END;
GO

CREATE OR ALTER PROCEDURE dbo.SetReconciledStockProduct
    @Quantity decimal(18,3),
    @ItemId int
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE dbo.menu_items
    SET stock_qty = CAST(@Quantity AS decimal(18,3))
    WHERE id = @ItemId;
END;
GO

CREATE OR ALTER PROCEDURE dbo.WriteStockReconciliation
    @Type nvarchar(20),
    @ItemId int,
    @Previous decimal(18,3),
    @Corrected decimal(18,3),
    @Reason nvarchar(500),
    @UserId int
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO dbo.stock_reconciliations
    (item_type, item_id, previous_stock, corrected_stock, reason, user_id)
    VALUES (@Type, @ItemId, @Previous, @Corrected, @Reason, @UserId);
END;
GO
