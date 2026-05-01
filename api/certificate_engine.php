<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/fpdf.php';
require_once __DIR__ . '/mail_helper.php';

function issue_course_certificate($student_id, $course_id) {
    global $pdo;

    // 1. Check if already issued
    $stmt = $pdo->prepare("SELECT cert_id FROM (SELECT id, user_id AS student_id, course_id, metric_value AS cert_id, created_at AS issued_at FROM progress WHERE record_type = 'certificate') progress_certificates WHERE student_id = ? AND course_id = ?");
    $stmt->execute([$student_id, $course_id]);
    $existing = $stmt->fetch();
    
    $cert_id = $existing ? $existing['cert_id'] : '';
    $file_path = __DIR__ . '/../temp/' . $cert_id . '.pdf';

    // If already exists AND file exists, we are done
    if ($existing && file_exists($file_path)) {
        return $cert_id;
    }

    // 1b. CRITICAL: Verify course completion before issuing
    $total_stmt = $pdo->prepare("SELECT COUNT(*) FROM progress WHERE record_type = 'lesson' AND course_id = ?");
    $total_stmt->execute([$course_id]);
    $total_lessons = $total_stmt->fetchColumn();

    $done_stmt = $pdo->prepare("SELECT COUNT(*) FROM progress WHERE record_type = 'lesson_progress' AND user_id = ? AND item_id IN (SELECT id FROM progress WHERE record_type = 'lesson' AND course_id = ?) AND metric_value = 'completed'");
    $done_stmt->execute([$student_id, $course_id]);
    $done_lessons = $done_stmt->fetchColumn();

    if ($total_lessons == 0 || $done_lessons < $total_lessons) {
        // Course not yet completed
        return false;
    }
    
    // If record doesn't exist, generate new ID and Insert
    if (!$existing) {
        $cert_id = "JU-" . strtoupper(substr(md5($student_id . $course_id . time()), 0, 8));
        $ins = $pdo->prepare("INSERT INTO progress (record_type, user_id, course_id, metric_value) VALUES ('certificate', ?, ?, ?)");
        $ins->execute([$student_id, $course_id, $cert_id]);
        $file_path = __DIR__ . '/../temp/' . $cert_id . '.pdf';
    }

    // 2. Fetch Data (needed for PDF & Email)
    $s_stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
    $s_stmt->execute([$student_id]);
    $student = $s_stmt->fetch();

    $c_stmt = $pdo->prepare("SELECT title FROM (SELECT id, user_id AS creator_id, title, description, file_path AS thumbnail_path, category, generic_value AS level, meta_text AS tags, is_resource, is_deleted, deleted_at, created_at FROM content WHERE record_type = 'course') content_courses WHERE id = ?");
    $c_stmt->execute([$course_id]);
    $course = $c_stmt->fetch();

    if (!$student || !$course) {
        error_log("Certificate Engine: Student or Course data missing for SID: $student_id, CID: $course_id");
        return false;
    }

    // 5. Generate PDF
    $pdf = new FPDF('L', 'mm', 'A4');
    $pdf->AddPage();
    
    // Background Border
    $pdf->SetLineWidth(3);
    $pdf->SetDrawColor(197, 160, 89); // Gold (#c5a059)
    $pdf->Rect(10, 10, 277, 190, 'D');
    
    $pdf->SetLineWidth(0.8);
    $pdf->Rect(13, 13, 271, 184, 'D');

    // Header
    $pdf->SetY(25);
    $pdf->SetFont('Arial', 'B', 24);
    $pdf->SetTextColor(30, 41, 59); // Dark blue/grey
    $pdf->Cell(0, 10, 'JU Learn', 0, 1, 'C');
    
    $pdf->Ln(10);
    $pdf->SetFont('Helvetica', 'B', 48);
    $pdf->SetTextColor(26, 26, 26);
    $pdf->Cell(0, 25, 'CERTIFICATE', 0, 1, 'C');
    
    $pdf->SetFont('Helvetica', 'B', 14);
    $pdf->SetTextColor(100, 116, 139); // Text secondary
    $pdf->Cell(0, 10, 'OF COMPLETION', 0, 1, 'C');

    $pdf->Ln(15);
    $pdf->SetFont('Arial', '', 14);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(0, 10, 'This is to certify that', 0, 1, 'C');

    $pdf->Ln(5);
    $pdf->SetFont('Times', 'B', 42);
    $pdf->SetTextColor(0, 229, 153); // JU Green
    $pdf->Cell(0, 20, $student['full_name'], 0, 1, 'C');
    
    $pdf->SetDrawColor(226, 232, 240);
    $pdf->Line(80, $pdf->GetY(), 217, $pdf->GetY());

    $pdf->Ln(10);
    $pdf->SetFont('Arial', '', 16);
    $pdf->SetTextColor(51, 65, 85);
    $pdf->Cell(0, 10, 'has successfully completed the course', 0, 1, 'C');

    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 24);
    $pdf->SetTextColor(30, 41, 59);
    $pdf->Cell(0, 15, '"' . $course['title'] . '"', 0, 1, 'C');

    $pdf->Ln(20);
    
    // Footer Area
    $pdf->SetY(165);
    
    // Left: Signature
    $pdf->SetX(30);
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetTextColor(30, 41, 59);
    $pdf->Cell(60, 10, 'JU Learn Admin', 0, 0, 'C');
    
    // Center: QR Placeholder / Text
    $pdf->SetX(110);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(148, 163, 184);
    $pdf->Cell(77, 10, 'VERIFIED CREDENTIAL', 0, 0, 'C');
    
    // Right: Date
    $pdf->SetX(207);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(30, 41, 59);
    $pdf->Cell(60, 10, date('F d, Y'), 0, 1, 'C');

    // Lines
    $pdf->SetDrawColor(203, 213, 225);
    $pdf->Line(30, 175, 90, 175);
    $pdf->Line(207, 175, 267, 175);
    
    $pdf->SetY(177);
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->SetX(30);
    $pdf->Cell(60, 5, 'Authorized Signature', 0, 0, 'C');
    $pdf->SetX(207);
    $pdf->Cell(60, 5, 'Date of Issuance', 0, 1, 'C');

    $pdf->SetY(188);
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(148, 163, 184);
    $pdf->Cell(0, 10, 'Digital ID: ' . $cert_id . ' | Verify at julearn.com/verify', 0, 1, 'C');

    // Save PDF temporarily
    if (!is_dir(__DIR__ . '/../temp')) mkdir(__DIR__ . '/../temp', 0777, true);
    $file_path = __DIR__ . '/../temp/' . $cert_id . '.pdf';
    $pdf->Output('F', $file_path);

    // 6. Send Email with Beautiful HTML Body
    $subject = "Congratulations! Your Certificate for " . $course['title'];
    $issued_date = date('F d, Y');
    
    $body = "
    <div style='background-color: #f1f5f9; padding: 40px; font-family: sans-serif;'>
        <div style='max-width: 600px; margin: 0 auto; background: white; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05);'>
            <div style='background: #0f172a; padding: 30px; text-align: center;'>
                <h1 style='color: white; margin: 0; font-size: 24px;'>JU<span style='color: #00e599;'>Learn</span></h1>
            </div>
            <div style='padding: 40px; text-align: center;'>
                <h2 style='color: #1e293b; margin-top: 0;'>Congratulations, {$student['full_name']}!</h2>
                <p style='color: #64748b; line-height: 1.6; font-size: 16px;'>
                    You have successfully completed the course <strong>\"{$course['title']}\"</strong>. 
                    Your official certificate is attached to this email as a professional PDF.
                </p>
                <div style='margin: 40px 0; padding: 30px; border: 8px double #c5a059; background: #fffcf5; position: relative;'>
                    <h3 style='margin: 0; color: #1e293b; font-family: Georgia, serif; font-size: 20px;'>CERTIFICATE OF COMPLETION</h3>
                    <p style='margin: 15px 0; color: #00e599; font-weight: bold; font-size: 22px; font-style: italic;'>{$student['full_name']}</p>
                    <p style='margin: 0; color: #64748b; font-size: 13px;'>for successfully completing</p>
                    <p style='margin: 5px 0; color: #1e293b; font-weight: bold; font-size: 16px;'>\"{$course['title']}\"</p>
                    <div style='margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 10px; font-size: 11px; color: #94a3b8;'>
                        Certificate ID: $cert_id | Issued on $issued_date
                    </div>
                </div>
                <div style='margin-top: 40px;'>
                    <a href='http://localhost/lastfyp/view_certificate.php?id=$cert_id' style='background: #00e599; color: #000; padding: 16px 32px; border-radius: 50px; text-decoration: none; font-weight: bold; display: inline-block;'>View Online & Download Higher Quality</a>
                </div>
            </div>
            <div style='background: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #e2e8f0;'>
                <p style='margin: 0; color: #94a3b8; font-size: 12px;'>JU Learn | Professional Learning Platform</p>
            </div>
        </div>
    </div>
    ";
    
    send_system_email($student['email'], $student['full_name'], $subject, $body, $file_path, "Certificate_{$cert_id}.pdf");

    return $cert_id;
}
