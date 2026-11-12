/*
    SCRIPT APROVADO PARA EXECUÇÃO NO CONNECTA_2024.

    Este ficheiro não é uma migration do Laravel e nunca é executado
    automaticamente. Cria apenas tabelas próprias da nova aplicação.
    Não lê, altera ou remove qualquer objeto do sistema legado.
*/

SET XACT_ABORT ON;
BEGIN TRANSACTION;

IF OBJECT_ID(N'dbo.MonitorDePremios_Setores', N'U') IS NOT NULL
    THROW 51000, 'MonitorDePremios_Setores already exists. Review is required.', 1;

IF OBJECT_ID(N'dbo.MonitorDePremios_Regras_Premio', N'U') IS NOT NULL
    THROW 51001, 'MonitorDePremios_Regras_Premio already exists. Review is required.', 1;

IF OBJECT_ID(N'dbo.MonitorDePremios_Premios_Mensais', N'U') IS NOT NULL
    THROW 51002, 'MonitorDePremios_Premios_Mensais already exists. Review is required.', 1;

CREATE TABLE dbo.MonitorDePremios_Setores
(
    SectorId SMALLINT IDENTITY(1, 1) NOT NULL,
    SectorKey VARCHAR(32) NOT NULL,
    DisplayName NVARCHAR(80) NOT NULL,
    IsActive BIT NOT NULL CONSTRAINT DF_MonitorDePremios_Setores_IsActive DEFAULT (1),
    CreatedAt DATETIME2(0) NOT NULL CONSTRAINT DF_MonitorDePremios_Setores_CreatedAt DEFAULT (SYSUTCDATETIME()),
    CONSTRAINT PK_MonitorDePremios_Setores PRIMARY KEY (SectorId),
    CONSTRAINT UQ_MonitorDePremios_Setores_SectorKey UNIQUE (SectorKey)
);

CREATE TABLE dbo.MonitorDePremios_Regras_Premio
(
    PrizeRuleId BIGINT IDENTITY(1, 1) NOT NULL,
    SectorId SMALLINT NOT NULL,
    RuleVersion VARCHAR(64) NOT NULL,
    MinimumSquares INT NOT NULL,
    PrizePoolCents BIGINT NOT NULL,
    Currency CHAR(3) NOT NULL,
    IsActive BIT NOT NULL CONSTRAINT DF_MonitorDePremios_Regras_Premio_IsActive DEFAULT (1),
    CreatedAt DATETIME2(0) NOT NULL CONSTRAINT DF_MonitorDePremios_Regras_Premio_CreatedAt DEFAULT (SYSUTCDATETIME()),
    CONSTRAINT PK_MonitorDePremios_Regras_Premio PRIMARY KEY (PrizeRuleId),
    CONSTRAINT FK_MonitorDePremios_Regras_Premio_Setor
        FOREIGN KEY (SectorId) REFERENCES dbo.MonitorDePremios_Setores (SectorId),
    CONSTRAINT CK_MonitorDePremios_Regras_Premio_MinimumSquares CHECK (MinimumSquares >= 0),
    CONSTRAINT CK_MonitorDePremios_Regras_Premio_PrizePoolCents CHECK (PrizePoolCents >= 0),
    CONSTRAINT UQ_MonitorDePremios_Regras_Premio_VersionTier
        UNIQUE (SectorId, RuleVersion, MinimumSquares)
);

CREATE TABLE dbo.MonitorDePremios_Premios_Mensais
(
    MonthlyPrizeId BIGINT IDENTITY(1, 1) NOT NULL,
    SectorId SMALLINT NOT NULL,
    PrizeYear SMALLINT NOT NULL,
    PrizeMonth TINYINT NOT NULL,
    ProductionSquares DECIMAL(18, 2) NOT NULL,
    TotalHours DECIMAL(12, 2) NOT NULL,
    AverageHoursPerEmployee DECIMAL(12, 2) NOT NULL,
    AppliedRuleVersion VARCHAR(64) NOT NULL,
    AppliedPrizeRuleId BIGINT NULL,
    PrizePoolCents BIGINT NOT NULL,
    AveragePrizeCents BIGINT NOT NULL,
    Currency CHAR(3) NOT NULL,
    CalculatedAt DATETIME2(0) NOT NULL CONSTRAINT DF_MonitorDePremios_Premios_Mensais_CalculatedAt DEFAULT (SYSUTCDATETIME()),
    CONSTRAINT PK_MonitorDePremios_Premios_Mensais PRIMARY KEY (MonthlyPrizeId),
    CONSTRAINT FK_MonitorDePremios_Premios_Mensais_Setor
        FOREIGN KEY (SectorId) REFERENCES dbo.MonitorDePremios_Setores (SectorId),
    CONSTRAINT FK_MonitorDePremios_Premios_Mensais_AppliedRule
        FOREIGN KEY (AppliedPrizeRuleId) REFERENCES dbo.MonitorDePremios_Regras_Premio (PrizeRuleId),
    CONSTRAINT CK_MonitorDePremios_Premios_Mensais_Year CHECK (PrizeYear BETWEEN 2000 AND 2100),
    CONSTRAINT CK_MonitorDePremios_Premios_Mensais_Month CHECK (PrizeMonth BETWEEN 1 AND 12),
    CONSTRAINT CK_MonitorDePremios_Premios_Mensais_Production CHECK (ProductionSquares >= 0),
    CONSTRAINT CK_MonitorDePremios_Premios_Mensais_TotalHours CHECK (TotalHours > 0),
    CONSTRAINT CK_MonitorDePremios_Premios_Mensais_AverageHours CHECK (
        AverageHoursPerEmployee >= 0 AND AverageHoursPerEmployee <= TotalHours
    ),
    CONSTRAINT CK_MonitorDePremios_Premios_Mensais_Pool CHECK (PrizePoolCents >= 0),
    CONSTRAINT CK_MonitorDePremios_Premios_Mensais_AveragePrize CHECK (AveragePrizeCents >= 0),
    CONSTRAINT UQ_MonitorDePremios_Premios_Mensais_SectorPeriod
        UNIQUE (SectorId, PrizeYear, PrizeMonth)
);

INSERT INTO dbo.MonitorDePremios_Setores (SectorKey, DisplayName)
VALUES ('pvc', N'PVC'), ('aluminio', N'Alumínio');

DECLARE @PvcSectorId SMALLINT = (
    SELECT SectorId FROM dbo.MonitorDePremios_Setores WHERE SectorKey = 'pvc'
);
DECLARE @AluminiumSectorId SMALLINT = (
    SELECT SectorId FROM dbo.MonitorDePremios_Setores WHERE SectorKey = 'aluminio'
);

INSERT INTO dbo.MonitorDePremios_Regras_Premio
    (SectorId, RuleVersion, MinimumSquares, PrizePoolCents, Currency)
VALUES
    (@PvcSectorId, 'premio-producao-v1', 2300, 80000, 'EUR'),
    (@PvcSectorId, 'premio-producao-v1', 2530, 120000, 'EUR'),
    (@PvcSectorId, 'premio-producao-v1', 2760, 180000, 'EUR'),
    (@PvcSectorId, 'premio-producao-v1', 2990, 270000, 'EUR'),
    (@PvcSectorId, 'premio-producao-v1', 3220, 400000, 'EUR'),
    (@PvcSectorId, 'premio-producao-v1', 3450, 570000, 'EUR'),
    (@PvcSectorId, 'premio-producao-v1', 3680, 800000, 'EUR'),
    (@AluminiumSectorId, 'premio-producao-v1', 2300, 80000, 'EUR'),
    (@AluminiumSectorId, 'premio-producao-v1', 2530, 120000, 'EUR'),
    (@AluminiumSectorId, 'premio-producao-v1', 2760, 180000, 'EUR'),
    (@AluminiumSectorId, 'premio-producao-v1', 2990, 270000, 'EUR'),
    (@AluminiumSectorId, 'premio-producao-v1', 3220, 400000, 'EUR'),
    (@AluminiumSectorId, 'premio-producao-v1', 3450, 570000, 'EUR'),
    (@AluminiumSectorId, 'premio-producao-v1', 3680, 800000, 'EUR');

COMMIT TRANSACTION;
