$ErrorActionPreference = 'Stop'
$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$outputDirectory = Join-Path $projectRoot 'dist'
[IO.Directory]::CreateDirectory($outputDirectory) | Out-Null
$zipPath = Join-Path $outputDirectory 'qeai-hostinger-urls-limpas.zip'
$entries = @(
    '.htaccess',
    'index.php',
    'contact.php',
    'privacy.php',
    'app/public_parts.php',
    'workspace/index.php',
    'workspace/logout.php'
)
$stream = [IO.File]::Open($zipPath, [IO.FileMode]::Create)
$archive = [IO.Compression.ZipArchive]::new($stream, [IO.Compression.ZipArchiveMode]::Create)
try {
    foreach ($entry in $entries) {
        [IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $archive,
            (Join-Path $projectRoot $entry),
            $entry,
            [IO.Compression.CompressionLevel]::Optimal
        ) | Out-Null
    }
} finally {
    $archive.Dispose()
    $stream.Dispose()
}
$verification = [IO.Compression.ZipFile]::OpenRead($zipPath)
try {
    $names = @($verification.Entries | ForEach-Object { $_.FullName })
    foreach ($required in $entries) {
        if ($names -notcontains $required) { throw ('Arquivo ausente: ' + $required) }
    }
    if ($names.Count -ne $entries.Count) { throw 'O pacote contém arquivos inesperados.' }
    "Pacote de URLs limpas validado: $($names.Count) arquivos."
} finally {
    $verification.Dispose()
}
Get-Item -LiteralPath $zipPath | Select-Object FullName, Length
