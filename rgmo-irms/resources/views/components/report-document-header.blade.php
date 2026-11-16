@props(['title', 'period' => null, 'type' => 'Operational report'])

<section class="report-document-header">
    <div class="report-document-header__seal" aria-hidden="true">
        <img src="{{ asset('images/logo.png') }}" alt="">
    </div>
    <div class="report-document-header__body">
        <p class="report-document-header__office">Resource Generation and Management Office</p>
        <h1>{{ $title }}</h1>
        @if($period)
            <p class="report-document-header__period">{{ $period }}</p>
        @endif
    </div>
    <span class="report-document-header__type">{{ $type }}</span>
</section>
