[CmdletBinding(SupportsShouldProcess = $true, ConfirmImpact = 'High')]
param(
    [ValidateNotNullOrEmpty()]
    [string] $ProductionServer,

    [ValidateNotNullOrEmpty()]
    [string] $ProductionDatabase,

    [ValidateNotNullOrEmpty()]
    [string] $DashboardServer,

    [ValidateNotNullOrEmpty()]
    [string] $DashboardDatabase,

    [ValidateNotNullOrEmpty()]
    [string] $DriverName = 'ODBC Driver 18 for SQL Server',

    [string[]] $AdditionalDriverProperty = @(),

    [switch] $CopyCurrentUserDsns,

    [switch] $ReplaceExisting
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$identity = [Security.Principal.WindowsIdentity]::GetCurrent()
$principal = [Security.Principal.WindowsPrincipal]::new($identity)

if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw 'Execute este script numa consola PowerShell elevada (Executar como administrador).'
}

function Get-CurrentUserDsnConfiguration {
    param(
        [Parameter(Mandatory)]
        [string] $Name
    )

    $userDsn = Get-OdbcDsn -Name $Name -DsnType User -Platform '64-bit' -ErrorAction SilentlyContinue

    if ($null -eq $userDsn) {
        throw "O User DSN de 64 bits '$Name' não foi encontrado para o utilizador atual."
    }

    $driverName = [string] $userDsn.DriverName

    if ($driverName -eq '') {
        throw "Não foi possível identificar o driver do User DSN '$Name'."
    }

    $registryPath = "HKCU:\SOFTWARE\ODBC\ODBC.INI\$Name"
    $registryProperties = Get-ItemProperty -Path $registryPath -ErrorAction Stop
    $properties = foreach ($property in $registryProperties.PSObject.Properties) {
        if ($property.Name -in @('PSPath', 'PSParentPath', 'PSChildName', 'PSDrive', 'PSProvider', 'Driver')) {
            continue
        }

        if ($property.Name -match '(?i)(password|pwd|user|uid)') {
            continue
        }

        if ($property.Value -is [string]) {
            if ([string]::IsNullOrWhiteSpace($property.Value)) {
                continue
            }

            "$($property.Name)=$($property.Value)"
            continue
        }

        if ($property.Value -is [int] -or $property.Value -is [bool]) {
            "$($property.Name)=$($property.Value)"
        }
    }

    return [pscustomobject]@{
        Name = $Name
        DriverName = $driverName
        Properties = @($properties)
    }
}

if ($CopyCurrentUserDsns) {
    if ($ProductionServer -or $ProductionDatabase -or $DashboardServer -or $DashboardDatabase -or $AdditionalDriverProperty.Count -gt 0) {
        throw 'Não combine -CopyCurrentUserDsns com parâmetros de servidor, base ou propriedades adicionais.'
    }

    $dsns = @(
        Get-CurrentUserDsnConfiguration -Name 'BD_PREF'
        Get-CurrentUserDsnConfiguration -Name 'Conecta-Conexao'
    )
} else {
    if (-not $ProductionServer -or -not $ProductionDatabase -or -not $DashboardServer -or -not $DashboardDatabase) {
        throw 'Indique os servidores e as bases de ambas as fontes ou use -CopyCurrentUserDsns.'
    }

    $dsns = @(
        [pscustomobject]@{
            Name = 'BD_PREF'
            DriverName = $DriverName
            Properties = @("Server=$ProductionServer", "Database=$ProductionDatabase") + $AdditionalDriverProperty
        },
        [pscustomobject]@{
            Name = 'Conecta-Conexao'
            DriverName = $DriverName
            Properties = @("Server=$DashboardServer", "Database=$DashboardDatabase") + $AdditionalDriverProperty
        }
    )
}

foreach ($driver in $dsns.DriverName | Select-Object -Unique) {
    if (-not (Get-OdbcDriver -Name $driver -Platform '64-bit' -ErrorAction SilentlyContinue)) {
        throw "O driver ODBC de 64 bits '$driver' não está instalado."
    }
}

foreach ($dsn in $dsns) {
    if ($dsn.Properties.Count -eq 0) {
        throw "O User DSN '$($dsn.Name)' não contém propriedades não sensíveis para copiar."
    }
}

$existing = foreach ($dsn in $dsns) {
    Get-OdbcDsn -Name $dsn.Name -DsnType System -Platform '64-bit' -ErrorAction SilentlyContinue
}

if ($existing -and -not $ReplaceExisting) {
    $names = ($existing | ForEach-Object Name) -join ', '
    throw "System DSN já existente: $names. Reveja-o manualmente ou repita com -ReplaceExisting e -WhatIf antes de confirmar a substituição."
}

foreach ($dsn in $dsns) {
    if ($ReplaceExisting -and $PSCmdlet.ShouldProcess($dsn.Name, 'remover System DSN ODBC de 64 bits existente')) {
        Remove-OdbcDsn -Name $dsn.Name -DsnType System -Platform '64-bit'
    }

    if ($PSCmdlet.ShouldProcess($dsn.Name, "criar System DSN ODBC de 64 bits com o driver '$($dsn.DriverName)'")) {
        Add-OdbcDsn `
            -Name $dsn.Name `
            -DsnType System `
            -Platform '64-bit' `
            -DriverName $dsn.DriverName `
            -SetPropertyValue $dsn.Properties `
            -ErrorAction Stop
    }
}

Write-Host ''
if ($WhatIfPreference) {
    Write-Host 'Simulação concluída. Nenhum System DSN foi alterado.'
} else {
    Write-Host 'DSNs configurados. Não foram copiadas credenciais para os System DSNs.'
}
Write-Host 'Valide agora com:'
Write-Host '  C:\PHP\php.exe -d extension=fileinfo artisan monitores:odbc:check'
