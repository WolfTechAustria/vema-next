<?php

namespace App\Mail;

use App\Models\InventoryItem;
use App\Models\InventoryLoan;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Sammel-Erinnerung an Admins und Zeugwarte: fällige Prüfungen und
 * überfällige Rückgaben.
 */
class InventoryReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, InventoryItem>  $inspections
     * @param  Collection<int, InventoryLoan>  $returns
     */
    public function __construct(public Collection $inspections, public Collection $returns) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Material: fällige Prüfungen und Rückgaben');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.inventory-reminder', with: [
            'inventoryUrl' => route('inventory.index'),
        ]);
    }
}
