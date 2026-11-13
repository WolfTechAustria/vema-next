/*
 * Hybrider Editor für die Vereinshomepage: Text wie gewohnt im visuellen
 * Editor (Quill), dazu HTML-Blöcke (Tabellen, Bilder, Videos …), die Quill
 * unverändert als Vorschau mitführt, und ein Umschalter auf den Quelltext.
 *
 * HTML-Blöcke werden als <div class="hp-html">…</div> gespeichert. Alles,
 * was Quill nicht selbst abbilden kann, wird beim Laden automatisch in
 * solche Blöcke gepackt, damit nichts verloren geht.
 */

/**
 * Elemente, die Quill selbst darstellen kann (ohne Inline-Styles,
 * IDs und fremde Kind-Elemente).
 */
const QUILL_BLOCKS = ['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'UL', 'OL', 'BLOCKQUOTE'];
const QUILL_INLINE = ['STRONG', 'B', 'EM', 'I', 'U', 'S', 'A', 'SPAN', 'BR'];
const FOREIGN_SELECTOR = 'img, iframe, table, div, section, figure, hr, pre, code, small, sub, sup, h4, h5, h6, [style], [id]';

const TOOLBARS = {
    full: [
        [{ header: [2, 3, false] }],
        ['bold', 'italic', 'underline'],
        ['link', 'blockquote'],
        [{ list: 'ordered' }, { list: 'bullet' }],
        ['html-block'],
        ['clean'],
    ],
    compact: [
        ['bold', 'italic', 'underline'],
        ['link'],
        [{ list: 'ordered' }, { list: 'bullet' }],
        ['html-block'],
        ['clean'],
    ],
};

let blotRegistered = false;

function registerHtmlBlot(Quill) {
    if (blotRegistered) {
        return;
    }

    const BlockEmbed = Quill.import('blots/block/embed');

    class HtmlBlock extends BlockEmbed {
        static blotName = 'htmlBlock';
        static tagName = 'DIV';
        static className = 'hp-html';

        static create(value) {
            const node = super.create();
            node.setAttribute('contenteditable', 'false');
            node.innerHTML = value;

            return node;
        }

        static value(node) {
            return node.innerHTML;
        }
    }

    Quill.register(HtmlBlock, true);
    blotRegistered = true;
}

function isQuillCompatible(node) {
    if (node.nodeType !== Node.ELEMENT_NODE) {
        return true;
    }

    if (node.classList.contains('hp-html')) {
        return true;
    }

    const tag = node.tagName;

    if (!QUILL_BLOCKS.includes(tag) && !QUILL_INLINE.includes(tag)) {
        return false;
    }

    return !node.hasAttribute('style') && !node.hasAttribute('id') && !node.querySelector(FOREIGN_SELECTOR);
}

/**
 * Packt alle Top-Level-Elemente, die Quill nicht darstellen kann, in
 * HTML-Blöcke (aufeinanderfolgende in einen gemeinsamen Block).
 */
export function wrapForeignHtml(html) {
    const template = document.createElement('template');
    template.innerHTML = html || '';

    const result = document.createElement('div');
    let block = null;

    Array.from(template.content.childNodes).forEach((node) => {
        const isBlankText = node.nodeType === Node.TEXT_NODE && node.textContent.trim() === '';

        if (isBlankText) {
            block?.appendChild(node);

            return;
        }

        if (node.nodeType === Node.COMMENT_NODE) {
            return;
        }

        if (isQuillCompatible(node)) {
            block = null;
            result.appendChild(node);

            return;
        }

        if (!block) {
            block = document.createElement('div');
            block.className = 'hp-html';
            result.appendChild(block);
        }

        block.appendChild(node);
    });

    result.querySelectorAll('.hp-html').forEach((element) => {
        element.innerHTML = element.innerHTML.trim();
    });

    return result.innerHTML;
}

/**
 * Quelltext-Ansicht: Block-Hüllen entfernen, ein Element je Zeile.
 */
export function unwrapHtmlBlocks(html) {
    const template = document.createElement('template');
    template.innerHTML = html || '';

    template.content.querySelectorAll('.hp-html').forEach((block) => {
        block.replaceWith(...block.childNodes);
    });

    return Array.from(template.content.childNodes)
        .map((node) => (node.nodeType === Node.ELEMENT_NODE ? node.outerHTML : node.textContent.trim()))
        .filter((part) => part !== '' && part !== '<p><br></p>')
        .join('\n');
}

export function registerHomepageEditor(Alpine) {
    Alpine.data('homepageEditor', (property, initialHtml, toolbar = 'full') => {
        let quill = null;
        let editingBlot = null;
        let insertIndex = 0;

        return {
            mode: 'editor',
            source: '',
            dialogOpen: false,
            blockHtml: '',
            isEditingBlock: false,

            init() {
                const Quill = window.Quill;
                registerHtmlBlot(Quill);

                quill = new Quill(this.$refs.editor, {
                    theme: 'snow',
                    modules: {
                        toolbar: {
                            container: TOOLBARS[toolbar] ?? TOOLBARS.full,
                            handlers: {
                                'html-block': () => this.openBlockDialog(),
                            },
                        },
                    },
                });

                const button = quill.getModule('toolbar').container.querySelector('.ql-html-block');

                if (button) {
                    button.textContent = '</>';
                    button.title = 'HTML-Block einfügen (Tabelle, Bild, Video …)';
                    button.classList.add('hp-editor__html-button');
                }

                this.load(initialHtml);

                quill.on('text-change', () => this.sync(quill.root.innerHTML));

                quill.root.addEventListener('dblclick', (event) => {
                    const node = event.target.closest('.hp-html');

                    if (node) {
                        this.openBlockDialog(Quill.find(node));
                    }
                });
            },

            load(html) {
                quill.setContents(quill.clipboard.convert({ html: wrapForeignHtml(html) }), 'silent');
                quill.history.clear();
            },

            sync(html) {
                this.$wire.set(property, html, false);
            },

            showSource() {
                if (this.mode === 'source') {
                    return;
                }

                this.source = unwrapHtmlBlocks(quill.root.innerHTML);
                this.mode = 'source';
            },

            showEditor() {
                if (this.mode === 'editor') {
                    return;
                }

                this.load(this.source);
                this.mode = 'editor';
                this.sync(quill.root.innerHTML);
            },

            sourceChanged() {
                this.sync(this.source);
            },

            openBlockDialog(blot = null) {
                editingBlot = blot;
                insertIndex = quill.getSelection()?.index ?? quill.getLength() - 1;
                this.isEditingBlock = blot !== null;
                this.blockHtml = blot ? blot.domNode.innerHTML : '';
                this.dialogOpen = true;
                this.$nextTick(() => this.$refs.blockInput?.focus());
            },

            closeBlockDialog() {
                this.dialogOpen = false;
                editingBlot = null;
            },

            saveBlock() {
                const html = this.blockHtml.trim();
                let index;

                if (editingBlot) {
                    index = quill.getIndex(editingBlot);
                    quill.deleteText(index, 1, 'user');
                } else {
                    index = insertIndex;
                }

                if (html !== '') {
                    quill.insertEmbed(index, 'htmlBlock', html, 'user');
                    quill.setSelection(index + 1, 0, 'silent');
                }

                this.closeBlockDialog();
            },

            removeBlock() {
                this.blockHtml = '';
                this.saveBlock();
            },
        };
    });
}
