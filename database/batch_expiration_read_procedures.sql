CREATE OR ALTER PROCEDURE dbo.GetBatchForExpirationCorrection
    @BatchId int,
    @IngredientId int
AS
BEGIN
    SET NOCOUNT ON;

    SELECT expiration_date, CONVERT(char(10), TRY_CONVERT(date, received_date, 23), 23) AS received_date
    FROM dbo.inventory_batches WITH (UPDLOCK, HOLDLOCK)
    WHERE id = @BatchId AND ingredient_id = @IngredientId AND quantity > 0;
END;
GO
