CREATE OR ALTER PROCEDURE dbo.WriteAuditEvent
    @UserId int,
    @Username nvarchar(100),
    @Action nvarchar(100),
    @Details nvarchar(max)
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO dbo.audit_log (user_id, username, action, details, created_at)
    VALUES (@UserId, @Username, @Action, @Details, SYSDATETIME());
END;
GO

CREATE OR ALTER PROCEDURE dbo.CreateStaffAccount
    @Username nvarchar(100),
    @Password nvarchar(255),
    @Role nvarchar(30)
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO dbo.users (username, password, role)
    OUTPUT INSERTED.id
    VALUES (@Username, @Password, @Role);
END;
GO

CREATE OR ALTER PROCEDURE dbo.WriteStaffCreationAudit
    @UserId int,
    @Username nvarchar(100),
    @Details nvarchar(max)
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO dbo.audit_log (user_id, username, action, details, created_at)
    VALUES (@UserId, @Username, 'User Created', @Details, SYSDATETIME());
END;
GO

CREATE OR ALTER PROCEDURE dbo.ResetStaffPassword
    @Password nvarchar(255),
    @UserId int
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE dbo.users
    SET password = @Password
    OUTPUT INSERTED.username
    WHERE id = @UserId AND role IN ('Cashier', 'Manager');
END;
GO

CREATE OR ALTER PROCEDURE dbo.SetStaffAccountStatus
    @Active bit,
    @UserId int,
    @PreviousComparison bit
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE dbo.users
    SET is_active = @Active, session_version = session_version + 1
    OUTPUT INSERTED.username
    WHERE id = @UserId AND role IN ('Cashier', 'Manager') AND is_active <> @PreviousComparison;
END;
GO

CREATE OR ALTER PROCEDURE dbo.SetStaffAccountRole
    @Role nvarchar(30),
    @UserId int,
    @PreviousRole nvarchar(30)
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE dbo.users
    SET role = @Role, session_version = session_version + 1
    OUTPUT INSERTED.username, DELETED.role AS previous_role
    WHERE id = @UserId AND role = @PreviousRole AND role IN ('Cashier', 'Manager');
END;
GO

CREATE OR ALTER PROCEDURE dbo.RehashLoginPassword
    @Password nvarchar(255),
    @UserId int,
    @PreviousHash nvarchar(255)
AS
BEGIN
    SET NOCOUNT OFF;

    UPDATE dbo.users
    SET password = @Password
    WHERE id = @UserId AND password COLLATE Latin1_General_100_BIN2 = @PreviousHash;
END;
GO
