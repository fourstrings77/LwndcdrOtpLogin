<?php declare(strict_types=1);

namespace Lwndcdr\OtpLogin\Core\Event;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when an email OTP code has been generated and needs to be sent.
 *
 * Implements MailAware + ScalarValuesAware so Shopware's SendMailAction can
 * handle the send via a flow without any custom action code.
 *
 * Template variables available in the mail template:
 *   {{ customer.firstName }}, {{ customer.lastName }}, {{ customer.email }}
 *   {{ otpCode }}           — the plain one-time code
 *   {{ expiresInMinutes }}  — TTL from plugin configuration
 *   {{ shopName }}          — sales-channel display name
 */
class OtpCodeRequestedEvent extends Event implements FlowEventAware, MailAware, CustomerAware, SalesChannelAware, ScalarValuesAware, ShopwareSalesChannelEvent
{
    public const EVENT_NAME = 'lwndcdr_otp.code_requested';

    private ?MailRecipientStruct $mailRecipientStruct = null;

    private readonly string $shopName;

    public function __construct(
        private readonly SalesChannelContext $salesChannelContext,
        private readonly CustomerEntity $customer,
        private readonly string $otpCode,
        private readonly int $expiresInMinutes,
    ) {
        $this->shopName = (string) $salesChannelContext->getSalesChannel()->getTranslation('name');
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('customer', new EntityType(CustomerDefinition::class))
            ->add('otpCode', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('expiresInMinutes', new ScalarValueType(ScalarValueType::TYPE_INT))
            ->add('shopName', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array
    {
        return [
            'otpCode' => $this->otpCode,
            'expiresInMinutes' => $this->expiresInMinutes,
            'shopName' => $this->shopName,
        ];
    }

    public function getMailStruct(): MailRecipientStruct
    {
        if ($this->mailRecipientStruct === null) {
            $this->mailRecipientStruct = new MailRecipientStruct([
                $this->customer->getEmail() => trim($this->customer->getFirstName() . ' ' . $this->customer->getLastName()),
            ]);
        }

        return $this->mailRecipientStruct;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelContext->getSalesChannelId();
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    public function getCustomerId(): string
    {
        return $this->customer->getId();
    }
}
