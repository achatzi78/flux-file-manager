# flux-file-manager

A filemanager for fluxui that can be used as standalone or part of the editor

# How to use


## Standalone
To use it as standalone (gallery mode), you include it in your blade
```blade
<livewire:flux-file-manager
    mode="gallery"
    category="image"
    key="flux-gallery"
    path="images/gallery"
/>
```

## Selector
To use it as a selector for an input or whatever, put it in a modal and listen to the events
Example: in a form blade
```blade
<flux:modal.trigger name="documentPickerModal">
    <flux:button variant="ghost" icon="plus" size="xs" />
</flux:modal.trigger>
<flux:modal name="documentPickerModal" class="min-w-9/10 sm:min-w-xl md:min-w-2xl lg:min-w-4xl xl:min-w-6xl 2xl:min-w-7xl">
    <div class="space-y-6 pt-7">
        <livewire:flux-file-manager
            wire:key="documentPicker"
            name="documentPicker"
            mode="picker"
            category="file"
            path="files"
        />
    </div>
</flux:modal>
```

and in the livewire component
```php
protected $listeners = [
    'fileSelected' => 'addDocument',
];

public function addDocument(string $url, string $filemanager): void
{
    if ($filemanager == 'documentPicker') {
        if (in_array($url, array_column($this->documents, 'url'))) {
            Flux::toast(
                text: __('validation.exists', ['attribute' => trans_choice('document', 1)]),
                variant: 'danger',
            );
        }
        else {
            $this->documents[] = [
                'name' => basename($url),
                'url' => $url,
            ];
        }

        $this->modal('documentPickerModal')->close();
    }
}
```

# In the Editor
Now, for the editor, a larger setup is needed.
First install the tiptap Image extension `npm install @tiptap/extension-image` and then register it for the editor. Since we will be modifying various attributes we extend the Image to include these.

In the app.js file
```javascript
import Image from '@tiptap/extension-image';
document.addEventListener('flux:editor', (e) => {
    e.detail.registerExtension(
        Image.configure({
            inline: true,
            HTMLAttributes: {
                //class: 'rounded shadow',
            },
        }).extend({
            addAttributes() {
                return {
                    ...this.parent?.(),
                    src: {
                        default: null,
                    },
                    alt: {
                        default: '',
                    },
                    style: {
                        default: null,
                        parseHTML: element => element.getAttribute('style'),
                        renderHTML: attributes => {
                            return {
                                style: attributes.style,
                            };
                        },
                    },
                }
            }
        })
    );

    e.detail.init(({ editor }) => {
        if (!editor.options.element) return;

        // find the FluxUI editor wrapper
        const wrapper = editor.options.element.closest('[data-flux-editor]');
        if (wrapper) {
            // stash the Editor instance for Alpine later
            wrapper.__editor = editor;
        }
});
```

In the initialization of the editor, add the button for the image (resources/view/flux/editor/image.blade)
```blade
<flux:editor
    toolbar="heading | bold italic strike underline align | bullet ordered blockquote | subscript superscript highlight | link image | code | view-source ~ undo | redo"
    wire:key="descriptionEditor"
    id="descriptionEditor"
    name="description_editor"
    wire:model="description"
    label="{{ __('description') }}"
    :invalid="$errors->has('description')"
/>
```


Please note that the id attribute in the editor is important because this way the filemanager modal knows with which editor to communicate. You can have multiple editors in the same page, as long as each one has each unique id. When you click the toolbar button, the popup will open and then click on the browse button to open a new modal with the filemanager. When you select an image, the attributes will be populated (src, alt, width, height, border, radius and style). Similarly, when you double click an image in the editor (or single click and then the toolbar button) the attributes will be populated from that.
