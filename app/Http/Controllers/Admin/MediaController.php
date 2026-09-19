<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LibraryAsset;
use App\Services\Media\MediaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class MediaController extends Controller
{
    public function __construct(
        protected MediaService $mediaService
    ) {}

    public function index(): View
    {
        Gate::authorize('admin.media.index');

        return view('admin.media.index');
    }

    public function destroy(LibraryAsset $media): RedirectResponse
    {
        Gate::authorize('delete', $media);

        $this->mediaService->delete($media);

        return redirect()->route('admin.media.index')->with('success', 'File deleted successfully.');
    }
}
