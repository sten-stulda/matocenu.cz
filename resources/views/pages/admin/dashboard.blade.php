<?php

use App\Domain\Analytics\Services\AdminDashboardStats;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::admin')] #[Title('Přehled')] class extends Component {
    public function with(AdminDashboardStats $stats): array
    {
        return [
            'users' => $stats->users(),
            'tasks' => $stats->scheduledTasks(),
            'failedJobs' => $stats->failedJobs(),
            'widgets' => collect(config('matocenu.admin_widgets', []))->map(fn ($c) => app($c))->filter(fn ($w) => auth()->user()->can($w->permission())),
        ];
    }
}; ?>

<div class="space-y-6">
    <p class="text-sm text-muted">{{ local_time(now())->translatedFormat('l j. n. Y, H:i') }} · všechna čísla jsou skutečná data z databáze</p>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card"><p class="text-sm text-muted">Aktivní uživatelé (24 h)</p><p class="mt-1 font-display text-4xl font-extrabold">{{ cz_number($users['active_24h']) }}</p><p class="mt-1 text-sm text-muted">7 dní: {{ cz_number($users['active_7d']) }}</p></div>
        <div class="card"><p class="text-sm text-muted">Nové registrace dnes</p><p class="mt-1 font-display text-4xl font-extrabold">{{ cz_number($users['new_today']) }}</p><p class="mt-1 text-sm text-muted">7 dní: {{ cz_number($users['new_7d']) }} · celkem {{ cz_number($users['total']) }}</p></div>
        <div class="card"><p class="text-sm text-muted">Retence (7 dní)</p><p class="mt-1 font-display text-4xl font-extrabold">{{ $users['retention_7d'] === null ? '–' : cz_number($users['retention_7d'], 1).' %' }}</p><p class="mt-1 text-sm text-muted">kohorta registrovaná před 8–14 dny</p></div>
        <div class="card {{ $failedJobs ? 'bg-terra-100 dark:bg-surface' : '' }}"><p class="text-sm text-muted">Selhané úlohy ve frontě</p><p class="mt-1 font-display text-4xl font-extrabold {{ $failedJobs ? 'text-terra-ink' : '' }}">{{ $failedJobs }}</p><p class="mt-1 text-sm text-muted"><a href="/horizon" class="link">Horizon</a></p></div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        @foreach ($widgets as $w)
            @include($w->view(), $w->data())
        @endforeach

        <section class="card">
            <h2 class="font-display text-xl font-bold">Plánované úlohy</h2>
            <table class="table-admin mt-3">
                <thead><tr><th>Úloha</th><th>Poslední běh</th></tr></thead>
                <tbody>
                @forelse ($tasks as $t)
                    <tr><td>{{ $t['name'] }}</td><td>{{ $t['last_run'] ? local_time(\Carbon\Carbon::parse($t['last_run']))->format('j. n. H:i') : 'zatím neběžela' }}</td></tr>
                @empty
                    <tr><td colspan="2" class="text-muted">Žádné plánované úlohy.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>
    </div>
</div>
