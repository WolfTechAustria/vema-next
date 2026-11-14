<?php

namespace App\Notifications;

use App\Enums\PushCategory;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Eine Push-Benachrichtigung aufs Handy bzw. den Desktop. Geht nur an
 * Personen, die die Kategorie eingeschaltet und ein Gerät registriert haben.
 */
class PushMessage extends Notification
{
    public function __construct(
        public PushCategory $category,
        public string $title,
        public string $body,
        public string $url,
        public ?string $tag = null,
    ) {}

    /**
     * @return list<class-string>
     */
    public function via(object $notifiable): array
    {
        return method_exists($notifiable, 'wantsPush') && $notifiable->wantsPush($this->category)
            ? [WebPushChannel::class]
            : [];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $message = (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            ->icon(asset('images/pwa/icon-192.png'))
            ->badge(asset('images/pwa/icon-192.png'))
            ->data(['url' => $this->url])
            ->options(['TTL' => 86400, 'urgency' => 'normal']);

        if ($this->tag) {
            $message->tag($this->tag);
        }

        return $message;
    }
}
