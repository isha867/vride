$files = Get-ChildItem -Filter *.php -Recurse
foreach ($file in $files) {
    if ($file.FullName -match 'vendor|firebase') { continue }
    $content = Get-Content $file.FullName -Raw
    
    $newContent = $content -replace "'(Mumbai|Goa|Hyderabad)'", "'LPU Main Gate'"
    $newContent = $newContent -replace "'(Delhi|Chennai)'", "'At Shop'"
    $newContent = $newContent -replace "'[Bb]angalore'", "'Law Gate'"
    $newContent = $newContent -replace "'Pune'", "'Green Valley'"

    $newContent = $newContent -replace '"(Mumbai|Goa|Hyderabad)"', '"LPU Main Gate"'
    $newContent = $newContent -replace '"(Delhi|Chennai)"', '"At Shop"'
    $newContent = $newContent -replace '"[Bb]angalore"', '"Law Gate"'
    $newContent = $newContent -replace '"Pune"', '"Green Valley"'
    
    $newContent = $newContent -replace 'Mumbai, Delhi, Pune', 'LPU Main Gate, Law Gate, At Shop'
    $newContent = $newContent -replace 'Mumbai, Delhi, Bangalore', 'LPU Main Gate, Law Gate, At Shop'
    
    $newContent = $newContent -replace 'Deepak Goa', 'Deepak Admin'

    if ($content -ne $newContent) {
        Set-Content -Path $file.FullName -Value $newContent
        Write-Host "Updated $($file.Name)"
    }
}
