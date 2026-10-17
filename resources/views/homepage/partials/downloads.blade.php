{{-- PDF-Downloads eines Beitrags. --}}
@if($attachments->isNotEmpty())
    <div class="mt-9 rounded-xl bg-brand-soft px-6 py-5">
        <h2 class="mb-2.5 text-lg font-bold text-brand-deep">Downloads</h2>

        <ul>
            @foreach($attachments as $attachment)
                <li class="py-1.5">
                    <a href="{{ $imageUrls->attachment($attachment) }}" target="_blank" rel="noopener" class="font-semibold text-brand hover:underline">
                        <span class="mr-2.5 inline-block rounded-[3px] bg-accent px-1.5 py-px align-[2px] text-[11px] font-extrabold text-white">PDF</span>{{ $attachment->label }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@endif
