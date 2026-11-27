CREATE OR ALTER PROCEDURE dbo.FindDuplicateIngredient
    @Name nvarchar(100),
    @ItemId int
AS
BEGIN
    SET NOCOUNT ON;

    SELECT id
    FROM dbo.ingredients WITH (UPDLOCK, HOLDLOCK)
    WHERE LOWER(name) = LOWER(@Name) AND id <> @ItemId;
END;
GO

CREATE OR ALTER PROCEDURE dbo.FindIngredientForEdit
    @ItemId int
AS
BEGIN
    SET NOCOUNT ON;

    SELECT id
    FROM dbo.ingredients WITH (UPDLOCK, HOLDLOCK)
    WHERE id = @ItemId;
END;
GO
