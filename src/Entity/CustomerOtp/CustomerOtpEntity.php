<?php declare(strict_types=1);

namespace Lwndcdr\OtpLogin\Entity\CustomerOtp;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class CustomerOtpEntity extends Entity
{
    use EntityIdTrait;

    protected string $customerId;

    protected ?string $totpSecret = null;

    protected bool $totpEnabled = false;

    protected bool $emailOtpEnabled = false;

    protected ?string $emailOtpCode = null;

    protected ?\DateTimeImmutable $emailOtpExpiresAt = null;

    protected ?CustomerEntity $customer = null;

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getTotpSecret(): ?string
    {
        return $this->totpSecret;
    }

    public function setTotpSecret(?string $totpSecret): void
    {
        $this->totpSecret = $totpSecret;
    }

    public function isTotpEnabled(): bool
    {
        return $this->totpEnabled;
    }

    public function setTotpEnabled(bool $totpEnabled): void
    {
        $this->totpEnabled = $totpEnabled;
    }

    public function isEmailOtpEnabled(): bool
    {
        return $this->emailOtpEnabled;
    }

    public function setEmailOtpEnabled(bool $emailOtpEnabled): void
    {
        $this->emailOtpEnabled = $emailOtpEnabled;
    }

    public function getEmailOtpCode(): ?string
    {
        return $this->emailOtpCode;
    }

    public function setEmailOtpCode(?string $emailOtpCode): void
    {
        $this->emailOtpCode = $emailOtpCode;
    }

    public function getEmailOtpExpiresAt(): ?\DateTimeImmutable
    {
        return $this->emailOtpExpiresAt;
    }

    public function setEmailOtpExpiresAt(?\DateTimeImmutable $emailOtpExpiresAt): void
    {
        $this->emailOtpExpiresAt = $emailOtpExpiresAt;
    }

    public function getCustomer(): ?CustomerEntity
    {
        return $this->customer;
    }

    public function setCustomer(?CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }
}
