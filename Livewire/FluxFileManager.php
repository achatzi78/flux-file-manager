namespace App\Livewire;

use Arr;
use Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Storage;
use Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Mime\MimeTypes;
use Throwable;

class FluxFileManager extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?string $name = null;

    #[Locked]
    public string $mode = 'picker';    // 'picker' or 'gallery'

    #[Locked]
    public string $category = 'image'; // image or file

    public string $current_path = '';

    #[Locked]
    public array $items = [];

    public ?string $sort_by = null;

    public string $sort_direction = 'asc';

    public string $new_folder_name = '';

    public array $upload_files = [];

    #[Locked]
    public string $home_path = '/';

    #[Locked]
    public array $allowed_types = [];

    #[Locked]
    public int $max_size = 0;

    #[Locked]
    public string $disk;

    public ?string $selected_path = null;

    public ?string $rename_path = null;

    public ?string $rename_name = null;

    public ?string $search_term = null;
    
    /**
     * @throws Throwable
     */
    public function mount(string $mode = 'browse', string $path = '', string $category = 'image', string $name = null): void
    {
        $allowed_modes = [
            'picker',
            'gallery',
        ];
        $allowed_categories = [
            'image',
            'file',
        ];

        throw_unless(in_array($mode, $allowed_modes), \Exception::class, 'Invalid mode');
        throw_unless(in_array($category, $allowed_categories), \Exception::class, 'Invalid category');

        $this->disk = config('flux-file-manager.disk', 'filemanager');
        $this->mode = $mode;
        $this->current_path = $path;
        $this->home_path = $path;
        $this->category = $category;
        $this->allowed_types = config("flux-file-manager.folders.{$category}.valid_mimes", []);
        $this->max_size = config("flux-file-manager.folders.{$category}.max_size", 0);
        $this->name = $name ?? Str::uuid()->toString();

        $this->ensureDefaultFolders();
        $this->loadDirectory();
    }

    #[Computed]
    public function breadcrumbs(): array
    {
        $base = $this->home_path;
        $path = Str::after($this->current_path, $this->home_path);
        $segments = explode('/', $path);
        $breadcrumbs = [];

        foreach ($segments as $segment) {
            if (empty($segment)) {
                continue;
            }
            $breadcrumbs[$segment] = $base .= "/{$segment}";
        }

        return $breadcrumbs;
    }

    public function updatedSearchTerm(): void
    {
        $this->loadDirectory();
    }

    /**
     * @throws ValidationException
     */
    public function updatedUploadFiles(): void
    {
        $this->upload();
    }

    public function loadDirectory(): void
    {
        $this->reset('items', 'sort_by', 'sort_direction');

        $disk = Storage::disk($this->disk);

        $folders = $disk->directories($this->current_path);
        foreach ($folders as $folder) {
            $name = basename($folder);

            if (!empty($this->search_term)) {
                if (!Str::contains($name, $this->search_term, true)) {
                    continue;
                }
            }

            $this->items[] = [
                'id' => md5("folder_{$folder}"),
                'name' => $name,
                'path' => $folder,
                'url' => $disk->url($folder),
                'type' => 'folder',
            ];
        }

        $files = $disk->files($this->current_path);
        foreach ($files as $file) {
            $name = basename($file);

            if (!empty($this->search_term)) {
                if (!Str::contains($name, $this->search_term, true)) {
                    continue;
                }
            }

            $this->items[] = [
                'id' => md5($file),
                'name' => $name,
                'path' => $file,
                'url' => $disk->url($file),
                'size' => round($disk->size($file) / 1024, 2),
                'type' => $disk->mimeType($file),
                'last_modified_at' => Carbon::createFromTimestamp($disk->lastModified($file), auth()->user()->timezone)->format('Y-m-d H:i:s'),
            ];
        }

        if (empty($this->sort_by)) {
            usort($this->items, function ($a, $b) {
                // Folders first
                $a_is_folder = $a['type'] === 'folder';
                $b_is_folder = $b['type'] === 'folder';

                // If one is a folder and the other is not, the folder comes first
                if ($a_is_folder && !$b_is_folder) return -1;
                if (!$a_is_folder && $b_is_folder) return 1;

                // Otherwise, sort alphabetically by name
                return strcasecmp($a['name'], $b['name']);
            });
        }
    }

    public function sortBy($sort_by): void
    {
        // 1) Update sortField & sortDirection
        if ($this->sort_by === $sort_by) {
            $this->sort_direction = $this->sort_direction === 'asc' ? 'desc' : 'asc';
        }
        else {
            $this->sort_by = $sort_by;
            $this->sort_direction = 'asc';
        }

        // 2) Sort in-place
        usort($this->items, function (array $a, array $b) {
            $f = $this->sort_by;
            $v1 = $a[$f] ?? '';
            $v2 = $b[$f] ?? '';

            // string vs numeric
            if (is_string($v1) || is_string($v2)) {
                $cmp = strcasecmp((string)$v1, (string)$v2);
            }
            else {
                $cmp = $v1 <=> $v2;
            }

            return $this->sort_direction === 'asc' ? $cmp : -$cmp;
        });
    }

    public function setPath(string $path): void
    {
        $this->current_path = $path;
        $this->loadDirectory();
    }

    public function select($path): void
    {
        $url = Storage::disk($this->disk)->url($path);
        $this->selected_path = $url;
        $this->dispatch('fileSelected', url: $url, filemanager: $this->name ?? $this->id);
    }

    /**
     * @throws ValidationException
     */
    public function upload(): void
    {
        $this->resetValidation('upload_files');

        try {
            $this->validateOnly('upload_files');
        }
        catch (ValidationException $e) {
            Flux::toast(text: implode(', ', Arr::flatten($e->errors())), variant: 'danger');

            throw $e;
        }

        /* @var TemporaryUploadedFile $file */
        foreach ($this->upload_files as $file) {
            $file->storeAs(
                $this->current_path,
                str(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                    ->slug('_')
                    ->append('.', $file->getClientOriginalExtension()),
                $this->disk
            );
        }

        $this->reset('upload_files');
        $this->loadDirectory();

        Flux::toast(text: __('Upload successful'), variant: 'success');
    }

    public function download(string $path): ?StreamedResponse
    {
        $disk = Storage::disk($this->disk);

        if (!$disk->exists($path)) {
            Flux::toast(text: __('file not found'), variant: 'danger');

            return null;
        }

        return $disk->download($path);
    }

    /**
     * @throws ValidationException
     */
    public function createFolder(): void
    {
        $this->validateOnly('new_folder_name');

        $name = str($this->new_folder_name)->trim()->slug();
        $target = "{$this->current_path}/{$name}";

        Storage::disk($this->disk)->makeDirectory($target);

        $this->current_path = $target;
        $this->reset('new_folder_name');
        $this->loadDirectory();

        $this->modal('createFolderModal')->close();
    }

    public function delete(string $path): void
    {
        $disk = Storage::disk($this->disk);

        if ($disk->exists($path)) {
            // if it's a file, mimeType() returns something; dirs return null
            if ($disk->mimeType($path)) {
                $disk->delete($path);
            }
            else {
                $disk->deleteDirectory($path);
            }

            $this->loadDirectory();
        }
    }

    public function move(string $from, string $to): void
    {
        $destination = trim($to . '/' . basename($from), '/');
        Storage::disk($this->disk)->move($from, $destination);
        $this->loadDirectory();
    }

    /**
     * @throws ValidationException
     * @throws Throwable
     */
    public function rename(): void
    {
        $disk = Storage::disk($this->disk);
        $mime = $disk->mimeType($this->rename_path);
        $parent = dirname($this->rename_path) === '.' ? '' : dirname($this->rename_path);

        //check if rename folder
        if (empty($mime)) {
            $this->validate([
                'rename_name' => $this->rules()['new_folder_name'],
            ]);

            $new_path = $parent === '' ? $this->rename_name : "{$parent}/{$this->rename_name}";

            throw_if($disk->exists($new_path), ValidationException::withMessages([
                'rename_name' => __("A folder named “{$this->rename_name}” already exists here."),
            ]));

            $disk->move($this->rename_path, $new_path);
        }
        else {
            $this->validateOnly('rename_name');

            $ext = strtolower(pathinfo($this->rename_name, PATHINFO_EXTENSION));

            $mime_types = MimeTypes::getDefault();
            $valid_mime_extensions = $mime_types->getExtensions($mime) ?: [];

            if (!in_array($ext, $valid_mime_extensions, true)) {
                throw ValidationException::withMessages([
                    'rename_name' => __("The extension “.{$ext}” does not match the file’s MIME type ({$mime}). Valid extensions for this file are: ") . implode(', ', $valid_mime_extensions),
                ]);
            }

            if (!in_array($ext, $this->allowed_types, true)) {
                throw ValidationException::withMessages([
                    'rename_name' => __("The extension “.{$ext}” is not permitted here. Allowed extensions: ") . implode(', ', $this->allowed_types),
                ]);
            }

            throw_if($disk->exists("{$this->current_path}/{$this->rename_name}"), ValidationException::withMessages([
                'rename_name' => __("A file named “{$this->rename_name}” already exists here."),
            ]));

            $disk->move($this->rename_path, "{$this->current_path}/{$this->rename_name}");
        }

        $this->loadDirectory();

        Flux::toast(text: __('rename successful'), variant: 'success');

        $this->modal('renameModal')->close();
    }

    public function render(): View
    {
        return view('livewire.flux-file-manager');
    }

    protected function ensureDefaultFolders(): void
    {
        $disk = Storage::disk($this->disk);

        foreach (config('flux-file-manager.folders', []) as $settings) {
            $folder = $settings['folder_name'];
            if (!$disk->exists($folder)) {
                $disk->makeDirectory($folder);
            }
        }
    }

    protected function rules(): array
    {
        return [
            'new_folder_name' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Za-z0-9_-]+$/',
                function ($attribute, $value, $fail) {
                    $reserved = ['CON', 'PRN', 'AUX', 'NUL'];
                    for ($i = 1; $i <= 9; $i++) {
                        $reserved[] = "COM{$i}";
                        $reserved[] = "LPT{$i}";
                    }

                    if (in_array(strtoupper($value), $reserved)) {
                        $fail("The {$value} is a reserved name and cannot be used.");
                    }

                    if (preg_match('/[. ]$/', $value)) {
                        $fail("The {$value} cannot end with a space or dot.");
                    }
                },
            ],
            'upload_files' => [
                'required',
                'array',
                'min:1',
            ],
            'upload_files.*' => [
                'required',
                'file',
                'max:' . $this->max_size,
                'mimes:' . implode(',', $this->allowed_types),
            ],
            'rename_name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[A-Za-z0-9_-]+\.[A-Za-z0-9]+$/',
            ],
        ];
    }
}
