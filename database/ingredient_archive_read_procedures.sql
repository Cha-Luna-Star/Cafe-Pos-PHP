CREATE OR ALTER PROCEDURE dbo.CountRemainingIngredientBatches
    @ItemId int
AS
BEGIN
    SET NOCOUNT ON;

        SELECT COUNT(*)
        FROM dbo.inventory_batches WITH (UPDLOCK, HOLDLOCK)
        WHERE ingredient_id = @ItemId AND quantity > 0;
END;
GO

CREATE OR ALTER PROCEDURE dbo.CountIngredientRecipeReferences
    @ItemId int
AS
BEGIN
    SET NOCOUNT ON;

        SELECT COUNT(*)
        FROM dbo.recipe_items WITH (UPDLOCK, HOLDLOCK)
        WHERE ingredient_id = @ItemId;
END;
GO

CREATE OR ALTER PROCEDURE dbo.FindIngredientPendingOrderUsage
    @ItemId int
AS
BEGIN
    SET NOCOUNT ON;

        SELECT TOP (1) 1
        FROM dbo.ingredient_usage iu
        JOIN dbo.orders o ON o.id = iu.order_id
        WHERE iu.ingredient_id = @ItemId AND o.status = 'Pending';
END;
GO
