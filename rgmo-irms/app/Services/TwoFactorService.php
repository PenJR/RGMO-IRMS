<?php

namespace App\Services;

class TwoFactorService
{
    public function generateSecret(int $length = 16): string
    {
        $bytes = random_bytes($length);
        return $this->base32Encode($bytes);
    }

    public function getProvisioningUri(string $label, string $secret, string $issuer = 'RGMO-IRMS'): string
    {
        $label = rawurlencode($label);
        $issuer = rawurlencode($issuer);
        return "otpauth://totp/{$issuer}:{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
    }

    public function verifyCode(string $secret, string $code, int $window = 1): bool
    {
        $code = trim($code);
        if ($code === '') {
            return false;
        }

        $timeSlice = floor(time() / 30);
        for ($i = -$window; $i <= $window; $i++) {
            $calculated = $this->getCode($secret, $timeSlice + $i);
            $calculated = str_pad((string) $calculated, 6, '0', STR_PAD_LEFT);

            if (hash_equals($calculated, $code)) {
                return true;
            }
        }

        return false;
    }

    private function getCode(string $secret, int $timeSlice): int
    {
        $key = $this->base32Decode($secret);

        $time = pack('N2', 0, $timeSlice);
        $hash = hash_hmac('sha1', $time, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7f) << 24) |
                  ((ord($hash[$offset + 1]) & 0xff) << 16) |
                  ((ord($hash[$offset + 2]) & 0xff) << 8) |
                  (ord($hash[$offset + 3]) & 0xff);

        return $binary % 1000000;
    }

    private function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        $output = '';

        foreach (str_split($data) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }

        foreach (str_split($bits, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $output .= $alphabet[bindec($chunk)];
        }

        return $output;
    }

    private function base32Decode(string $b32): string
    {
        $b32 = strtoupper($b32);
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        $data = '';

        foreach (str_split($b32) as $c) {
            $pos = strpos($alphabet, $c);
            if ($pos === false) continue;
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) < 8) continue;
            $data .= chr(bindec($byte));
        }

        return $data;
    }
}
