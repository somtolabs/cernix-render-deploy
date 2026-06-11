<article class="history-row">
    <div class="history-row-top">
        <strong>{{ $row['student'] }}</strong>
        <span class="decision {{ $row['decision'] }}">{{ $row['decision'] === 'DUPLICATE' ? 'ALREADY USED' : ($row['decision'] === 'APPROVED' ? 'VERIFIED' : $row['decision']) }}</span>
    </div>
    <span class="muted">{{ $row['matric_no'] }} · {{ $row['time'] }}</span>
    <a class="btn btn-ghost" href="{{ $row['detail_url'] }}">View</a>
</article>
