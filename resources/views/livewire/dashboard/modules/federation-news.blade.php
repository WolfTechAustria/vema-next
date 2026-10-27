<ul class="divide-y divide-slate-100">
    @forelse($data['messages'] as $message)
        <li class="py-2">
            <a href="{{ route('federation-inbox') }}" wire:navigate class="block rounded-lg hover:bg-slate-50">
                <div class="text-sm font-medium text-slate-900">
                    {{ $message->subject }}
                    @unless(in_array($message->id, $data['readIds'], true))
                        <span class="ml-1 rounded-full bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-800">neu</span>
                    @endunless
                </div>
                <div class="text-xs text-slate-500">{{ $message->federation->name }} · {{ $message->created_at->format('d.m.Y') }}</div>
            </a>
        </li>
    @empty
        <li class="py-2 text-sm text-slate-500">Noch keine Mitteilungen.</li>
    @endforelse
</ul>
