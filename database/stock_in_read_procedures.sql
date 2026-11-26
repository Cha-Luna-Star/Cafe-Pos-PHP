CREATE OR ALTER PROCEDURE dbo.GetStockInIngredient
    @ItemId int
AS
BEGIN
    SET NOCOUNT ON;

    SELECT name
    FROM dbo.ingredients WITH (UPDLOCK, HOLDLOCK)
    WHERE id = @ItemId AND is_archived = 0;
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetStockInProduct
    @ItemId int
AS
BEGIN
    SET NOCOUNT ON;

    SELECT name
    FROM dbo.menu_items WITH (UPDLOCK, HOLDLOCK)
    WHERE id = @ItemId AND is_archived = 0;
END;
GO
