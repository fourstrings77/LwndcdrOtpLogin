<?php declare(strict_types=1);

namespace Lwndcdr\OtpLogin\Entity\CustomerOtp;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<CustomerOtpEntity>
 */
class CustomerOtpCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return CustomerOtpEntity::class;
    }
}
