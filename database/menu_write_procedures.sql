CREATE OR ALTER PROCEDURE dbo.UpdateMenuItem
    @Name nvarchar(150),
    @Price float,
    @CategoryId int,
    @Available int,
    @HasTemperature int,
    @HasSizes int,
    @ItemId int
AS
BEGIN
    SET NOCOUNT OFF;

    UPDATE dbo.menu_items
            SET name = @Name,
                price = @Price,
                category_id = @CategoryId,
                available = @Available,
                has_temp_option = @HasTemperature,
                has_sizes = @HasSizes
            WHERE id = @ItemId;
END;
GO

CREATE OR ALTER PROCEDURE dbo.UpdateMenuItemWithThreshold
    @Name nvarchar(150),
    @Price float,
    @CategoryId int,
    @Available int,
    @HasTemperature int,
    @HasSizes int,
    @Threshold int,
    @ItemId int
AS
BEGIN
    SET NOCOUNT OFF;

    EXEC sys.sp_executesql N'UPDATE dbo.menu_items
            SET name = @Name,
                price = @Price,
                category_id = @CategoryId,
                available = @Available,
                has_temp_option = @HasTemperature,
                has_sizes = @HasSizes, low_stock_threshold = @Threshold
            WHERE id = @ItemId;', N'@Name nvarchar(150), @Price float, @CategoryId int, @Available int, @HasTemperature int, @HasSizes int, @Threshold int, @ItemId int', @Name, @Price, @CategoryId, @Available, @HasTemperature, @HasSizes, @Threshold, @ItemId;
END;
GO

CREATE OR ALTER PROCEDURE dbo.CreateMenuItem
    @Name nvarchar(150),
    @Price float,
    @CategoryId int,
    @Available int,
    @HasTemperature int,
    @HasSizes int
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO dbo.menu_items
            (name, price, category_id, available, stock_qty,
             discount, has_temp_option, has_sizes, is_archived)
            OUTPUT INSERTED.id
            VALUES (@Name, @Price, @CategoryId, @Available, 0, 0, @HasTemperature, @HasSizes, 0);
END;
GO

CREATE OR ALTER PROCEDURE dbo.CreateMenuItemWithThreshold
    @Name nvarchar(150),
    @Price float,
    @CategoryId int,
    @Available int,
    @HasTemperature int,
    @HasSizes int,
    @Threshold int
AS
BEGIN
    SET NOCOUNT ON;

    EXEC sys.sp_executesql N'INSERT INTO dbo.menu_items
            (name, price, category_id, available, stock_qty,
             discount, has_temp_option, has_sizes, is_archived, low_stock_threshold)
            OUTPUT INSERTED.id
            VALUES (@Name, @Price, @CategoryId, @Available, 0, 0, @HasTemperature, @HasSizes, 0, @Threshold);', N'@Name nvarchar(150), @Price float, @CategoryId int, @Available int, @HasTemperature int, @HasSizes int, @Threshold int', @Name, @Price, @CategoryId, @Available, @HasTemperature, @HasSizes, @Threshold;
END;
GO
