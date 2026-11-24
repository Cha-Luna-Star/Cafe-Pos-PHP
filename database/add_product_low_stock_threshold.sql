IF COL_LENGTH('dbo.menu_items', 'low_stock_threshold') IS NULL
BEGIN
    ALTER TABLE dbo.menu_items ADD low_stock_threshold int NOT NULL
        CONSTRAINT DF_menu_items_low_stock_threshold DEFAULT (10)
        CONSTRAINT CK_menu_items_low_stock_threshold CHECK (low_stock_threshold >= 0);
END;
