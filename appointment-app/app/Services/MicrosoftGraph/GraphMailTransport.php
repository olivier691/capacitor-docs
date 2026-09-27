<?php

namespace App\Services\MicrosoftGraph;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

/**
 * Transport Laravel Mail qui envoie les e-mails via l'API Microsoft Graph (sendMail)
 * depuis la boîte Microsoft 365 de l'expéditeur (MAIL_FROM_ADDRESS).
 */
class GraphMailTransport extends AbstractTransport
{
    public function __construct(private readonly GraphClient $graph)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $sender = $email->getFrom()[0]->getAddress();

        $this->graph->request()->post(GraphClient::userPath($sender).'/sendMail', [
            'message' => $this->toGraphMessage($email),
            'saveToSentItems' => false,
        ]);
    }

    private function toGraphMessage(Email $email): array
    {
        $recipients = fn (array $addresses) => array_map(
            fn (Address $a) => ['emailAddress' => array_filter(['address' => $a->getAddress(), 'name' => $a->getName()])],
            $addresses,
        );

        $html = $email->getHtmlBody();

        return array_filter([
            'subject' => $email->getSubject(),
            'body' => [
                'contentType' => $html !== null ? 'HTML' : 'Text',
                'content' => (string) ($html ?? $email->getTextBody()),
            ],
            'toRecipients' => $recipients($email->getTo()),
            'ccRecipients' => $recipients($email->getCc()),
            'bccRecipients' => $recipients($email->getBcc()),
            'replyTo' => $recipients($email->getReplyTo()),
        ]);
    }

    public function __toString(): string
    {
        return 'microsoft-graph';
    }
}
