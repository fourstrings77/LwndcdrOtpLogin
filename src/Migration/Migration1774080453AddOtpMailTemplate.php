<?php declare(strict_types=1);

namespace Lwndcdr\OtpLogin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1774080453AddOtpMailTemplate extends MigrationStep
{
    public const MAIL_TEMPLATE_TYPE_TECHNICAL_NAME = 'lwndcdr_otp.code_requested';

    public function getCreationTimestamp(): int
    {
        return 1774080453;
    }

    public function update(Connection $connection): void
    {
        $typeId = $this->createMailTemplateType($connection);
        $templateId = $this->createMailTemplate($connection, $typeId);
        $this->createFlow($connection, $templateId);
    }

    private function createMailTemplateType(Connection $connection): string
    {
        $existing = $connection->fetchOne(
            'SELECT id FROM mail_template_type WHERE technical_name = :name',
            ['name' => self::MAIL_TEMPLATE_TYPE_TECHNICAL_NAME],
        );

        if ($existing !== false) {
            return $existing;
        }

        $typeId = Uuid::randomBytes();

        $connection->insert('mail_template_type', [
            'id' => $typeId,
            'technical_name' => self::MAIL_TEMPLATE_TYPE_TECHNICAL_NAME,
            'available_entities' => json_encode([
                'customer' => 'customer',
                'salesChannel' => 'sales_channel',
            ], \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $enId = $this->getLanguageIdByLocale($connection, 'en-GB');
        $deId = $this->getLanguageIdByLocale($connection, 'de-DE');

        if ($enId) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => $typeId,
                'language_id' => $enId,
                'name' => 'OTP Login: One-Time Code',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        if ($deId) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => $typeId,
                'language_id' => $deId,
                'name' => 'OTP-Login: Einmalcode',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        return $typeId;
    }

    private function createMailTemplate(Connection $connection, string $typeId): string
    {
        $existing = $connection->fetchOne(
            'SELECT id FROM mail_template WHERE mail_template_type_id = :typeId',
            ['typeId' => $typeId],
        );

        if ($existing !== false) {
            return $existing;
        }

        $templateId = Uuid::randomBytes();

        $connection->insert('mail_template', [
            'id' => $templateId,
            'mail_template_type_id' => $typeId,
            'system_default' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $enId = $this->getLanguageIdByLocale($connection, 'en-GB');
        $deId = $this->getLanguageIdByLocale($connection, 'de-DE');

        if ($enId) {
            $connection->insert('mail_template_translation', [
                'mail_template_id' => $templateId,
                'language_id' => $enId,
                'sender_name' => '{{ shopName }}',
                'subject' => 'Your one-time login code',
                'description' => 'Sent to customers after login when email OTP is enabled.',
                'content_html' => $this->getHtmlContent('en'),
                'content_plain' => $this->getPlainContent('en'),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        if ($deId) {
            $connection->insert('mail_template_translation', [
                'mail_template_id' => $templateId,
                'language_id' => $deId,
                'sender_name' => '{{ shopName }}',
                'subject' => 'Ihr Einmalcode zur Anmeldung',
                'description' => 'Wird nach dem Login an Kunden gesendet, wenn E-Mail-OTP aktiviert ist.',
                'content_html' => $this->getHtmlContent('de'),
                'content_plain' => $this->getPlainContent('de'),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        return $templateId;
    }

    private function createFlow(Connection $connection, string $templateId): void
    {
        $existing = $connection->fetchOne(
            "SELECT id FROM flow WHERE event_name = 'lwndcdr_otp.code_requested'",
        );

        if ($existing !== false) {
            return;
        }

        $flowId = Uuid::randomBytes();

        $connection->insert('flow', [
            'id' => $flowId,
            'name' => 'OTP Login: Send one-time code',
            'event_name' => 'lwndcdr_otp.code_requested',
            'active' => true,
            'payload' => null,
            'invalid' => 0,
            'custom_fields' => null,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $connection->insert('flow_sequence', [
            'id' => Uuid::randomBytes(),
            'flow_id' => $flowId,
            'rule_id' => null,
            'parent_id' => null,
            'action_name' => SendMailAction::ACTION_NAME,
            'position' => 1,
            'true_case' => 0,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'config' => json_encode([
                'recipient' => ['data' => [], 'type' => 'default'],
                'mailTemplateId' => Uuid::fromBytesToHex($templateId),
                'documentTypeIds' => [],
            ], \JSON_THROW_ON_ERROR),
        ]);

        $this->registerIndexer($connection, 'flow.indexer');
    }

    private function getLanguageIdByLocale(Connection $connection, string $locale): ?string
    {
        $id = $connection->fetchOne(
            'SELECT language.id FROM language
             INNER JOIN locale ON locale.id = language.locale_id
             WHERE locale.code = :code',
            ['code' => $locale],
        );

        if ($id === false) {
            return null;
        }

        return $id;
    }

    private function getHtmlContent(string $locale): string
    {
        if ($locale === 'de') {
            return <<<'HTML'
<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
    <h2>Ihr Anmeldecode</h2>
    <p>Hallo {{ customer.firstName }},</p>
    <p>um Ihre Anmeldung bei <strong>{{ shopName }}</strong> abzuschließen, geben Sie bitte den folgenden Einmalcode ein:</p>
    <div style="text-align:center;margin:30px 0;padding:20px;background:#f5f5f5;border-radius:6px;">
        <span style="font-size:36px;font-weight:bold;letter-spacing:10px;color:#333;">{{ otpCode }}</span>
    </div>
    <p>Der Code ist <strong>{{ expiresInMinutes }} Minuten</strong> gültig.</p>
    <p style="color:#888;font-size:12px;">Wenn Sie sich nicht angemeldet haben, ignorieren Sie diese E-Mail bitte.</p>
</div>
HTML;
        }

        return <<<'HTML'
<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
    <h2>Your login code</h2>
    <p>Hello {{ customer.firstName }},</p>
    <p>To complete your login at <strong>{{ shopName }}</strong>, please enter the following one-time code:</p>
    <div style="text-align:center;margin:30px 0;padding:20px;background:#f5f5f5;border-radius:6px;">
        <span style="font-size:36px;font-weight:bold;letter-spacing:10px;color:#333;">{{ otpCode }}</span>
    </div>
    <p>This code is valid for <strong>{{ expiresInMinutes }} minutes</strong>.</p>
    <p style="color:#888;font-size:12px;">If you did not try to log in, please ignore this email.</p>
</div>
HTML;
    }

    private function getPlainContent(string $locale): string
    {
        if ($locale === 'de') {
            return <<<'TEXT'
Hallo {{ customer.firstName }},

um Ihre Anmeldung bei {{ shopName }} abzuschließen, geben Sie bitte den folgenden Einmalcode ein:

{{ otpCode }}

Der Code ist {{ expiresInMinutes }} Minuten gültig.

Wenn Sie sich nicht angemeldet haben, ignorieren Sie diese E-Mail bitte.
TEXT;
        }

        return <<<'TEXT'
Hello {{ customer.firstName }},

To complete your login at {{ shopName }}, please enter the following one-time code:

{{ otpCode }}

This code is valid for {{ expiresInMinutes }} minutes.

If you did not try to log in, please ignore this email.
TEXT;
    }
}
