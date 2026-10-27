$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$outputDirectory = Join-Path $projectRoot 'dist'
[IO.Directory]::CreateDirectory($outputDirectory) | Out-Null
$zipPath = Join-Path $outputDirectory 'qeai-hostinger-atualizacao.zip'
$entries = [Collections.Generic.List[string]]::new()
foreach ($name in @('.htaccess','.user.ini','index.php','contact.php','privacy.php','cookies.php','terms.php','blog.php','blog_api.php','sitemap.php','feed.php','robots.txt','config/.htaccess','storage/.htaccess')) { $entries.Add($name) }
foreach ($directory in @('app','assets','workspace')) {
    Get-ChildItem -LiteralPath (Join-Path $projectRoot $directory) -File -Recurse -Force | ForEach-Object {
        $entries.Add($_.FullName.Substring($projectRoot.Length + 1).Replace('\','/'))
    }
}
$stream = [IO.File]::Open($zipPath, [IO.FileMode]::Create)
$archive = [IO.Compression.ZipArchive]::new($stream, [IO.Compression.ZipArchiveMode]::Create)
try {
    foreach ($entry in $entries) {
        [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, (Join-Path $projectRoot $entry), $entry, [IO.Compression.CompressionLevel]::Optimal) | Out-Null
    }
} finally { $archive.Dispose(); $stream.Dispose() }
$verification = [IO.Compression.ZipFile]::OpenRead($zipPath)
try {
    $names = @($verification.Entries | ForEach-Object { $_.FullName })
    if ($names.Count -ne $entries.Count) { throw 'Quantidade de arquivos divergente no ZIP.' }
    foreach ($required in @('index.php','terms.php','blog.php','blog_api.php','sitemap.php','feed.php','app/bootstrap.php','app/blog.php','app/blog_admin.php','app/storage.php','app/url.php','app/db.php','.htaccess','.user.ini','assets/css/site.css','assets/css/site-premium.css','assets/js/site-premium.js','assets/css/blog.css','assets/css/blog-admin.css','assets/css/workspace.css','config/.htaccess','storage/.htaccess')) {
        if ($names -notcontains $required) { throw ('Arquivo ausente: ' + $required) }
    }
    if ($names | Where-Object { $_ -match '\.key$|^storage/(?!\.htaccess$)|^tests/|^private-docs/' }) { throw 'O pacote contém arquivos privados que deveriam ser preservados no servidor.' }
    '{0} arquivos verificados no ZIP de atualização.' -f $names.Count
} finally { $verification.Dispose() }
Get-Item -LiteralPath $zipPath | Select-Object FullName,Length
