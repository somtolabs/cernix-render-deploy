@extends('layouts.student-portal')

@section('title', 'Course QR Pass')

@section('student-content')
<style>
    .qr-pass-actions {
        width: min(470px, 100%);
        margin: 18px auto 0;
        display: flex;
        justify-content: flex-end;
        gap: 9px;
        flex-wrap: wrap;
    }
    .qr-pass-actions .btn {
        min-height: 42px;
        padding-inline: 16px;
        border-radius: 10px;
        font-size: 13px;
    }
    @media (max-width: 560px) {
        .qr-pass-actions {
            display: grid;
            grid-template-columns: 1fr;
        }
        .qr-pass-actions .btn {
            width: 100%;
        }
    }
</style>

<div class="cx-page-head">
    <div class="cx-eyebrow">Course Access</div>
    <h1>Course QR Pass</h1>
    <p>Your QR pass for this course, ready to present at the examination entrance.</p>
</div>
@if($token)
    @include('student.partials.exam-access-id')
    <p id="save-qr-err" style="text-align:center;font-size:12px;font-weight:700;color:var(--red);margin:10px 0 0;display:none"></p>
    <div class="qr-pass-actions no-print">
        <button class="btn btn-primary" type="button" id="saveExamAccessId">Save Course QR</button>
        <a class="btn btn-ghost" href="{{ route('student.exam-pass.course', ['timetable' => $passExam->id]) }}">Print Course QR</a>
        <a class="btn btn-ghost" href="{{ route('student.timetable') }}">Back to My Exams</a>
    </div>
@else
    <div class="cx-empty">
        <strong>QR not generated for this course.</strong><br>
        Select a course to generate or view its QR pass.
        <div style="margin-top:12px"><a class="btn btn-primary" href="{{ route('student.timetable') }}">Open My Exams</a></div>
    </div>
@endif
@endsection

@push('student-scripts')
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js" defer></script>
<script>
    const _saveErrEl = document.getElementById('save-qr-err');
    function _showSaveErr(msg) {
        if (_saveErrEl) { _saveErrEl.textContent = msg; _saveErrEl.style.display = ''; }
    }
    document.getElementById('saveExamAccessId')?.addEventListener('click', async () => {
        if (_saveErrEl) _saveErrEl.style.display = 'none';
        const card = document.getElementById('exam-access-id-card');
        if (!card || !window.html2canvas) {
            _showSaveErr('Could not save — use Print Course QR instead.');
            return;
        }

        try {
            const canvas = await window.html2canvas(card, {
                backgroundColor: null,
                scale: Math.min(window.devicePixelRatio || 2, 2),
                useCORS: true
            });
            const link = document.createElement('a');
            link.download = 'exam-pass-{{ preg_replace('/[^A-Za-z0-9_-]/', '-', $passExam->course_code ?? 'course') }}-{{ preg_replace('/[^A-Za-z0-9_-]/', '-', $student->matric_no ?? 'student') }}.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
        } catch (error) {
            _showSaveErr('Could not save — use Print Course QR instead.');
        }
    });
</script>
@endpush
