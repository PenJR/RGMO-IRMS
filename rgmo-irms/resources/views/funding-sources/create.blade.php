<x-app-layout>
    <x-slot name="header"><h2 class="fw-bold">Add funding source</h2></x-slot>
    <div class="container-fluid py-4"><div class="card border-0 shadow-sm"><div class="card-body"><form method="POST" action="{{ route('funding-sources.store') }}">@include('funding-sources._form')</form></div></div></div>
</x-app-layout>
