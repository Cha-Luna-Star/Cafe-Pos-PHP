CREATE OR ALTER PROCEDURE dbo.GetLoginAccount
    @Username nvarchar(max)
AS
BEGIN
    SET NOCOUNT ON;

    IF COL_LENGTH('dbo.users', 'is_active') IS NULL OR COL_LENGTH('dbo.users', 'session_version') IS NULL
    BEGIN
        SELECT id, username, password, role, 0 AS session_version
        FROM dbo.users WHERE username = @Username;
        RETURN;
    END;

    SELECT id, username, password, role, session_version
    FROM dbo.users WHERE username = @Username AND is_active = 1;
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetSessionAccount
    @UserId int
AS
BEGIN
    SET NOCOUNT ON;

    IF COL_LENGTH('dbo.users', 'is_active') IS NULL OR COL_LENGTH('dbo.users', 'session_version') IS NULL
    BEGIN
        SELECT password, 0 AS session_version
        FROM dbo.users WHERE id = @UserId;
        RETURN;
    END;

    SELECT password, session_version
    FROM dbo.users WHERE id = @UserId AND is_active = 1;
END;
GO

CREATE OR ALTER PROCEDURE dbo.GetCurrentAccountPassword
    @UserId int
AS
BEGIN
    SET NOCOUNT ON;

    SELECT password FROM dbo.users WHERE id = @UserId;
END;
GO
