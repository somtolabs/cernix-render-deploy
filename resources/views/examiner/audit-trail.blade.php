@extends('layouts.examiner-portal', ['title' => 'Audit Trail'])

@section('examiner-content')
<div class="ex-page-head">
    <div>
        <h1 class="ex-title">Audit Trail</h1>
        <p class="ex-subtitle">Traceability view for examiner actions. This page focuses on accountability and scan outcomes.</p>
    </div>
</div>

<section class="ex-panel ex-section-pad">
    @if(empty($auditRows))
        <p class="ex-empty">No audit activity is available for this examiner yet.</p>
    @else
        <div class="ex-list">
            @foreach($auditRows as $row)
                <article class="ex-record">
                    <div class="ex-record-top">
                        <div class="safe">
                            <strong>{{ $row['action'] }}</strong>
                            <div class="ex-muted">{{ $row['student'] }} · <span class="ex-mono">{{ $row['matric_no'] }}</span></div>
                        </div>
                        <span class="ex-muted">{{ $row['time'] }}</span>
                    </div>
                    <div style="margin-top:12px">
                        <a class="ex-action secondary" href="{{ $row['detail_url'] }}">View</a>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</section>
@endsection
