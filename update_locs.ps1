$files = Get-ChildItem -Filter *.php -Recurse
foreach ($file in $files) {
    if ($file.FullName -match 'vendor|firebase') { continue }
    $content = Get-Content $file.FullName -Raw
    
    $newContent = $content -replace '"city"=>"Mumbai"', '"city"=>"LPU Main Gate"'
    $newContent = $newContent -replace '"city"=>"Delhi"', '"city"=>"At Shop"'
    $newContent = $newContent -replace '"city"=>"Bangalore"', '"city"=>"Law Gate"'
    $newContent = $newContent -replace '"city"=>"Pune"', '"city"=>"Green Valley"'
    $newContent = $newContent -replace '"city"=>"Goa"', '"city"=>"LPU Main Gate"'
    $newContent = $newContent -replace '"city"=>"Chennai"', '"city"=>"At Shop"'
    
    $newContent = $newContent -replace 'placeholder="Mumbai, Delhi, Goa\.\.\."', 'placeholder="LPU Main Gate, Law Gate, At Shop..."'
    $newContent = $newContent -replace 'placeholder="Mumbai, Delhi, Bangalore\.\.\."', 'placeholder="LPU Main Gate, Law Gate, At Shop..."'
    
    $newContent = $newContent -replace 'Owner, Mumbai', 'Owner, LPU Main Gate'
    $newContent = $newContent -replace 'Biker, Delhi', 'Biker, At Shop'
    $newContent = $newContent -replace 'Family Traveller, Bangalore', 'Family Traveller, Law Gate'
    $newContent = $newContent -replace 'Adventure, Pune', 'Adventure, Green Valley'
    $newContent = $newContent -replace 'Renter, Hyderabad', 'Renter, LPU Main Gate'
    $newContent = $newContent -replace 'Part-time Owner, Chennai', 'Part-time Owner, At Shop'
    
    $newContent = $newContent -replace '<option>Home / Hotel Delivery</option><option>Airport</option><option>Custom Location</option>', '<option>LPU Main Gate</option><option>At Shop</option><option>Law Gate</option><option>Green Valley</option>'

    if ($content -ne $newContent) {
        Set-Content -Path $file.FullName -Value $newContent
        Write-Host "Updated $($file.Name)"
    }
}
