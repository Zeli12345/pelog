<x-dashboard-layout title="Audit">
    <div class="space-y-4">
        <section class="card p-4">
            <form method="GET" action="{{ route('audit.index') }}" class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                <div>
                    <label class="label" for="action">Aksi</label>
                    <select id="action" name="action" class="input text-xs">
                        <option value="">Semua aksi</option>
                        @foreach ($actions as $item)
                            <option value="{{ $item }}" @selected($action === $item)>{{ $item }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="from">Dari</label>
                    <input type="date" id="from" name="from" value="{{ $from }}" class="input text-xs">
                </div>
                <div>
                    <label class="label" for="to">Sampai</label>
                    <input type="date" id="to" name="to" value="{{ $to }}" class="input text-xs">
                </div>
                <div class="flex items-end gap-2 lg:col-span-2">
                    <button type="submit" class="btn-primary !py-2 text-xs">Terapkan</button>
                    <a href="{{ route('audit.index') }}" class="btn-secondary !py-2 text-xs">Reset</a>
                    <span class="ml-auto self-center font-mono text-xs text-ink-faint">{{ $logs->total() }} baris</span>
                </div>
            </form>
        </section>

        <section class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-line text-sm">
                    <thead>
                        <tr class="bg-paper/70 text-left text-[11px] uppercase tracking-wider text-ink-faint">
                            <th class="px-4 py-2.5 font-semibold">Waktu (WITA)</th>
                            <th class="px-4 py-2.5 font-semibold">Aksi</th>
                            <th class="px-4 py-2.5 font-semibold">Entitas</th>
                            <th class="px-4 py-2.5 font-semibold">Pelaku</th>
                            <th class="px-4 py-2.5 font-semibold">Metadata</th>
                            <th class="px-4 py-2.5 font-semibold">IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line/70">
                        @forelse ($logs as $log)
                            <tr class="hover:bg-paper/60">
                                <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs text-ink-soft">
                                    {{ $log->created_at->timezone('Asia/Makassar')->format('d/m/Y H:i:s') }}
                                </td>
                                <td class="px-4 py-2.5">
                                    <span class="badge border-navy-200 bg-navy-50 font-mono text-[11px] text-navy-800">{{ $log->action }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-xs text-ink-soft">
                                    @if ($log->entity_type)
                                        {{ class_basename($log->entity_type) }}#{{ $log->entity_id }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-xs text-ink-soft">
                                    {{ $log->actor_type }}@if ($log->actor_id) #{{ $log->actor_id }}@endif
                                </td>
                                <td class="max-w-[20rem] px-4 py-2.5">
                                    @if ($log->metadata)
                                        <code class="block truncate rounded bg-paper px-1.5 py-0.5 text-[11px] text-ink-soft" title="{{ json_encode($log->metadata) }}">
                                            {{ json_encode($log->metadata) }}
                                        </code>
                                    @else
                                        <span class="text-xs text-ink-faint">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 font-mono text-[11px] text-ink-faint">{{ $log->ip ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center text-sm text-ink-faint">Belum ada catatan audit.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-line px-4 py-3">{{ $logs->links() }}</div>
        </section>
    </div>
</x-dashboard-layout>
