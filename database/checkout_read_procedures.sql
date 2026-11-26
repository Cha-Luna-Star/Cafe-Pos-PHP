CREATE OR ALTER PROCEDURE dbo.GetCheckoutSupplies
AS
BEGIN
    SET NOCOUNT ON;

    SELECT id, name
    FROM dbo.ingredients
    WHERE name IN ('Cups (Hot)', 'Cups (Cold)', 'Ice');
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetCheckoutMenuItem
    @Id int
AS
BEGIN
    SET NOCOUNT ON;

    SELECT id, name, price, stock_qty, has_sizes, has_temp_option
    FROM dbo.menu_items WITH (UPDLOCK, HOLDLOCK)
    WHERE id = @Id
      AND available = 1
      AND is_archived = 0;
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetCheckoutSizeModifier
    @Name nvarchar(max), @ItemId int, @PreferredId int
AS
BEGIN
    SET NOCOUNT ON;

    SELECT TOP 1 price_adjustment
    FROM dbo.size_modifiers
    WHERE name = @Name
    AND (menu_item_id = @ItemId OR menu_item_id IS NULL)
    ORDER BY CASE WHEN menu_item_id = @PreferredId THEN 0 ELSE 1 END;
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetCheckoutRecipe
    @ItemId int
AS
BEGIN
    SET NOCOUNT ON;

    SELECT ingredient_id, quantity_needed
    FROM dbo.recipe_items
    WHERE menu_item_id = @ItemId;
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetCheckoutIngredient
    @Id int
AS
BEGIN
    SET NOCOUNT ON;

    SELECT name, unit, stock_qty
    FROM dbo.ingredients WITH (UPDLOCK, HOLDLOCK)
    WHERE id = @Id AND is_archived = 0;
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetCheckoutIngredientStock
    @Id int, @Today date
AS
BEGIN
    SET NOCOUNT ON;

    SELECT COALESCE(SUM(quantity), 0)
    FROM dbo.inventory_batches WITH (UPDLOCK, HOLDLOCK)
    WHERE ingredient_id = @Id
    AND quantity > 0
    AND (
        NULLIF(LTRIM(RTRIM(expiration_date)), '') IS NULL OR (
            DATALENGTH(expiration_date) = 20
            AND expiration_date = CONVERT(char(10), TRY_CONVERT(date, expiration_date, 23), 23)
            AND TRY_CONVERT(date, expiration_date, 23) >= CONVERT(date, @Today, 23)
        )
    );
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetCheckoutProductBatches
    @Id int, @Today date
AS
BEGIN
    SET NOCOUNT ON;

    SELECT id, quantity
    FROM dbo.product_batches WITH (UPDLOCK, HOLDLOCK)
    WHERE menu_item_id = @Id
    AND quantity > 0
    AND (
        expiration_date IS NULL
        OR expiration_date >= CONVERT(date, @Today, 23)
    )
    ORDER BY
        CASE WHEN expiration_date IS NULL THEN 1 ELSE 0 END,
        expiration_date ASC,
        received_date ASC,
        id ASC;
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetCheckoutIngredientBatches
    @Id int, @Today date
AS
BEGIN
    SET NOCOUNT ON;

    SELECT id, quantity
    FROM dbo.inventory_batches WITH (UPDLOCK, HOLDLOCK)
    WHERE ingredient_id = @Id AND quantity > 0
        AND (
        NULLIF(LTRIM(RTRIM(expiration_date)), '') IS NULL OR (
            DATALENGTH(expiration_date) = 20
            AND expiration_date = CONVERT(char(10), TRY_CONVERT(date, expiration_date, 23), 23)
            AND TRY_CONVERT(date, expiration_date, 23) >= CONVERT(date, @Today, 23)
        )
    )
    ORDER BY TRY_CONVERT(date, received_date, 23) ASC, id ASC;
END;
GO
