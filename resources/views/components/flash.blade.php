@if (session('status') || session('error'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)" role="status"
         class="container-page mt-4">
        <div class="flex items-start gap-3 rounded-md border px-4 py-3 text-sm font-semibold {{ session('error') ? 'border-terra-ink bg-terra-100 text-terra-ink' : 'border-forest bg-forest-100 text-forest' }}">
            <span class="flex-1">{{ session('error') ?? session('status') }}</span>
            <button type="button" class="opacity-70 hover:opacity-100" @click="show = false" aria-label="Zavřít"><x-icon name="x" :size="16" /></button>
        </div>
    </div>
@endif
