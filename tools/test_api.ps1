<#!
.SYNOPSIS
    Smoke test API Global pada environment development.

.EXAMPLE
    .\tools\test_api.ps1 -AppToken (Read-Host 'Token aplikasi')

.EXAMPLE
    .\tools\test_api.ps1 -AppCode monbis -AppToken 'app_token' -EmployeeId '102-119' -EmployeePhone '08xxxxxxxxxx'

.NOTES
    Token aplikasi dan password tidak disimpan oleh script. Jangan commit nilai
    rahasia sebagai default parameter atau menaruhnya di file ini.
#>
[CmdletBinding()]
param(
    [string] $BaseUrl = 'https://devsso.bkkjateng.co.id',
    [string] $AppCode = 'monbis',
    [string] $AppToken,
    [string] $EmployeeId,
    [string] $EmployeePhone,
    [string] $Search = 'zzzzzz',
    [ValidateSet('dana', 'kredit', 'remedial')]
    [string] $TypeAo = 'dana',
    [ValidatePattern('^\d{3}$')]
    [string] $KodeKantor = '000',
    [switch] $SkipAo,
    [switch] $SkipSearch
)

$ErrorActionPreference = 'Stop'
$BaseUrl = $BaseUrl.TrimEnd('/')

function ConvertFrom-SecureStringValue {
    param([Security.SecureString] $Value)

    $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($Value)
    try {
        return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    }
    finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
    }
}

function Read-SecretValue {
    param([string] $Prompt)

    $secure = Read-Host -Prompt $Prompt -AsSecureString
    return ConvertFrom-SecureStringValue $secure
}

function Invoke-Api {
    param(
        [ValidateSet('GET', 'POST')]
        [string] $Method,
        [string] $Path,
        [hashtable] $Headers = @{},
        [object] $Body
    )

    $requestHeaders = @{ Accept = 'application/json' }
    foreach ($key in $Headers.Keys) {
        $requestHeaders[$key] = [string] $Headers[$key]
    }

    $request = @{
        Uri = "$BaseUrl$Path"
        Method = $Method
        Headers = $requestHeaders
        UseBasicParsing = $true
        ErrorAction = 'Stop'
    }
    if ($null -ne $Body) {
        $request.ContentType = 'application/json'
        $request.Body = $Body | ConvertTo-Json -Depth 8
    }

    $status = 0
    $raw = ''
    try {
        $response = Invoke-WebRequest @request
        $status = [int] $response.StatusCode
        $raw = [string] $response.Content
    }
    catch {
        $response = $_.Exception.Response
        if ($null -eq $response) {
            throw
        }

        $status = [int] $response.StatusCode
        $reader = New-Object System.IO.StreamReader($response.GetResponseStream())
        try {
            $raw = $reader.ReadToEnd()
        }
        finally {
            $reader.Dispose()
        }
    }

    $json = $null
    if (-not [string]::IsNullOrWhiteSpace($raw)) {
        try {
            $json = $raw | ConvertFrom-Json
        }
        catch {
            $json = $null
        }
    }

    [PSCustomObject] @{
        Status = $status
        Json = $json
        Raw = $raw
        Path = $Path
    }
}

function Get-ApiMessage {
    param($Result)

    if ($null -ne $Result.Json -and $null -ne $Result.Json.message) {
        return [string] $Result.Json.message
    }
    return 'Response bukan JSON atau message tidak tersedia.'
}

function Show-Result {
    param(
        [string] $Label,
        $Result,
        [int[]] $ExpectedStatus
    )

    $isExpected = $ExpectedStatus -contains $Result.Status
    $color = if ($isExpected) { 'Green' } else { 'Red' }
    $state = if ($isExpected) { 'PASS' } else { 'FAIL' }
    Write-Host ("[{0}] {1} -> HTTP {2} ({3})" -f $state, $Label, $Result.Status, (Get-ApiMessage $Result)) -ForegroundColor $color

    if (-not $isExpected) {
        throw "Test gagal pada $Label. URL: $BaseUrl$($Result.Path)"
    }
}

if ([string]::IsNullOrWhiteSpace($AppToken)) {
    $AppToken = Read-SecretValue 'X-App-Token aplikasi'
}
if ([string]::IsNullOrWhiteSpace($AppToken)) {
    throw 'App token wajib diisi.'
}

$appHeaders = @{
    App = $AppCode
    'X-App-Token' = $AppToken
}

Write-Host "Testing API: $BaseUrl" -ForegroundColor Cyan
Write-Host "Aplikasi: $AppCode" -ForegroundColor Cyan

$probe = Invoke-Api -Method GET -Path '/api/simpeg/pegawai/search?nama=zzzzzz'
if ($probe.Status -eq 404) {
    throw "Base URL belum mengarah ke aplikasi API. Route $BaseUrl$($probe.Path) masih 404. Periksa root site dan rewrite Nginx aaPanel."
}
if ($probe.Status -eq 401) {
    Show-Result 'Proteksi app token tanpa header' $probe @(401)
}
elseif ($probe.Status -eq 200) {
    Write-Host '[WARN] APP_TOKEN_REQUIRED belum aktif pada environment ini.' -ForegroundColor Yellow
}
else {
    Write-Host ("[INFO] Probe awal mengembalikan HTTP {0}. Pengujian token dilanjutkan." -f $probe.Status) -ForegroundColor Yellow
}

if (-not $SkipSearch) {
    $encodedSearch = [Uri]::EscapeDataString($Search)
    $searchResult = Invoke-Api -Method GET -Path "/api/simpeg/pegawai/search?nama=$encodedSearch" -Headers $appHeaders
    Show-Result 'Search pegawai dengan app token' $searchResult @(200)
}

if (-not $SkipAo) {
    $aoResult = Invoke-Api -Method GET -Path "/api/simpeg/ao/list?type_ao=$TypeAo&kode_kantor=$KodeKantor" -Headers $appHeaders
    Show-Result "List AO $TypeAo kantor $KodeKantor" $aoResult @(200)
}

if ([string]::IsNullOrWhiteSpace($EmployeeId)) {
    Write-Host '[SKIP] Login, whoami, dan endpoint protected dilewati karena -EmployeeId belum diisi.' -ForegroundColor Yellow
    Write-Host 'Smoke test app token selesai.' -ForegroundColor Green
    return
}

$employeePassword = Read-SecretValue "Password pegawai $EmployeeId"
if ([string]::IsNullOrWhiteSpace($EmployeePhone)) {
    $EmployeePhone = Read-Host "Nomor HP pegawai $EmployeeId"
}
if ([string]::IsNullOrWhiteSpace($EmployeePhone)) {
    throw 'Nomor HP pegawai wajib diisi untuk login.'
}

$loginResult = Invoke-Api -Method POST -Path '/api/auth/login' -Headers $appHeaders -Body @{
    id_peg = $EmployeeId
    telp = $EmployeePhone
    password = $employeePassword
}
Show-Result 'Login pegawai' $loginResult @(200)

$jwt = [string] ($loginResult.Json.data.token)
if ([string]::IsNullOrWhiteSpace($jwt)) {
    throw 'Login mengembalikan HTTP 200 tetapi data.token tidak ditemukan.'
}

$userHeaders = @{
    App = $AppCode
    'X-App-Token' = $AppToken
    Authorization = "Bearer $jwt"
}
$whoamiResult = Invoke-Api -Method GET -Path '/api/auth/whoami' -Headers $userHeaders
Show-Result 'Whoami dengan app token dan JWT' $whoamiResult @(200)

Write-Host 'Semua test yang dipilih berhasil. Token JWT tidak ditampilkan.' -ForegroundColor Green
