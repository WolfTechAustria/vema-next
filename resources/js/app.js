import Quill from 'quill';
import 'quill/dist/quill.snow.css';

window.Quill = Quill;

document.addEventListener('alpine:init', () => {

    Alpine.data('templateEditor', (bodyHtml) => ({

        quill: null,
        bodyHtml: bodyHtml,

        init() {

            this.quill = new Quill(
                this.$refs.editor,
                {
                    theme: 'snow',

                    modules: {
                        toolbar: [
                            ['bold', 'italic', 'underline'],
                            [{ size: ['small', false, 'large', 'huge'] }],
                            [{ color: [] }],
                            [{ align: [] }],
                            [{ list: 'ordered' }, { list: 'bullet' }],
                            ['clean']
                        ]
                    }
                }
            );

            this.quill.root.innerHTML =
                this.bodyHtml || '';

            this.quill.on('text-change', () => {

                this.bodyHtml =
                    this.quill.root.innerHTML;

                this.$wire.set(
                    'bodyHtml',
                    this.bodyHtml,
                    false
                );
            });

            this.$watch(
                'bodyHtml',
                (value) => {

                    const html = value || '';

                    if (
                        this.quill &&
                        this.quill.root.innerHTML !== html
                    ) {
                        this.quill.root.innerHTML = html;
                    }
                }
            );
        }

    }));

});
