/*
    SCRIPT APROVADO E APLICADO EM CONNECTA_2024 EM 2026-08-31.

    Cria somente a tabela própria de utilizadores administrativos no
    CONNECTA_2024. Não cria contas, não guarda passwords em texto simples e
    não altera tabelas industriais, do legado ou de outras aplicações.
*/

SET XACT_ABORT ON;
BEGIN TRANSACTION;

IF OBJECT_ID(N'dbo.MonitorDePremios_Usuarios', N'U') IS NOT NULL
    THROW 51020, 'MonitorDePremios_Usuarios already exists. Review is required.', 1;

CREATE TABLE dbo.MonitorDePremios_Usuarios
(
    UserId BIGINT IDENTITY(1, 1) NOT NULL,
    Username VARCHAR(64) NOT NULL,
    DisplayName NVARCHAR(128) NOT NULL,
    PasswordHash VARCHAR(255) NOT NULL,
    UserRole VARCHAR(32) NOT NULL CONSTRAINT DF_MonitorDePremios_Usuarios_UserRole DEFAULT ('administrator'),
    IsActive BIT NOT NULL CONSTRAINT DF_MonitorDePremios_Usuarios_IsActive DEFAULT (1),
    LastLoginAt DATETIME2(0) NULL,
    CreatedAt DATETIME2(0) NOT NULL CONSTRAINT DF_MonitorDePremios_Usuarios_CreatedAt DEFAULT (SYSUTCDATETIME()),
    UpdatedAt DATETIME2(0) NOT NULL CONSTRAINT DF_MonitorDePremios_Usuarios_UpdatedAt DEFAULT (SYSUTCDATETIME()),
    CONSTRAINT PK_MonitorDePremios_Usuarios PRIMARY KEY (UserId),
    CONSTRAINT UQ_MonitorDePremios_Usuarios_Username UNIQUE (Username),
    CONSTRAINT CK_MonitorDePremios_Usuarios_Username CHECK (
        Username NOT LIKE '%[^A-Za-z0-9._-]%'
        AND LEN(Username) BETWEEN 3 AND 64
    ),
    CONSTRAINT CK_MonitorDePremios_Usuarios_Role CHECK (
        UserRole IN ('administrator')
    )
);

CREATE INDEX IX_MonitorDePremios_Usuarios_Active
    ON dbo.MonitorDePremios_Usuarios (Username, IsActive);

COMMIT TRANSACTION;
