<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        {{ $circular->subject ?: $circular->title }}
    </title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])
</head>

<body class="bg-slate-100 text-slate-900">

<div class="mx-auto max-w-4xl p-6">

    <div class="mb-4">
        <button
            type="button"
            onclick="window.close()"
            class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
        >
            Fenster schließen
        </button>
    </div>


    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

        {{-- Kopf --}}
        <div class="border-b border-slate-200 px-6 py-5">

            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                E-Mail
            </div>

            <h1 class="mt-1 text-xl font-semibold text-slate-900">
                {{ $circular->subject ?: $circular->title }}
            </h1>


            <div class="mt-4 grid gap-2 text-sm text-slate-600">

                <div>
                    <span class="font-medium text-slate-500">
                        An:
                    </span>

                    {{ $member->full_name }}
                </div>

                <div>
                    <span class="font-medium text-slate-500">
                        E-Mail:
                    </span>

                    {{ $recipient->email }}
                </div>

                @if($recipient->sent_at)

                    <div>
                        <span class="font-medium text-slate-500">
                            Versandt:
                        </span>

                        {{ $recipient->sent_at->format('d.m.Y H:i') }}
                    </div>

                @endif

            </div>

        </div>


        {{-- Mailinhalt --}}
        <div class="px-6 py-6">

            <div class="prose max-w-none">
                {!! $body !!}
            </div>

        </div>


        {{-- Anhänge --}}
        @if($circular->attachments->isNotEmpty())

            <div class="border-t border-slate-200 bg-slate-50 px-6 py-5">

                <div class="mb-3 text-sm font-semibold text-slate-700">
                    Anhänge
                </div>

                <div class="space-y-2">

                    @foreach($circular->attachments as $attachment)

                        <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-4 py-3">

                            <div class="min-w-0">

                                <a
                                    href="{{ route('circular-attachments.show', $attachment) }}"
                                    target="_blank"
                                    class="truncate text-sm font-medium text-blue-600 hover:text-blue-800"
                                >
                                    📎 {{ $attachment->file_name }}
                                </a>

                                @if($attachment->file_size)

                                    <div class="mt-1 text-xs text-slate-500">

                                        {{
                                            number_format(
                                                $attachment->file_size / 1024 / 1024,
                                                2,
                                                ',',
                                                '.'
                                            )
                                        }}
                                        MB

                                    </div>

                                @endif

                            </div>

                        </div>

                    @endforeach

                </div>

            </div>

        @endif

    </div>

</div>

</body>

</html>
