<?php
if (! isset($formData) || empty($formData)) {
    echo '<div class="bg-red-100 p-4 rounded-lg mb-4">Không tìm thấy dữ liệu form đánh giá</div>';
    return;
}

$isManager        = isset($formData['form_type']) && $formData['form_type'] === 'lanh_dao';
$sections         = $formData['sections'] ?? [];
$totalRescore     = $totalScore;
$competencyLevels = $formData['competency_levels'] ?? [];

// Get evaluation data if available
$evaluationData      = isset($evaluation) && isset($evaluation['content']) ? json_decode($evaluation['content'], true) : [];
$employeeSelfScore   = $evaluationData['criteria'] ?? [];
$managerScore        = isset($evaluation['manager_score']) ? json_decode($evaluation['manager_score'], true) : [];
$deputyDirectorScore = isset($evaluation['deputy_director_score']) ? json_decode($evaluation['deputy_director_score'], true) : [];
$directorScore       = isset($evaluation['director_score']) ? json_decode($evaluation['director_score'], true) : [];

// Determine if the current user is viewing, evaluating, or has already evaluated
$userRole = $_SESSION['user']['role'] ?? '';

// Define the evaluation status flow
$isEvaluator      = false;
$hasEvaluated     = false;
$evaluationStatus = isset($evaluation['status']) ? $evaluation['status'] : '';

// Check if current user is a manager who can evaluate
if ($userRole === 'lanh_dao' && $evaluationStatus === 'sent') {
    $isEvaluator  = true;
    $hasEvaluated = ! empty($evaluation['manager_comment']);
    $reviewType   = 'manager';
}
// Check if current user is a deputy director who can evaluate
else if ($userRole === 'pho_giam_doc' && $evaluationStatus === 'reviewed') {
    $isEvaluator  = true;
    $hasEvaluated = ! empty($evaluation['deputy_director_comment']);
    $reviewType   = 'deputy_director';
}
// Check if current user is a director who can evaluate
else if ($userRole === 'giam_doc' && ($evaluationStatus === 'deputy_reviewed' || $evaluationStatus === 'reviewed')) {
    $isEvaluator  = true;
    $hasEvaluated = ! empty($evaluation['director_comment']);
    $reviewType   = 'director';
}

$isNewForm = isset($isNewForm) && $isNewForm === true;

// Format functions
function formatDate($date)
{
    return date('d/m/Y', strtotime($date));
}

// Define submission URL based on role
$submissionUrl = $isManager
    ? $GLOBALS['config']['base_url'] . 'lanh-dao-danh-gia'
    : $GLOBALS['config']['base_url'] . 'form-danh-gia';
?>

<div class="bg-white rounded-lg shadow-md p-6 print:shadow-none print:p-0">
    <!-- Form header with actions -->
    <div class="flex justify-between items-center mb-4 print:hidden">
        <h2 class="text-xl font-bold text-gray-800">
            <?php echo $isNewForm ? ($isManager ? 'Tạo đánh giá lãnh đạo' : 'Tạo đánh giá chuyên viên') : 'Chi tiết đánh giá - Phó Giám đốc' ?>
        </h2>
        <div>
            <?php if (! $isNewForm): ?>
                <button onclick="window.print()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 mr-2">
                    <i class="fas fa-print mr-1"></i> In mẫu đánh giá
                </button>
            <?php endif; ?>
            <a href="javascript:history.back()" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">
                Quay lại
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p><?php echo $_SESSION['error'] ?></p>
            <?php unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
            <p><?php echo $_SESSION['success'] ?></p>
            <?php unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <!-- Form content cho trường hợp tạo mới -->
    <?php if ($isNewForm): ?>
        <form method="POST" action="<?php echo $submissionUrl ?>" id="evaluationForm" class="space-y-6">
            <!-- Department selection if available -->
            <?php if (isset($userDepartments) && count($userDepartments) > 0): ?>
                <div class="bg-indigo-50 p-4 mb-6 rounded-lg">
                    <p class="text-indigo-800 font-medium">
                        Mẫu đánh giá dành cho <?php echo $isManager ? 'Lãnh đạo' : 'Chuyên viên không giữ chức vụ lãnh đạo, quản lý' ?>
                    </p>
                    <div class="mt-2 bg-white p-2 rounded border border-indigo-200">
                        <p class="text-gray-700"><span class="font-medium">Phòng ban:</span>
                            <?php echo htmlspecialchars($evaluation['department_name']) ?>
                        </p>
                        <input type="hidden" name="department_id" value="<?php echo $evaluation['department_id'] ?>">
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Mở form cho người đánh giá nếu cần -->
        <?php if ($isEvaluator && ! $hasEvaluated && ! $isNewForm): ?>
            <form method="POST" action="<?php echo $GLOBALS['config']['base_url'] ?><?php echo $reviewType === 'deputy_director' ? 'pho-giam-doc-xem/submit' : 'admin/evaluations/save-review' ?>/<?php echo $evaluation['id'] ?>">
                <input type="hidden" name="review_type" value="<?php echo $reviewType ?>">
            <?php endif; ?>

            <!-- Print layout -->
            <div class="<?php echo $isNewForm ? '' : 'print:block' ?>">

                <!-- Employee information -->
                <div class="mb-4                                                                                                                                                                                                 <?php echo $isNewForm ? 'hidden' : '' ?>">
                    <p><span class="font-bold">Họ và tên:</span> <?php echo isset($evaluation['employee_name']) ? $evaluation['employee_name'] : '___________________' ?></p>
                    <p><span class="font-bold">Chức vụ:</span> <?php echo $isManager ? 'Lãnh đạo' : 'Chuyên viên' ?></p>
                    <p><span class="font-bold">Đơn vị công tác (phòng, ban...):</span>
                        <?php echo isset($evaluation['department_name']) ? $evaluation['department_name'] : '___________________' ?>
                    </p>
                </div>

                <!-- Evaluation form table -->
                <table class="w-full border-collapse border border-gray-300">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-300 p-2 w-8 text-center">STT</th>
                            <th class="border border-gray-300 p-2 text-center">TIÊU CHÍ ĐÁNH GIÁ</th>
                            <th class="border border-gray-300 p-2 w-16 text-center">ĐIỂM TỐI ĐA</th>
                            <?php if ($isNewForm): ?>
                                <th class="border border-gray-300 p-2 w-16 text-center">ĐIỂM TỰ CHẤM</th>
                            <?php else: ?>
                                <th class="border border-gray-300 p-2 w-16 text-center">ĐIỂM TỰ CHẤM</th>
                            <?php endif; ?>
                            <th class="border border-gray-300 p-2 w-16 text-center">ĐIỂM CHẤM LẠI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sectionCounter  = 1;
                        $criteriaCounter = 1;
                        $totalMaxScore   = 0;
                        $totalSelfScore  = 0;
                        // $totalRescore    = 0;
                        if (isset($rescore) && is_array($rescore)) {
                            foreach ($sections as $section) {
                                foreach ($section['criteria'] as $criteria) {
                                    $criteriaId = $criteria['id'];
                                    $score      = isset($rescore[$criteriaId]) ? (float) $rescore[$criteriaId] : 0;
                                    // $totalRescore += $score;
                                }
                            }
                        }
                        foreach ($sections as $sectionIndex => $section):
                            $totalMaxScore += $section['weight'];
                        ?>
                            <tr class="bg-gray-100">
                                <td class="border border-gray-300 p-2 text-center font-bold"><?php echo toRoman($sectionCounter++) ?></td>
                                <td class="border border-gray-300 p-2 font-bold" colspan="<?php echo $isNewForm ? '2' : '2' ?>"><?php echo $section['title'] ?></td>
                            </tr>
                            <?php
                            $sectionSelfScore = 0;
                            foreach ($section['criteria'] as $criteriaIndex => $criteria):
                                $criteriaId = $criteria['id'];
                                $selfScore  = isset($employeeSelfScore[$criteriaId]['score']) ? $employeeSelfScore[$criteriaId]['score'] : '';
                                if ($selfScore !== '') {
                                    $sectionSelfScore += (float) $selfScore;
                                    $totalSelfScore += (float) $selfScore;
                                }
                            ?>
                                <tr>
                                    <td class="border border-gray-300 p-2 text-center"><?php echo $criteriaCounter++ ?></td>
                                    <td class="border border-gray-300 p-2"><?php echo $criteria['text'] ?></td>
                                    <td class="border border-gray-300 p-2 text-center"><?php echo $criteria['max_score'] ?></td>
                                    <?php if ($isNewForm): ?>
                                        <td class="border border-gray-300 p-2 text-center">
                                            <input type="number" name="criteria[<?php echo $criteriaId ?>][score]" min="0" max="<?php echo $criteria['max_score'] ?>"
                                                class="w-16 px-2 py-1 border rounded criteria-score-input" data-section="<?php echo $section['id'] ?>" required>
                                        </td>
                                        <td class="border border-gray-300 p-2 text-center"></td>
                                    <?php else: ?>
                                        <td class="border border-gray-300 p-2 text-center"><?php echo $selfScore ?></td>
                                        <td class="border border-gray-300 p-2 text-center">
                                            <?php if ($isEvaluator && ! $hasEvaluated): ?>
                                                <input type="number" name="rescore[<?php echo $criteriaId ?>]" value="<?php echo isset($rescore[$criteriaId]) ? $rescore[$criteriaId] : '' ?>"
                                                    min="0" max="<?php echo $criteria['max_score'] ?>" class="w-16 border rounded px-2 py-1">
                                            <?php else: ?>

                                                <?php

                                                $leaderrescore = isset($evaluation['deputy_director_rescore']) ? json_decode($evaluation['deputy_director_rescore'], true) : [];

                                                echo ! empty($leaderrescore[$criteriaId]) ? $leaderrescore[$criteriaId] : 0;
                                                ?>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="bg-gray-50">
                                <td class="border border-gray-300 p-2" colspan="2">
                                    <span class="font-bold">Tổng điểm phần <?php echo toRoman($sectionCounter - 1) ?></span>
                                </td>
                                <td class="border border-gray-300 p-2 text-center font-bold"><?php echo $section['weight'] ?></td>
                                <?php if ($isNewForm): ?>
                                    <td class="border border-gray-300 p-2 text-center font-bold">
                                        <span id="section_<?php echo $section['id'] ?>_total">0</span>
                                    </td>
                                <?php else: ?>
                                    <td class="border border-gray-300 p-2 text-center font-bold"><?php echo $sectionSelfScore ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="bg-blue-100">
                            <td class="border border-gray-300 p-2 text-left font-bold" colspan="2">TỔNG SỐ ĐIỂM CHÍNH THỨC (*)</td>
                            <td class="border border-gray-300 p-2 text-center font-bold"><?php echo $totalMaxScore ?></td>
                            <?php if ($isNewForm): ?>
                                <td class="border border-gray-300 p-2 text-center font-bold">
                                    <span id="total_score_display">0</span>
                                    <input type="hidden" name="total_score" id="total_score_input" value="0">
                                </td>
                            <?php else: ?>
                                <td class="border border-gray-300 p-2 text-center font-bold"><?php echo $totalSelfScore ?></td>
                            <?php endif; ?>
                        </tr>
                        <tr class="bg-blue-50">
                            <td class="border border-gray-300 p-2 text-left font-bold" colspan="4">TỔNG ĐIỂM CHẤM LẠI</td>
                            <td class="border border-gray-300 p-2 text-center font-bold" id="rescore-total"><?php echo $totalRescore ?></td>
                        </tr>
                        <tr>
                            <td colspan="<?php echo $isNewForm ? '4' : '4' ?>" class="border border-gray-300 p-2 text-sm italic">
                                (*) Tổng số điểm = Tổng (I + II + III...)
                            </td>
                        </tr>
                        <tr>
                            <td colspan="3" class="border border-gray-300 p-2 font-bold">Số điểm bị trừ bổ sung (nếu có)</td>
                            <td class="border border-gray-300 p-2 text-center font-bold">
                                <span ><?php echo $evaluation['extra_deduction'] ?? 0 ?></span>
                            </td>
                            <td class="border border-gray-300 p-2 text-center font-bold">
                                <?php if ($isEvaluator && ! $hasEvaluated && ! $isNewForm): ?>
                                    <input type="number" id="extra-deduction-input" name="extra_deduction" value="0" style="width:60px;">
                                <?php else: ?>
                                    <?php echo $evaluation['extra_deduction_deputy_director'] ?? 0 ?>
                                <?php endif; ?>
                            </td>
                            <td colspan="4"></td>
                        </tr>
                        <tr>
                            <td colspan="3" class="border border-gray-300 p-2 font-bold">TỔNG SỐ ĐIỂM CHÍNH THỨC SAU TRỪ</td>
                            <td class="border border-gray-300 p-2 text-center font-bold">
                                <span ><?php echo $evaluation['employee_rescore_final'] ?? 0 ?></span>
                            </td>
                            <td class="border border-gray-300 p-2 text-center font-bold">
                                <span id="final-score-after-deduction"><?php echo ($evaluation['deputy_director_rescore_final'] ?? 0) ?></span>
                            </td>
                            <td colspan="4"></td>
                        </tr>
                        <?php
                        $finalScore = ($totalRescore > 0 ? $totalRescore : $totalSelfScore) - ($evaluation['extra_deduction_deputy_director'] ?? 0);
                        $dutyScore  = 0;
                        // Get the last section
                        $lastSectionIndex = count($sections) - 1;
                        if (isset($sections[$lastSectionIndex])) {
                            // Get the last criterion in the last section
                            $lastCriteria = end($sections[$lastSectionIndex]['criteria']);
                            $criteriaId = $lastCriteria['id'];
                            $dutyScore = isset($employeeSelfScore[$criteriaId]['score']) ? $employeeSelfScore[$criteriaId]['score'] : 0;
                        }
                        $rank = '';
                        if ($finalScore >= 95 && $dutyScore >= 5) {
                            $rank = 'Hoàn thành xuất sắc';
                        } elseif ($finalScore >= 80 && $dutyScore >= 4) {
                            $rank = 'Hoàn thành tốt';
                        } elseif ($finalScore >= 50 && $dutyScore >= 3) {
                            $rank = 'Hoàn thành';
                        } else {
                            $rank = 'Không hoàn thành';
                        }
                        ?>
                        <tr>
                            <td colspan="5">
                                <h3 class="font-bold mt-8 mb-2">KẾT QUẢ XẾP LOẠI:</h3>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="5">
                                <table class="w-full border-collapse border border-gray-300 mb-6">
                                    <tr class="bg-gray-100">
                                        <th class="border border-gray-300 p-2">Mức xếp loại</th>
                                        <th class="border border-gray-300 p-2">Hoàn thành xuất sắc</th>
                                        <th class="border border-gray-300 p-2">Hoàn thành tốt</th>
                                        <th class="border border-gray-300 p-2">Hoàn thành</th>
                                        <th class="border border-gray-300 p-2">Không hoàn thành</th>
                                    </tr>
                                    <tr>
                                        <td class="border border-gray-300 p-2">Điều kiện</td>
                                        <td class="border border-gray-300 p-2">Tổng ≥ 95, chức trách ≥ 50%</td>
                                        <td class="border border-gray-300 p-2">Tổng ≥ 80, chức trách ≥ 40%</td>
                                        <td class="border border-gray-300 p-2">Tổng ≥ 50, chức trách ≥ 30%</td>
                                        <td class="border border-gray-300 p-2">Tổng < 50</td>
                                    </tr>
                                    <tr>
                                        <td class="border border-gray-300 p-2 font-bold">Đánh dấu</td>
                                        <td class="border border-gray-300 p-2 text-center"><?php echo $rank == 'Hoàn thành xuất sắc' ? '✔️' : '' ?></td>
                                        <td class="border border-gray-300 p-2 text-center"><?php echo $rank == 'Hoàn thành tốt' ? '✔️' : '' ?></td>
                                        <td class="border border-gray-300 p-2 text-center"><?php echo $rank == 'Hoàn thành' ? '✔️' : '' ?></td>
                                        <td class="border border-gray-300 p-2 text-center"><?php echo $rank == 'Không hoàn thành' ? '✔️' : '' ?></td>
                                    </tr>
                                </table>
                                <p><b>Mức xếp loại:</b> <?php echo $rank ?></p>
                                <p>Điểm thực hiện chức trách, nhiệm vụ (phần IV): <?php echo $dutyScore ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Additional fields for new form -->
                <?php if ($isNewForm): ?>
                    <?php if ($isManager): ?>
                        <!-- <div class="mt-6">
            <h3 class="font-bold mb-2">Phần đánh giá dành cho lãnh đạo:</h3>
            <div class="space-y-4">
                <div class="p-3 bg-gray-50 rounded">
                    <label class="block mb-2">1. Mức độ hoàn thành nhiệm vụ cá nhân:</label>
                    <select name="part3_level_1" class="w-full border rounded px-3 py-2" required>
                        <option value="">-- Chọn mức độ --</option>
                        <option value="10">Hoàn thành xuất sắc (10 điểm)</option>
                        <option value="8">Hoàn thành tốt (8 điểm)</option>
                        <option value="6">Hoàn thành (6 điểm)</option>
                        <option value="0">Không hoàn thành (0 điểm)</option>
                    </select>
                </div>
                <div class="p-3 bg-gray-50 rounded">
                    <label class="block mb-2">2. Mức độ hoàn thành nhiệm vụ của cơ quan, đơn vị:</label>
                    <select name="part3_level_2" class="w-full border rounded px-3 py-2" required>
                        <option value="">-- Chọn mức độ --</option>
                        <option value="10">Hoàn thành xuất sắc (10 điểm)</option>
                        <option value="8">Hoàn thành tốt (8 điểm)</option>
                        <option value="6">Hoàn thành (6 điểm)</option>
                        <option value="0">Không hoàn thành (0 điểm)</option>
                    </select>
                </div>
            </div>
        </div> -->
                    <?php endif; ?>
                    <div class="mt-6">
                        <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Ghi chú (nếu có)</label>
                        <textarea id="notes" name="notes" rows="3"
                            class="w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            placeholder="Nhập ghi chú nếu cần..."></textarea>
                    </div>
                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md transition duration-300">
                            <i class="fas fa-paper-plane mr-1"></i> Gửi đánh giá
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Comments and signature section -->
                <div class="mt-6 grid grid-cols-3 gap-4                                                                                                                                                                                                                                                                                                                                                                                         <?php echo $isNewForm ? 'hidden' : '' ?>">
                    <div>
                        <p class="font-bold mb-2">Ý kiến nhận xét của lãnh đạo phòng:</p>
                        <p class="min-h-[80px] border-b border-gray-300 py-2">
                            <?php echo isset($evaluation['manager_comment']) ? $evaluation['manager_comment'] : '' ?>
                        </p>
                    </div>
                    <div>
                        <p class="font-bold mb-2">Ý kiến nhận xét của phó giám đốc:</p>
                        <p class="min-h-[80px] border-b border-gray-300 py-2">
                            <?php echo isset($evaluation['deputy_director_comment']) ? $evaluation['deputy_director_comment'] : '' ?>
                        </p>
                    </div>
                    <div>
                        <p class="font-bold mb-2">Ý kiến nhận xét của giám đốc:</p>
                        <p class="min-h-[80px] border-b border-gray-300 py-2">
                            <?php echo isset($evaluation['director_comment']) ? $evaluation['director_comment'] : '' ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Đóng form cho trường hợp tạo mới -->
            <?php if ($isNewForm): ?>
            </form>
        <?php endif; ?>

        <!-- Evaluation form for managers/deputy directors/directors -->
        <?php if ($isEvaluator && ! $hasEvaluated && ! $isNewForm): ?>
            <div class="mt-8 print:hidden">
                <h3 class="text-xl font-semibold mb-4">
                    <?php
                    $reviewTitles = [
                        'manager'         => 'Nhập đánh giá (Lãnh đạo phòng)',
                        'deputy_director' => 'Nhập đánh giá (Phó giám đốc)',
                        'director'        => 'Nhập đánh giá (Giám đốc)',
                    ];
                    echo $reviewTitles[$reviewType] ?? 'Nhập đánh giá';
                    ?>
                </h3>
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm mb-6">
                    <div class="p-4 bg-gray-50 border-b border-gray-200">
                        <h4 class="font-medium text-lg">Nhận xét đánh giá</h4>
                        <p class="text-sm text-gray-600">Vui lòng nhập nhận xét của bạn về kết quả làm việc của nhân viên</p>
                    </div>
                    <div class="p-4">
                        <textarea id="comment" name="comment" rows="5"
                            class="w-full px-4 py-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            placeholder="Nhập nhận xét chi tiết của bạn..." required></textarea>
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="px-6 py-3 bg-indigo-600 text-white font-medium rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors">
                        <?php
                        $buttonLabels = [
                            'manager'         => 'Duyệt đánh giá',
                            'deputy_director' => 'Duyệt đánh giá (Phó giám đốc)',
                            'director'        => 'Phê duyệt đánh giá (Giám đốc)',
                        ];
                        echo $buttonLabels[$reviewType] ?? 'Lưu đánh giá';
                        ?>
                    </button>
                </div>
            </div>
        </form>
    <?php endif; ?>

    <!-- Display current status as a badge at the top if not a new form -->
    <?php if (! $isNewForm): ?>
        <div class="mb-4">
            <div class="flex items-center mb-2">
                <span class="mr-2 font-medium">Trạng thái:</span>
                <?php
                $statusLabels = [
                    'sent'            => '<span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm">Đã gửi - Chờ lãnh đạo phòng duyệt</span>',
                    'reviewed'        => '<span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm">Lãnh đạo phòng đã duyệt - Chờ phó giám đốc duyệt</span>',
                    'deputy_reviewed' => '<span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-sm">Phó giám đốc đã duyệt - Chờ giám đốc duyệt</span>',
                    'approved'        => '<span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm">Giám đốc đã phê duyệt</span>',
                ];
                echo $statusLabels[$evaluationStatus] ?? '<span class="px-3 py-1 bg-gray-100 text-gray-800 rounded-full text-sm">' . $evaluationStatus . '</span>';
                ?>
            </div>
            <div class="flex flex-wrap gap-2 mb-4">
                <?php if (! empty($evaluation['manager_comment'])): ?>
                    <span class="inline-flex items-center px-3 py-1 bg-green-50 text-green-700 rounded-full text-xs">
                        <i class="fas fa-check-circle mr-1"></i> Lãnh đạo phòng đã nhận xét
                    </span>
                <?php endif; ?>
                <?php if (! empty($evaluation['deputy_director_comment'])): ?>
                    <span class="inline-flex items-center px-3 py-1 bg-purple-50 text-purple-700 rounded-full text-xs">
                        <i class="fas fa-check-circle mr-1"></i> Phó giám đốc đã nhận xét
                    </span>
                <?php endif; ?>
                <?php if (! empty($evaluation['director_comment'])): ?>
                    <span class="inline-flex items-center px-3 py-1 bg-blue-50 text-blue-700 rounded-full text-xs">
                        <i class="fas fa-check-circle mr-1"></i> Giám đốc đã nhận xét
                    </span>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style type="text/css" media="print">
    @page {
        size: A4;
        margin: 1.5cm;
    }

    body {
        font-family: 'Times New Roman', serif;
        font-size: 12pt;
    }

    table {
        break-inside: avoid;
    }

    .print\:hidden {
        display: none;
    }

    .print\:block {
        display: block;
    }

    .print\:shadow-none {
        box-shadow: none;
    }

    .print\:p-0 {
        padding: 0;
    }
</style>

<?php
function toRoman($number)
{
    $romanNumerals = [
        'I',
        'II',
        'III',
        'IV',
        'V',
        'VI',
        'VII',
        'VIII',
        'IX',
        'X',
        'XI',
        'XII',
        'XIII',
        'XIV',
        'XV',
        'XVI',
        'XVII',
        'XVIII',
        'XIX',
        'XX',
    ];
    return $romanNumerals[$number - 1] ?? $number;
}
?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const rescoreInputs = document.querySelectorAll('input[name^="rescore"]');
        const rescoreTotalElement = document.getElementById('rescore-total');
        const extraDeductionInput = document.getElementById('extra-deduction-input');
        const finalScoreElement = document.getElementById('final-score-after-deduction');

        if (rescoreInputs.length) {
            // Add event listener to the form submission
            const form = rescoreInputs[0].closest('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    // For each empty rescore field, use the corresponding self-assessment score
                    rescoreInputs.forEach(function(input) {
                        if (input.value === '' || input.value === null) {
                            const criteriaId = input.name.match(/rescore\[(.*?)\]/)[1];
                            const selfScoreCell = input.closest('tr').querySelector('td:nth-child(4)');
                            const selfScore = parseFloat(selfScoreCell.textContent.trim());
                            if (!isNaN(selfScore)) {
                                input.value = selfScore;
                            }
                        }
                    });
                });
            }
        }

        if (!rescoreInputs.length || !rescoreTotalElement) return;

        function updateRescoreTotal() {
            let total = 0;
            rescoreInputs.forEach(function(input) {
                let val = parseFloat(input.value);
                if (!isNaN(val)) total += val;
            });
            rescoreTotalElement.textContent = total;
            updateFinalScore(total);
        }

        function updateFinalScore(total) {
            if (!finalScoreElement) return;
            
            const deduction = parseFloat(extraDeductionInput?.value || 0);
            const finalScore = Math.max(0, total - deduction);
            finalScoreElement.textContent = finalScore;
        }

        // Add event listeners for inputs
        rescoreInputs.forEach(function(input) {
            input.addEventListener('input', updateRescoreTotal);
        });

        if (extraDeductionInput && finalScoreElement) {
            extraDeductionInput.addEventListener('input', function() {
                const totalScore = parseFloat(rescoreTotalElement.textContent || 0);
                updateFinalScore(totalScore);
            });
        }
        
        // Initialize calculations
        updateRescoreTotal();
    });
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($isNewForm): ?>
            const scoreInputs = document.querySelectorAll('.criteria-score-input');
            const totalScoreDisplay = document.getElementById('total_score_display');
            const totalScoreInput = document.getElementById('total_score_input');

            function calculateTotalScores() {
                const sectionTotals = {};
                let grandTotal = 0;

                scoreInputs.forEach(input => {
                    const sectionId = input.dataset.section;
                    const score = parseFloat(input.value) || 0;

                    if (!sectionTotals[sectionId]) {
                        sectionTotals[sectionId] = 0;
                    }

                    sectionTotals[sectionId] += score;
                    grandTotal += score;
                });

                for (const sectionId in sectionTotals) {
                    const sectionTotalElement = document.getElementById(`section_${sectionId}_total`);
                    if (sectionTotalElement) {
                        sectionTotalElement.textContent = sectionTotals[sectionId];
                    }
                }

                totalScoreDisplay.textContent = grandTotal;
                totalScoreInput.value = grandTotal;
            }

            scoreInputs.forEach(input => {
                input.addEventListener('input', calculateTotalScores);
            });

            document.getElementById('evaluationForm').addEventListener('submit', function(e) {
                const totalScore = parseFloat(totalScoreInput.value);

                if (totalScore > <?php echo $totalMaxScore ?>) {
                    e.preventDefault();
                    alert(`Tổng điểm không được vượt quá ${<?php echo $totalMaxScore ?>}. Vui lòng kiểm tra lại.`);
                    return;
                }
            });
        <?php endif; ?>
    });
</script>

<?php if ($isNewForm && isset($evaluations) && ! empty($evaluations)): ?>
    <div class="mt-10 border-t border-gray-200 pt-6">
        <h3 class="text-xl font-semibold text-gray-800 mb-4">Lịch sử đánh giá</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-300">
                <thead>
                    <tr>
                        <th class="px-6 py-3 border-b-2 border-gray-300 text-left text-sm leading-4 font-medium text-gray-700 tracking-wider">Ngày tạo</th>
                        <th class="px-6 py-3 border-b-2 border-gray-300 text-left text-sm leading-4 font-medium text-gray-700 tracking-wider">Điểm tự đánh giá</th>
                        <th class="px-6 py-3 border-b-2 border-gray-300 text-left text-sm leading-4 font-medium text-gray-700 tracking-wider">Trạng thái</th>
                        <th class="px-6 py-3 border-b-2 border-gray-300 text-left text-sm leading-4 font-medium text-gray-700 tracking-wider">Chi tiết</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($evaluations as $eval): ?>
                        <?php
                        $evalData       = json_decode($eval['content'], true);
                        $evalTotalScore = isset($evalData['total_score']) ? $evalData['total_score'] : 'N/A';
                        ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap border-b border-gray-300">
                                <?php echo date('d/m/Y H:i', strtotime($eval['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap border-b border-gray-300 font-bold">
                                <?php echo $evalTotalScore ?> / 100
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap border-b border-gray-300">
                                <?php
                                $statusLabels = [
                                    'sent'            => '<span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs">Đã gửi</span>',
                                    'reviewed'        => '<span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">Đã duyệt</span>',
                                    'approved'        => '<span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs">Đã phê duyệt</span>',
                                    'deputy_reviewed' => '<span class="px-2 py-1 bg-purple-100 text-purple-800 rounded-full text-xs">Phó giám đốc đã duyệt</span>',
                                ];
                                echo $statusLabels[$eval['status']] ?? $eval['status'];
                                ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap border-b border-gray-300">
                                <a href="<?php echo $GLOBALS['config']['base_url'] ?><?php echo $isManager ? 'lanh-dao-view' : 'nhan-vien-review' ?>/<?php echo $eval['id'] ?>"
                                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-3 rounded text-xs">
                                    Xem chi tiết
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>