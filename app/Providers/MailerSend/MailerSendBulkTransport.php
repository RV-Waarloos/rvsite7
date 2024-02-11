<?php

namespace App\Providers\MailerSend;

use MailerSend\Helpers\Builder\Attachment;
use MailerSend\Helpers\Builder\EmailParams;
use MailerSend\Helpers\Builder\Recipient;
use MailerSend\MailerSend;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\RawMessage;
use function json_decode;
use Illuminate\Support\Arr;

class MailerSendBulkTransport implements TransportInterface
{
    public const MAILERSEND_DATA_TYPE = 'text';
    public const MAILERSEND_DATA_SUBTYPE = 'mailersend-data';

    public const MAILERSEND_DATA_TEMPLATE_ID = 'template_id';
    public const MAILERSEND_DATA_VARIABLES = 'variables';
    public const MAILERSEND_DATA_TAGS = 'tags';
    public const MAILERSEND_DATA_PERSONALIZATION = 'personalization';
    public const MAILERSEND_DATA_PRECENDECE_BULK_HEADER = 'precedence_bulk_header';
    public const MAILERSEND_DATA_SEND_AT = 'send_at';

    protected MailerSend $mailersend;

    public function __construct(MailerSend $mailersend)
    {
        $this->mailersend = $mailersend;
    }

    /**
     * @throws \Assert\AssertionFailedException
     * @throws \JsonException
     * @throws \Psr\Http\Client\ClientExceptionInterface
     * @throws TransportExceptionInterface
     * @throws \MailerSend\Exceptions\MailerSendAssertException
     */
    public function send(RawMessage $message, Envelope $envelope = null): ?SentMessage
    {
        ['email' => $fromEmail, 'name' => $fromName] = $this->getFrom($message);
        ['email' => $replyToEmail, 'name' => $replyToName] = $this->getReplyTo($message);

        $text = $message->getTextBody();
        // $html = $message->getHtmlBody();
        $html = $this->escapeMarkdown($message->getHtmlBody());

        $to = $this->getRecipients('to', $message);
        $cc = $this->getRecipients('cc', $message);
        $bcc = $this->getRecipients('bcc', $message);

        $subject = $message->getSubject();

        $attachments = $this->getAttachments($message);

        [
            'template_id' => $template_id,
            'variables' => $variables,
            'tags' => $tags,
            'personalization' => $personalization,
            'precedence_bulk_header' => $precedenceBulkHeader,
            'send_at' => $sendAt,
        ] = $this->getAdditionalData($message);


        $sendblocks = array_chunk($to, config('rv.mailer.max_bulk_send_size'));

        foreach ($sendblocks as $sendblock) {
            $emails = [];
            foreach ($sendblock as $dest) {
                // Get the variables for this destinee
                $destEmail = $dest->toArray()['email'];
                $filteredVariables = Arr::where($variables, function ($value, $key) use ($destEmail) {
                    return $value['email'] === $destEmail;
                });
    
                $emailParams = app(EmailParams::class)
                    ->setFrom($fromEmail)
                    ->setFromName($fromName)
                    ->setReplyTo($replyToEmail)
                    ->setReplyToName(strval($replyToName))
                    ->setRecipients([$dest])
                    ->setCc($cc)
                    ->setBcc($bcc)
                    ->setSubject($subject)
                    ->setHtml($html)
                    ->setText($text)
                    ->setTemplateId($template_id)
                    ->setVariables($filteredVariables)
                    ->setPersonalization($personalization)
                    ->setAttachments($attachments)
                    ->setTags($tags)
                    ->setPrecedenceBulkHeader($precedenceBulkHeader)
                    ->setSendAt($sendAt);
    
                $emails[] = $emailParams;
            }
    
            $response = $this->mailersend->bulkEmail->send($emails);
    
            /** @var ResponseInterface $respInterface */
            $respInterface = $response['body'];
            $bulkEmailId[] = $respInterface['bulk_email_id'];
        }

        $message->getHeaders()?->addTextHeader('X-MailerSend-BulkEmailId', implode(',', $bulkEmailId));

        return new SentMessage($message, $envelope);
    }

    protected function getFrom(RawMessage $message): array
    {
        $from = $message->getFrom();

        if (count($from) > 0) {
            return ['name' => $from[0]->getName(), 'email' => $from[0]->getAddress()];
        }

        return ['email' => '', 'name' => ''];
    }

    protected function getReplyTo(RawMessage $message): array
    {
        $from = $message->getReplyTo();

        if (count($from) > 0) {
            return ['name' => $from[0]->getName(), 'email' => $from[0]->getAddress()];
        }

        return ['email' => '', 'name' => ''];
    }

    /**
     * @throws \MailerSend\Exceptions\MailerSendAssertException
     */
    protected function getRecipients(string $type, RawMessage $message): array
    {
        $recipients = [];

        if ($addresses = $message->{'get' . ucfirst($type)}()) {
            foreach ($addresses as $address) {
                $recipients[] = new Recipient($address->getAddress(), $address->getName());
            }
        }

        return $recipients;
    }

    protected function getAttachments(RawMessage $message): array
    {
        $attachments = [];

        foreach ($message->getAttachments() as $attachment) {
            /** @var DataPart $attachment */

            if ($attachment->getMediaSubtype() === self::MAILERSEND_DATA_SUBTYPE) {
                continue;
            }

            $attachments[] = new Attachment(
                $attachment->getBody(),
                $attachment->getPreparedHeaders()->get('content-disposition')?->getParameter('filename'),
                $attachment->getPreparedHeaders()->get('content-disposition')?->getBody(),
                $attachment->getPreparedHeaders()->get('content-id')?->getBodyAsString()
            );
        }

        return $attachments;
    }

    /**
     * @param  RawMessage  $message
     * @param  array  $payload
     * @throws \JsonException
     */
    protected function getAdditionalData(RawMessage $message): array
    {
        $defaultValues = [
            'template_id' => null,
            'variables' => [],
            'personalization' => [],
            'tags' => [],
            'precedence_bulk_header' => null,
            'send_at' => null,
        ];

        foreach ($message->getAttachments() as $attachment) {
            /** @var DataPart $attachment */

            if ($attachment->getMediaSubtype() !== self::MAILERSEND_DATA_SUBTYPE) {
                continue;
            }

            return array_merge(
                $defaultValues,
                json_decode($attachment->getBody(), true, 512, JSON_THROW_ON_ERROR)
            );

            // $xx =json_decode($attachment->getBody(), true, 512, JSON_THROW_ON_ERROR);
            // return array_merge(
            //     $defaultValues,
            //     json_decode($attachment->getBody(), true, 512, JSON_THROW_ON_ERROR)
            // );
        }

        return $defaultValues;
    }

    public function __toString(): string
    {
        return 'mailersendbulk';
    }

    public function escapeMarkdown($text)
    {
        $markdown = [
            '%7B',
            '%24',
            '%7D',
            // ... rest of markdown entities
        ];

        $replacements = [
            '{',
            '$',
            '}',
            // ... rest of corresponding escaped markdown
        ];

        return str_replace($markdown, $replacements, $text);
    }
}
