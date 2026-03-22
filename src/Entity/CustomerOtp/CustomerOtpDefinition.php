<?php declare(strict_types=1);

namespace Lwndcdr\OtpLogin\Entity\CustomerOtp;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;

class CustomerOtpDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'lwndcdr_customer_otp';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CustomerOtpEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CustomerOtpCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('customer_id', 'customerId', CustomerDefinition::class))->addFlags(new Required()),

            new StringField('totp_secret', 'totpSecret'),

            new BoolField('totp_enabled', 'totpEnabled'),

            new BoolField('email_otp_enabled', 'emailOtpEnabled'),

            // Stores bcrypt hash of the email OTP code
            new StringField('email_otp_code', 'emailOtpCode'),

            new DateTimeField('email_otp_expires_at', 'emailOtpExpiresAt'),

            new OneToOneAssociationField('customer', 'customer_id', 'id', CustomerDefinition::class, false),

            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
