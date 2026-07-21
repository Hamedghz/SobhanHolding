. "$PSScriptRoot\common.ps1"

$root = Get-SobhanRepoRoot
Set-Location $root

Write-Host "Checking Codex phase execution contract" -ForegroundColor Cyan

function Assert-FileContains {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Path,

        [Parameter(Mandatory = $true)]
        [string[]]$RequiredText
    )

    if (-not (Test-Path $Path -PathType Leaf)) {
        throw "Required policy file was not found: $Path"
    }

    $content = Get-Content -Raw $Path

    foreach ($text in $RequiredText) {
        if (-not $content.Contains($text)) {
            throw "Policy contract is missing required text in ${Path}: $text"
        }
    }

    Write-Host "[OK] $Path" -ForegroundColor Green
}

Assert-FileContains -Path ".codex\RULES.md" -RequiredText @(
    "## Task Mode and Phase Execution",
    "### Implementation Mode",
    "A phase prompt is not documentation-only by default.",
    "High risk changes the validation depth; it is not by itself a reason to refuse implementation.",
    "### Read-Only Mode",
    "### Markdown Is Supporting Work",
    "Do not satisfy an implementation phase by creating or updating only ``*.md`` files",
    "### Legitimate Blockers"
)

Assert-FileContains -Path ".codex\CONTEXT.md" -RequiredText @(
    "## Codex Execution Expectation",
    "This repository uses action-first phased delivery.",
    "They must not be used as a generic reason to produce only Markdown."
)

Assert-FileContains -Path ".codex\SECURITY.md" -RequiredText @(
    "## Security Is a Guardrail, Not a Default Refusal",
    "High risk alone does not convert an implementation request into an audit or documentation-only task."
)

Assert-FileContains -Path ".codex\TASKS.md" -RequiredText @(
    "## Task Board Execution Contract",
    "Documentation remains part of the definition of done when applicable, but it is not the sole deliverable"
)

Assert-FileContains -Path ".codex\README-FA.md" -RequiredText @(
    "## قرارداد اجرای فازها",
    ".codex/scripts/policy-contract-test.ps1"
)

Assert-FileContains -Path "AGENTS.md" -RequiredText @(
    ".codex/RULES.md"
)

Assert-FileContains -Path ".codex\scripts\test.ps1" -RequiredText @(
    'policy-contract-test.ps1'
)

Write-Host "Codex phase execution contract passed." -ForegroundColor Green
