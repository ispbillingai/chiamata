<?php
/**
 * Web Push without a payload: the push only wakes the waiter's phone, then the
 * service worker (waiter/sw.js) asks api/waiter.php?a=push_summary what is
 * waiting and shows the notification. No payload means no message encryption,
 * only the VAPID signature (ES256), which PHP's openssl does natively.
 */
declare(strict_types=1);

function b64url(string $bin): string
{
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

/** [private key PEM, public key base64url (65-byte uncompressed point)], created once. */
function vapid_keys(): array
{
    $pem = setting_once('vapid_private_pem', function () {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        openssl_pkey_export($key, $out);
        return $out;
    });
    $d = openssl_pkey_get_details(openssl_pkey_get_private($pem));
    $pub = "\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT);
    return [$pem, b64url($pub)];
}

function vapid_public_key(): string
{
    return vapid_keys()[1];
}

/** openssl gives a DER signature; JWT ES256 wants raw r||s, 32 bytes each. */
function der_to_raw_signature(string $der): string
{
    $pos = 2;
    if (ord($der[1]) & 0x80) $pos += ord($der[1]) & 0x7f;
    $parts = [];
    for ($i = 0; $i < 2; $i++) {
        $pos++;                          // INTEGER tag
        $len = ord($der[$pos++]);
        $parts[] = str_pad(ltrim(substr($der, $pos, $len), "\0"), 32, "\0", STR_PAD_LEFT);
        $pos += $len;
    }
    return $parts[0] . $parts[1];
}

function vapid_jwt(string $audience, string $pem): string
{
    $head = b64url(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $body = b64url(json_encode([
        'aud' => $audience,
        'exp' => time() + 3600,
        'sub' => 'https://' . ($_SERVER['HTTP_HOST'] ?? 'chiamata.upgradesrls.com'),
    ]));
    openssl_sign("$head.$body", $der, $pem, OPENSSL_ALGO_SHA256);
    return "$head.$body." . b64url(der_to_raw_signature($der));
}

/**
 * Sends a wake-up push to each subscription (rows of push_subscriptions).
 * Subscriptions the push service says are gone (404/410) are removed.
 * Returns ['sent' => n, 'failed' => n].
 */
function push_send(array $subs): array
{
    $result = ['sent' => 0, 'failed' => 0];
    if (!$subs) return $result;
    [$pem, $pub] = vapid_keys();
    $mh = curl_multi_init();
    $handles = [];
    $jwts = [];
    foreach ($subs as $s) {
        $u = parse_url($s['endpoint']);
        if (empty($u['scheme']) || empty($u['host'])) continue;
        $aud = $u['scheme'] . '://' . $u['host'] . (isset($u['port']) ? ':' . $u['port'] : '');
        $jwts[$aud] ??= vapid_jwt($aud, $pem);
        $ch = curl_init($s['endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_HTTPHEADER => [
                'TTL: 600',
                'Urgency: high',
                'Content-Length: 0',
                'Authorization: vapid t=' . $jwts[$aud] . ', k=' . $pub,
            ],
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[(int) $s['id']] = $ch;
    }
    do {
        $status = curl_multi_exec($mh, $running);
        if ($running) curl_multi_select($mh, 1.0);
    } while ($running && $status === CURLM_OK);

    foreach ($handles as $id => $ch) {
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($code >= 200 && $code < 300) {
            $result['sent']++;
            db()->prepare('UPDATE push_subscriptions SET last_ok_at = NOW() WHERE id = ?')->execute([$id]);
        } else {
            $result['failed']++;
            if ($code === 404 || $code === 410) {
                db()->prepare('DELETE FROM push_subscriptions WHERE id = ?')->execute([$id]);
            } else {
                error_log("chiamata push: subscription $id got HTTP $code " . substr((string) curl_multi_getcontent($ch), 0, 200) . ' ' . curl_error($ch));
            }
        }
        curl_multi_remove_handle($mh, $ch);
    }
    curl_multi_close($mh);
    return $result;
}
