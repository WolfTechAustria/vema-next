<div
    x-data="{
        query: '',
        active: null,
        image: null,
        chapters: @js(collect($manual['chapters'])->map(fn ($chapter) => [
            'id' => $chapter['id'],
            'text' => $chapter['text'],
            'sections' => collect($chapter['sections'])->map(fn ($section) => [
                'id' => $section['id'],
                'text' => $section['text'],
            ])->all(),
        ])->all()),

        get needle() {
            return this.query.trim().toLowerCase();
        },

        chapterById(id) {
            return this.chapters.find(chapter => chapter.id === id);
        },

        sectionVisible(chapterId, sectionId) {
            if (! this.needle) return true;
            const chapter = this.chapterById(chapterId);

            return chapter.text.includes(this.needle)
                || chapter.sections.find(section => section.id === sectionId).text.includes(this.needle);
        },

        chapterVisible(chapterId) {
            if (! this.needle) return true;
            const chapter = this.chapterById(chapterId);

            return chapter.text.includes(this.needle)
                || chapter.sections.some(section => section.text.includes(this.needle));
        },

        get resultCount() {
            if (! this.needle) return null;

            return this.chapters.reduce((count, chapter) => count
                + (chapter.text.includes(this.needle) ? 1 : 0)
                + chapter.sections.filter(section => section.text.includes(this.needle)).length, 0);
        },

        highlight() {
            if (! window.CSS || ! CSS.highlights) return;
            CSS.highlights.delete('manual-search');
            if (this.needle.length < 2) return;

            const ranges = [];
            const walker = document.createTreeWalker(this.$refs.content, NodeFilter.SHOW_TEXT);

            while (walker.nextNode()) {
                const node = walker.currentNode;
                if (node.parentElement.closest('[style*=\'display: none\']')) continue;
                const text = node.nodeValue.toLowerCase();
                let index = text.indexOf(this.needle);

                while (index !== -1) {
                    const range = new Range();
                    range.setStart(node, index);
                    range.setEnd(node, index + this.needle.length);
                    ranges.push(range);
                    index = text.indexOf(this.needle, index + this.needle.length);
                }
            }

            CSS.highlights.set('manual-search', new Highlight(...ranges));
        },

        init() {
            this.$watch('query', () => this.$nextTick(() => this.highlight()));

            const observer = new IntersectionObserver(entries => {
                entries.filter(entry => entry.isIntersecting)
                    .forEach(entry => this.active = entry.target.dataset.chapter);
            }, { rootMargin: '-80px 0px -70% 0px' });

            this.$el.querySelectorAll('[data-chapter]').forEach(element => observer.observe(element));
        },
    }"
    @keydown.escape.window="image = null"
>

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold tracking-tight">
                {{ $manual['title'] }}
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Schritt-für-Schritt-Erklärungen zu allen Bereichen von VEMA.
            </p>
        </div>

        <div class="w-full sm:w-80">
            <label for="manual-search" class="sr-only">Anleitung durchsuchen</label>

            <input
                id="manual-search"
                type="search"
                x-model.debounce.200ms="query"
                placeholder="Anleitung durchsuchen …"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
            >

            <p
                x-show="resultCount !== null"
                x-cloak
                class="mt-1 text-xs text-slate-500"
                x-text="resultCount === 0 ? 'Keine Treffer' : resultCount + (resultCount === 1 ? ' Treffer' : ' Treffer')"
            ></p>
        </div>
    </div>

    <div class="grid gap-8 lg:grid-cols-[15rem_minmax(0,1fr)]">

        {{-- Inhaltsverzeichnis --}}
        <nav class="lg:sticky lg:top-24 lg:max-h-[calc(100vh-7rem)] lg:self-start lg:overflow-y-auto">

            <select
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm lg:hidden"
                @change="document.getElementById($event.target.value)?.scrollIntoView({ behavior: 'smooth' })"
                aria-label="Kapitel wählen"
            >
                @foreach($manual['chapters'] as $chapter)
                    <option value="{{ $chapter['id'] }}">{{ $chapter['title'] }}</option>
                @endforeach
            </select>

            <ul class="hidden space-y-0.5 text-sm lg:block">
                @foreach($manual['chapters'] as $chapter)
                    <li x-show="chapterVisible(@js($chapter['id']))">
                        <a
                            href="#{{ $chapter['id'] }}"
                            class="block rounded-lg px-3 py-1.5 font-medium transition"
                            :class="active === @js($chapter['id'])
                                ? 'bg-slate-900 text-white'
                                : 'text-slate-700 hover:bg-slate-100'"
                        >
                            {{ $chapter['title'] }}
                        </a>

                        @if(count($chapter['sections']) > 0)
                            <ul
                                x-show="active === @js($chapter['id']) || needle"
                                x-collapse
                                class="my-1 ml-3 space-y-0.5 border-l border-slate-200 pl-2"
                            >
                                @foreach($chapter['sections'] as $section)
                                    <li x-show="sectionVisible(@js($chapter['id']), @js($section['id']))">
                                        <a
                                            href="#{{ $section['id'] }}"
                                            class="block rounded px-2 py-1 text-xs text-slate-500 hover:bg-slate-100 hover:text-slate-900"
                                        >
                                            {{ $section['title'] }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>

        </nav>

        {{-- Inhalt --}}
        <div
            x-ref="content"
            class="manual-content min-w-0 space-y-6"
            @click="const img = $event.target.closest('figure img'); if (img) image = { src: img.src, alt: img.alt }"
        >

            @foreach($manual['notes'] as $note)
                {!! $note !!}
            @endforeach

            @foreach($manual['chapters'] as $chapter)
                <section
                    id="{{ $chapter['id'] }}"
                    data-chapter="{{ $chapter['id'] }}"
                    x-show="chapterVisible(@js($chapter['id']))"
                    class="scroll-mt-24 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8"
                >
                    <h2>{{ $chapter['title'] }}</h2>

                    {!! $chapter['html'] !!}

                    @foreach($chapter['sections'] as $section)
                        <div
                            id="{{ $section['id'] }}"
                            x-show="sectionVisible(@js($chapter['id']), @js($section['id']))"
                            class="scroll-mt-24"
                        >
                            <h3>{{ $section['title'] }}</h3>

                            {!! $section['html'] !!}
                        </div>
                    @endforeach
                </section>
            @endforeach

            <p
                x-show="resultCount === 0"
                x-cloak
                class="rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500"
            >
                Zu „<span x-text="query"></span>“ wurde in der Anleitung nichts gefunden.
            </p>

        </div>

    </div>

    {{-- Vergrößerte Abbildung --}}
    <div
        x-show="image"
        x-cloak
        x-transition.opacity
        @click="image = null"
        class="fixed inset-0 z-50 flex cursor-zoom-out flex-col items-center justify-center gap-3 bg-slate-900/80 p-4"
    >
        <img
            :src="image?.src"
            :alt="image?.alt"
            class="max-h-[85vh] max-w-full rounded-lg bg-white shadow-2xl"
        >

        <p class="text-sm text-white" x-text="image?.alt"></p>
    </div>

</div>
