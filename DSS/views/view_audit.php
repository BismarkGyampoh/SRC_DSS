<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole(['Admin', 'Executive Board']);

$logId = filter_input(INPUT_GET, 'log_id', FILTER_VALIDATE_INT);

if (!$logId) { 
    die('Invalid Audit Log ID.'); 
}

$stmt = $pdo->prepare('SELECT report_html, created_at FROM audit_logs WHERE log_id = :log_id');
$stmt->execute([':log_id' => $logId]);
$log = $stmt->fetch();

if (!$log) { 
    die('Audit report not found or has been purged.'); 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SRC DSS — Official Selection Report</title>
    <link rel="stylesheet" href="/dss/public/css/app.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        body { padding: 2rem; }
        .verification-header { background: #025928; color: #fff; padding: 1.5rem; border-radius: 8px 8px 0 0; text-align: center; }
        .verification-body { background: #fff; padding: 2rem; border-radius: 0 0 8px 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .verification-body { overflow-x: auto; }
        .verification-body table { width: 100%; min-width: 560px; }
        .verification-body img, .verification-body canvas { max-width: 100%; height: auto !important; }
        @media (max-width: 600px) {
            body { padding: 1rem; }
            .verification-header, .verification-body { padding: 1.25rem; }
        }
        .export-btn-wrapper { text-align: right; margin-bottom: 1rem; }
        .export-pdf-btn {
            background: #025928;
            color: #fff;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-weight: 600;
            letter-spacing: 0.02em;
        }
        .export-pdf-btn:hover { background: #034d1f; }
        @media print {
            .export-btn-wrapper { display: none !important; }
            .verification-shell { box-shadow: none !important; }
            .verification-header, .verification-body { border-radius: 0 !important; box-shadow: none !important; }
            body { padding: 0 !important; }
        }
    </style>
</head>
<body>
    <div class="export-btn-wrapper">
        <button class="export-pdf-btn" id="exportPdfBtn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:0.3rem;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Export to PDF
        </button>
    </div>
    <div class="verification-shell">
    <div class="verification-header">
        <h2>Official Project Selection Report</h2>
        <p><i class="material-icons" style="vertical-align: middle; margin-right: 0.3rem;">calendar_today</i>Date: <?= htmlspecialchars((string) $log['created_at'], ENT_QUOTES, 'UTF-8') ?></p>
    </div>
        <div class="verification-body"><?= $log['report_html'] ?></div>
    </div>

    <script>
    (function() {
        const btn = document.getElementById('exportPdfBtn');
        if (!btn) return;

        btn.addEventListener('click', function() {
            const element = document.querySelector('.verification-shell');
            if (!element) return;

            const timestamp = new Date().toISOString().replace(/[:.]/g, '-').replace('T', '_').slice(0, 19);
            const logId = <?= (int) $logId ?>;
            const filename = 'SRC_Selection_Report_Log_' + logId + '_' + timestamp + '.pdf';

            const opt = {
                margin:       0,
                filename:     filename,
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' },
                pagebreak:    { mode: ['avoid-all', 'css', 'legacy'] }
            };

            btn.disabled = true;
            btn.textContent = 'Generating PDF...';

            html2pdf().set(opt).from(element).save().then(function() {
                btn.disabled = false;
                btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:0.3rem;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Export to PDF';
            }).catch(function() {
                btn.disabled = false;
                btn.textContent = 'Export to PDF';
                alert('Failed to generate PDF. Please try again.');
            });
        });
    })();
    </script>
</body>
</html>
