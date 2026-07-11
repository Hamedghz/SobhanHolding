# Suggested Codex Local Environment Actions

Configure these actions in the ChatGPT desktop app for this project.

## Doctor

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .codex/scripts/doctor.ps1
```

## PHP Lint — Changed Files

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .codex/scripts/lint.ps1
```

## Validation

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .codex/scripts/test.ps1
```

## Source Backup

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .codex/scripts/backup.ps1
```

## Cleanup Script

Use only temporary Codex-created files:

```powershell
if (Test-Path ".codex-runtime\tmp") {
    Remove-Item ".codex-runtime\tmp\*" -Recurse -Force -ErrorAction SilentlyContinue
}
```
