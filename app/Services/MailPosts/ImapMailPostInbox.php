<?php

namespace App\Services\MailPosts;

use App\Models\HomepageSetting;
use RuntimeException;
use Throwable;
use Webklex\PHPIMAP\Attachment;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;

/**
 * Ruft das Beitrags-Postfach der Homepage per IMAP ab (webklex/php-imap).
 */
class ImapMailPostInbox implements MailPostInbox
{
    private ?Client $client = null;

    /**
     * @return iterable<int, IncomingMail>
     */
    public function unread(HomepageSetting $settings): iterable
    {
        $messages = $this->folder($settings)
            ->messages()
            ->unseen()
            ->leaveUnread()
            ->setFetchBody(true)
            ->get();

        foreach ($messages as $message) {
            yield $this->toIncomingMail($message);
        }
    }

    public function markProcessed(HomepageSetting $settings, IncomingMail $mail): void
    {
        /** @var Message $message */
        $message = $mail->handle;

        $message->setFlag('Seen');

        if (filled($settings->mail_posts_processed_folder)) {
            $message->move($settings->mail_posts_processed_folder, expunge: true);
        }
    }

    public function test(HomepageSetting $settings): int
    {
        return $this->folder($settings)->messages()->unseen()->leaveUnread()->count();
    }

    public function disconnect(): void
    {
        try {
            $this->client?->disconnect();
        } catch (Throwable) {
            // Verbindung war bereits weg.
        }

        $this->client = null;
    }

    private function folder(HomepageSetting $settings): Folder
    {
        if (blank($settings->mail_posts_host) || blank($settings->mail_posts_username)) {
            throw new RuntimeException('Für das Beitrags-Postfach sind noch keine Zugangsdaten hinterlegt.');
        }

        $this->client ??= (new ClientManager)->make([
            'host' => $settings->mail_posts_host,
            'port' => $settings->mail_posts_port ?: 993,
            'encryption' => $settings->mail_posts_encryption ?: 'ssl',
            'validate_cert' => (bool) $settings->mail_posts_validate_cert,
            'username' => $settings->mail_posts_username,
            'password' => $settings->mail_posts_password,
            'protocol' => 'imap',
        ]);

        $this->client->connect();

        $path = $settings->mail_posts_folder ?: 'INBOX';
        $folder = $this->client->getFolderByPath($path);

        if (! $folder) {
            throw new RuntimeException('Der Ordner „'.$path.'“ wurde im Postfach nicht gefunden.');
        }

        return $folder;
    }

    private function toIncomingMail(Message $message): IncomingMail
    {
        $from = $message->getFrom()->first();

        $attachments = [];

        foreach ($message->getAttachments() as $attachment) {
            /** @var Attachment $attachment */
            $attachments[] = new IncomingMailAttachment(
                name: (string) ($attachment->getName() ?: 'anhang'),
                mimeType: strtolower((string) ($attachment->getMimeType() ?: $attachment->getContentType())),
                content: (string) $attachment->getContent(),
                inline: strtolower((string) $attachment->getDisposition()) === 'inline',
            );
        }

        $messageId = trim((string) $message->getMessageId(), '<> ');

        return new IncomingMail(
            messageId: $messageId !== '' ? $messageId : 'uid-'.$message->uid.'@'.$message->getFolderPath(),
            fromAddress: strtolower((string) ($from->mail ?? '')),
            fromName: filled($from->personal ?? null) ? (string) $from->personal : null,
            subject: (string) $message->getSubject(),
            html: $message->hasHTMLBody() ? $message->getHTMLBody() : null,
            text: $message->hasTextBody() ? $message->getTextBody() : null,
            attachments: $attachments,
            handle: $message,
        );
    }
}
