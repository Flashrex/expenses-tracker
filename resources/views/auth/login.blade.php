<x-layouts.guest title="Log in">
    <div class="mb-8 flex flex-col items-center text-center">
        <x-heroicon-o-banknotes class="size-10 text-emerald-600 dark:text-emerald-400" />
        <p class="mt-2 text-xl font-semibold">{{ config('app.name') }}</p>
    </div>

    <div class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
            @csrf

            <div>
                <label for="email" class="text-sm font-medium">Email</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="username"
                    @error('email') aria-invalid="true" @enderror
                    class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm placeholder:text-slate-400 focus:border-emerald-600 focus:outline-2 focus:outline-emerald-600/30 dark:border-slate-700 dark:bg-slate-950 dark:focus:border-emerald-400 dark:focus:outline-emerald-400/30"
                >
                @error('email')
                    <p class="text-sm text-rose-600 dark:text-rose-400" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="text-sm font-medium">Password</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm placeholder:text-slate-400 focus:border-emerald-600 focus:outline-2 focus:outline-emerald-600/30 dark:border-slate-700 dark:bg-slate-950 dark:focus:border-emerald-400 dark:focus:outline-emerald-400/30"
                >
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                <input type="checkbox" name="remember" value="1" class="size-4 rounded accent-emerald-600" @checked(old('remember'))>
                Remember me
            </label>

            <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:bg-emerald-500 dark:text-slate-950 dark:hover:bg-emerald-400 dark:focus-visible:outline-emerald-400">Log in</button>
        </form>
    </div>
</x-layouts.guest>
