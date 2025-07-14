<?php

namespace App\Controllers;

use Core\Auth; //xuất excel
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once __DIR__ . '/../../vendor/autoload.php'; //xuất excel
class EvaluationController extends \Core\Controller
{
    public function index()
    {
        echo "EvaluationController index action";
        // Hoặc render view mặc định nếu bạn muốn
    }
    public function __construct()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }
    // xuất file excel
    public function review($id)
    {
        $evaluationModel = $this->model('Evaluation');
        $evaluation      = $evaluationModel->getEvaluationById($id);

        if (! $evaluation) {
            $_SESSION['error'] = 'Không tìm thấy bản đánh giá';
            header('Location: ' . $GLOBALS['config']['base_url'] . 'dashboard');
            exit;
        }

        // Truyền $evaluation vào view
        $this->view('manager-review', ['evaluation' => $evaluation]);
    }
    // xuất file excel
    public function exportExcel($id)
    {
        if (! Auth::check()) {
            header('Location: ' . $GLOBALS['config']['base_url'] . 'login');
            exit;
        }
    
        $evaluationModel = $this->model('Evaluation');
        $evaluation      = $evaluationModel->getEvaluationById($id);
        $userId          = Auth::user()['id'];
    
        // Lấy mẫu form đánh giá phù hợp
        $formModel = $this->model('EvaluationForm');
        $formData  = null;
    
        // Xác định loại form (lãnh đạo hoặc chuyên viên)
        $evaluationContent = json_decode($evaluation['content'], true);
        $formType          = isset($evaluationContent['part3_level_1']) ? 'lanh_dao' : 'nhan_vien';
    
        if (! empty($evaluation['department_id'])) {
            // Lấy form đánh giá của phòng ban
            $form = $formModel->getFormByDepartmentId($evaluation['department_id'], $formType);
            if ($form) {
                $formData = json_decode($form['content'], true);
            }
        }
    
        foreach ($formData['sections'] as $sectionIndex => $section) {
            if (isset($section['criteria'])) {
                foreach ($section['criteria'] as $criteriaIndex => $criteria) {
                    if (isset($criteria['max_score'])) {
                        $formData['sections'][$sectionIndex]['criteria'][$criteriaIndex]['max_score'] = $criteria['max_score'];
                    }
                }
            }
        }
    
        function removeExamples(&$array)
        {
            foreach ($array as &$section) {
                if (isset($section['criteria']) && is_array($section['criteria'])) {
                    foreach ($section['criteria'] as &$criterion) {
                        if (isset($criterion['examples'])) {
                            unset($criterion['examples']);
                        }
                    }
                }
            }
        }
    
        function removeTextEnd($textNumber)
        {
            if (substr($textNumber, -1) === ' + ') {
                $textNumber = substr($textNumber, 0, -1);
                $textNumber = rtrim($textNumber); // cắt khoảng trắng nếu có
            }
            return $textNumber;
        }
    
        function convertUsersToSignatures(array $users): array
        {
            $signatures = [];
    
            foreach ($users as $user) {
                $signature = [
                    'label'         => '', // mặc định rỗng, bạn có thể tùy chỉnh theo role
                    'comment_lines' => 3,  // mặc định 3 dòng
                    'name'          => $user['name'] ?? '',
                    'position'      => strtoupper($user['position'] ?? ''),
                    'role'          => $user['role'] ?? '', // thêm role để xác định comment
                ];
    
                // Gán nhãn (label) tùy theo role
                switch ($user['role']) {
                    case 'lanh_dao':
                        $signature['label'] = 'Lãnh đạo phòng/Phụ trách phòng nhận xét';
                        break;
                    case 'pho_giam_doc':
                        $signature['label'] = 'Phó Giám đốc phụ trách phòng/đơn vị nhận xét';
                        break;
                    case 'giam_doc':
                        $signature['label']         = 'Ý KIẾN NHẬN XÉT CỦA GIÁM ĐỐC TRUNG TÂM';
                        $signature['comment_lines'] = 0; // không cần dòng nhận xét
                        break;
                    default:
                        $signature['label'] = '';
                }
    
                $signatures[] = $signature;
            }
    
            return $signatures;
        }
    
        function attachScoresToCriteria(&$sections, $input1, $input2, &$totalMaxScoreAll, &$totalSelfScoreAll, &$totalDirectorScoreAll)
        {
            $scores1 = $input1['criteria'] ?? [];
            $scores2 = $input2;
    
            // Khởi tạo tổng toàn bộ
            $totalMaxScoreAll      = 0;
            $totalSelfScoreAll     = 0;
            $totalDirectorScoreAll = 0;
    
            foreach ($sections as &$section) {
                if (! isset($section['criteria']) || ! is_array($section['criteria'])) {
                    continue;
                }
    
                $totalMax      = 0;
                $totalSelf     = 0;
                $totalDirector = 0;
    
                foreach ($section['criteria'] as &$criterion) {
                    $id = $criterion['id'];
    
                    // Gắn self_score
                    if (isset($scores1[$id])) {
                        $criterion['self_score'] = $scores1[$id]['score'];
                    }
    
                    // Gắn director_score
                    if (isset($scores2[$id])) {
                        $criterion['director_score'] = $scores2[$id];
                    }
    
                    // Cộng điểm từng loại
                    $totalMax += $criterion['max_score'] ?? 0;
                    $totalSelf += $criterion['self_score'] ?? 0;
                    $totalDirector += $criterion['director_score'] ?? 0;
                }
    
                // Gán tổng điểm vào section
                $section['total_max_score']      = $totalMax;
                $section['total_self_score']     = $totalSelf;
                $section['total_director_score'] = $totalDirector;
    
                // Cộng dồn tổng toàn bộ
                $totalMaxScoreAll += $totalMax;
                $totalSelfScoreAll += $totalSelf;
                $totalDirectorScoreAll += $totalDirector;
            }
    
            return $sections;
        }
    
        // Gọi hàm:
        $data = removeExamples($formData['sections']); // $data là mảng bạn đang có
    
        $dataVoteContent       = json_decode($evaluation['content'], true);
        $directorRescore       = json_decode($evaluation['director_rescore'], true);
        $totalMaxScoreAll      = 0;
        $totalSelfScoreAll     = 0;
        $totalDirectorScoreAll = 0;
    
        $mappingData = attachScoresToCriteria($formData['sections'], $dataVoteContent, $directorRescore, $totalMaxScoreAll, $totalSelfScoreAll, $totalDirectorScoreAll);
    
        if (! $evaluation) {
            $_SESSION['error'] = 'Không tìm thấy bản đánh giá';
            header('Location: ' . $GLOBALS['config']['base_url'] . 'dashboard');
            exit;
        }
    
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
    
        // Đặt font chữ Times New Roman cho toàn bộ file
        $spreadsheet->getDefaultStyle()->getFont()->setName('Times New Roman');
    
        // ========== Styling ==========
        $bold      = ['font' => ['bold' => true]];
        $center    = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]];
        $wrapText  = ['alignment' => ['wrapText' => true]];
        $borderAll = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ];
    
        $sheet->getDefaultRowDimension()->setRowHeight(-1);
        $sheet->getStyle('A1:F100')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
    
        $sheet->getColumnDimension('A')->setWidth(15); // STT
        $sheet->getColumnDimension('B')->setWidth(100); // TIÊU CHÍ ĐÁNH GIÁ
        $sheet->getColumnDimension('C')->setWidth(15); // ĐIỂM TỐI ĐA
        $sheet->getColumnDimension('D')->setWidth(15); // ĐIỂM CV, NLV TỰ CHẤM
        $sheet->getColumnDimension('E')->setWidth(15);
    
        // ========== Header ==========
        $sheet->mergeCells('A1:C1')->setCellValue('A1', 'BỘ KHOA HỌC VÀ CÔNG NGHỆ');
        $sheet->mergeCells('D1:E1')->setCellValue('D1', 'CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM');
        $sheet->mergeCells('A2:C2')->setCellValue('A2', 'TRUNG TÂM CHỨNG THỰC ĐIỆN TỬ QUỐC GIA');
        $sheet->mergeCells('D2:E2')->setCellValue('D2', 'Độc lập - Tự do - Hạnh phúc');
    
        $sheet->mergeCells('A4:E4')->setCellValue('A4', 'BÁO CÁO ĐÁNH GIÁ KẾT QUẢ ĐÁNH GIÁ CỦA CÁ NHÂN');
        $sheet->mergeCells('A5:E5')->setCellValue('A5', 'Kỳ đánh giá: ' . date("Y"));
        $sheet->getStyle("A4:E4")->applyFromArray($bold)->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A5:E5")->applyFromArray($bold)->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
        $sheet->setCellValue('A7', 'Họ và tên:')->setCellValue('B7', $evaluation['employee_name']);
        $sheet->setCellValue('A8', 'Chức vụ:')->setCellValue('B8', $evaluation['employee_title'] ?? 'Chuyên viên');
        $sheet->setCellValue('A9', 'Đơn vị công tác:')->setCellValue('B9', $evaluation['employee_unit'] ?? 'Chưa xác định');
    
        // ========== Table Header ==========
        $startRow = 11;
        $headers  = ['STT', 'TIÊU CHÍ ĐÁNH GIÁ', 'ĐIỂM TỐI ĐA', 'ĐIỂM CV, NLV TỰ CHẤM', 'ĐIỂM THỰC TẾ ĐẠT ĐƯỢC'];
        foreach ($headers as $i => $header) {
            $col = chr(65 + $i); // A-E
            $sheet->setCellValue($col . $startRow, $header);
        }
        $sheet->getStyle("A{$startRow}:E{$startRow}")->applyFromArray($bold + $center + $borderAll);
    
        // ========== Nội dung bảng ==========
        $row        = $startRow + 1;
        $stt        = 1;
        $textNumber = [];
        foreach ($mappingData as $item) {
            $sttRow = strtoupper($this->roman($stt++));
            $sheet->setCellValue("A{$row}", $sttRow);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $titleWithoutRoman = preg_replace('/^[IVXLCDM]+\.\s/', '', $item['title']);
            $sheet->setCellValue("B{$row}", $titleWithoutRoman);
            $sheet->setCellValue("C{$row}", $item['total_max_score']);
            $sheet->setCellValue("D{$row}", $item['total_self_score']);
            $sheet->setCellValue("E{$row}", $item['total_director_score']);
            $sheet->getStyle("A{$row}:E{$row}")->applyFromArray($bold + $borderAll)->getAlignment()->setWrapText(true);
            $row++;
    
            foreach ($item['criteria'] as $key => $criteria) {
                $sheet->setCellValue("A{$row}", is_numeric($key) ? $key + 1 : $key);
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->setCellValue("B{$row}", $criteria['text']);
                $sheet->setCellValue("C{$row}", $criteria['max_score']);
                $sheet->setCellValue("D{$row}", $criteria['self_score']);
                $sheet->setCellValue("E{$row}", $criteria['director_score']);
                $sheet->getStyle("A{$row}:E{$row}")->applyFromArray($borderAll);
                $row++;
            }
    
            $textNumber[] = $sttRow;
        }
    
        // ========== Tổng kết điểm ==========
        $sheet->setCellValue("B{$row}", 'Tổng số điểm đạt được ' . implode(' + ', $textNumber));
        $sheet->setCellValue("C{$row}", 100);
        $sheet->setCellValue("D{$row}", $totalSelfScoreAll);
        $sheet->setCellValue("E{$row}", $evaluation['director_rescore_total'] ?? '');
        $sheet->getStyle("A{$row}:E{$row}")->applyFromArray($bold + $borderAll)->getAlignment()->setWrapText(true);
        $row++;
    
        $sheet->setCellValue("B{$row}", 'TỔNG SỐ ĐIỂM CHÍNH THỨC');
        $sheet->setCellValue("C{$row}", 100);
        $sheet->setCellValue("D{$row}", $totalSelfScoreAll - $evaluation['extra_deduction']);
        $sheet->setCellValue("E{$row}", $evaluation['director_rescore_final'] ?? '');
        $sheet->getStyle("A{$row}:E{$row}")->applyFromArray($bold + $borderAll)->getAlignment()->setWrapText(true);
        $row++;
    
        // ========== Xếp loại ==========
        $sheet->mergeCells("A{$row}:E{$row}")->setCellValue("A{$row}", 'KẾT QUẢ XẾP LOẠI');
        $sheet->getStyle("A{$row}")->applyFromArray($bold)->getAlignment()->setWrapText(true);
        $row++;
    
        $bold = [
            'font' => ['bold' => true],
        ];
    
        $sheet->mergeCells("A{$row}:A" . ($row + 1));
        $sheet->setCellValue("A{$row}", 'Xếp loại mức độ hoàn thành nhiệm vụ');
        $sheet->getStyle("A{$row}")->applyFromArray($bold + $borderAll)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
        $sheet->mergeCells("B{$row}:B" . ($row + 1));
        $sheet->setCellValue("B{$row}", 'Khung điểm');
        $sheet->getStyle("B{$row}")->applyFromArray($bold + $borderAll)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
        $sheet->mergeCells("C{$row}:D{$row}");
        $sheet->setCellValue("C{$row}", 'Tổng số điểm chính thức');
        $sheet->getStyle("C{$row}:D{$row}")->applyFromArray($bold + $borderAll)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
        $sheet->mergeCells("E{$row}:E{$row}");
        $sheet->setCellValue("E{$row}", 'Kết quả xếp loại (đánh dấu)');
        $sheet->getStyle("E{$row}:E{$row}")->applyFromArray($bold + $borderAll)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
        $row++;
        $sheet->setCellValue("C{$row}", 'Điểm cá nhân tự chấm');
        $sheet->setCellValue("D{$row}", 'Người có thẩm quyền chấm');
        $sheet->setCellValue("E{$row}", 'Cá nhân tự xếp loại');
        $sheet->getStyle("C{$row}:E{$row}")->applyFromArray($bold + $borderAll)->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
        $row++;
        $rank1 = ['Hoàn thành xuất sắc nhiệm vụ', '', '', '', ''];
        $rank2 = ['Hoàn thành tốt nhiệm vụ', '', '', '', ''];
        $rank3 = ['Hoàn thành nhiệm vụ', '', '', '', '', ''];
        $rank4 = ['Không hoàn thành nhiệm vụ', '', '', '', ''];
    
        $directorRescoreData = json_decode($evaluation['director_rescore'], true);
        $dutyScore = 0;
        if (!empty($directorRescoreData)) {
            $lastCriteriaKey = max(array_map(function ($key) {
                return (int)str_replace('criteria_', '', $key);
            }, array_keys($directorRescoreData)));
            $lastCriteriaId = 'criteria_' . $lastCriteriaKey;
            $dutyScore = isset($directorRescoreData[$lastCriteriaId]) ? (float)$directorRescoreData[$lastCriteriaId] : 0;
        }
        $finalScore = $evaluation['director_rescore_final'];
    
        if ($finalScore >= 95 && $dutyScore >= 5) {
            $rank1 = ['Hoàn thành xuất sắc nhiệm vụ', '', $totalSelfScoreAll - $evaluation['extra_deduction'], $evaluation['director_rescore_final'], 'x'];
        } elseif ($finalScore >= 80 && $dutyScore >= 4) {
            $rank2 = ['Hoàn thành tốt nhiệm vụ', '', $totalSelfScoreAll - $evaluation['extra_deduction'], $evaluation['director_rescore_final'], 'x'];
        } elseif ($finalScore >= 50 && $dutyScore >= 3) {
            $rank3 = ['Hoàn thành nhiệm vụ', '', $totalSelfScoreAll - $evaluation['extra_deduction'], $evaluation['director_rescore_final'], 'x'];
        } else {
            $rank4 = ['Không hoàn thành nhiệm vụ', '', $totalSelfScoreAll - $evaluation['extra_deduction'], $evaluation['director_rescore_final'], 'x'];
        }
    
        $ranking = [
            $rank1,
            $rank2,
            $rank3,
            $rank4,
        ];
    
        foreach ($ranking as $rankRow) {
            $sheet->fromArray($rankRow, null, "A{$row}");
            $sheet->getStyle("A{$row}:E{$row}")->applyFromArray($bold + $borderAll)->getAlignment()->setWrapText(true);
            $row++;
        }
    
        // ========== Phần ký tên ==========
        $sheet->getStyle("E{$row}")->applyFromArray($bold)->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValue("E{$row}", 'Hà Nội, ngày ... tháng ... năm ' . date("Y"));
        $row++;
    
        $signatureTitles = [
            'NGƯỜI TỰ ĐÁNH GIÁ' => $evaluation['employee_name'],
        ];
    
        foreach ($signatureTitles as $title => $name) {
            $sheet->getStyle("E{$row}")->applyFromArray($bold)->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->setCellValue("E{$row}", $title);
            $row++;
            $sheet->getStyle("E{$row}")->applyFromArray($bold)->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->setCellValue("E{$row}", $name);
            $row += 2;
        }
    
        // ========== Lãnh đạo nhận xét ==========
        $dataSignatures = $evaluation['signatures'] ?? [];
        if (! empty($dataSignatures)) {
            $dataSignaturesDecode = json_decode($dataSignatures, true);
            $signatures = convertUsersToSignatures($dataSignaturesDecode);
    
            // Định nghĩa ánh xạ từ role sang trường comment
            $commentFields = [
                'lanh_dao' => 'manager_comment',
                'pho_giam_doc' => 'deputy_director_comment',
                'giam_doc' => 'director_comment',
            ];
    
            foreach ($signatures as $sig) {
                // Label: Lãnh đạo phòng/Phụ trách phòng nhận xét...
                if (! empty($sig['label'])) {
                    $sheet->mergeCells("C{$row}:E{$row}");
                    $sheet->setCellValue("C{$row}", $sig['label']);
                    $sheet->getStyle("C{$row}")->applyFromArray([
                        'font'      => ['bold' => false],
                        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT],
                    ])->getAlignment()->setWrapText(true);
                    $row++;
                }
    
                // Thêm nhận xét thực tế nếu có
                if (isset($commentFields[$sig['role']]) && !empty($evaluation[$commentFields[$sig['role']]])) {
                    $comment = $evaluation[$commentFields[$sig['role']]];
                    $sheet->mergeCells("C{$row}:E{$row}");
                    $sheet->setCellValue("C{$row}", $comment);
                    $sheet->getStyle("C{$row}")->applyFromArray([
                        'font'      => ['size' => 10],
                        'alignment' => [
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                            'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP,
                        ],
                    ])->getAlignment()->setWrapText(true);
                    $row++;
                }
    
                // Chừa khoảng trống 2 dòng để ký tay
                $row += 2;
    
                // Position (TRƯỞNG PHÒNG, PHÓ GIÁM ĐỐC...)
                if (! empty($sig['position'])) {
                    $sheet->mergeCells("E{$row}:E{$row}");
                    $sheet->setCellValue("E{$row}", $sig['position']);
                    $sheet->getStyle("E{$row}")->applyFromArray([
                        'font'      => ['bold' => true],
                        'alignment' => [
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                            'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                        ],
                    ])->getAlignment()->setWrapText(true);
                    $row++;
                }
    
                // Chừa thêm khoảng trống 4 dòng để ký tay dưới chức vụ
                $row += 4;
    
                if (! empty($sig['name'])) {
                    $sheet->mergeCells("E{$row}:E{$row}");
                    $sheet->setCellValue("E{$row}", $sig['name']);
                    $sheet->getStyle("E{$row}")->applyFromArray([
                        'font'      => ['bold' => true],
                        'alignment' => [
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                            'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                        ],
                    ])->getAlignment()->setWrapText(true);
                    $row += 2; // Khoảng cách trước người tiếp theo
                }
            }
        }
    
        // ========== Xuất file ==========
        $filename = 'bao_cao_danh_gia_' . $id . '_' . date('Ymd_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
    
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
    

    // Helper hàm đổi số sang La Mã
    public function roman($num)
    {
        $map = [
            'M' => 1000,
            'CM' => 900,
            'D' => 500,
            'CD' => 400,
            'C' => 100,
            'XC'  => 90,
            'L'  => 50,
            'XL'  => 40,
            'X' => 10,
            'IX'   => 9,
            'V'   => 5,
            'IV'   => 4,
            'I' => 1,
        ];
        $returnValue = '';
        while ($num > 0) {
            foreach ($map as $roman => $int) {
                if ($num >= $int) {
                    $num -= $int;
                    $returnValue .= $roman;
                    break;
                }
            }
        }
        return $returnValue;
    }

    public function createForm()
    {
        Auth::requireRole('nhan_vien');
        $evaluationModel = $this->model('Evaluation');
        $userEvaluations = $evaluationModel->getEvaluationsByEmployeeId(Auth::user()['id']);

        // echo "<pre>";
        // print_r($userEvaluations);die;
        // echo "</pre>";

        // Lấy danh sách phòng ban của người dùng
        $departmentModel = $this->model('Department');
        $userDepartments = $departmentModel->getUserDepartments(Auth::user()['id']);
        //print_r($userDepartments);
        // Lấy form đánh giá phù hợp với phòng ban
        $formData     = null;
        $departmentId = null;

        if (! empty($userDepartments)) {
            $departmentId = $userDepartments[0]['id'];

            // Lấy form đánh giá chuyên viên cho phòng ban này
            $formModel = $this->model('EvaluationForm');
            $form      = $formModel->getFormByDepartmentId($departmentId, 'nhan_vien');
            if ($form) {
                $formData = json_decode($form['content'], true);
            } else {
                // Nếu không có form riêng cho phòng ban, dùng form mặc định
                $defaultForm = $formModel->getDefaultForm('nhan_vien');
                if ($defaultForm) {
                    $formData = json_decode($defaultForm['content'], true);
                } else {
                    // Nếu không tìm thấy cả form mặc định
                    require_once __DIR__ . '/../../data.php';
                    $formData = $dataRate['nhan_vien'];
                }
            }
        }

        // Nếu không tìm thấy form nào, dùng dữ liệu cố định từ data.php
        if (empty($formData)) {
            require_once __DIR__ . '/../../data.php';
            $formData = $dataRate['nhan_vien'];
        }

        // Tạo một đối tượng evaluation trống để sử dụng trong template
        $evaluation = [
            'employee_name'   => Auth::user()['name'],
            'employee_email'  => Auth::user()['email'],
            'department_id'   => $departmentId,
            'department_name' => ! empty($userDepartments) ? $userDepartments[0]['name'] : 'Chưa xác định',
        ];

        $data = [
            'title'           => 'Tự đánh giá',
            'evaluations'     => $userEvaluations,
            'userDepartments' => $userDepartments,
            'formData'        => $formData,
            'evaluation'      => $evaluation,
            'config'          => $GLOBALS['config'],
            'isNewForm'       => true, // Đánh dấu đây là form tạo mới để template có thể hiển thị nút submit
        ];
        $this->view('templates/header', $data);

        // Sử dụng template mới nếu form data có cấu trúc mới
        if (isset($formData['sections'])) {
            $this->view('evaluation/evaluation-form-template', $data);
        } else {
            $this->view('evaluation/create', $data);
        }
    }

    public function managerCreateForm()
    {
        Auth::requireRole('lanh_dao');

        $evaluationModel = $this->model('Evaluation');
        $evaluations     = $evaluationModel->getEvaluationsByEmployeeId(Auth::user()['id']);

        // Lấy danh sách phòng ban của người dùng
        $departmentModel = $this->model('Department');
        $userDepartments = $departmentModel->getUserDepartments(Auth::user()['id']);

        // Lấy form đánh giá phù hợp với phòng ban
        $formData     = null;
        $departmentId = null;

        if (! empty($userDepartments)) {
            $departmentId = $userDepartments[0]['id'];

            // Lấy form đánh giá lãnh đạo cho phòng ban này
            $formModel = $this->model('EvaluationForm');
            $form      = $formModel->getFormByDepartmentId($departmentId, 'lanh_dao');

            if ($form) {
                // Nếu tìm thấy form phòng ban
                $formData = json_decode($form['content'], true);
            } else {
                // Nếu không có form riêng cho phòng ban, dùng form mặc định
                $defaultForm = $formModel->getDefaultForm('lanh_dao');
                if ($defaultForm) {
                    $formData = json_decode($defaultForm['content'], true);
                } else {
                    // Nếu không tìm thấy cả form mặc định
                    require_once __DIR__ . '/../../data.php';
                    $formData = $dataRate['lanh_dao'];
                }
            }
        }

        // Nếu không tìm thấy form nào, dùng dữ liệu cố định từ data.php
        if (empty($formData)) {
            require_once __DIR__ . '/../../data.php';
            $formData = $dataRate['lanh_dao'];
        }

        // Tạo một đối tượng evaluation trống để sử dụng trong template
        $evaluation = [
            'employee_name'   => Auth::user()['name'],
            'employee_email'  => Auth::user()['email'],
            'department_id'   => $departmentId,
            'department_name' => ! empty($userDepartments) ? $userDepartments[0]['name'] : 'Chưa xác định',
        ];

        $data = [
            'title'           => 'Tự đánh giá lãnh đạo',
            'evaluations'     => $evaluations,
            'userDepartments' => $userDepartments,
            'formData'        => $formData,
            'evaluation'      => $evaluation,
            'config'          => $GLOBALS['config'],
            'isNewForm'       => true, // Đánh dấu đây là form tạo mới để template có thể hiển thị nút submit
        ];

        $this->view('templates/header', $data);

        // Sử dụng template mới nếu form data có cấu trúc mới
        if (isset($formData['sections'])) {
            $this->view('evaluation/evaluation-form-template', $data);
        } else {
            $this->view('evaluation/manager-create', $data);
        }
    }

    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $GLOBALS['config']['base_url'] . 'form-danh-gia');
            exit;
        }
        // dd($_POST);

        Auth::requireRole('nhan_vien');

        // Lấy dữ liệu từ form
        $criteriaScores = $_POST['criteria'] ?? [];
        $totalScore     = $_POST['total_score'] ?? 0;
        $notes          = $_POST['notes'] ?? '';
        $departmentId   = $_POST['department_id'] ?? null;
        $extra_deduction   = $_POST['extra_deduction'] ?? 0;
        $employee_rescore_total   = $totalScore ?? 0;
        $employee_rescore_final   = $employee_rescore_total - $extra_deduction ?? 0;

        // Nếu không có department_id, lấy phòng ban đầu tiên của người dùng
        if (! $departmentId) {
            $departmentModel = $this->model('Department');
            $userDepartments = $departmentModel->getUserDepartments(Auth::user()['id']);
            if (! empty($userDepartments)) {
                $departmentId = $userDepartments[0]['id'];
            }
        }

        // Validate dữ liệu
        $validationErrors = [];

        // Kiểm tra các trường điểm tiêu chí
        if (empty($criteriaScores)) {
            $validationErrors[] = 'Vui lòng điền đầy đủ điểm các tiêu chí';
        } else {
            // Kiểm tra từng tiêu chí có điểm hợp lệ không
            foreach ($criteriaScores as $criteriaKey => $criteriaData) {
                if (! isset($criteriaData['score']) || $criteriaData['score'] === '') {
                    $validationErrors[] = 'Vui lòng điền đầy đủ điểm cho tất cả các tiêu chí';
                    break;
                }
            }
        }

        // Kiểm tra tổng điểm
        if (empty($totalScore)) {
            $validationErrors[] = 'Vui lòng điền tổng điểm đánh giá';
        }

        // Nếu có lỗi, chuyển hướng về trang form với thông báo lỗi
        if (! empty($validationErrors)) {
            $_SESSION['error'] = implode('. ', $validationErrors);
            header('Location: ' . $GLOBALS['config']['base_url'] . 'form-danh-gia');
            exit;
        }

        // Tạo JSON data để lưu vào DB
        $contentData = [
            'criteria'    => $criteriaScores,
            'total_score' => $totalScore,
            'notes'       => $notes
        ];

        $content = json_encode($contentData, JSON_UNESCAPED_UNICODE);

        // Lưu vào DB sử dụng form đánh giá theo phòng ban
        $evaluationModel = $this->model('Evaluation');
        $success         = $evaluationModel->createEvaluationWithForm(Auth::user()['id'], $content, $departmentId, $employee_rescore_total, $employee_rescore_final, $extra_deduction);

        if ($success) {
            $_SESSION['success'] = 'Đã gửi đánh giá thành công';
        } else {
            $_SESSION['error'] = 'Có lỗi xảy ra khi gửi đánh giá';
        }

        header('Location: ' . $GLOBALS['config']['base_url'] . 'form-danh-gia');
        exit;
    }

    public function managerStore()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $GLOBALS['config']['base_url'] . 'lanh-dao-danh-gia');
            exit;
        }

        Auth::requireRole('lanh_dao');

        // Lấy dữ liệu từ form
        $criteriaScores = $_POST['criteria'] ?? [];
        $part3Level1    = $_POST['part3_level_1'] ?? '';
        $part3Level2    = $_POST['part3_level_2'] ?? '';
        $totalScore     = $_POST['total_score'] ?? '';
        $notes          = $_POST['notes'] ?? '';
        $departmentId   = $_POST['department_id'] ?? null;

        // echo "<pre>";
        // print_r($criteriaScores);die;
        // echo "</pre>";

        // Nếu không có department_id, lấy phòng ban đầu tiên của người dùng
        if (! $departmentId) {
            $departmentModel = $this->model('Department');
            $userDepartments = $departmentModel->getUserDepartments(Auth::user()['id']);
            if (! empty($userDepartments)) {
                $departmentId = $userDepartments[0]['id'];
            }
        }

        // Validate dữ liệu
        $validationErrors = [];

        // Kiểm tra các trường điểm tiêu chí
        if (empty($criteriaScores)) {
            $validationErrors[] = 'Vui lòng điền đầy đủ điểm các tiêu chí';
        } else {
            // Kiểm tra từng tiêu chí có điểm hợp lệ không
            foreach ($criteriaScores as $criteriaKey => $criteriaData) {
                if (! isset($criteriaData['score']) || $criteriaData['score'] === '') {
                    $validationErrors[] = 'Vui lòng điền đầy đủ điểm cho tất cả các tiêu chí';
                    break;
                }
            }
        }

        // Kiểm tra phần 3 cho lãnh đạo
        if (empty($part3Level1)) {
            $validationErrors[] = 'Vui lòng chọn mức độ đánh giá cá nhân';
        }

        if (empty($part3Level2)) {
            $validationErrors[] = 'Vui lòng chọn mức độ đánh giá đơn vị';
        }

        // Kiểm tra tổng điểm
        if (empty($totalScore)) {
            $validationErrors[] = 'Vui lòng điền tổng điểm đánh giá';
        }

        // Nếu có lỗi, chuyển hướng về trang form với thông báo lỗi
        if (! empty($validationErrors)) {
            $_SESSION['error'] = implode('. ', $validationErrors);
            header('Location: ' . $GLOBALS['config']['base_url'] . 'lanh-dao-danh-gia');
            exit;
        }

        // Tạo JSON data để lưu vào DB
        $contentData = [
            'criteria'      => $criteriaScores,
            'part3_level_1' => $part3Level1,
            'part3_level_2' => $part3Level2,
            'total_score'   => $totalScore,
            'notes'         => $notes,
        ];

        $content = json_encode($contentData, JSON_UNESCAPED_UNICODE);

        // Lưu vào DB sử dụng form đánh giá theo phòng ban
        $evaluationModel = $this->model('Evaluation');
        $success         = $evaluationModel->createManagerEvaluationWithForm(Auth::user()['id'], $content, $departmentId);

        if ($success) {
            $_SESSION['success'] = 'Đã gửi đánh giá thành công';
        } else {
            $_SESSION['error'] = 'Có lỗi xảy ra khi gửi đánh giá';
        }

        header('Location: ' . $GLOBALS['config']['base_url'] . 'lanh-dao-danh-gia');
        exit;
    }

    public function managerReviewList()
    {
        Auth::requireRole('lanh_dao');

        $evaluationModel     = $this->model('Evaluation');
        $userId              = Auth::user()['id'];
        $pendingEvaluations  = $evaluationModel->getEvaluationsByStatusAndDepartment('sent', $userId);
        $reviewedEvaluations = $evaluationModel->getEvaluationsByStatusAndDepartment('reviewed', $userId);

        $data = [
            'title'               => 'Duyệt đánh giá',
            'pendingEvaluations'  => $pendingEvaluations,
            'reviewedEvaluations' => $reviewedEvaluations,
        ];

        $this->view('templates/header', $data);
        $this->view('evaluation/manager-list', $data);
    }
    public function managerReviewForm($id)
    {
        Auth::requireRole('lanh_dao');

        $evaluationModel = $this->model('Evaluation');
        $evaluation      = $evaluationModel->getEvaluationById($id);
        $userId          = Auth::user()['id'];

        $leader_rescore = $evaluation['leader_rescore'];
        if (is_string($leader_rescore)) {
            $rescoreData = json_decode($leader_rescore, true);
        } else {
            $rescoreData = $leader_rescore;
        }

        $totalScore = 0;
        if (is_array($rescoreData)) {
            foreach ($rescoreData as $criteriaId => $score) {
                $totalScore += (float)$score;
            }
        }
        // dd($totalScore);

        // Kiểm tra xem đánh giá có tồn tại không
        if (! $evaluation) {
            $_SESSION['error'] = 'Không tìm thấy bản đánh giá';
            header('Location: ' . $GLOBALS['config']['base_url'] . 'lanh-dao-review');
            exit;
        }

        // Kiểm tra xem lãnh đạo có quyền quản lý phòng ban của người được đánh giá không
        $departmentModel     = $this->model('Department');
        $userDepartments     = $departmentModel->getUserDepartments($userId);
        $hasDepartmentAccess = false;

        foreach ($userDepartments as $department) {
            if ($department['is_leader'] && $department['id'] == $evaluation['department_id']) {
                $hasDepartmentAccess = true;
                break;
            }
        }

        if (! $hasDepartmentAccess) {
            $_SESSION['error'] = 'Bạn không có quyền xem đánh giá này';
            header('Location: ' . $GLOBALS['config']['base_url'] . 'lanh-dao-duyet');
            exit;
        }

        // Lấy mẫu form đánh giá phù hợp
        $formModel = $this->model('EvaluationForm');
        $formData  = null;

        // Xác định loại form (lãnh đạo hoặc chuyên viên)
        $evaluationContent = json_decode($evaluation['content'], true);
        $formType          = isset($evaluationContent['part3_level_1']) ? 'lanh_dao' : 'nhan_vien';

        if (! empty($evaluation['department_id'])) {
            // Lấy form đánh giá của phòng ban
            $form = $formModel->getFormByDepartmentId($evaluation['department_id'], $formType);
            if ($form) {
                $formData = json_decode($form['content'], true);
            }
        }

        // Nếu không có form riêng của phòng ban, sử dụng form mặc định
        if (! $formData) {
            $defaultForm = $formModel->getDefaultForm($formType);
            if ($defaultForm) {
                $formData = json_decode($defaultForm['content'], true);
            }
        }

        $data = [
            'title'      => 'Đánh giá bản tự đánh giá của: ' . $evaluation['employee_name'],
            'evaluation' => $evaluation,
            'formData'   => $formData,
            'totalScore'   => $totalScore,
        ];
        $this->view('templates/header', $data);
        $this->view('evaluation/manager-review', $data);
    }

    public function managerApprove($id)
    {
        Auth::requireRole('lanh_dao');
        // dd($_POST);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action         = $_POST['action'] ?? '';
            $comment        = $_POST['manager_comment'] ?? '';
            $extraDeductionLeader = $_POST['extra_deduction'] ?? 0;
            $rescore        = $_POST['rescore'] ?? [];

            $evaluationModel = $this->model('Evaluation');
            $evaluation      = $evaluationModel->getEvaluationById($id);
            $userId          = Auth::user()['id'];

            if (! $evaluation) {
                $_SESSION['error'] = 'Không tìm thấy bản đánh giá';
                header('Location: ' . $GLOBALS['config']['base_url'] . 'lanh-dao-review');
                exit;
            }
            if ($action === 'approve') {
                // Lưu điểm chấm lại vào DB
                $evaluationModel->updateLeaderRescore($id, $rescore, $extraDeductionLeader);

                $success = $evaluationModel->updateStatusAndManagerComment($id, 'reviewed', $comment);

                if ($success) {
                    $_SESSION['success'] = 'Đã duyệt đánh giá thành công';
                } else {
                    $_SESSION['error'] = 'Có lỗi xảy ra, vui lòng thử lại';
                }
            } elseif ($action === 'update') {
                // Chỉ cập nhật comment mà không thay đổi trạng thái
                $success = $evaluationModel->updateManagerComment($id, $comment, $rescore);

                if ($success) {
                    $_SESSION['success'] = 'Đã cập nhật nhận xét thành công';
                } else {
                    $_SESSION['error'] = 'Có lỗi xảy ra, vui lòng thử lại';
                }
            } elseif ($action === 'cancel') {
                // Kiểm tra xem có thể hoàn tác hay không (phó giám đốc và giám đốc chưa duyệt)
                if (empty($evaluation['deputy_director_comment']) && empty($evaluation['director_comment'])) {
                    // Đổi trạng thái về 'sent' và giữ nguyên comment
                    $success = $evaluationModel->updateStatus($id, 'sent');

                    if ($success) {
                        $_SESSION['success'] = 'Đã hoàn tác phê duyệt thành công';
                    } else {
                        $_SESSION['error'] = 'Có lỗi xảy ra, vui lòng thử lại';
                    }
                } else {
                    $_SESSION['error'] = 'Không thể hoàn tác phê duyệt khi đã có nhận xét từ cấp cao hơn';
                }
            }

            header('Location: ' . $GLOBALS['config']['base_url'] . 'lanh-dao-review');
            exit;
        }

        header('Location: ' . $GLOBALS['config']['base_url'] . 'lanh-dao-review');
        exit;
    }

    public function directorList()
    {
        Auth::requireRole('giam_doc');

        $evaluationModel = $this->model('Evaluation');

        // Get all evaluations but prioritize those with deputy director reviews
        $allEvaluations = $evaluationModel->getAllEvaluations();

        // Split evaluations into priority (deputy_reviewed) and others
        $priorityEvaluations = [];
        $otherEvaluations    = [];

        foreach ($allEvaluations as $evaluation) {
            if ($evaluation['status'] === 'deputy_reviewed') {
                $priorityEvaluations[] = $evaluation;
            } else {
                $otherEvaluations[] = $evaluation;
            }
        }

        // Combine with priority first
        $evaluations = array_merge($priorityEvaluations, $otherEvaluations);

        $data = [
            'title'       => 'Danh sách đánh giá',
            'evaluations' => $evaluations,
        ];

        $this->view('templates/header', $data);
        $this->view('evaluation/director-list', $data);
    }

    public function directorReviewForm($id)
    {
        Auth::requireRole('giam_doc');

        $evaluationModel = $this->model('Evaluation');
        $evaluation      = $evaluationModel->getEvaluationById($id);

        if (! $evaluation || ($evaluation['status'] != 'reviewed' && $evaluation['status'] != 'deputy_reviewed' && $evaluation['status'] != 'approved')) {
            $_SESSION['error'] = 'Không tìm thấy bản đánh giá hoặc bản đánh giá không ở trạng thái hợp lệ';
            header('Location: ' . $GLOBALS['config']['base_url'] . 'giam-doc-xem');
            exit;
        }

        $director_rescore = $evaluation['director_rescore'];
        if (is_string($director_rescore)) {
            $rescoreData = json_decode($director_rescore, true);
        } else {
            $rescoreData = $director_rescore;
        }

        $totalScore = 0;
        if (is_array($rescoreData)) {
            foreach ($rescoreData as $criteriaId => $score) {
                $totalScore += (float)$score;
            }
        }

        // Lấy mẫu form đánh giá phù hợp
        $formModel = $this->model('EvaluationForm');
        $formData  = null;

        // Xác định loại form (lãnh đạo hoặc chuyên viên)
        $evaluationContent = json_decode($evaluation['content'], true);
        $formType          = isset($evaluationContent['part3_level_1']) ? 'lanh_dao' : 'nhan_vien';

        if (! empty($evaluation['department_id'])) {
            // Lấy form đánh giá của phòng ban
            $form = $formModel->getFormByDepartmentId($evaluation['department_id'], $formType);
            if ($form) {
                $formData = json_decode($form['content'], true);
            }
        }

        // Nếu không có form riêng của phòng ban, sử dụng form mặc định
        if (! $formData) {
            $defaultForm = $formModel->getDefaultForm($formType);
            if ($defaultForm) {
                $formData = json_decode($defaultForm['content'], true);
            }
        }

        $data = [
            'title'      => 'Duyệt đánh giá: ' . $evaluation['employee_name'],
            'evaluation' => $evaluation,
            'formData'   => $formData,
            'totalScore'   => $totalScore,
        ];

        $this->view('templates/header', $data);

        // Sử dụng template mới nếu form data hợp lệ
        if ($formData && isset($formData['sections'])) {
            $this->view('evaluation/director-review', $data);
        } else {
            $this->view('evaluation/director-review', $data);
        }
    }

    public function directorSaveComment($id)
    {
        Auth::requireRole('giam_doc');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $comment = $_POST['director_comment'] ?? '';
            $action  = $_POST['action'] ?? '';
            $rescore = $_POST['rescore'] ?? [];
            $extra_deduction = $_POST['extra_deduction'] ?? 0;

            $evaluationModel = $this->model('Evaluation');
            $evaluation      = $evaluationModel->getEvaluationById($id);

            if (! $evaluation) {
                $_SESSION['error'] = 'Không tìm thấy bản đánh giá';
                header('Location: ' . $GLOBALS['config']['base_url'] . 'giam-doc-xem');
                exit;
            }

            if ($action === 'approve') {
                $success = $evaluationModel->updateDirectorCommentAndApprove($id, $comment);
                // Lưu điểm chấm lại vào DB
                $evaluationModel->updateDirectorRescore($id, $rescore, $extra_deduction);

                if ($success) {
                    $_SESSION['success'] = 'Đã phê duyệt và lưu nhận xét thành công';
                } else {
                    $_SESSION['error'] = 'Có lỗi xảy ra, vui lòng thử lại';
                }
            } elseif ($action === 'deputy_reviewed') {
                // Chỉ cập nhật comment mà không thay đổi trạng thái
                $success = $evaluationModel->updateDirectorComment($id, $comment, $rescore);

                if ($success) {
                    $_SESSION['success'] = 'Đã cập nhật nhận xét thành công';
                } else {
                    $_SESSION['error'] = 'Có lỗi xảy ra, vui lòng thử lại';
                }
            } elseif ($action === 'cancel') {
                // Kiểm tra xem có thể hoàn tác hay không (phó giám đốc chưa duyệt)
                if (empty($evaluation['deputy_director_comment'])) {
                    // Đổi trạng thái về 'reviewed' và giữ nguyên comment
                    $success = $evaluationModel->updateStatus($id, 'reviewed');

                    if ($success) {
                        $_SESSION['success'] = 'Đã hoàn tác phê duyệt thành công';
                    } else {
                        $_SESSION['error'] = 'Có lỗi xảy ra, vui lòng thử lại';
                    }
                } else {
                    $_SESSION['error'] = 'Không thể hoàn tác phê duyệt khi đã có nhận xét từ phó giám đốc';
                }
            }

            header('Location: ' . $GLOBALS['config']['base_url'] . 'giam-doc-xem');
            exit;
        }

        header('Location: ' . $GLOBALS['config']['base_url'] . 'giam-doc-xem');
        exit;
    }

    public function viewDetails($id)
    {
        // Require authentication (any role can view)
        if (! Auth::check()) {
            header('Location: ' . $GLOBALS['config']['base_url'] . 'login');
            exit;
        }

        $evaluationModel = $this->model('Evaluation');
        $evaluation      = $evaluationModel->getEvaluationById($id);

        if (! $evaluation) {
            $_SESSION['error'] = 'Không tìm thấy bản đánh giá';

            // Redirect based on user role
            $redirect = 'dashboard';
            if (Auth::hasRole('nhan_vien')) {
                $redirect = 'form-danh-gia';
            } elseif (Auth::hasRole('lanh_dao')) {
                $redirect = 'lanh-dao-danh-gia';
            } elseif (Auth::hasRole('pho_giam_doc')) {
                $redirect = 'pho-giam-doc-xem';
            } elseif (Auth::hasRole('giam_doc')) {
                $redirect = 'giam-doc-xem';
            }

            header('Location: ' . $GLOBALS['config']['base_url'] . $redirect);
            exit;
        }

        // Check if user has permission to view this evaluation
        $currentUser = Auth::user();
        if (
            $evaluation['employee_id'] != $currentUser['id'] &&
            ! Auth::hasRole('lanh_dao') &&
            ! Auth::hasRole('pho_giam_doc') &&
            ! Auth::hasRole('giam_doc') &&
            ! Auth::hasRole('admin')
        ) {

            $_SESSION['error'] = 'Bạn không có quyền xem bản đánh giá này';
            header('Location: ' . $GLOBALS['config']['base_url'] . 'dashboard');
            exit;
        }

        // Add redirect destination for pho_giam_doc role
        $redirect = 'dashboard';
        if (Auth::hasRole('nhan_vien')) {
            $redirect = 'form-danh-gia';
        } elseif (Auth::hasRole('lanh_dao')) {
            $redirect = 'lanh-dao-danh-gia';
        } elseif (Auth::hasRole('pho_giam_doc')) {
            $redirect = 'pho-giam-doc-xem';
        } elseif (Auth::hasRole('giam_doc')) {
            $redirect = 'giam-doc-xem';
        }

        // Lấy mẫu form đánh giá phù hợp từ EvaluationForm model
        $formModel = $this->model('EvaluationForm');
        $formData  = null;

        // Xác định loại form (lãnh đạo hoặc chuyên viên)
        $evaluationContent = json_decode($evaluation['content'], true);
        $formType          = isset($evaluationContent['part3_level_1']) ? 'lanh_dao' : 'nhan_vien';

        if (! empty($evaluation['department_id'])) {
            // Lấy form đánh giá của phòng ban
            $form = $formModel->getFormByDepartmentId($evaluation['department_id'], $formType);
            if ($form) {
                $formData = json_decode($form['content'], true);
            }
        }

        // Nếu không có form riêng của phòng ban, sử dụng form mặc định
        if (! $formData) {
            $defaultForm = $formModel->getDefaultForm($formType);
            if ($defaultForm) {
                $formData = json_decode($defaultForm['content'], true);
            }
        }

        $data = [
            'title'      => 'Chi tiết đánh giá',
            'evaluation' => $evaluation,
            'formData'   => $formData,
            'config'     => $GLOBALS['config'],
        ];

        $this->view('templates/header', $data);

        // Sử dụng template mới nếu form data hợp lệ, ngược lại sử dụng template cũ
        if ($formData && isset($formData['sections'])) {
            $this->view('evaluation/evaluation-form-template', $data);
        } else {
            // Fallback to old template if new form structure is not available
            $this->view('evaluation/view-details', $data);
        }
    }

    public function employeeReviewForm($id)
    {
        Auth::requireRole('nhan_vien');

        $evaluationModel = $this->model('Evaluation');
        $evaluation      = $evaluationModel->getEvaluationById($id);

        if (! $evaluation) {
            $_SESSION['error'] = 'Không tìm thấy bản đánh giá';
            header('Location: ' . $GLOBALS['config']['base_url'] . 'form-danh-gia');
            exit;
        }

        // Verify that the employee is viewing their own evaluation
        $currentUser = Auth::user();
        if ($evaluation['employee_id'] != $currentUser['id']) {
            $_SESSION['error'] = 'Bạn không có quyền xem bản đánh giá này';
            header('Location: ' . $GLOBALS['config']['base_url'] . 'form-danh-gia');
            exit;
        }

        // Lấy mẫu form đánh giá phù hợp
        $formModel = $this->model('EvaluationForm');
        $formData  = null;

        // Xác định loại form (lãnh đạo hoặc chuyên viên)
        $evaluationContent = json_decode($evaluation['content'], true);
        $formType          = isset($evaluationContent['part3_level_1']) ? 'lanh_dao' : 'nhan_vien';

        if (! empty($evaluation['department_id'])) {
            // Lấy form đánh giá của phòng ban
            $form = $formModel->getFormByDepartmentId($evaluation['department_id'], $formType);
            if ($form) {
                $formData = json_decode($form['content'], true);
            }
        }

        // Nếu không có form riêng của phòng ban, sử dụng form mặc định
        if (! $formData) {
            $defaultForm = $formModel->getDefaultForm($formType);
            if ($defaultForm) {
                $formData = json_decode($defaultForm['content'], true);
            }
        }

        $data = [
            'title'      => 'Chi tiết đánh giá',
            'evaluation' => $evaluation,
            'formData'   => $formData,
            'config'     => $GLOBALS['config'],
        ];

        $this->view('templates/header', $data);

        // Sử dụng template mới nếu form data hợp lệ
        if ($formData && isset($formData['sections'])) {
            $this->view('evaluation/view-details', $data);
        } else {
            $this->view('evaluation/view-details', $data);
        }
    }

    public function managerViewForm($id)
    {
        Auth::requireRole('lanh_dao');

        $evaluationModel = $this->model('Evaluation');
        $evaluation      = $evaluationModel->getEvaluationById($id);

        if (! $evaluation) {
            $_SESSION['error'] = 'Không tìm thấy bản đánh giá';
            header('Location: ' . $GLOBALS['config']['base_url'] . 'lanh-dao-danh-gia');
            exit;
        }

        // Verify that the manager is viewing their own evaluation
        $currentUser = Auth::user();
        if ($evaluation['employee_id'] != $currentUser['id']) {
            $_SESSION['error'] = 'Bạn không có quyền xem bản đánh giá này';
            header('Location: ' . $GLOBALS['config']['base_url'] . 'lanh-dao-danh-gia');
            exit;
        }

        // Lấy mẫu form đánh giá phù hợp
        $formModel = $this->model('EvaluationForm');
        $formData  = null;

        // Xác định loại form (lãnh đạo hoặc chuyên viên)
        $evaluationContent = json_decode($evaluation['content'], true);
        $formType          = isset($evaluationContent['part3_level_1']) ? 'lanh_dao' : 'nhan_vien';

        if (! empty($evaluation['department_id'])) {
            // Lấy form đánh giá của phòng ban
            $form = $formModel->getFormByDepartmentId($evaluation['department_id'], $formType);
            if ($form) {
                $formData = json_decode($form['content'], true);
            }
        }

        // Nếu không có form riêng của phòng ban, sử dụng form mặc định
        if (! $formData) {
            $defaultForm = $formModel->getDefaultForm($formType);
            if ($defaultForm) {
                $formData = json_decode($defaultForm['content'], true);
            }
        }

        $data = [
            'title'      => 'Chi tiết đánh giá',
            'evaluation' => $evaluation,
            'formData'   => $formData,
            'config'     => $GLOBALS['config'],
        ];

        $this->view('templates/header', $data);

        // Sử dụng template mới nếu form data hợp lệ
        if ($formData && isset($formData['sections'])) {
            $this->view('evaluation/evaluation-form-template', $data);
        } else {
            $this->view('evaluation/view-details', $data);
        }
    }

    /**
     * Deputy Director Review List
     */
    public function deputyDirectorList()
    {
        Auth::requireRole('pho_giam_doc');

        $evaluationModel = $this->model('Evaluation');
        $evaluations     = $evaluationModel->getEvaluationsForDeputyDirector();

        $data = [
            'title'       => 'Danh sách đánh giá cần phê duyệt',
            'evaluations' => $evaluations,
        ];

        $this->view('templates/header', $data);
        $this->view('evaluation/deputy-director-list', $data);
    }

    /**
     * Deputy Director Review Form
     */
    public function deputyDirectorReviewForm($id)
    {
        Auth::requireRole('pho_giam_doc');

        $evaluationModel = $this->model('Evaluation');
        $evaluation      = $evaluationModel->getEvaluationById($id);

        if (! $evaluation || ($evaluation['status'] != 'reviewed' && $evaluation['status'] != 'deputy_reviewed')) {
            $_SESSION['error'] = 'Không tìm thấy bản đánh giá hoặc bản đánh giá không ở trạng thái chờ phê duyệt';
            header('Location: ' . $GLOBALS['config']['base_url'] . 'pho-giam-doc-xem');
            exit;
        }

        $deputy_director_rescore = $evaluation['deputy_director_rescore'];
        if (is_string($deputy_director_rescore)) {
            $rescoreData = json_decode($deputy_director_rescore, true);
        } else {
            $rescoreData = $deputy_director_rescore;
        }

        $totalScore = 0;
        if (is_array($rescoreData)) {
            foreach ($rescoreData as $criteriaId => $score) {
                $totalScore += (float)$score;
            }
        }

        // Lấy mẫu form đánh giá phù hợp
        $formModel = $this->model('EvaluationForm');
        $formData  = null;

        // Xác định loại form (lãnh đạo hoặc chuyên viên)
        $evaluationContent = json_decode($evaluation['content'], true);
        $formType          = isset($evaluationContent['part3_level_1']) ? 'lanh_dao' : 'nhan_vien';

        if (! empty($evaluation['department_id'])) {
            // Lấy form đánh giá của phòng ban
            $form = $formModel->getFormByDepartmentId($evaluation['department_id'], $formType);
            if ($form) {
                $formData = json_decode($form['content'], true);
            }
        }

        // Nếu không có form riêng của phòng ban, sử dụng form mặc định
        if (! $formData) {
            $defaultForm = $formModel->getDefaultForm($formType);
            if ($defaultForm) {
                $formData = json_decode($defaultForm['content'], true);
            }
        }

        $data = [
            'title'      => 'Phê duyệt đánh giá: ' . $evaluation['employee_name'],
            'evaluation' => $evaluation,
            'formData'   => $formData,
            'totalScore'   => $totalScore,
        ];

        $this->view('templates/header', $data);

        // Sử dụng template mới nếu form data hợp lệ
        if ($formData && isset($formData['sections'])) {
            $this->view('evaluation/deputy-director-review', $data);
        } else {
            $this->view('evaluation/deputy-director-review', $data);
        }
    }

    /**
     * Deputy Director Save Comment
     */
    public function deputyDirectorSaveComment($id)
    {
        Auth::requireRole('pho_giam_doc');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $extra_deduction = $_POST['extra_deduction'] ?? 0;

            $comment         = $_POST['comment'] ?? '';
            $evaluationModel = $this->model('Evaluation');
            $rescore         = $_POST['rescore'] ?? [];
            $evaluationModel->updateDeputyDirectorRescore($id, $rescore, $extra_deduction);
            $evaluation = $evaluationModel->getEvaluationById($id);

            if (! $evaluation || ($evaluation['status'] != 'reviewed' && $evaluation['status'] != 'deputy_reviewed')) {
                $_SESSION['error'] = 'Không tìm thấy bản đánh giá hoặc bản đánh giá không ở trạng thái chờ phê duyệt';
                header('Location: ' . $GLOBALS['config']['base_url'] . 'pho-giam-doc-xem');
                exit;
            }

            // Nếu đã ở trạng thái deputy_reviewed, chỉ cập nhật comment
            if ($evaluation['status'] === 'deputy_reviewed') {
                $success = $evaluationModel->updateDeputyDirectorComment($id, $comment);
            } else {
                $success = $evaluationModel->updateStatusAndDeputyDirectorComment($id, 'deputy_reviewed', $comment, $rescore, $extra_deduction);
            }

            if ($success) {
                $_SESSION['success'] = 'Đã lưu nhận xét và chuyển cho giám đốc';
                header('Location: ' . $GLOBALS['config']['base_url'] . 'pho-giam-doc-xem');
                exit;
            } else {
                $_SESSION['error'] = 'Có lỗi xảy ra, vui lòng thử lại';
                header('Location: ' . $GLOBALS['config']['base_url'] . 'pho-giam-doc-xem/' . $id);
                exit;
            }
        }

        header('Location: ' . $GLOBALS['config']['base_url'] . 'pho-giam-doc-xem/' . $id);
        exit;
    }
    public function updateExtraDeduction()
    {
        Auth::requireRole(['nhan_vien', 'lanh_dao', 'pho_giam_doc', 'giam_doc']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $evaluationId    = $_POST['evaluation_id'] ?? null;
            $extraDeduction  = $_POST['extra_deduction'] ?? 0;
            $evaluationModel = $this->model('Evaluation');
            $evaluationModel->updateExtraDeduction($evaluationId, $extraDeduction);
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit;
        }
    }
}
