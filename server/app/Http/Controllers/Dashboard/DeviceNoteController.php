<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceNote;
use App\Models\NoteAttachment;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class DeviceNoteController extends Controller
{
    public function store(Request $request, Device $device): RedirectResponse
    {
        $data = $this->validated($request);
        $data['device_id'] = $device->id;
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $note = DeviceNote::query()->create($data);
        $this->storeAttachments($request, $note);

        Audit::log(
            action: 'device_note_created',
            entityType: DeviceNote::class,
            entityId: $note->id,
            metadata: ['device_id' => $device->id, 'category' => $note->category, 'status' => $note->status],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        return redirect()
            ->route('devices.show', ['device' => $device, 'tab' => 'notes'])
            ->with('status', 'Catatan ditambahkan.');
    }

    public function update(Request $request, Device $device, DeviceNote $note): RedirectResponse
    {
        abort_unless($note->device_id === $device->id, 404);

        $data = $this->validated($request);
        $data['updated_by'] = $request->user()->id;

        $note->forceFill($data)->save();
        $this->storeAttachments($request, $note);

        Audit::log(
            action: 'device_note_updated',
            entityType: DeviceNote::class,
            entityId: $note->id,
            metadata: ['device_id' => $device->id, 'status' => $note->status],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        return redirect()
            ->route('devices.show', ['device' => $device, 'tab' => 'notes'])
            ->with('status', 'Catatan diperbarui.');
    }

    public function destroy(Request $request, Device $device, DeviceNote $note): RedirectResponse
    {
        abort_unless($note->device_id === $device->id, 404);

        $note->delete();

        Audit::log(
            action: 'device_note_deleted',
            entityType: DeviceNote::class,
            entityId: $note->id,
            metadata: ['device_id' => $device->id],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        return redirect()
            ->route('devices.show', ['device' => $device, 'tab' => 'notes'])
            ->with('status', 'Catatan dihapus.');
    }

    public function destroyAttachment(Request $request, Device $device, NoteAttachment $attachment): RedirectResponse
    {
        abort_unless($attachment->note?->device_id === $device->id, 404);

        Storage::disk('local')->delete(array_filter([$attachment->path, $attachment->thumb_path]));
        $attachment->delete();

        Audit::log(
            action: 'device_note_attachment_deleted',
            entityType: NoteAttachment::class,
            entityId: $attachment->id,
            metadata: ['device_id' => $device->id],
            actorType: 'user',
            actorId: $request->user()->id,
            request: $request,
        );

        return back()->with('status', 'Lampiran dihapus.');
    }

    public function attachment(NoteAttachment $attachment): BinaryFileResponse
    {
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return response()->file(Storage::disk('local')->path($attachment->path));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $request->validate([
            'category' => ['required', 'string', 'max:30'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:5000'],
            'status' => ['required', 'in:open,in_progress,resolved'],
            'priority' => ['required', 'in:low,normal,high,critical'],
            'occurred_at' => ['nullable', 'date'],
            'cost' => ['nullable', 'string', 'max:30'],
            'vendor' => ['nullable', 'string', 'max:120'],
            'tags' => ['nullable', 'string', 'max:255'],
            'pinned' => ['nullable', 'boolean'],
            'usage_session_id' => ['nullable', 'integer', 'exists:usage_sessions,id'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:5120', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf'],
        ]);

        $body = trim((string) $request->input('body'));
        $title = trim((string) $request->input('title', ''));
        $costInput = trim((string) $request->input('cost', ''));
        $cost = null;

        if ($costInput !== '') {
            $digits = preg_replace('/[^0-9]/', '', $costInput);
            $cost = $digits !== '' ? ((float) $digits) : null;
        }

        $tags = collect(explode(',', (string) $request->input('tags', '')))
            ->map(fn ($tag) => trim($tag))
            ->filter()
            ->unique()
            ->take(8)
            ->values()
            ->all();

        $status = (string) $request->input('status');
        $occurredAt = $request->filled('occurred_at')
            ? Carbon::parse((string) $request->input('occurred_at'), 'Asia/Makassar')->utc()
            : null;

        return [
            'usage_session_id' => $request->input('usage_session_id') ?: null,
            'category' => Str::slug((string) $request->input('category'), '_') ?: 'lainnya',
            'title' => $title !== '' ? $title : null,
            'body' => $body,
            'status' => $status,
            'priority' => (string) $request->input('priority'),
            'occurred_at' => $occurredAt,
            'resolved_at' => $status === 'resolved' ? now() : null,
            'cost' => $cost,
            'vendor' => trim((string) $request->input('vendor', '')) ?: null,
            'tags' => $tags,
            'pinned' => $request->boolean('pinned'),
        ];
    }

    private function storeAttachments(Request $request, DeviceNote $note): void
    {
        foreach ($request->file('attachments', []) as $file) {
            if ($file === null) {
                continue;
            }

            $mime = (string) $file->getMimeType();
            $extension = strtolower($file->getClientOriginalExtension()) ?: ($mime === 'application/pdf' ? 'pdf' : 'jpg');
            $directory = 'device-notes/'.now()->format('Y/m');
            $base = 'note-'.$note->id.'-'.Str::uuid();
            $path = $file->storeAs($directory, $base.'.'.$extension, 'local');
            $absolute = Storage::disk('local')->path($path);
            $thumb = $this->makeThumbnail($absolute, $directory, $base);

            NoteAttachment::query()->create([
                'device_note_id' => $note->id,
                'path' => $path,
                'thumb_path' => $thumb,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $mime,
                'size_bytes' => (int) (filesize($absolute) ?: 0),
                'created_by' => $request->user()->id,
            ]);
        }
    }

    private function makeThumbnail(string $absolutePath, string $directory, string $base): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        try {
            $info = @getimagesize($absolutePath);

            if ($info === false) {
                return null;
            }

            $source = match ($info[2]) {
                IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absolutePath) : false,
                IMAGETYPE_JPEG => @imagecreatefromjpeg($absolutePath),
                IMAGETYPE_PNG => @imagecreatefrompng($absolutePath),
                default => false,
            };

            if ($source === false) {
                return null;
            }

            $width = imagesx($source);
            $height = imagesy($source);
            $thumbWidth = min(480, $width);
            $thumbHeight = (int) max(1, round($height * $thumbWidth / max(1, $width)));
            $thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);
            imagecopyresampled($thumb, $source, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);

            $relative = $directory.'/'.$base.'-thumb.jpg';
            imagejpeg($thumb, Storage::disk('local')->path($relative), 78);
            imagedestroy($thumb);
            imagedestroy($source);

            return $relative;
        } catch (Throwable) {
            return null;
        }
    }
}
