<?php
session_start();
header('Content-Type: application/octet-stream');

require_once 'admin_db_config.php';
require_once '../middleware/AdminAuth.php';
require_once '../utils/validation.php';

try {
    AdminAuth::requireAdmin();
    $db = AdminDatabase::getInstance();
    $conn = $db->getConnection();

    $type = $_POST['type'] ?? 'pdf';
    $range = $_POST['range'] ?? '7';
    $startDate = $_POST['start_date'] ?? null;
    $endDate = $_POST['end_date'] ?? null;

    // Set appropriate headers based on export type
    if ($type === 'pdf') {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="report.pdf"');
    } else {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="report.xlsx"');
    }

    // Get report data
    $data = generateReportData($conn, $range, $startDate, $endDate);

    // Generate report based on type
    if ($type === 'pdf') {
        generatePDFReport($data);
    } else {
        generateExcelReport($data);
    }

} catch (Exception $e) {
    error_log("Error in export_report: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to export report'
    ]);
}

function generateReportData($conn, $range, $startDate, $endDate) {
    if ($range === 'custom' && $startDate && $endDate) {
        $dateCondition = "BETWEEN '$startDate' AND '$endDate'";
    } else {
        $dateCondition = ">= DATE_SUB(CURRENT_DATE, INTERVAL $range DAY)";
    }

    // User statistics
    $stmt = $conn->query("
        SELECT 
            COUNT(*) as total_users,
            COUNT(CASE WHEN created_at $dateCondition THEN 1 END) as new_users
        FROM users
    ");
    $userStats = $stmt->fetch();

    // Workout statistics
    $stmt = $conn->query("
        SELECT 
            COUNT(*) as total_workouts,
            COUNT(CASE WHEN status = 'completed' AND completed_at $dateCondition THEN 1 END) as completed_workouts
        FROM workout_schedules
    ");
    $workoutStats = $stmt->fetch();

    // Active users
    $stmt = $conn->query("
        SELECT COUNT(DISTINCT user_id) as active_users
        FROM workout_schedules
        WHERE created_at $dateCondition
    ");
    $activeUsers = $stmt->fetch();

    return [
        'user_stats' => $userStats,
        'workout_stats' => $workoutStats,
        'active_users' => $activeUsers['active_users'],
        'date_range' => [
            'start' => $startDate ?? date('Y-m-d', strtotime("-$range days")),
            'end' => $endDate ?? date('Y-m-d')
        ]
    ];
}

function generatePDFReport($data) {
    require_once '../vendor/autoload.php';

    $pdf = new TCPDF();
    $pdf->SetCreator('MuscleZen Admin');
    $pdf->SetAuthor('MuscleZen');
    $pdf->SetTitle('MuscleZen Analytics Report');

    $pdf->AddPage();

    // Add report header
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'MuscleZen Analytics Report', 0, 1, 'C');
    $pdf->Ln(10);

    // Add date range
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, 'Date Range: ' . $data['date_range']['start'] . ' to ' . $data['date_range']['end'], 0, 1);
    $pdf->Ln(5);

    // Add statistics
    addPDFStatistics($pdf, $data);

    $pdf->Output('report.pdf', 'D');
}

function generateExcelReport($data) {
    require_once '../vendor/autoload.php';

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Add title
    $sheet->setCellValue('A1', 'MuscleZen Analytics Report');
    $sheet->setCellValue('A2', 'Date Range: ' . $data['date_range']['start'] . ' to ' . $data['date_range']['end']);

    // Add statistics
    addExcelStatistics($sheet, $data);

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
}

function addPDFStatistics($pdf, $data) {
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'User Statistics', 0, 1);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, 'Total Users: ' . $data['user_stats']['total_users'], 0, 1);
    $pdf->Cell(0, 10, 'New Users: ' . $data['user_stats']['new_users'], 0, 1);
    $pdf->Cell(0, 10, 'Active Users: ' . $data['active_users'], 0, 1);

    $pdf->Ln(5);

    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Workout Statistics', 0, 1);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, 'Total Workouts: ' . $data['workout_stats']['total_workouts'], 0, 1);
    $pdf->Cell(0, 10, 'Completed Workouts: ' . $data['workout_stats']['completed_workouts'], 0, 1);
}

function addExcelStatistics($sheet, $data) {
    $sheet->setCellValue('A4', 'User Statistics');
    $sheet->setCellValue('A5', 'Total Users');
    $sheet->setCellValue('B5', $data['user_stats']['total_users']);
    $sheet->setCellValue('A6', 'New Users');
    $sheet->setCellValue('B6', $data['user_stats']['new_users']);
    $sheet->setCellValue('A7', 'Active Users');
    $sheet->setCellValue('B7', $data['active_users']);

    $sheet->setCellValue('A9', 'Workout Statistics');
    $sheet->setCellValue('A10', 'Total Workouts');
    $sheet->setCellValue('B10', $data['workout_stats']['total_workouts']);
    $sheet->setCellValue('A11', 'Completed Workouts');
    $sheet->setCellValue('B11', $data['workout_stats']['completed_workouts']);

    // Style the worksheet
    $sheet->getStyle('A1:A2')->getFont()->setBold(true);
    $sheet->getStyle('A4')->getFont()->setBold(true);
    $sheet->getStyle('A9')->getFont()->setBold(true);
}
