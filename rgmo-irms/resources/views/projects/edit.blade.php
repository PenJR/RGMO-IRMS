<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="fw-bold mb-1">Edit Project</h2>
            <p class="text-muted mb-0">Update project details and manager assignments.</p>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <x-breadcrumb :items="['Projects' => route('projects.index'), $project->name => route('projects.show', $project), 'Edit' => '#']" />

        <form method="POST" action="{{ route('projects.update', $project) }}">
            @method('PUT')
            @include('projects._form')
        </form>
    </div>
</x-app-layout>
