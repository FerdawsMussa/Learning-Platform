<?php
require_once 'api/session_helper.php';
require_once 'api/db.php';

// This page can be public but we usually check if it belongs to the user or is a valid cert
$cert_id = $_GET['id'] ?? '';

if (empty($cert_id)) {
    die("Certificate ID missing.");
}

// Fetch Certificate & Course/Student Data
$stmt = $pdo->prepare("
    SELECT c.*, s.full_name as student_name, co.title as course_title, co.thumbnail_path
    FROM (SELECT id, user_id AS student_id, course_id, metric_value AS cert_id, created_at AS issued_at FROM progress WHERE record_type = 'certificate') c
    JOIN users s ON c.student_id = s.id
    JOIN (SELECT id, title, file_path AS thumbnail_path FROM content WHERE record_type = 'course') co ON c.course_id = co.id
    WHERE c.cert_id = ?
");
$stmt->execute([$cert_id]);
$cert_data = $stmt->fetch();

if (!$cert_data) {
    die("Certificate not found or invalid.");
}

$issued_date = date('F d, Y', strtotime($cert_data['issued_at']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate of Completion | <?= htmlspecialchars($cert_data['course_title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --cert-gold: #c5a059;
            --cert-dark: #1a1a1a;
            --cert-bg: #fdfdfd;
            --accent-green: #00e599;
        }

        body {
            background-color: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            font-family: 'Inter', sans-serif;
            color: #1e293b;
        }

        .certificate-container {
            position: relative;
            max-width: 1100px;
            width: 100%;
            padding: 20px;
        }

        .certificate-wrapper {
            background: white;
            padding: 20px;
            box-shadow: 0 40px 100px rgba(0,0,0,0.5);
            position: relative;
            background-image: url('https://www.transparenttextures.com/patterns/natural-paper.png');
            border-radius: 4px;
        }

        .certificate-inner {
            /* Double border fix for html2canvas */
            border: 4px solid var(--cert-gold);
            padding: 70px 50px;
            text-align: center;
            position: relative;
            background: rgba(255, 255, 255, 0.9);
            min-height: 700px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        /* Inner decorative border */
        .certificate-inner::before {
            content: '';
            position: absolute;
            top: 8px; left: 8px; right: 8px; bottom: 8px;
            border: 2px solid var(--cert-gold);
            pointer-events: none;
        }

        .brand-logo {
            font-size: 2.5rem;
            font-weight: 800;
            color: #111;
            margin-bottom: 40px;
            letter-spacing: -1.5px;
        }
        .brand-logo span { color: var(--accent-green); }

        .cert-title {
            font-family: 'Georgia', serif;
            font-size: 4rem;
            color: var(--cert-dark);
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 12px;
            font-weight: 900;
        }

        .cert-subtitle {
            font-size: 1.2rem;
            color: #64748b;
            margin-bottom: 60px;
            letter-spacing: 6px;
            font-weight: 700;
        }

        .student-name {
            font-size: 3.8rem;
            color: var(--accent-green);
            font-family: 'Dancing Script', cursive;
            font-weight: 700;
            margin-bottom: 20px;
            display: inline-block;
            padding: 0 40px 10px 40px;
            border-bottom: 3px double #e2e8f0;
        }

        .course-info {
            font-size: 1.6rem;
            color: #334155;
            margin-bottom: 40px;
            max-width: 85%;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.4;
        }

        .cert-footer {
            margin-top: 80px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            padding: 0 40px;
        }

        .signature-block {
            text-align: center;
            width: 250px;
        }

        .signature-text {
            font-family: 'Dancing Script', cursive;
            font-size: 2.2rem;
            color: #1e293b;
            margin-bottom: 0;
            line-height: 1;
        }

        .signature-line {
            border-top: 2px solid #cbd5e1;
            margin-top: 10px;
            padding-top: 8px;
            font-size: 0.9rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 600;
        }

        .qr-section {
            text-align: center;
        }

        .qr-code {
            width: 90px;
            height: 90px;
            margin-bottom: 10px;
            border: 2px solid #f1f5f9;
            padding: 5px;
            background: white;
        }

        .cert-meta {
            font-size: 0.8rem;
            color: #94a3b8;
            margin-top: 60px;
            font-family: 'Courier New', monospace;
            letter-spacing: 0.5px;
        }

        .no-print-tools {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1000;
            background: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(15px);
            padding: 15px 30px;
            border-radius: 100px;
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 20px 50px rgba(0,0,0,0.6);
        }

        @media (max-width: 992px) {
            .cert-title { font-size: 3rem; letter-spacing: 6px; }
            .student-name { font-size: 2.8rem; }
            .certificate-inner { padding: 50px 30px; }
        }

        @media (max-width: 768px) {
            .cert-title { font-size: 2.2rem; letter-spacing: 4px; }
            .student-name { font-size: 2.2rem; }
            .course-info { font-size: 1.2rem; }
            .cert-footer { flex-direction: column; align-items: center; gap: 40px; }
            .signature-block { width: 100%; }
        }

        @media print {
            @page {
                size: landscape;
                margin: 0;
            }
            .no-print-tools { display: none !important; }
            body { background: white !important; padding: 0 !important; margin: 0 !important; overflow: hidden; }
            .certificate-container { 
                max-width: 100% !important; 
                width: 100% !important; 
                height: 100vh !important;
                padding: 0 !important;
                margin: 0 !important;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .certificate-wrapper { 
                box-shadow: none !important; 
                border: none !important;
                padding: 0 !important;
                width: 100% !important;
                height: 100% !important;
            }
            .certificate-inner {
                border-width: 15px !important;
                min-height: 100% !important;
            }
        }
    </style>
</head>
<body>

    <div class="no-print-tools d-flex gap-3 align-items-center">
        <div class="text-white small d-none d-md-block me-2">Professional Credential</div>
        <button onclick="downloadPDF()" id="pdfBtn" class="btn btn-success px-4 rounded-pill fw-bold">Download PDF</button>
        <button onclick="downloadCertificate()" id="downloadBtn" class="btn btn-outline-success px-4 rounded-pill fw-bold text-white">Save as Image</button>
        <button onclick="window.print()" class="btn btn-outline-light px-4 rounded-pill border-opacity-25">Print</button>
        <a href="dashboard.php" class="btn btn-outline-light px-3 rounded-pill border-opacity-25">Dashboard</a>
    </div>



    <div class="certificate-container" id="certificateArea">
        <div class="certificate-wrapper">
            <div class="certificate-inner">
                <div class="brand-logo">JU<span>Learn</span></div>
                
                <h1 class="cert-title">Certificate</h1>
                <p class="cert-subtitle">OF COMPLETION</p>

                <p class="mb-2 text-secondary">This is to certify that</p>
                <div class="student-name"><?= htmlspecialchars($cert_data['student_name']) ?></div>

                <p class="mt-4 text-secondary">has successfully completed the course</p>
                <h2 class="course-info fw-bold text-dark">"<?= htmlspecialchars($cert_data['course_title']) ?>"</h2>

                <div class="cert-footer">
                    <div class="signature-block">
                        <p class="signature-text">JU Learn Admin</p>
                        <div class="signature-line">Authorized Signature</div>
                    </div>

                    <div class="qr-section">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=https://julearn.com/verify?id=<?= $cert_data['cert_id'] ?>" alt="Verification QR" class="qr-code">
                        <div class="text-uppercase fw-bold text-secondary" style="font-size: 0.6rem; letter-spacing: 1px;">Verify Credential</div>
                    </div>

                    <div class="signature-block">
                        <p class="mb-0 fw-bold text-dark" style="font-size: 1.2rem;"><?= $issued_date ?></p>
                        <div class="signature-line">Date of Issuance</div>
                    </div>
                </div>

                <div class="cert-meta">
                    Digital ID: <?= htmlspecialchars($cert_data['cert_id']) ?> | Official Academic Record | Verify at julearn.com/verify
                </div>
            </div>
        </div>
    </div>

    <!-- html2canvas and html2pdf for high-quality downloads -->
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        function downloadCertificate() {
            const btn = document.getElementById('downloadBtn');
            const originalText = btn.innerText;
            btn.innerText = "Generating PNG...";
            btn.disabled = true;

            const area = document.getElementById('certificateArea');
            const options = {
                scale: 4, // Higher resolution
                useCORS: true,
                backgroundColor: null,
                logging: false
            };

            html2canvas(area, options).then(canvas => {
                const link = document.createElement('a');
                link.download = `Certificate_<?= str_replace(' ', '_', $cert_data['student_name']) ?>.png`;
                link.href = canvas.toDataURL("image/png");
                link.click();
                
                btn.innerText = originalText;
                btn.disabled = false;
            }).catch(err => {
                console.error("Download failed:", err);
                alert("Download failed.");
                btn.innerText = originalText;
                btn.disabled = false;
            });
        }

        function downloadPDF() {
            const btn = document.getElementById('pdfBtn');
            const originalText = btn.innerText;
            btn.innerText = "Generating PDF...";
            btn.disabled = true;

            const element = document.getElementById('certificateArea');
            const opt = {
                margin: 0,
                filename: `Certificate_<?= str_replace(' ', '_', $cert_data['student_name']) ?>.pdf`,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 3, useCORS: true },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' }
            };

            html2pdf().set(opt).from(element).save().then(() => {
                btn.innerText = originalText;
                btn.disabled = false;
            }).catch(err => {
                console.error("PDF generation failed:", err);
                alert("PDF generation failed.");
                btn.innerText = originalText;
                btn.disabled = false;
            });
        }
    </script>

</body>
</html>
