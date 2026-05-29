<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentController extends Controller
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file'            => ['required', 'file', 'max:10240'], // 10 MB
            'attachable_type' => ['required', 'string'],
            'attachable_id'   => ['required', 'integer'],
        ]);

        $file = $request->file('file');
        $mime = $file->getMimeType();

        if (! in_array($mime, self::ALLOWED_MIME_TYPES)) {
            return back()->withErrors(['file' => 'File type not allowed.']);
        }

        // Resolve morph class
        $morphMap = [
            'ris'       => \App\Models\RisRequest::class,
            'transfer'  => \App\Models\DepartmentTransfer::class,
            'assembly'  => \App\Models\Assembly::class,
            'iar'       => \App\Models\IarRecord::class,
            'item'      => \App\Models\Item::class,
        ];

        $modelClass = $morphMap[$request->attachable_type] ?? null;
        if (! $modelClass) {
            return back()->withErrors(['attachable_type' => 'Invalid attachment target.']);
        }

        $model = $modelClass::findOrFail($request->attachable_id);

        // Store file
        $filename  = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $file->storeAs('attachments', $filename, 'public');

        Attachment::create([
            'attachable_type'  => $modelClass,
            'attachable_id'    => $model->id,
            'filename'         => $filename,
            'original_name'    => $file->getClientOriginalName(),
            'mime_type'        => $mime,
            'size'             => $file->getSize(),
            'uploaded_by_id'   => auth()->id(),
        ]);

        return back()->with('success', 'File uploaded successfully.');
    }

    public function destroy(Attachment $attachment): RedirectResponse
    {
        if ($attachment->uploaded_by_id !== auth()->id() && ! auth()->user()->isAdmin()) {
            abort(403);
        }

        Storage::disk('public')->delete("attachments/{$attachment->filename}");
        $attachment->delete();

        return back()->with('success', 'Attachment deleted.');
    }
}
