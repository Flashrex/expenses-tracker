<x-layouts.app title="Upload">
    <div class="mx-auto max-w-2xl space-y-6">
        <form method="POST" action="{{ route('upload.store') }}" enctype="multipart/form-data" x-data="uploadDropzone(@js(['maxFiles' => $maxFiles, 'maxBytes' => $maxUploadBytes]))" x-ref="form">
            @csrf

            <label
                for="statements"
                class="flex cursor-pointer flex-col items-center justify-center rounded-3xl border-2 border-dashed border-slate-300 bg-white px-6 py-16 text-center transition focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-emerald-600 dark:border-slate-700 dark:bg-slate-900 dark:focus-within:outline-emerald-400"
                :class="dragging ? 'border-emerald-500 bg-emerald-50 dark:border-emerald-400 dark:bg-emerald-400/10' : 'hover:border-slate-400 dark:hover:border-slate-600'"
                @dragover.prevent="dragging = true"
                @dragleave.prevent="dragging = false"
                @drop.prevent="dragging = false; choose($event.dataTransfer.files, true)"
            >
                <div x-show="!busy" class="flex flex-col items-center">
                    <div class="flex size-14 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-400/10 dark:text-emerald-400">
                        <x-heroicon-o-arrow-up-tray class="size-7" />
                    </div>
                    <p class="mt-4 text-lg font-semibold">Drop your ING statements here</p>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">or click to choose PDFs (up to 12)</p>
                </div>

                <div x-show="busy" x-cloak class="flex flex-col items-center">
                    <x-heroicon-o-arrow-path class="size-7 animate-spin text-emerald-600 dark:text-emerald-400" />
                    <p class="mt-4 text-sm font-medium" x-text="count > 1 ? `Reading ${count} statements…` : 'Reading statement…'">Reading statement…</p>
                </div>

                <input id="statements" name="statements[]" type="file" multiple accept="application/pdf,.pdf" class="sr-only" x-ref="input" @change="choose($refs.input.files)">
            </label>

            <noscript>
                <div class="text-center">
                    <button type="submit" class="mt-4 inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:bg-emerald-500 dark:text-slate-950 dark:hover:bg-emerald-400 dark:focus-visible:outline-emerald-400">Upload</button>
                </div>
            </noscript>

            @error('statements')
                <p role="alert" class="mt-3 text-center text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror

            @if ($errors->has('files'))
                <ul data-failed-files class="mt-2 space-y-1 text-center text-sm text-rose-600 dark:text-rose-400">
                    @foreach ($errors->get('files') as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            @endif

            @if ($tooLarge)
                <p role="alert" class="mt-3 text-center text-sm text-rose-600 dark:text-rose-400">Upload too large, try fewer files.</p>
            @endif

            <p role="alert" x-show="error" x-text="error" x-cloak class="mt-3 text-center text-sm text-rose-600 dark:text-rose-400"></p>
        </form>

        @if ($imported->isNotEmpty())
            <section>
                <h2 class="mb-3 flex items-center gap-2 text-sm font-medium text-slate-500 dark:text-slate-400">
                    <x-heroicon-o-archive-box class="size-4" /> Imported
                </h2>

                <ul class="flex flex-wrap gap-2">
                    @foreach ($imported as $period)
                        <li class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1 text-sm dark:border-slate-800 dark:bg-slate-900">
                            <x-heroicon-m-check class="size-4 text-emerald-600 dark:text-emerald-400" />
                            {{ \App\Support\Period::short($period) }}
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-layouts.app>
