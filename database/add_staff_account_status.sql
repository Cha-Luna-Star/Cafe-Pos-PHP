IF COL_LENGTH('dbo.users', 'is_active') IS NULL
   AND COL_LENGTH('dbo.users', 'session_version') IS NULL
BEGIN
    ALTER TABLE dbo.users ADD
        is_active bit NOT NULL CONSTRAINT DF_users_is_active DEFAULT (1),
        session_version bigint NOT NULL CONSTRAINT DF_users_session_version DEFAULT (0);
END
ELSE IF COL_LENGTH('dbo.users', 'is_active') IS NULL
     OR COL_LENGTH('dbo.users', 'session_version') IS NULL
BEGIN
    THROW 50001, 'Staff account status schema is incomplete.', 1;
END;
