param([string]$Configuration='Release',[string]$Runtime='win-x64')
$ErrorActionPreference='Stop'
dotnet restore
dotnet publish .\SobhanNotificationHub.csproj -c $Configuration -r $Runtime --self-contained true /p:PublishSingleFile=true /p:IncludeNativeLibrariesForSelfExtract=true -p:Platform=x64
Write-Host "Publish completed: bin\$Configuration\net8.0-windows10.0.19041.0\$Runtime\publish"
