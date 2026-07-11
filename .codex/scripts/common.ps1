Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Get-SobhanRepoRoot {
    param([string]$StartPath = (Get-Location).Path)

    $current = (Resolve-Path $StartPath).Path
    while ($true) {
        if ((Test-Path (Join-Path $current ".git")) -or
            (Test-Path (Join-Path $current "AGENTS.md"))) {
            return $current
        }

        $parent = Split-Path $current -Parent
        if ([string]::IsNullOrWhiteSpace($parent) -or $parent -eq $current) {
            return (Resolve-Path $StartPath).Path
        }

        $current = $parent
    }
}

function Find-Executable {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Name,

        [string[]]$Candidates = @()
    )

    $command = Get-Command $Name -ErrorAction SilentlyContinue
    if ($command) {
        return $command.Source
    }

    foreach ($candidate in @($Candidates)) {
        if ([string]::IsNullOrWhiteSpace($candidate)) {
            continue
        }

        $matches = @(Resolve-Path -Path $candidate -ErrorAction SilentlyContinue)
        foreach ($match in $matches) {
            if (Test-Path $match.Path -PathType Leaf) {
                return $match.Path
            }
        }
    }

    return $null
}

function Get-PhpExecutable {
    $root = Get-SobhanRepoRoot

    return Find-Executable -Name "php" -Candidates @(
        $env:SOBHAN_PHP_PATH,
        (Join-Path $root ".tools\php\php.exe"),
        "C:\php\php.exe",
        "C:\xampp\php\php.exe",
        "C:\laragon\bin\php\php*\php.exe",
        "C:\wamp64\bin\php\php*\php.exe",
        "C:\Program Files\PHP\php.exe"
    )
}

function Get-MySqlExecutable {
    $root = Get-SobhanRepoRoot

    return Find-Executable -Name "mysql" -Candidates @(
        $env:SOBHAN_MYSQL_PATH,
        (Join-Path $root ".tools\mysql\mysql.exe"),
        "C:\xampp\mysql\bin\mysql.exe",
        "C:\laragon\bin\mysql\mysql*\bin\mysql.exe",
        "C:\wamp64\bin\mysql\mysql*\bin\mysql.exe",
        "C:\Program Files\MySQL\MySQL Server *\bin\mysql.exe"
    )
}

function Write-Check {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Name,

        [Parameter(Mandatory = $true)]
        [bool]$Ok,

        [string]$Detail = ""
    )

    $status = if ($Ok) { "OK" } else { "WARN" }
    $color = if ($Ok) { "Green" } else { "Yellow" }

    Write-Host ("[{0}] {1}: {2}" -f $status, $Name, $Detail) -ForegroundColor $color
}
