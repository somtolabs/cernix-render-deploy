@props([
    'name',
    'id' => null,
    'accept' => '.csv,text/csv',
    'maxSizeMb' => 5,
    'label' => 'Upload File',
    'help' => 'Drag and drop your file here, or click to browse',
    'subHelp' => null,
    'isImage' => false,
    'required' => false,
    'currentPreview' => null,
    'compact' => false,
])

@php
    $inputId = $id ?: 'uploader_' . str_replace(['[', ']', '_'], ['', '', '-'], $name) . '_' . substr(md5(uniqid('', true)), 0, 6);
    $wrapperId = 'wrap_' . $inputId;
@endphp

<div id="{{ $wrapperId }}" class="cx-uploader {{ $compact ? 'compact' : '' }}" data-cx-uploader data-max-size="{{ (float) $maxSizeMb }}" data-is-image="{{ $isImage ? '1' : '0' }}">
    <input type="file"
           name="{{ $name }}"
           id="{{ $inputId }}"
           accept="{{ $accept }}"
           class="cx-uploader-input"
           style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;"
           @if($required) required @endif>

    {{-- Drop Target --}}
    <div class="cx-uploader-dropzone" tabindex="0" role="button" aria-label="{{ $label }}" style="outline:none;">
        <div class="cx-uploader-idle">
            <div class="cx-uploader-icon" aria-hidden="true">
                @if($isImage)
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="18" height="18" rx="3" ry="3"/>
                        <circle cx="8.5" cy="8.5" r="1.5"/>
                        <polyline points="21 15 16 10 5 21"/>
                    </svg>
                @else
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="12" y1="18" x2="12" y2="12"/>
                        <line x1="9" y1="15" x2="12" y2="12"/>
                        <line x1="15" y1="15" x2="12" y2="12"/>
                    </svg>
                @endif
            </div>
            <div class="cx-uploader-text">
                <span class="cx-uploader-primary-label">
                    Drag and drop your file here, or <span class="cx-uploader-link">browse</span>
                </span>
                <span class="cx-uploader-subtext">{{ $help }}</span>
                @if($subHelp)
                    <span class="cx-uploader-subhelp">{!! $subHelp !!}</span>
                @endif
            </div>
        </div>

        {{-- Active Selected State --}}
        <div class="cx-uploader-selected" style="display:none;">
            <div class="cx-uploader-file-info">
                @if($isImage)
                    <div class="cx-uploader-thumb-wrap">
                        <img class="cx-uploader-img-preview" src="" alt="Selected preview">
                    </div>
                @else
                    <div class="cx-uploader-doc-badge" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <path d="M9 15h6"/>
                        </svg>
                    </div>
                @endif
                <div class="cx-uploader-details">
                    <span class="cx-uploader-filename">file.csv</span>
                    <div class="cx-uploader-meta">
                        <span class="cx-uploader-filesize">0 KB</span>
                        <span class="cx-uploader-tag">Ready</span>
                    </div>
                </div>
            </div>
            <div class="cx-uploader-actions">
                <button type="button" class="cx-uploader-change-btn">Change</button>
                <button type="button" class="cx-uploader-remove-btn" title="Remove file" aria-label="Remove file">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Error Banner --}}
    <div class="cx-uploader-error" style="display:none;" role="alert"></div>
</div>

@once
<style>
    .cx-uploader {
        width: 100%;
        margin-bottom: 6px;
    }
    .cx-uploader-dropzone {
        border: 2px dashed var(--line-2, #d7d4c8);
        border-radius: 14px;
        padding: 24px 20px;
        background: #ffffff;
        cursor: pointer;
        transition: border-color .18s ease, background .18s ease, box-shadow .18s ease, transform .15s ease;
        text-align: center;
        user-select: none;
    }
    .cx-uploader.compact .cx-uploader-dropzone {
        padding: 14px 16px;
        border-radius: 12px;
    }
    .cx-uploader-dropzone:hover,
    .cx-uploader-dropzone:focus-visible {
        border-color: var(--navy, #33475f);
        background: rgba(51, 71, 95, 0.02);
    }
    .cx-uploader-dropzone.drag-active {
        border-color: var(--navy, #33475f);
        background: rgba(51, 71, 95, 0.06);
        box-shadow: 0 0 0 4px rgba(51, 71, 95, 0.12);
        transform: scale(1.005);
    }
    .cx-uploader-idle {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
    }
    .cx-uploader.compact .cx-uploader-idle {
        flex-direction: row;
        text-align: left;
        gap: 12px;
    }
    .cx-uploader-icon {
        width: 46px;
        height: 46px;
        border-radius: 12px;
        background: rgba(51, 71, 95, 0.05);
        color: var(--navy, #33475f);
        display: grid;
        place-items: center;
        flex-shrink: 0;
        transition: transform .15s ease, background .15s ease;
    }
    .cx-uploader.compact .cx-uploader-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
    }
    .cx-uploader-dropzone:hover .cx-uploader-icon,
    .cx-uploader-dropzone.drag-active .cx-uploader-icon {
        background: rgba(51, 71, 95, 0.1);
        transform: translateY(-2px);
    }
    .cx-uploader-text {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
    }
    .cx-uploader-primary-label {
        font-size: 13.5px;
        font-weight: 700;
        color: var(--ink, #222a33);
        line-height: 1.4;
    }
    .cx-uploader-link {
        color: var(--navy, #33475f);
        text-decoration: underline;
        font-weight: 800;
    }
    .cx-uploader-subtext {
        font-size: 12px;
        color: var(--ink-3, #6f7882);
    }
    .cx-uploader-subhelp {
        display: block;
        margin-top: 4px;
        font-size: 11px;
        color: var(--ink-4, #969da4);
        line-height: 1.4;
    }
    .cx-uploader-subhelp code {
        background: rgba(51, 71, 95, 0.06);
        border-radius: 4px;
        padding: 1px 5px;
        font-family: 'JetBrains Mono', monospace;
        color: var(--navy, #33475f);
    }

    /* Selected state */
    .cx-uploader-selected {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 4px;
        text-align: left;
    }
    .cx-uploader-file-info {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
        flex: 1;
    }
    .cx-uploader-doc-badge {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        background: rgba(5, 150, 105, 0.1);
        color: var(--emerald, #557565);
        display: grid;
        place-items: center;
        flex-shrink: 0;
    }
    .cx-uploader-thumb-wrap {
        width: 52px;
        height: 52px;
        border-radius: 10px;
        border: 1px solid var(--line, #e6e4dc);
        background: var(--bg, #f5f5f2);
        overflow: hidden;
        flex-shrink: 0;
        display: grid;
        place-items: center;
    }
    .cx-uploader-img-preview {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }
    .cx-uploader-details {
        min-width: 0;
        flex: 1;
    }
    .cx-uploader-filename {
        display: block;
        font-size: 13.5px;
        font-weight: 700;
        color: var(--ink, #222a33);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .cx-uploader-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 3px;
        font-size: 11.5px;
    }
    .cx-uploader-filesize {
        font-family: 'JetBrains Mono', monospace;
        color: var(--ink-3, #6f7882);
    }
    .cx-uploader-tag {
        display: inline-flex;
        align-items: center;
        padding: 1px 7px;
        border-radius: 999px;
        background: rgba(5, 150, 105, 0.12);
        color: var(--emerald, #557565);
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .04em;
    }
    .cx-uploader-actions {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-shrink: 0;
    }
    .cx-uploader-change-btn {
        background: var(--bg, #f5f5f2);
        border: 1px solid var(--line, #e6e4dc);
        border-radius: 8px;
        padding: 6px 11px;
        font-size: 11.5px;
        font-weight: 700;
        color: var(--ink-2, #46515d);
        cursor: pointer;
        transition: background .12s, color .12s;
    }
    .cx-uploader-change-btn:hover {
        background: var(--line, #e6e4dc);
        color: var(--ink, #222a33);
    }
    .cx-uploader-remove-btn {
        width: 28px;
        height: 28px;
        border-radius: 8px;
        border: 1px solid transparent;
        background: transparent;
        color: var(--ink-3, #6f7882);
        display: grid;
        place-items: center;
        cursor: pointer;
        transition: background .12s, color .12s;
    }
    .cx-uploader-remove-btn:hover {
        background: rgba(220, 38, 38, 0.08);
        color: var(--red, #8a5b5b);
    }
    .cx-uploader-error {
        margin-top: 8px;
        padding: 8px 12px;
        border-radius: 8px;
        background: rgba(220, 38, 38, 0.08);
        border-left: 3px solid var(--red, #8a5b5b);
        color: var(--red, #8a5b5b);
        font-size: 12px;
        font-weight: 600;
        line-height: 1.4;
    }
</style>

<script>
(function () {
    if (window.__cxUploaderInit) return;
    window.__cxUploaderInit = true;

    function initUploader(wrap) {
        if (!wrap || wrap.__cxInit) return;
        wrap.__cxInit = true;

        var input = wrap.querySelector('.cx-uploader-input');
        var dropzone = wrap.querySelector('.cx-uploader-dropzone');
        var idleView = wrap.querySelector('.cx-uploader-idle');
        var selectedView = wrap.querySelector('.cx-uploader-selected');
        var errView = wrap.querySelector('.cx-uploader-error');
        var filenameEl = wrap.querySelector('.cx-uploader-filename');
        var filesizeEl = wrap.querySelector('.cx-uploader-filesize');
        var removeBtn = wrap.querySelector('.cx-uploader-remove-btn');
        var changeBtn = wrap.querySelector('.cx-uploader-change-btn');
        var imgPreview = wrap.querySelector('.cx-uploader-img-preview');

        var maxMb = parseFloat(wrap.getAttribute('data-max-size') || '5');
        var isImage = wrap.getAttribute('data-is-image') === '1';

        function showError(msg) {
            if (!errView) return;
            errView.textContent = msg;
            errView.style.display = 'block';
        }

        function clearError() {
            if (!errView) return;
            errView.textContent = '';
            errView.style.display = 'none';
        }

        function formatSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(2) + ' MB';
        }

        function updateUI(file) {
            clearError();
            if (!file) {
                idleView.style.display = '';
                selectedView.style.display = 'none';
                if (imgPreview) imgPreview.src = '';
                return;
            }

            // Size check
            if (file.size > maxMb * 1024 * 1024) {
                showError('File exceeds the maximum allowed size of ' + maxMb + ' MB (' + formatSize(file.size) + ').');
                input.value = '';
                idleView.style.display = '';
                selectedView.style.display = 'none';
                return;
            }

            filenameEl.textContent = file.name;
            filesizeEl.textContent = formatSize(file.size);

            if (isImage && imgPreview) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    imgPreview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }

            idleView.style.display = 'none';
            selectedView.style.display = 'flex';
        }

        // Click to open file dialog
        dropzone.addEventListener('click', function (e) {
            if (e.target.closest('.cx-uploader-remove-btn')) return;
            input.click();
        });

        dropzone.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                input.click();
            }
        });

        if (changeBtn) {
            changeBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                input.click();
            });
        }

        if (removeBtn) {
            removeBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                input.value = '';
                updateUI(null);
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }

        // Native input change
        input.addEventListener('change', function () {
            var file = this.files && this.files[0];
            updateUI(file);
        });

        // Drag & Drop
        dropzone.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.add('drag-active');
        });

        dropzone.addEventListener('dragleave', function (e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('drag-active');
        });

        dropzone.addEventListener('drop', function (e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('drag-active');

            if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
                var file = e.dataTransfer.files[0];
                var dt = new DataTransfer();
                dt.items.add(file);
                input.files = dt.files;
                updateUI(file);
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-cx-uploader]').forEach(initUploader);
    });

    // In case DOM is already loaded or content is injected dynamically
    if (document.readyState === 'interactive' || document.readyState === 'complete') {
        document.querySelectorAll('[data-cx-uploader]').forEach(initUploader);
    }
})();
</script>
@endonce
