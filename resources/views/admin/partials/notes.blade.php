@php
    $notes = $notes ?? collect();
@endphp

<section class="admin-section" style="margin-top:16px">
    <div class="admin-section-head">
        <div>
            <h2>Admin Notes</h2>
            <span>Internal context for review, corrections, and operational follow-up.</span>
        </div>
        <span>{{ $notes->count() }} recent</span>
    </div>
    <div class="admin-section-body">
        @if($errors->any())
            <div class="admin-empty" style="margin-bottom:12px;color:var(--red)">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.notes.store') }}" class="admin-note-form">
            @csrf
            <input type="hidden" name="entity_type" value="{{ $entityType }}">
            <input type="hidden" name="entity_id" value="{{ $entityId }}">
            <select name="note_type" aria-label="Note type">
                <option value="internal">Internal</option>
                <option value="review">Review</option>
                <option value="correction">Correction</option>
                <option value="warning">Warning</option>
            </select>
            <select name="visibility" aria-label="Note visibility">
                <option value="internal">Internal only</option>
                <option value="student">Show to Student</option>
                <option value="examiner">Show to Examiner</option>
                <option value="both">Show to Student and Examiner</option>
            </select>
            <textarea name="note" maxlength="2000" required placeholder="Add a concise admin note for this record."></textarea>
            <div style="display:grid;gap:8px">
                <label class="muted" style="display:flex;gap:7px;align-items:center;font-size:12px">
                    <input type="checkbox" name="requires_acknowledgement" value="1">
                    Require acknowledgement
                </label>
                <button class="admin-action" type="submit">Add Note</button>
            </div>
        </form>
        <p class="admin-note-helper">Only share notes that the selected user should be able to see.</p>

        <div class="admin-notes-list">
            @forelse($notes as $note)
                <article class="admin-note-item">
                    <div class="admin-note-meta">
                        <span style="display:flex;gap:8px;flex-wrap:wrap">
                            <span class="admin-status {{ ($note->note_type ?? 'internal') === 'warning' ? 'amber' : (($note->note_type ?? 'internal') === 'correction' ? 'red' : 'green') }}">
                                {{ Str::headline($note->note_type ?? 'Internal') }}
                            </span>
                            <span class="admin-status {{ ($note->visibility ?? 'internal') === 'internal' ? 'amber' : 'green' }}">
                                {{ match($note->visibility ?? 'internal') {
                                    'student' => 'Visible to Student',
                                    'examiner' => 'Visible to Examiner',
                                    'both' => 'Visible to Both',
                                    default => 'Internal Only',
                                } }}
                            </span>
                        </span>
                        <span class="mono muted">{{ $note->created_at }}</span>
                    </div>
                    <p>{{ $note->note }}</p>
                    <span class="muted">Added by {{ $note->actor_name ?? 'Admin' }}</span>
                </article>
            @empty
                <div class="admin-empty">No admin notes have been added yet.</div>
            @endforelse
        </div>
    </div>
</section>
