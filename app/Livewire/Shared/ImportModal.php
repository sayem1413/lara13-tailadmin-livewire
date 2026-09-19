<?php

namespace App\Livewire\Shared;

use App\Contracts\Importable;
use App\Services\Import\ImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Backs <x-import.button>: a reusable "Import" modal for any Eloquent
 * model implementing App\Contracts\Importable. Dispatches "imported" once
 * rows are successfully created, so the host index page can refresh its
 * list.
 */
class ImportModal extends Component
{
    use WithFileUploads;

    /** @var class-string<Model&Importable> */
    public string $modelClass;

    public string $permission;

    public string $title = 'Import';

    public bool $show = false;

    public mixed $file = null;

    public ?int $importedCount = null;

    /** @var array<int, array{row: int, errors: array<int, string>}> */
    public array $failures = [];

    /**
     * @param  class-string<Model&Importable>  $modelClass
     */
    public function mount(string $modelClass, string $permission, string $title = 'Import'): void
    {
        $this->modelClass = $modelClass;
        $this->permission = $permission;
        $this->title = $title;
    }

    public function open(): void
    {
        Gate::authorize($this->permission);

        $this->reset('failures', 'importedCount');
        $this->show = true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            // mimes:csv alone rejects real-world CSV files: browsers and
            // OSes commonly report a plain comma-separated file as
            // text/plain rather than the handful of MIME types Laravel's
            // "csv" extension mapping recognizes. extensions: checks the
            // filename the user chose; mimetypes: lists every MIME string
            // actually seen in practice for these three formats, rather
            // than relying on mimes:'s built-in extension-to-MIME guess.
            'file' => [
                'required',
                'file',
                'extensions:xlsx,xls,csv',
                'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        ];
    }

    public function downloadTemplate(ImportService $importService): BinaryFileResponse
    {
        Gate::authorize($this->permission);

        return $importService->downloadTemplate($this->modelClass, 'import-template.xlsx');
    }

    public function updatedFile(ImportService $importService): void
    {
        Gate::authorize($this->permission);

        $this->validate();

        $result = $importService->import($this->modelClass, $this->file);

        $this->importedCount = $result['imported'];
        $this->failures = $result['failures'];
        $this->reset('file');

        if ($this->failures === []) {
            $this->show = false;
            $this->dispatch('toast', type: 'success', message: "Imported {$this->importedCount} record(s).");
            $this->dispatch('imported');
        }
    }

    public function render(): View
    {
        return view('livewire.shared.import-modal');
    }
}
