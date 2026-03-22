<?php declare(strict_types=1);

namespace Lwndcdr\OtpLogin\Core\Customer;

use Lwndcdr\OtpLogin\Entity\CustomerOtp\CustomerOtpDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class OtpCustomerExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToOneAssociationField('customerOtp', 'id', 'customer_id', CustomerOtpDefinition::class, true),
        );
    }

    public function getDefinitionClass(): string
    {
        return CustomerDefinition::class;
    }

    public function getEntityName(): string
    {
        return 'customer';
    }
}
