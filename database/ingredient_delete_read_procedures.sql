CREATE OR ALTER PROCEDURE dbo.GetIngredientForDeletion
    @ItemId int
AS
BEGIN
    SET NOCOUNT ON;

    SELECT name, stock_qty
    FROM dbo.ingredients WITH (UPDLOCK, HOLDLOCK)
    WHERE id = @ItemId;
END;
GO

CREATE OR ALTER PROCEDURE dbo.CountIngredientBatchReferences
    @ItemId int
AS
BEGIN
    SET NOCOUNT ON;

    SELECT COUNT(*)
    FROM dbo.inventory_batches WITH (UPDLOCK, HOLDLOCK)
    WHERE ingredient_id = @ItemId;
END;
GO

CREATE OR ALTER PROCEDURE dbo.CountIngredientUsageReferences
    @ItemId int
AS
BEGIN
    SET NOCOUNT ON;

    SELECT COUNT(*)
    FROM dbo.ingredient_usage WITH (UPDLOCK, HOLDLOCK)
    WHERE ingredient_id = @ItemId;
END;
GO

CREATE OR ALTER PROCEDURE dbo.CountIngredientMovementReferences
    @ItemId int
AS
BEGIN
    SET NOCOUNT ON;

    SELECT COUNT(*)
    FROM dbo.stock_movements WITH (UPDLOCK, HOLDLOCK)
    WHERE item_id = @ItemId AND item_type = 'Ingredient';
END;
GO
