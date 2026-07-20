<?php

class TwoFactorAuth
{
    public static function generateSecret(): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 32; $i++) {
            $secret .= $alphabet[random_int(0, 31)];
        }
        return $secret;
    }

    public static function getQrCodeUrl(string $secret, string $email, string $issuer = 'SRC DSS'): string
    {
        $url = 'otpauth://totp/' . rawurlencode($issuer . ':' . $email) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&digits=6&period=30';
        return $url;
    }

    public static function verifyCode(string $secret, string $code): bool
    {
        $code = preg_replace('/[^0-9]/', '', $code);
        if (strlen($code) !== 6) {
            return false;
        }

        $timestamp = floor(time() / 30);
        for ($i = -1; $i <= 1; $i++) {
            if (self::generateTOTP($secret, $timestamp + $i) === $code) {
                return true;
            }
        }
        return false;
    }

    private static function generateTOTP(string $secret, int $timestamp): string
    {
        $key = self::base32Decode($secret);
        $msg = pack('J', $timestamp);
        $hash = hash_hmac('sha1', $msg, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $truncated = (unpack('N', substr($hash, $offset, 4))[1] & 0x7fffffff) % 1000000;
        return str_pad((string) $truncated, 6, '0', STR_PAD_LEFT);
    }

    private static function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper($secret);
        $output = '';
        $buffer = 0;
        $bits = 0;

        for ($i = 0; $i < strlen($secret); $i++) {
            $char = $secret[$i];
            if ($char === '=') break;
            $val = strpos($alphabet, $char);
            if ($val === false) continue;
            $buffer = ($buffer << 5) | $val;
            $bits += 5;
            if ($bits >= 8) {
                $output .= chr(($buffer >> ($bits - 8)) & 0xff);
                $bits -= 8;
            }
        }
        return $output;
    }
}
