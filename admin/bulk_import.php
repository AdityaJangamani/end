<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/admin_login.php");
    exit();
}
require '../php/csrf.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Import Employees - AI System</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* ── Drop zone ── */
        .drop-zone {
            border: 2px dashed rgba(99, 102, 241, 0.5);
            border-radius: 16px;
            padding: 3.5rem 2rem;
            text-align: center;
            cursor: pointer;
            transition: all .25s;
            background: rgba(99, 102, 241, 0.04);
            position: relative;
        }
        .drop-zone:hover,
        .drop-zone.dragging {
            border-color: #6366f1;
            background: rgba(99, 102, 241, 0.10);
        }
        .drop-zone input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }
        .drop-icon {
            font-size: 3rem;
            margin-bottom: .75rem;
        }
        .drop-zone h3 { margin: 0 0 .4rem; color: #e0e7ff; font-size: 1.1rem; }
        .drop-zone p  { margin: 0; color: #818cf8; font-size: .85rem; }
        .file-chosen  { color: #34d399; font-weight: 600; margin-top: .6rem; font-size: .9rem; }

        /* ── Template download strip ── */
        .template-strip {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: rgba(99,102,241,.08);
            border: 1px solid rgba(99,102,241,.2);
            border-radius: 10px;
            padding: .9rem 1.3rem;
            margin-bottom: 1.5rem;
        }
        .template-strip p { margin: 0; font-size: .88rem; color: #a5b4fc; flex: 1; }
        .btn-template {
            background: rgba(99,102,241,.2);
            color: #818cf8;
            border: 1px solid rgba(99,102,241,.35);
            padding: .45rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: .83rem;
            white-space: nowrap;
            text-decoration: none;
            display: inline-block;
            transition: background .2s;
        }
        .btn-template:hover { background: rgba(99,102,241,.35); color: #e0e7ff; }

        /* ── Preview section ── */
        #previewSection { display: none; margin-top: 2rem; }
        .preview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .preview-header h3 { margin: 0; font-size: 1rem; color: #e0e7ff; }
        .badge {
            background: rgba(99,102,241,.2);
            color: #818cf8;
            border-radius: 20px;
            padding: .2rem .75rem;
            font-size: .78rem;
            font-weight: 600;
        }
        .preview-table-wrap { overflow-x: auto; max-height: 360px; overflow-y: auto; }
        .preview-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .82rem;
            min-width: 820px;
        }
        .preview-table th {
            background: rgba(99,102,241,.15);
            color: #a5b4fc;
            padding: 8px 12px;
            text-align: left;
            position: sticky;
            top: 0;
            white-space: nowrap;
        }
        .preview-table td {
            padding: 7px 12px;
            border-bottom: 1px solid rgba(255,255,255,.05);
            color: #e0e7ff;
            white-space: nowrap;
        }
        .preview-table tr:hover td { background: rgba(99,102,241,.07); }
        .row-ok   { border-left: 3px solid #059669; }
        .row-warn { border-left: 3px solid #f59e0b; }
        .row-err  { border-left: 3px solid #dc2626; }
        .tag {
            display: inline-block;
            padding: .15rem .5rem;
            border-radius: 4px;
            font-size: .72rem;
            font-weight: 600;
        }
        .tag-ok   { background: rgba(5,150,105,.2);  color: #34d399; }
        .tag-warn { background: rgba(245,158,11,.2); color: #fbbf24; }
        .tag-err  { background: rgba(220,38,38,.2);  color: #f87171; }

        /* ── Progress bar ── */
        #progressWrap { display: none; margin-top: 1.5rem; }
        #progressBar {
            height: 8px;
            background: linear-gradient(90deg,#4F46E5,#7C3AED);
            border-radius: 4px;
            width: 0%;
            transition: width .3s;
        }
        #progressTrack {
            background: rgba(255,255,255,.08);
            border-radius: 4px;
            overflow: hidden;
        }
        #progressLabel {
            text-align: center;
            font-size: .82rem;
            color: #a5b4fc;
            margin-top: .5rem;
        }

        /* ── Result log ── */
        #resultLog {
            display: none;
            margin-top: 1.5rem;
            max-height: 240px;
            overflow-y: auto;
            background: rgba(0,0,0,.25);
            border-radius: 8px;
            padding: 1rem;
            font-size: .82rem;
            font-family: monospace;
        }
        .log-ok   { color: #34d399; }
        .log-err  { color: #f87171; }
        .log-info { color: #818cf8; }

        /* ── Action bar ── */
        .action-bar {
            display: flex;
            gap: .75rem;
            margin-top: 1.5rem;
            justify-content: flex-end;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include '../php/sidebar.php'; ?>

        <main class="main-content">
            <header class="page-header">
                <h1 class="page-title">📥 Bulk Import Employees</h1>
                <a href="add_employee.php" class="btn btn-primary" style="font-size:.85rem;">+ Add Single Employee</a>
            </header>

            <div class="glass-panel animate-fade-in" style="padding:2rem;">

                <!-- Template download -->
                <div class="template-strip">
                    <p>📄 Don't have a file yet? Download the CSV template with the correct column headers.</p>
                    <a href="download_template.php" class="btn-template">⬇ Download Template CSV</a>
                </div>

                <!-- Supported columns info -->
                <div style="background:rgba(99,102,241,.06);border-radius:10px;padding:1rem 1.3rem;margin-bottom:1.5rem;font-size:.83rem;color:#a5b4fc;line-height:1.8;">
                    <strong style="color:#e0e7ff;">Required columns:</strong>
                    <code style="color:#818cf8;">employee_id, first_name, last_name, email, department, date_joined</code><br>
                    <strong style="color:#e0e7ff;">Optional columns:</strong>
                    <code style="color:#818cf8;">job_role, age, password</code>
                    <span style="color:#6b7280;"> (auto-generated as FirstName@123 if blank)</span>
                </div>

                <!-- Drop zone -->
                <div class="drop-zone" id="dropZone">
                    <input type="file" id="csvFile" accept=".csv,.xlsx,.xls">
                    <div class="drop-icon">📂</div>
                    <h3>Drag &amp; Drop your CSV file here</h3>
                    <p>or click to browse — supports .csv files</p>
                    <div class="file-chosen" id="fileChosen" style="display:none;"></div>
                </div>

                <!-- Live preview -->
                <div id="previewSection">
                    <div class="preview-header">
                        <h3>📋 Preview</h3>
                        <span class="badge" id="rowCountBadge">0 rows</span>
                    </div>
                    <div class="preview-table-wrap">
                        <table class="preview-table" id="previewTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Status</th>
                                    <th>Employee ID</th>
                                    <th>First Name</th>
                                    <th>Last Name</th>
                                    <th>Email</th>
                                    <th>Department</th>
                                    <th>Job Role</th>
                                    <th>Age</th>
                                    <th>Date Joined</th>
                                    <th>Password</th>
                                </tr>
                            </thead>
                            <tbody id="previewBody"></tbody>
                        </table>
                    </div>

                    <!-- Progress -->
                    <div id="progressWrap">
                        <div id="progressTrack"><div id="progressBar"></div></div>
                        <div id="progressLabel">Importing…</div>
                    </div>

                    <!-- Result log -->
                    <div id="resultLog"></div>

                    <!-- Actions -->
                    <div class="action-bar">
                        <button class="btn" id="btnClear"
                            style="background:rgba(255,255,255,.07);color:#a5b4fc;border:1px solid rgba(99,102,241,.3);"
                            onclick="clearAll()">✖ Clear</button>
                        <button class="btn btn-primary" id="btnImport" onclick="startImport()">
                            ⬆ Import All Valid Rows
                        </button>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
    // ── Valid departments (must match DB/add_employee.php) ───────────────────
    const VALID_DEPTS = ['Engineering','Product','Finance','Sales','Marketing','HR','Operations'];
    const REQUIRED    = ['employee_id','first_name','last_name','email','department','date_joined'];

    let parsedRows = [];   // [{raw}, …]
    let validRows  = [];   // subset ready to import

    // ── Drag & Drop handling ─────────────────────────────────────────────────
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('csvFile');

    dropZone.addEventListener('dragover',  e => { e.preventDefault(); dropZone.classList.add('dragging'); });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragging'));
    dropZone.addEventListener('drop', e => {
        e.preventDefault();
        dropZone.classList.remove('dragging');
        const file = e.dataTransfer.files[0];
        if (file) handleFile(file);
    });
    fileInput.addEventListener('change', () => {
        if (fileInput.files[0]) handleFile(fileInput.files[0]);
    });

    // ── Parse CSV ────────────────────────────────────────────────────────────
    function handleFile(file) {
        if (!file.name.match(/\.csv$/i)) {
            alert('Please upload a .csv file.');
            return;
        }
        document.getElementById('fileChosen').style.display = 'block';
        document.getElementById('fileChosen').textContent   = '✅ ' + file.name;

        const reader = new FileReader();
        reader.onload = e => parseCSV(e.target.result);
        reader.readAsText(file);
    }

    function parseCSV(text) {
        const lines = text.trim().split(/\r?\n/);
        if (lines.length < 2) { alert('File is empty or has no data rows.'); return; }

        // Normalize headers
        const headers = lines[0].split(',').map(h => h.trim().toLowerCase().replace(/\s+/g,'_'));

        parsedRows = [];
        validRows  = [];

        for (let i = 1; i < lines.length; i++) {
            if (!lines[i].trim()) continue;
            const cols   = splitCSVLine(lines[i]);
            const obj    = {};
            headers.forEach((h, idx) => obj[h] = (cols[idx] || '').trim());

            // Validate
            const errors = [];
            REQUIRED.forEach(f => { if (!obj[f]) errors.push('Missing ' + f); });
            if (obj.email && !obj.email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) errors.push('Invalid email');
            if (obj.department && !VALID_DEPTS.includes(obj.department)) errors.push('Unknown dept');
            if (obj.age && (isNaN(obj.age) || obj.age < 18 || obj.age > 80)) errors.push('Age 18-80');

            // Auto-generate password if blank
            if (!obj.password && obj.first_name) {
                obj.password = obj.first_name.charAt(0).toUpperCase() + obj.first_name.slice(1) + '@123';
                obj._pw_generated = true;
            }

            obj._row   = i;
            obj._errors = errors;
            parsedRows.push(obj);
            if (errors.length === 0) validRows.push(obj);
        }

        renderPreview();
    }

    // Handles quoted CSV fields
    function splitCSVLine(line) {
        const result = [];
        let cur = '', inQuotes = false;
        for (let c of line) {
            if (c === '"') { inQuotes = !inQuotes; }
            else if (c === ',' && !inQuotes) { result.push(cur); cur = ''; }
            else cur += c;
        }
        result.push(cur);
        return result;
    }

    // ── Render preview table ─────────────────────────────────────────────────
    function renderPreview() {
        const tbody = document.getElementById('previewBody');
        tbody.innerHTML = '';

        parsedRows.forEach(r => {
            const ok   = r._errors.length === 0;
            const tr   = document.createElement('tr');
            tr.className = ok ? 'row-ok' : 'row-err';

            const statusTag = ok
                ? '<span class="tag tag-ok">✔ Ready</span>'
                : `<span class="tag tag-err" title="${r._errors.join(', ')}">✖ ${r._errors[0]}</span>`;

            const pwDisplay = r._pw_generated
                ? `<em style="color:#6b7280;">${escHtml(r.password)} (auto)</em>`
                : escHtml(r.password || '—');

            tr.innerHTML = `
                <td>${r._row}</td>
                <td>${statusTag}</td>
                <td>${escHtml(r.employee_id||'')}</td>
                <td>${escHtml(r.first_name||'')}</td>
                <td>${escHtml(r.last_name||'')}</td>
                <td>${escHtml(r.email||'')}</td>
                <td>${escHtml(r.department||'')}</td>
                <td>${escHtml(r.job_role||'—')}</td>
                <td>${escHtml(r.age||'—')}</td>
                <td>${escHtml(r.date_joined||'')}</td>
                <td>${pwDisplay}</td>
            `;
            tbody.appendChild(tr);
        });

        document.getElementById('rowCountBadge').textContent =
            `${parsedRows.length} rows · ${validRows.length} valid · ${parsedRows.length - validRows.length} errors`;
        document.getElementById('previewSection').style.display = 'block';
        document.getElementById('resultLog').style.display = 'none';
        document.getElementById('resultLog').innerHTML = '';
        document.getElementById('progressWrap').style.display = 'none';
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    // ── Import ───────────────────────────────────────────────────────────────
    async function startImport() {
        if (validRows.length === 0) { alert('No valid rows to import.'); return; }

        const btn = document.getElementById('btnImport');
        btn.disabled = true;
        btn.textContent = 'Importing…';

        const log   = document.getElementById('resultLog');
        const wrap  = document.getElementById('progressWrap');
        const bar   = document.getElementById('progressBar');
        const label = document.getElementById('progressLabel');

        log.innerHTML = '';
        log.style.display = 'block';
        wrap.style.display = 'block';

        let success = 0, failed = 0;

        for (let i = 0; i < validRows.length; i++) {
            const row = validRows[i];
            const pct = Math.round(((i + 1) / validRows.length) * 100);
            bar.style.width   = pct + '%';
            label.textContent = `Importing ${i + 1} / ${validRows.length}…`;

            try {
                const fd = new FormData();
                Object.entries(row).forEach(([k, v]) => {
                    if (!k.startsWith('_')) fd.append(k, v);
                });
                fd.append('csrf_token', '<?php echo csrf_token(); ?>');

                const res  = await fetch('import_employees.php', { method: 'POST', body: fd });
                const json = await res.json();

                if (json.success) {
                    success++;
                    log.innerHTML += `<div class="log-ok">✔ Row ${row._row}: ${row.first_name} ${row.last_name} imported.</div>`;
                } else {
                    failed++;
                    log.innerHTML += `<div class="log-err">✖ Row ${row._row}: ${json.message}</div>`;
                }
            } catch (err) {
                failed++;
                log.innerHTML += `<div class="log-err">✖ Row ${row._row}: Network error.</div>`;
            }

            log.scrollTop = log.scrollHeight;
        }

        bar.style.width   = '100%';
        label.textContent = `Done — ${success} imported, ${failed} failed.`;
        log.innerHTML += `<div class="log-info" style="margin-top:.5rem;font-weight:bold;">
            ✅ ${success} employees imported successfully. ❌ ${failed} failed.</div>`;

        btn.disabled    = false;
        btn.textContent = '⬆ Import All Valid Rows';
    }

    function clearAll() {
        parsedRows = [];
        validRows  = [];
        document.getElementById('previewSection').style.display = 'none';
        document.getElementById('fileChosen').style.display = 'none';
        document.getElementById('csvFile').value = '';
    }
    </script>
</body>

</html>
