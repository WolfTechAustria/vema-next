<?php

use App\Services\UploadLimits;

it('formats limits in megabytes', function () {
    expect(UploadLimits::label(2048))->toBe('2 MB')
        ->and(UploadLimits::label(20480))->toBe('20 MB')
        ->and(UploadLimits::label(1536))->toBe('1,5 MB');
});

it('never allows more than the server or the app limit', function () {
    $serverMaxKb = app(UploadLimits::class)->serverMaxKb();

    expect(app(UploadLimits::class)->effectiveKb(10240))->toBe($serverMaxKb === null ? 10240 : min(10240, $serverMaxKb));
});

it('raises the livewire limit for temporary uploads to 25 MB', function () {
    expect(config('livewire.temporary_file_upload.rules'))->toBe(['required', 'file', 'max:25600']);
});

it('replaces raw upload errors with an understandable message', function () {
    $this->withViewErrors(['newAttachments.0' => 'validation.uploaded'])
        ->blade('<x-upload-error field="newAttachments" :max-kb="2048" />')
        ->assertSee('Die Datei konnte nicht hochgeladen werden – vermutlich ist sie zu groß. Erlaubt sind höchstens 2 MB je Datei.')
        ->assertDontSee('validation.uploaded');
});

it('keeps other upload errors unchanged', function () {
    $this->withViewErrors(['newAttachments.0' => 'Die Datei ist kein gültiges PDF.'])
        ->blade('<x-upload-error field="newAttachments" :max-kb="2048" />')
        ->assertSee('Die Datei ist kein gültiges PDF.');
});

it('checks the file size in the browser before uploading', function () {
    $this->blade('<x-upload-guard :max-kb="2048"><input type="file" wire:model="newAttachments"></x-upload-guard>')
        ->assertSee('x-on:change.capture', false)
        ->assertSee('file.size > 2097152', false)
        ->assertSee('Erlaubt sind höchstens 2 MB je Datei.', false);
});
