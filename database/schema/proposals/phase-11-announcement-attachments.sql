/*
    SCRIPT APROVADO E APLICADO EM CONNECTA_2024 EM 2026-09-25.

    Este ficheiro não é uma migration do Laravel e nunca é executado
    automaticamente. Cria somente a tabela própria de anexos dos comunicados.
    Não altera dbo.MonitorDePremios_Comunicados nem qualquer objeto do sistema
    legado ou industrial.

    Regras:
    - um anexo por comunicado (UQ em AnnouncementId);
    - fase 1 aceita apenas imagens JPEG, PNG e WEBP (sem SVG);
    - o ficheiro fica em storage/app/private/announcements com o nome
      StoredName; a base de dados guarda apenas os metadados;
    - a remoção de um comunicado com anexo tem de apagar primeiro o anexo
      (sem ON DELETE CASCADE, para a aplicação remover também o ficheiro e
      registar a auditoria).

    Para a fase 2 (PDF), a restrição CK_..._MimeType terá de ser revista.
*/

SET XACT_ABORT ON;
BEGIN TRANSACTION;

IF OBJECT_ID(N'dbo.MonitorDePremios_Comunicados', N'U') IS NULL
    THROW 51030, 'MonitorDePremios_Comunicados does not exist. Apply phase 6 first.', 1;

IF OBJECT_ID(N'dbo.MonitorDePremios_Comunicados_Anexos', N'U') IS NOT NULL
    THROW 51031, 'MonitorDePremios_Comunicados_Anexos already exists. Review is required.', 1;

CREATE TABLE dbo.MonitorDePremios_Comunicados_Anexos
(
    AttachmentId BIGINT IDENTITY(1, 1) NOT NULL,
    AnnouncementId BIGINT NOT NULL,
    StoredName VARCHAR(64) NOT NULL,
    OriginalName NVARCHAR(255) NOT NULL,
    MimeType VARCHAR(64) NOT NULL,
    SizeBytes BIGINT NOT NULL,
    Sha256 CHAR(64) NOT NULL,
    Width INT NULL,
    Height INT NULL,
    CreatedAt DATETIME2(0) NOT NULL CONSTRAINT DF_MonitorDePremios_Comunicados_Anexos_CreatedAt DEFAULT (SYSUTCDATETIME()),
    CONSTRAINT PK_MonitorDePremios_Comunicados_Anexos PRIMARY KEY (AttachmentId),
    CONSTRAINT FK_MonitorDePremios_Comunicados_Anexos_Comunicado
        FOREIGN KEY (AnnouncementId) REFERENCES dbo.MonitorDePremios_Comunicados (AnnouncementId),
    CONSTRAINT UQ_MonitorDePremios_Comunicados_Anexos_Announcement UNIQUE (AnnouncementId),
    CONSTRAINT UQ_MonitorDePremios_Comunicados_Anexos_StoredName UNIQUE (StoredName),
    CONSTRAINT CK_MonitorDePremios_Comunicados_Anexos_StoredName CHECK (
        LEN(StoredName) > 0
        AND StoredName NOT LIKE '%[/\:]%'
        AND StoredName NOT LIKE '%..%'
    ),
    CONSTRAINT CK_MonitorDePremios_Comunicados_Anexos_OriginalName CHECK (LEN(OriginalName) > 0),
    CONSTRAINT CK_MonitorDePremios_Comunicados_Anexos_MimeType CHECK (
        MimeType IN ('image/jpeg', 'image/png', 'image/webp')
    ),
    CONSTRAINT CK_MonitorDePremios_Comunicados_Anexos_SizeBytes CHECK (SizeBytes > 0),
    CONSTRAINT CK_MonitorDePremios_Comunicados_Anexos_Sha256 CHECK (
        LEN(Sha256) = 64 AND Sha256 NOT LIKE '%[^0-9a-fA-F]%'
    ),
    CONSTRAINT CK_MonitorDePremios_Comunicados_Anexos_Dimensions CHECK (
        (Width IS NULL AND Height IS NULL)
        OR (Width > 0 AND Height > 0)
    )
);

COMMIT TRANSACTION;
