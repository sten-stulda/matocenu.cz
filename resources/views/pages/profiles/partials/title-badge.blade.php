@php $t = $achievementsVisible ? $profile->title() : null; @endphp
@if ($t)<span class="badge mt-1 {{ $t->badgeClass() }}">{{ $t->name }}</span>@endif
