<?php

namespace App\Platform\Operations\Application;

use App\Platform\Modules\Models\MarketplaceCredential;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Facade;
use RuntimeException;

/**
 * Moves an installation off the APP_KEY that InvoiceShelf used to ship in
 * `.env.example`.
 *
 * That key is public, so it protects nothing it encrypts or signs: cookies,
 * and the marketplace credential, the one value encrypted at rest. Docker
 * installs kept it unless they set their own, because the image copied
 * `.env.example` and only generated a key when the value was empty.
 *
 * Retiring it gives the installation a key of its own when it still runs on
 * the shipped one, and seals the marketplace credential again with whatever
 * key is current, so a Docker install whose entrypoint has just replaced the
 * key keeps its marketplace pairing. Everyone has to sign in again once.
 */
class ShippedKeyRetirement
{
    /** The key `.env.example` carried before 3.0.0-alpha.4 and 2.4.4. */
    public const SHIPPED_KEY = 'base64:kgk/4DW1vEVy7aEvet5FPp5un6PIGe/so8H0mvoUtW0=';

    public function isShipped(?string $key): bool
    {
        return $key === self::SHIPPED_KEY;
    }

    /**
     * Replace the shipped key in `.env` with a new one, and make the running
     * process use it.
     *
     * @throws RuntimeException when the key in use is not the one in `.env`,
     *                          i.e. it is set in the server's environment
     */
    public function rotate(): string
    {
        $path = app()->environmentFilePath();
        $contents = is_file($path) ? (string) file_get_contents($path) : '';
        $pattern = '/^APP_KEY=["\']?'.preg_quote(self::SHIPPED_KEY, '/').'["\']?[ \t]*$/m';

        if (! preg_match($pattern, $contents)) {
            throw new RuntimeException(
                'APP_KEY is set to the key InvoiceShelf used to ship, but not in '.$path.
                ', so it comes from the server environment. Replace it there with a key of your own.'
            );
        }

        $key = 'base64:'.base64_encode(Encrypter::generateKey((string) config('app.cipher')));

        if (file_put_contents($path, preg_replace($pattern, 'APP_KEY='.$key, $contents)) === false) {
            throw new RuntimeException('Could not write the new APP_KEY to '.$path.'.');
        }

        if (app()->configurationIsCached()) {
            Artisan::call('config:clear');
        }

        config(['app.key' => $key]);
        app()->forgetInstance('encrypter');
        Facade::clearResolvedInstance('encrypter');

        return $key;
    }

    /**
     * Seal every marketplace credential that the shipped key sealed with the
     * given key instead.
     *
     * @return int credentials sealed again
     */
    public function resealCredentials(string $currentKey): int
    {
        if ($this->isShipped($currentKey)) {
            throw new RuntimeException('The installation still runs on the shipped APP_KEY; rotate it first.');
        }

        $cipher = (string) config('app.cipher');
        $shipped = new Encrypter($this->keyBytes(self::SHIPPED_KEY), $cipher);
        $current = new Encrypter($this->keyBytes($currentKey), $cipher);
        $resealed = 0;

        foreach (MarketplaceCredential::query()->get() as $credential) {
            try {
                $plain = $shipped->decryptString($credential->credential);
            } catch (DecryptException) {
                continue;
            }

            $credential->forceFill(['credential' => $current->encryptString($plain)])->save();
            $resealed++;
        }

        return $resealed;
    }

    private function keyBytes(string $key): string
    {
        return str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7)) : $key;
    }
}
