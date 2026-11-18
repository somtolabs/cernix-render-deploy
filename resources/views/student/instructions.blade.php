@extends('layouts.student-portal')

@section('title', 'Exam Instructions')

@section('student-content')
<div class="stu-head">
    <p class="stu-head-eyebrow">Exam Conduct &amp; Venue Guidelines</p>
    <h1>Candidate Instructions</h1>
    <p>Read and comply with these institutional regulations before proceeding to any examination hall.</p>
</div>

<section class="stu-card">
    <div class="stu-card-head">
        <h2>General Examination Rules</h2>
        <span class="chip navy stu-chip-sm">Mandatory</span>
    </div>
    <div class="stu-card-body">
        <div class="cx-timeline">
            @foreach([
                'Arrive at the assigned examination venue at least 30 minutes before the scheduled start time.' => 'Late entry after 30 minutes of exam commencement is strictly prohibited.',
                'Carry your official institutional ID card and your generated Course QR Pass.' => 'Examiners verify both your digital pass and physical identity before admission.',
                'Present your digital or printed QR pass to the examiner for verification.' => 'Each pass is validated server-side in real time against the session roster.',
                'Verify your course code, exam date, and assigned venue on your timetable.' => 'Ensure you report any venue conflicts or timetable errors to the exam office in advance.',
                'Do not share, screenshot-transfer, or duplicate your QR pass.' => 'Repeated scans from different venues or devices trigger security flags and audit review.',
                'Comply with all institutional examination malpractice regulations.' => 'Electronic devices, unauthorized notes, and candidate impersonation lead to immediate disqualification.',
            ] as $rule => $detail)
                <article class="cx-step">
                    <div class="cx-step-dot">{{ $loop->iteration }}</div>
                    <div>
                        <b>{{ $rule }}</b>
                        <span>{{ $detail }}</span>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>

<div class="cx-notice" role="note">
    <b>Assistance &amp; Emergency Enquiries:</b> If you experience any technical difficulty with your course pass or discover a schedule clash, contact your departmental examination officer immediately or visit the Examination Support Desk.
</div>
@endsection
