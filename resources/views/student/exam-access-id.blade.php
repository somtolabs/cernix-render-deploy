@extends('layouts.student-portal')

@section('title', 'Course QR Pass')

@section('student-content')
<div class="cx-page-head">
    <div class="cx-eyebrow">Course Access</div>
    <h1>Course QR Pass</h1>
    <p>This QR pass is valid only for the selected course and timetable entry shown below.</p>
</div>
@if($token)
    @include('student.partials.exam-access-id')
    <div class="no-print" style="width:min(720px,100%);margin:18px auto 0;display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn btn-primary" type="button" id="saveExamAccessId">Save Course QR</button>
        <a class="btn btn-primary" href="{{ route('student.exam-pass.course', ['timetable' => $passExam->id]) }}">Print Course QR</a>
        <a class="btn btn-ghost" href="{{ route('student.generate-exam-pass') }}">Back to Generate QR Pass</a>
    </div>
@else
    <div class="cx-empty">
        <strong>QR not generated for this course.</strong><br>
        Select a course to generate or view its QR pass.
        <div style="margin-top:12px"><a class="btn btn-primary" href="{{ route('student.generate-exam-pass') }}">Generate QR Pass</a></div>
    </div>
@endif
@endsection

@push('student-scripts')
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js" defer></script>
<script>
    document.getElementById('saveExamAccessId')?.addEventListener('click', async () => {
        const card = document.getElementById('exam-access-id-card');
        if (!card || !window.html2canvas) {
            alert('Could not save the course QR pass. Please use Print Course QR instead.');
            return;
        }

        try {
            const canvas = await window.html2canvas(card, {
                backgroundColor: null,
                scale: Math.min(window.devicePixelRatio || 2, 2),
                useCORS: true
            });
            const link = document.createElement('a');
            link.download = 'cernix-course-qr-{{ preg_replace('/[^A-Za-z0-9_-]/', '-', $passExam->course_code ?? 'course') }}-{{ preg_replace('/[^A-Za-z0-9_-]/', '-', $student->matric_no ?? 'student') }}.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
        } catch (error) {
            alert('Could not save the course QR pass. Please use Print Course QR instead.');
        }
    });
</script>
@endpush
