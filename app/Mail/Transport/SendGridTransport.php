<?php

namespace App\Mail\Transport;

use SendGrid;
use SendGrid\Mail\Mail as SendGridMail;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Part\DataPart;

class SendGridTransport extends AbstractTransport
{
    public function __construct(
        protected string $apiKey,
        protected bool $euDataResidency = false,
        protected int $timeout = 10
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $original = $message->getOriginalMessage();

        if (!$original instanceof Email) {
            throw new \RuntimeException('SendGrid transport only supports Symfony Email messages.');
        }

        $sgMail = new SendGridMail();

        $from = $this->firstAddress($original->getFrom());
        if ($from) {
            $sgMail->setFrom($from->getAddress(), $from->getName());
        }

        foreach ($original->getTo() as $to) {
            $sgMail->addTo($to->getAddress(), $to->getName());
        }

        foreach ($original->getCc() as $cc) {
            $sgMail->addCc($cc->getAddress(), $cc->getName());
        }

        foreach ($original->getBcc() as $bcc) {
            $sgMail->addBcc($bcc->getAddress(), $bcc->getName());
        }

        foreach ($original->getReplyTo() as $replyTo) {
            $sgMail->setReplyTo($replyTo->getAddress(), $replyTo->getName());
        }

        $subject = (string) $original->getSubject();
        if ($subject !== '') {
            $sgMail->setSubject($subject);
        }

        $textBody = $original->getTextBody();
        if (!empty($textBody)) {
            $sgMail->addContent('text/plain', $textBody);
        }

        $htmlBody = $original->getHtmlBody();
        if (!empty($htmlBody)) {
            $sgMail->addContent('text/html', $htmlBody);
        }

        $headers = $original->getHeaders();
        $this->addCustomHeaders($headers, $sgMail);
        $this->addAttachments($original, $sgMail);

        $sendGrid = new SendGrid($this->apiKey);
        if ($this->euDataResidency) {
            $sendGrid->setDataResidency('eu');
        }

        $response = $sendGrid->send($sgMail);

        if ($response->statusCode() >= 400) {
            throw new \RuntimeException(
                'SendGrid API request failed with status '.$response->statusCode().': '.$response->body()
            );
        }
    }

    public function __toString(): string
    {
        return 'sendgrid';
    }

    /**
     * @param  Address[]  $addresses
     */
    protected function firstAddress(array $addresses): ?Address
    {
        return $addresses[0] ?? null;
    }

    protected function addCustomHeaders(Headers $headers, SendGridMail $sgMail): void
    {
        foreach ($headers->all() as $header) {
            $name = $header->getName();

            if (in_array(strtolower($name), [
                'from', 'to', 'cc', 'bcc', 'reply-to', 'subject',
                'content-type', 'mime-version', 'date', 'message-id',
            ], true)) {
                continue;
            }

            $sgMail->addHeader($name, $header->getBodyAsString());
        }
    }

    protected function addAttachments(Email $email, SendGridMail $sgMail): void
    {
        foreach ($email->getAttachments() as $attachment) {
            if (!$attachment instanceof DataPart) {
                continue;
            }

            $body = $attachment->getBody();
            $contents = is_resource($body) ? stream_get_contents($body) : (string) $body;
            if ($contents === false) {
                $contents = '';
            }

            $sgMail->addAttachment(
                base64_encode($contents),
                $attachment->getMediaType().'/'.$attachment->getMediaSubtype(),
                $attachment->getFilename() ?? 'attachment',
                'attachment'
            );
        }
    }
}
