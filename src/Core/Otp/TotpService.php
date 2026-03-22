<?php declare(strict_types=1);

namespace Lwndcdr\OtpLogin\Core\Otp;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use OTPHP\TOTP;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class TotpService
{
    private const CIPHER_METHOD = 'AES-256-CBC';
    private const CONFIG_KEY_ENCRYPTION_KEY = 'OtpLogin.config.totpEncryptionKey';

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
    ) {}

    /**
     * Generate a new TOTP secret (base32, plain).
     */
    public function generateSecret(): string
    {
        return TOTP::generate()->getSecret();
    }

    /**
     * Build an otpauth:// provisioning URI for QR code scanning.
     */
    public function buildProvisioningUri(string $plainSecret, string $label, string $issuer): string
    {
        $totp = TOTP::createFromSecret($plainSecret);
        $totp->setLabel($label);
        $totp->setIssuer($issuer);

        return $totp->getProvisioningUri();
    }

    /**
     * Render the provisioning URI as a base64 PNG data URI.
     */
    public function buildQrCodeDataUri(string $provisioningUri): string
    {
        $qrCode = new QrCode(
            data: $provisioningUri,
            size: 200,
            margin: 8,
        );

        $result = (new PngWriter())->write($qrCode);

        return $result->getDataUri();
    }

    /**
     * Verify a TOTP code against the given plain secret.
     * Allows one time-step leeway in each direction.
     */
    public function verifyCode(string $plainSecret, string $code): bool
    {
        $totp = TOTP::createFromSecret($plainSecret);

        return $totp->verify($code, leeway: 1);
    }

    /**
     * Encrypt a TOTP secret for storage using AES-256-CBC.
     * The result is base64-encoded IV + ciphertext.
     */
    public function encryptSecret(string $plainSecret): string
    {
        $key = $this->resolveEncryptionKey();
        $ivLength = openssl_cipher_iv_length(self::CIPHER_METHOD);
        $iv = random_bytes($ivLength);

        $ciphertext = openssl_encrypt($plainSecret, self::CIPHER_METHOD, $key, OPENSSL_RAW_DATA, $iv);

        return base64_encode($iv . $ciphertext);
    }

    /**
     * Decrypt a stored TOTP secret.
     */
    public function decryptSecret(string $encryptedSecret): string
    {
        $key = $this->resolveEncryptionKey();
        $ivLength = openssl_cipher_iv_length(self::CIPHER_METHOD);
        $raw = base64_decode($encryptedSecret, strict: true);
        $iv = substr($raw, 0, $ivLength);
        $ciphertext = substr($raw, $ivLength);

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER_METHOD, $key, OPENSSL_RAW_DATA, $iv);

        if ($plaintext === false) {
            throw new \RuntimeException('Failed to decrypt TOTP secret — check the encryption key configuration.');
        }

        return $plaintext;
    }

    /**
     * Derive a 32-byte key from the configured encryption key string.
     */
    private function resolveEncryptionKey(): string
    {
        $configured = $this->systemConfigService->getString(self::CONFIG_KEY_ENCRYPTION_KEY);

        if ($configured === '') {
            throw new \RuntimeException(
                'TOTP encryption key is not configured. Set LwndcdrOtpLogin.config.totpEncryptionKey.'
            );
        }

        // Stretch / normalise to exactly 32 bytes for AES-256
        return hash('sha256', $configured, binary: true);
    }
}
