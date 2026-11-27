CREATE OR ALTER PROCEDURE dbo.ArchiveIngredient
    @ItemId int
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE dbo.ingredients
    SET is_archived = 1
    WHERE id = @ItemId;
END;
GO

CREATE OR ALTER PROCEDURE dbo.RestoreIngredient
    @ItemId int
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE dbo.ingredients
    SET is_archived = 0
    OUTPUT INSERTED.name
    WHERE id = @ItemId AND is_archived = 1;
END;
GO

CREATE OR ALTER PROCEDURE dbo.DeleteIngredient
    @ItemId int
AS
BEGIN
    SET NOCOUNT ON;

    DELETE FROM dbo.ingredients
    WHERE id = @ItemId;
END;
GO

CREATE OR ALTER PROCEDURE dbo.UpdateIngredient
    @Name nvarchar(150),
    @Threshold float,
    @Cost float,
    @ItemId int
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE dbo.ingredients
    SET name = @Name,
        low_stock_threshold = @Threshold,
        cost_per_unit = @Cost
    WHERE id = @ItemId;
END;
GO

CREATE OR ALTER PROCEDURE dbo.CreateIngredient
    @Name nvarchar(150),
    @Unit nvarchar(50),
    @Threshold float,
    @Cost float
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO dbo.ingredients
    (
        name,
        unit,
        stock_qty,
        low_stock_threshold,
        cost_per_unit,
        initial_quantity
    )
    OUTPUT INSERTED.id
    VALUES (@Name, @Unit, 0, @Threshold, @Cost, 0);
END;
GO

CREATE OR ALTER PROCEDURE dbo.SetMenuItemArchiveStatus
    @Archived int,
    @ItemId int
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE dbo.menu_items
    SET is_archived = @Archived
    OUTPUT INSERTED.name, DELETED.is_archived AS previous_archived
    WHERE id = @ItemId;
END;
GO

CREATE OR ALTER PROCEDURE dbo.UpdateIngredientBatchExpiration
    @Expiration nvarchar(50),
    @BatchId int,
    @ItemId int
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE dbo.inventory_batches SET expiration_date = @Expiration WHERE id = @BatchId AND ingredient_id = @ItemId;
END;
GO
