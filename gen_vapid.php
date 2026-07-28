<?php
$key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
if (!$key) {
    echo 'EC key creation failed: ' . openssl_error_string();
    exit(1);
}
$details = openssl_pkey_get_details($key);
$xBytes = $details['ec']['x'];
$yBytes = $details['ec']['y'];
$privBytes = $details['ec']['d'];

// public key: uncompressed point 0x04 || x || y
$pubBytes = chr(0x04) . $xBytes . $yBytes;

function b64u(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

$pub  = b64u($pubBytes);
$priv = b64u($privBytes);

echo "VAPID_PUBLIC_KEY=" . $pub  . PHP_EOL;
echo "VAPID_PRIVATE_KEY=" . $priv . PHP_EOL;
echo PHP_EOL;
echo "NEXT_PUBLIC_VAPID_PUBLIC_KEY=" . $pub . PHP_EOL;
