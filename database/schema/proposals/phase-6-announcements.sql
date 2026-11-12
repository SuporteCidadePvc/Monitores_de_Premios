/*
    SCRIPT APROVADO PARA EXECUÇÃO NO CONNECTA_2024.

    Este ficheiro não é uma migration do Laravel e nunca é executado
    automaticamente. Cria somente tabelas próprias de comunicados e auditoria.
    Não lê, altera ou remove objetos do sistema legado ou industrial.
*/

SET XACT_ABORT ON;
BEGIN TRANSACTION;

IF OBJECT_ID(N'dbo.MonitorDePremios_Comunicados', N'U') IS NOT NULL
    THROW 51010, 'MonitorDePremios_Comunicados already exists. Review is required.', 1;

IF OBJECT_ID(N'dbo.MonitorDePremios_Auditoria', N'U') IS NOT NULL
    THROW 51011, 'MonitorDePremios_Auditoria already exists. Review is required.', 1;

CREATE TABLE dbo.MonitorDePremios_Comunicados
(
    AnnouncementId BIGINT IDENTITY(1, 1) NOT NULL,
    AnnouncementScope VARCHAR(16) NOT NULL,
    SectorId SMALLINT NULL,
    Title NVARCHAR(160) NOT NULL,
    Body NVARCHAR(MAX) NOT NULL,
    StartsAt DATETIME2(0) NOT NULL,
    EndsAt DATETIME2(0) NULL,
    IsActive BIT NOT NULL CONSTRAINT DF_MonitorDePremios_Comunicados_IsActive DEFAULT (1),
    CreatedAt DATETIME2(0) NOT NULL CONSTRAINT DF_MonitorDePremios_Comunicados_CreatedAt DEFAULT (SYSUTCDATETIME()),
    UpdatedAt DATETIME2(0) NOT NULL CONSTRAINT DF_MonitorDePremios_Comunicados_UpdatedAt DEFAULT (SYSUTCDATETIME()),
    CONSTRAINT PK_MonitorDePremios_Comunicados PRIMARY KEY (AnnouncementId),
    CONSTRAINT FK_MonitorDePremios_Comunicados_Setor
        FOREIGN KEY (SectorId) REFERENCES dbo.MonitorDePremios_Setores (SectorId),
    CONSTRAINT CK_MonitorDePremios_Comunicados_Scope CHECK (
        (AnnouncementScope = 'global' AND SectorId IS NULL)
        OR (AnnouncementScope IN ('pvc', 'aluminio') AND SectorId IS NOT NULL)
    ),
    CONSTRAINT CK_MonitorDePremios_Comunicados_Window CHECK (
        EndsAt IS NULL OR EndsAt > StartsAt
    )
);

CREATE INDEX IX_MonitorDePremios_Comunicados_ActiveWindow
    ON dbo.MonitorDePremios_Comunicados (IsActive, AnnouncementScope, SectorId, StartsAt, EndsAt);

CREATE TABLE dbo.MonitorDePremios_Auditoria
(
    AuditId BIGINT IDENTITY(1, 1) NOT NULL,
    OccurredAt DATETIME2(0) NOT NULL CONSTRAINT DF_MonitorDePremios_Auditoria_OccurredAt DEFAULT (SYSUTCDATETIME()),
    ActionName VARCHAR(64) NOT NULL,
    EntityType VARCHAR(64) NOT NULL,
    EntityId BIGINT NULL,
    SectorId SMALLINT NULL,
    ActorLabel NVARCHAR(128) NULL,
    RequestId UNIQUEIDENTIFIER NULL,
    MetadataJson NVARCHAR(MAX) NULL,
    CONSTRAINT PK_MonitorDePremios_Auditoria PRIMARY KEY (AuditId),
    CONSTRAINT FK_MonitorDePremios_Auditoria_Setor
        FOREIGN KEY (SectorId) REFERENCES dbo.MonitorDePremios_Setores (SectorId),
    CONSTRAINT CK_MonitorDePremios_Auditoria_MetadataJson CHECK (
        MetadataJson IS NULL OR ISJSON(MetadataJson) = 1
    )
);

CREATE INDEX IX_MonitorDePremios_Auditoria_Entity
    ON dbo.MonitorDePremios_Auditoria (EntityType, EntityId, OccurredAt DESC);

CREATE INDEX IX_MonitorDePremios_Auditoria_Sector
    ON dbo.MonitorDePremios_Auditoria (SectorId, OccurredAt DESC);

COMMIT TRANSACTION;
