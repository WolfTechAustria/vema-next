import Quill from 'quill';
import 'quill/dist/quill.snow.css';
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';

window.Quill = Quill;
window.Cropper = Cropper;

document.addEventListener('alpine:init', () => {

    /*
     * Profilbild zuschneiden: Bild wählen, quadratischen Ausschnitt per
     * Ziehen/Zoomen festlegen, als 400×400-JPEG an Livewire hochladen
     * (Eigenschaft „photo“ der Komponente).
     */
    Alpine.data('photoCropper', () => ({

        open: false,
        busy: false,
        error: null,
        cropper: null,

        choose() {
            this.error = null;
            this.$refs.fileInput.value = '';
            this.$refs.fileInput.click();
        },

        load(event) {
            const file = event.target.files[0];

            if (! file) {
                return;
            }

            if (! file.type.startsWith('image/')) {
                this.error = 'Bitte ein Bild auswählen (JPG, PNG oder WEBP).';

                return;
            }

            const reader = new FileReader();

            reader.onload = () => {
                this.open = true;

                this.$nextTick(() => {
                    this.destroy();
                    this.$refs.image.src = reader.result;

                    this.cropper = new Cropper(this.$refs.image, {
                        aspectRatio: 1,
                        viewMode: 1,
                        dragMode: 'move',
                        autoCropArea: 0.9,
                        background: false,
                        guides: false,
                        center: true,
                        toggleDragModeOnDblclick: false,
                    });
                });
            };

            reader.onerror = () => {
                this.error = 'Das Bild konnte nicht gelesen werden.';
            };

            reader.readAsDataURL(file);
        },

        zoom(ratio) {
            this.cropper?.zoom(ratio);
        },

        rotate() {
            this.cropper?.rotate(90);
        },

        cancel() {
            this.open = false;
            this.destroy();
        },

        apply() {
            if (! this.cropper) {
                return;
            }

            const canvas = this.cropper.getCroppedCanvas({
                width: 400,
                height: 400,
                fillColor: '#ffffff',
                imageSmoothingQuality: 'high',
            });

            if (! canvas) {
                this.error = 'Das Bild konnte nicht zugeschnitten werden. Bitte ein anderes Format (JPG oder PNG) verwenden.';

                return;
            }

            this.busy = true;

            canvas.toBlob((blob) => {
                const photo = new File([blob], 'profilbild.jpg', { type: 'image/jpeg' });

                this.$wire.upload(
                    'photo',
                    photo,
                    () => {
                        this.busy = false;
                        this.cancel();
                    },
                    () => {
                        this.busy = false;
                        this.error = 'Das Hochladen ist fehlgeschlagen. Bitte erneut versuchen.';
                    },
                );
            }, 'image/jpeg', 0.9);
        },

        destroy() {
            this.cropper?.destroy();
            this.cropper = null;
        },

    }));

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
