CREATE OR ALTER PROCEDURE dbo.HasStaffAccountStatus
AS
BEGIN
    SET NOCOUNT ON;
    SELECT CASE WHEN COL_LENGTH('dbo.users', 'is_active') IS NOT NULL
        AND COL_LENGTH('dbo.users', 'session_version') IS NOT NULL THEN 1 ELSE 0 END;
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetStaffAccounts
    @Search nvarchar(400),
    @Role nvarchar(30)
AS
BEGIN
    SET NOCOUNT ON;

    IF COL_LENGTH('dbo.users', 'is_active') IS NULL
        OR COL_LENGTH('dbo.users', 'session_version') IS NULL
    BEGIN
        SELECT id, username, role, 1 AS is_active
        FROM dbo.users
        WHERE (DATALENGTH(@Search) = 0 OR CHARINDEX(@Search, username) > 0)
          AND (DATALENGTH(@Role) = 0 OR role = @Role)
        ORDER BY username, id;
        RETURN;
    END;

    SELECT id, username, role, is_active
    FROM dbo.users
    WHERE (DATALENGTH(@Search) = 0 OR CHARINDEX(@Search, username) > 0)
      AND (DATALENGTH(@Role) = 0 OR role = @Role)
    ORDER BY username, id;
END;
GO
