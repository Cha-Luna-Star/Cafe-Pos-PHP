CREATE OR ALTER PROCEDURE dbo.GetOrderHistory
AS
BEGIN
    SET NOCOUNT ON;

    SELECT
        o.id,
        o.order_type,
        o.status,
        o.total,
        o.created_at,
        o.payment_method,
        u.username
    FROM dbo.orders o
    LEFT JOIN dbo.users u ON o.created_by = u.id
    ORDER BY o.created_at DESC, o.id DESC;
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetOrderReceipt
    @OrderId int
AS
BEGIN
    SET NOCOUNT ON;

    SELECT
        o.id,
        o.order_type,
        o.status,
        o.total,
        o.created_at,
        o.payment_method,
        o.amount_tendered,
        o.change_amount,
        o.tax,
        o.ref_number,
        u.username
    FROM dbo.orders o
    LEFT JOIN dbo.users u ON o.created_by = u.id
    WHERE o.id = @OrderId;
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetOrderReceiptItems
    @OrderId int
AS
BEGIN
    SET NOCOUNT ON;

    SELECT
        oi.quantity,
        oi.size,
        oi.subtotal,
        m.name
    FROM dbo.order_items oi
    LEFT JOIN dbo.menu_items m ON oi.menu_item_id = m.id
    WHERE oi.order_id = @OrderId
    ORDER BY oi.id;
END;
GO
