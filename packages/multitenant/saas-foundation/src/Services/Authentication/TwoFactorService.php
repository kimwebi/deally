<?php

namespace SaasFoundation\Services\Authentication;

use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use SaasFoundation\Models\User;

class TwoFactorService
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA;
    }

    public function generateSecret(User $user): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function getQrCodeUrl(User $user, string $secret): string
    {
        $company = config('app.name');
        $holder = $user->email;

        return $this->google2fa->getQRCodeUrl(
            $company,
            $holder,
            $secret
        );
    }

    public function enable(User $user, string $secret, string $code): bool
    {
        if (! $this->verifyCodeAgainstSecret($secret, $code)) {
            return false;
        }

        $user->update([
            'two_factor_secret' => $this->encryptSecret($secret),
            'two_factor_enabled_at' => now(),
        ]);

        $recoveryCodes = $this->generateRecoveryCodes($user);
        $user->update([
            'two_factor_recovery_codes' => $this->encryptRecoveryCodes($recoveryCodes),
        ]);

        return true;
    }

    public function disable(User $user, string $password): bool
    {
        if (! \Hash::check($password, $user->password)) {
            return false;
        }

        $user->update([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_enabled_at' => null,
        ]);

        return true;
    }

    public function verifyCode(User $user, string $code): bool
    {
        if (! $this->isEnabled($user)) {
            return false;
        }

        $secret = $this->decryptSecret($user->two_factor_secret);

        return $this->verifyCodeAgainstSecret($secret, $code);
    }

    public function generateRecoveryCodes(User $user): array
    {
        $codes = [];

        for ($i = 0; $i < 10; $i++) {
            $codes[] = strtoupper(Str::random(4).'-'.Str::random(4));
        }

        return $codes;
    }

    public function verifyRecoveryCode(User $user, string $code): bool
    {
        if (! $this->isEnabled($user)) {
            return false;
        }

        $codes = $this->decryptRecoveryCodes($user->two_factor_recovery_codes);
        $code = strtoupper($code);

        $index = array_search($code, $codes);

        if ($index === false) {
            return false;
        }

        unset($codes[$index]);
        $codes = array_values($codes);

        $user->update([
            'two_factor_recovery_codes' => $this->encryptRecoveryCodes($codes),
        ]);

        return true;
    }

    public function isEnabled(User $user): bool
    {
        return $user->two_factor_enabled_at !== null
            && $user->two_factor_secret !== null;
    }

    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = $this->generateRecoveryCodes($user);

        $user->update([
            'two_factor_recovery_codes' => $this->encryptRecoveryCodes($codes),
        ]);

        return $codes;
    }

    public function getRecoveryCodes(User $user): array
    {
        if (! $this->isEnabled($user) || empty($user->two_factor_recovery_codes)) {
            return [];
        }

        return $this->decryptRecoveryCodes($user->two_factor_recovery_codes);
    }

    protected function encryptSecret(string $secret): string
    {
        $key = (string) config('app.key');
        $iv = Str::random(16);
        $encrypted = openssl_encrypt($secret, 'aes-256-cbc', $key, 0, $iv);

        return base64_encode($iv.'::'.$encrypted);
    }

    protected function decryptSecret(string $encrypted): string
    {
        $key = (string) config('app.key');
        $decoded = base64_decode($encrypted);
        [$iv, $cipher] = explode('::', $decoded, 2);

        return openssl_decrypt($cipher, 'aes-256-cbc', $key, 0, $iv);
    }

    protected function verifyCodeAgainstSecret(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, $code, 1);
    }

    protected function encryptRecoveryCodes(array $codes): string
    {
        $json = json_encode($codes);
        $key = (string) config('app.key');
        $iv = Str::random(16);
        $encrypted = openssl_encrypt($json, 'aes-256-cbc', $key, 0, $iv);

        return base64_encode($iv.'::'.$encrypted);
    }

    protected function decryptRecoveryCodes(string $encrypted): array
    {
        $key = (string) config('app.key');
        $decoded = base64_decode($encrypted);
        [$iv, $cipher] = explode('::', $decoded, 2);
        $json = openssl_decrypt($cipher, 'aes-256-cbc', $key, 0, $iv);

        return json_decode($json, true) ?? [];
    }
}
