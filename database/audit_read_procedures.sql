CREATE OR ALTER PROCEDURE dbo.GetAuditActions
AS
BEGIN
    SET NOCOUNT ON;

    SELECT DISTINCT action
    FROM dbo.audit_log
    ORDER BY action;
END;
GO

CREATE OR ALTER PROCEDURE dbo.CountAuditEntries
    @Search nvarchar(400),
    @Action nvarchar(400),
    @Start datetime2(7),
    @End datetime2(7)
AS
BEGIN
    SET NOCOUNT ON;

    SELECT COUNT_BIG(*)
    FROM dbo.audit_log
    WHERE (DATALENGTH(@Search) = 0 OR CHARINDEX(@Search, username) > 0 OR CHARINDEX(@Search, details) > 0)
      AND (DATALENGTH(@Action) = 0 OR action = @Action)
      AND (@Start IS NULL OR created_at >= @Start)
      AND (@End IS NULL OR created_at <= @End);
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetAuditEntries
    @Search nvarchar(400),
    @Action nvarchar(400),
    @Start datetime2(7),
    @End datetime2(7),
    @Offset bigint,
    @PageSize int
AS
BEGIN
    SET NOCOUNT ON;

    SELECT id, user_id, username, action, details,
        CONVERT(char(19), created_at, 120) AS event_time
    FROM dbo.audit_log
    WHERE (DATALENGTH(@Search) = 0 OR CHARINDEX(@Search, username) > 0 OR CHARINDEX(@Search, details) > 0)
      AND (DATALENGTH(@Action) = 0 OR action = @Action)
      AND (@Start IS NULL OR created_at >= @Start)
      AND (@End IS NULL OR created_at <= @End)
    ORDER BY created_at DESC, id DESC
    OFFSET @Offset ROWS FETCH NEXT @PageSize ROWS ONLY;
END;
GO
