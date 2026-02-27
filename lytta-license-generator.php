<?php
/**
 * LYTTA WEB AGENCY - PRIVATE LICENSE GENERATOR
 * Do not distribute this file with the plugin.
 * 
 * Usage from CLI: php lytta-license-generator.php [COMPANY_ID]
 * Usage from Browser: Open in browser and add ?company_id=XXX to URL.
 */

define('LYTTA_SECRET_SALT', 'Lyt74_W4si_Pr0_2026!#');

function generate_lytta_license($company_id)
{
    if (empty($company_id)) {
        return "Error: Company ID is required.";
    }

    // Create a deterministic hash combining the secret salt, the company ID and a static prefix
    $raw_string = LYTTA_SECRET_SALT . '_CID_' . trim($company_id);
    $hashed = strtoupper(md5($raw_string));

    // Format into a readable license key: PRO-LYTTA-{PART1}-{PART2}-{PART3}
    $part1 = substr($hashed, 0, 5);
    $part2 = substr($hashed, 5, 5);
    $part3 = substr($hashed, 10, 5);
    $part4 = substr($hashed, 15, 5);

    return "PRO-LYTTA-{$part1}-{$part2}-{$part3}-{$part4}";
}

// Handle Browser Request
if (isset($_GET['company_id'])) {
    $cid = strip_tags($_GET['company_id']);
    echo "<h1>Lytta PRO Key Generator</h1>";
    echo "<p>Company ID: <strong>{$cid}</strong></p>";
    echo "<p>Generated License Key: <strong style='color:green;font-size:20px;'>" . generate_lytta_license($cid) . "</strong></p>";
    exit;
}

// Handle CLI Request
if (php_sapi_name() === 'cli') {
    $cid = isset($argv[1]) ? $argv[1] : '';
    echo "\n=== LYTTA PRO KEY GENERATOR ===\n";
    if (empty($cid)) {
        echo "Usage: php lytta-license-generator.php [COMPANY_ID]\n";
    }
    else {
        echo "Company ID: {$cid}\n";
        echo "License Key: " . generate_lytta_license($cid) . "\n";
    }
    echo "===============================\n\n";
    exit;
}

// Default Instructions
echo "<h1>Lytta PRO Key Generator</h1>";
echo "<p>Pass the <code>?company_id=XXX</code> parameter in the URL to generate a license key securely.</p>";
?>
