<?php

namespace App\Services\MailPosts;

use App\Enums\PostStatus;
use App\Mail\MailPostReceivedMail;
use App\Models\HomepageSetting;
use App\Models\Post;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\HomepageFileService;
use App\Services\HomepageImageService;
use App\Services\HtmlSanitizer;
use App\Services\ImapSentMailService;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Macht aus berechtigten E-Mails des Beitrags-Postfachs Beitrags-Entwürfe:
 * Betreff = Titel, Text = Inhalt, Bilder = Galerie, PDFs = Downloads.
 */
class MailPostImporter
{
    private const IMAGE_TYPES = ['image/jpeg', 'image/jpg', 'image/pjpeg', 'image/png', 'image/webp'];

    public function __construct(
        private MailPostInbox $inbox,
        private HtmlSanitizer $sanitizer,
        private HomepageImageService $images,
        private HomepageFileService $files,
    ) {}

    /**
     * @return array{created: int, rejected: int, duplicates: int, failed: int}
     */
    public function fetch(HomepageSetting $settings): array
    {
        $result = ['created' => 0, 'rejected' => 0, 'duplicates' => 0, 'failed' => 0];

        try {
            foreach ($this->inbox->unread($settings) as $mail) {
                try {
                    $outcome = $this->process($settings, $mail);
                    $result[$outcome]++;
                    $this->inbox->markProcessed($settings, $mail);
                } catch (Throwable $exception) {
                    $result['failed']++;
                    report($exception);
                }
            }

            $settings->update(['mail_posts_checked_at' => now(), 'mail_posts_last_error' => null]);
        } catch (Throwable $exception) {
            $settings->update([
                'mail_posts_checked_at' => now(),
                'mail_posts_last_error' => Str::limit($exception->getMessage(), 490),
            ]);

            throw $exception;
        } finally {
            $this->inbox->disconnect();
        }

        return $result;
    }

    /**
     * @return 'created'|'rejected'|'duplicates'
     */
    public function process(HomepageSetting $settings, IncomingMail $mail): string
    {
        $user = $this->authorizedUser($mail->fromAddress);

        if ($user === null && ! in_array(strtolower($mail->fromAddress), $settings->allowedMailPostSenders(), true)) {
            // Bewusst keine Antwort an unbekannte Absender (kein Spam-Rückläufer).
            return 'rejected';
        }

        if (Post::query()->where('mail_message_id', $mail->messageId)->exists()) {
            return 'duplicates';
        }

        $title = $this->title($mail->subject);

        $post = Post::create([
            'title' => $title,
            'slug' => Post::uniqueSlug($title),
            'body_html' => $this->body($mail) ?: null,
            'status' => PostStatus::Draft,
            'created_by' => $user?->id,
            'mail_message_id' => Str::limit($mail->messageId, 255, ''),
            'submitted_by_email' => Str::limit(strtolower($mail->fromAddress), 190, ''),
        ]);

        $this->storeAttachments($post, $mail->attachments);

        ActivityLogger::log(
            'homepage.post_received_by_mail',
            'Homepage-Beitrag „'.$post->title.'“ ist per E-Mail von '.$mail->fromAddress.' als Entwurf eingegangen.',
            'Post',
            $post->postID
        );

        $this->confirm($post, $mail);

        return 'created';
    }

    private function authorizedUser(string $address): ?User
    {
        if ($address === '') {
            return null;
        }

        $users = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($address)])
            ->where('enabled', true)
            ->get();

        return $users->first(fn (User $user) => $user->hasAnyRole(['admin', 'homepage']));
    }

    private function title(string $subject): string
    {
        $title = trim((string) preg_replace('/^\s*((re|aw|fw|fwd|wg|antw)\s*:\s*)+/iu', '', $subject));

        return Str::limit($title !== '' ? $title : 'Beitrag vom '.now()->format('d.m.Y'), 200, '');
    }

    /**
     * Klartext ist bei Mail-Programmen zuverlässiger als ihr HTML (Gmail,
     * Outlook) – Signatur und zitierte Antworten lassen sich sauber kappen.
     */
    private function body(IncomingMail $mail): string
    {
        if (filled($mail->text)) {
            return $this->sanitizer->sanitize($this->textToHtml($mail->text));
        }

        if (filled($mail->html)) {
            return $this->sanitizer->sanitize($this->cleanHtml($mail->html));
        }

        return '';
    }

    public function textToHtml(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Signatur („-- “) und zitierte Antworten abschneiden.
        $text = (string) preg_split('/^-- ?$/m', $text)[0];
        $text = (string) preg_split('/^(Am .+ schrieb .+:|On .+ wrote:|-----\s*(Original|Ursprüngliche) Nachricht\s*-----)\s*$/mi', $text)[0];
        $text = (string) preg_replace('/^>.*$/m', '', $text);

        $html = '';

        foreach (preg_split('/\n\s*\n/', trim($text)) ?: [] as $block) {
            $lines = array_values(array_filter(array_map('rtrim', explode("\n", trim($block))), fn ($line) => $line !== ''));

            if ($lines === []) {
                continue;
            }

            $bullets = array_filter($lines, fn ($line) => preg_match('/^\s*[-*•]\s+/u', $line));
            $numbers = array_filter($lines, fn ($line) => preg_match('/^\s*\d+[.)]\s+/', $line));

            if (count($bullets) === count($lines)) {
                $html .= '<ul>'.implode('', array_map(fn ($line) => '<li>'.$this->inline((string) preg_replace('/^\s*[-*•]\s+/u', '', $line)).'</li>', $lines)).'</ul>';
            } elseif (count($numbers) === count($lines)) {
                $html .= '<ol>'.implode('', array_map(fn ($line) => '<li>'.$this->inline((string) preg_replace('/^\s*\d+[.)]\s+/', '', $line)).'</li>', $lines)).'</ol>';
            } else {
                $html .= '<p>'.implode('<br>', array_map(fn ($line) => $this->inline(trim($line)), $lines)).'</p>';
            }
        }

        return $html;
    }

    /**
     * Text escapen und Web-Adressen klickbar machen.
     */
    private function inline(string $text): string
    {
        return (string) preg_replace_callback(
            '#\bhttps?://[^\s<>"]+#i',
            fn (array $match) => '<a href="'.$match[0].'">'.$match[0].'</a>',
            e($text)
        );
    }

    private function cleanHtml(string $html): string
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div id="mail-root">'.$html.'</div>', LIBXML_NONET);
        libxml_clear_errors();

        $xpath = new DOMXPath($document);
        $remove = '//script|//style|//head|//img|//blockquote[@type="cite"]'
            .'|//*[contains(@class,"gmail_quote") or contains(@class,"gmail_signature") or contains(@class,"moz-signature") or contains(@id,"divRplyFwdMsg") or contains(@id,"Signature")]';

        foreach (iterator_to_array($xpath->query($remove)) as $node) {
            $node->parentNode?->removeChild($node);
        }

        $inner = '';

        foreach ($document->getElementById('mail-root')?->childNodes ?? [] as $child) {
            $inner .= $document->saveHTML($child);
        }

        // Zeilen-Container (div) zu Absätzen, Rest entpacken.
        $inner = (string) preg_replace(['#<div\b[^>]*>#i', '#</div>#i', '#<(/?)h1\b[^>]*>#i', '#<(/?)h[4-6]\b[^>]*>#i'], ['<p>', '</p>', '<$1h2>', '<$1h3>'], $inner);
        $inner = strip_tags($inner, '<p><br><h2><h3><strong><b><em><i><u><a><ul><ol><li>');
        $inner = (string) preg_replace('#<p>\s*(?:&nbsp;|\xC2\xA0|<br\s*/?>|\s)*</p>#iu', '', $inner);

        return $inner;
    }

    /**
     * @param  list<IncomingMailAttachment>  $attachments
     */
    private function storeAttachments(Post $post, array $attachments): void
    {
        $sort = 1;

        foreach ($attachments as $attachment) {
            $isImage = in_array($attachment->mimeType, self::IMAGE_TYPES, true);
            $isPdf = $attachment->mimeType === 'application/pdf' || str_ends_with(strtolower($attachment->name), '.pdf');

            if (! $isImage && ! $isPdf) {
                continue;
            }

            $maxBytes = ($isImage ? HomepageImageService::MAX_SIZE_KB : HomepageFileService::MAX_SIZE_KB) * 1024;

            if (strlen($attachment->content) > $maxBytes) {
                continue;
            }

            $path = tempnam(sys_get_temp_dir(), 'mailpost');
            file_put_contents($path, $attachment->content);
            $upload = new UploadedFile($path, $attachment->name, $attachment->mimeType, null, true);

            try {
                if ($isImage) {
                    $this->images->storePostImage($post, $upload);
                } else {
                    $post->attachments()->create([
                        'label' => Str::limit(pathinfo($attachment->name, PATHINFO_FILENAME) ?: 'Download', 200, ''),
                        'file_path' => $this->files->storePdf($upload, 'homepage/posts/'.$post->postID),
                        'file_size' => strlen($attachment->content),
                        'sort' => $sort++,
                    ]);
                }
            } catch (Throwable $exception) {
                Log::warning('Anhang einer Beitrags-Mail konnte nicht übernommen werden.', [
                    'postID' => $post->postID,
                    'file' => $attachment->name,
                    'error' => $exception->getMessage(),
                ]);
            } finally {
                @unlink($path);
            }
        }
    }

    private function confirm(Post $post, IncomingMail $mail): void
    {
        $confirmation = new MailPostReceivedMail($post->fresh(['images', 'attachments']));
        $rawMessage = null;

        $confirmation->withSymfonyMessage(function ($message) use (&$rawMessage) {
            $rawMessage = $message->toString();
        });

        try {
            Mail::to($mail->fromAddress, $mail->fromName)->send($confirmation);
        } catch (Throwable $exception) {
            Log::warning('Bestätigung für einen Beitrag per E-Mail konnte nicht gesendet werden.', [
                'postID' => $post->postID,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        if ($rawMessage) {
            try {
                app(ImapSentMailService::class)->append($rawMessage);
            } catch (Throwable $exception) {
                Log::warning('Bestätigung für einen Beitrag per E-Mail wurde gesendet, aber nicht im IMAP-Gesendet-Ordner gespeichert.', [
                    'postID' => $post->postID,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }
}
