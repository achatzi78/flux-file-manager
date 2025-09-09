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

Then use the toolbar button for the editor (resources/views/flux/editor/image.blade)
