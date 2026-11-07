<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="fw-bold mb-1">New Project</h2>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <x-breadcrumb :items="['Projects' => route('projects.index'), 'Create' => '#']" />

        <form method="POST" action="{{ route('projects.store') }}">
            @include('projects._form')
        </form>
    </div>
</x-app-layout>
