@extends('layouts.student-portal')

@section('title', 'Exam Access ID')

@section('student-content')
<div class="cx-page-head">
    <div class="cx-eyebrow">Official Pass</div>
    <h1>Exam Access ID</h1>
    <p>This official pass contains the student identity, payment status, QR access, and next exam details.</p>
</div>
@include('student.partials.exam-access-id')
<div class="no-print" style="width:min(520px,100%);margin:18px auto 0;display:flex;gap:10px;flex-wrap:wrap">
    <button class="btn btn-primary" type="button" id="saveExamAccessId">Screenshot ID</button>
    <a class="btn btn-primary" href="{{ route('student.exam-pass') }}">Print Pass</a>
    <a class="btn btn-ghost" href="{{ route('student.dashboard') }}">Back to Overview</a>
</div>
@endsection

@push('student-scripts')
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js" defer></script>
<script>
    document.getElementById('saveExamAccessId')?.addEventListener('click', async () => {
        const card = document.getElementById('exam-access-id-card');
        if (!card || !window.html2canvas) {
            alert('Could not save ID image. Please use Print Pass instead.');
            return;
        }

        try {
            const canvas = await window.html2canvas(card, {
                backgroundColor: null,
                scale: Math.min(window.devicePixelRatio || 2, 2),
                useCORS: true
            });
            const link = document.createElement('a');
            link.download = 'cernix-exam-access-id-{{ preg_replace('/[^A-Za-z0-9_-]/', '-', $student->matric_no ?? 'student') }}.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
        } catch (error) {
            alert('Could not save ID image. Please use Print Pass instead.');
        }
    });
</script>
@endpush
