<?php
if (session_status() === PHP_SESSION_NONE) {
    @session_set_cookie_params(0, '/');
    @session_start();
}
$_SESSION['ai_shin_portal_auth'] = true;
if (file_exists(dirname(__DIR__) . '/auth_check.php')) {
    require_once dirname(__DIR__) . '/auth_check.php';
}
$categoriesFile = __DIR__ . '/data/categories.json';
$programsFile = __DIR__ . '/data/programs.json';

$categories = json_decode(file_get_contents($categoriesFile), true) ?? [];
$programs = json_decode(file_get_contents($programsFile), true) ?? [];

// 현재 선택된 매뉴얼 ID (URL GET 파라미터가 있을 경우에만 선택, 없을 경우 대시보드 표시)
$selectedManualId = $_GET['id'] ?? '';
$selectedManual = null;
$selectedProg = null;

if ($selectedManualId) {
    foreach ($programs as $p) {
        foreach ($p['manuals'] as $m) {
            if ($m['id'] === $selectedManualId) {
                $selectedManual = $m;
                $selectedProg = $p;
                break 2;
            }
        }
    }
}

// 대시보드 통계 집계
$totalCategories = count($categories);
$totalPrograms = count($programs);
$totalManuals = 0;
$totalSteps = 0;

foreach ($programs as $p) {
    $manualsCount = count($p['manuals'] ?? []);
    $totalManuals += $manualsCount;
    foreach ($p['manuals'] ?? [] as $m) {
        $totalSteps += count($m['steps'] ?? []);
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Box - 초보자를 위한 프로그램 & AI 매뉴얼 센터</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/static/pretendard.css">
</head>
<body>

    <!-- Header -->
    <header class="header">
        <div class="header-container">
            <a href="index.php" class="logo-area">
                <span class="logo-icon">📦</span>
                <span class="logo-title">Study Box</span>
                <span class="logo-badge">초보자 매뉴얼 가이드</span>
            </a>
            <div class="nav-actions">
                <a href="edit.php" class="btn btn-primary">
                    <span>🤖 새 매뉴얼 AI 프롬프트 제작</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Split Layout (Left Accordion Sidebar + Right Main Content) -->
    <div class="app-layout">
        
        <!-- Left Sidebar: Category -> Program Name -> Manual Title -->
        <aside class="sidebar">
            <div class="sidebar-search">
                <input type="text" id="sidebarSearch" class="search-input" placeholder="🔍 매뉴얼 / 프로그램 검색...">
            </div>
            
            <div class="sidebar-tree">
                <a href="index.php" class="nav-dashboard-link" style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; border-radius: var(--radius-sm); font-weight: 700; color: <?= !$selectedManual ? '#0284c7' : 'var(--text-main)' ?>; text-decoration: none; background: <?= !$selectedManual ? '#e0f2fe' : 'var(--bg-secondary)' ?>; margin-bottom: 0.75rem; border: 1px solid <?= !$selectedManual ? '#0284c7' : 'var(--border-color)' ?>; box-shadow: var(--shadow-sm);">
                    <span>📊 전체 대시보드 (프로그램 현황)</span>
                    <span style="font-size: 0.75rem; background: var(--accent-blue); color: #fff; padding: 2px 7px; border-radius: 10px;"><?= $totalPrograms ?>개</span>
                </a>

                <?php foreach ($categories as $cat): 
                    $catProgs = array_filter($programs, function($p) use ($cat) {
                        return $p['category'] === $cat['id'];
                    });

                    // 카테고리 접힘 상태 확인
                    $isCatOpen = false;
                    if ($selectedProg && $selectedProg['category'] === $cat['id']) {
                        $isCatOpen = true;
                    }
                ?>
                    <!-- Level 1: Category (분류) -->
                    <div class="nav-cat-item <?= $isCatOpen ? 'open' : '' ?>">
                        <div class="nav-cat-header">
                            <span><?= htmlspecialchars($cat['icon']) ?> <?= htmlspecialchars($cat['name']) ?></span>
                            <span class="arrow-icon">▶</span>
                        </div>
                        
                        <div class="nav-program-group">
                            <?php foreach ($catProgs as $prog): 
                                $isProgOpen = ($selectedProg && $selectedProg['id'] === $prog['id']);
                            ?>
                                <!-- Level 2: Program Name (프로그램 이름) -->
                                <div class="nav-prog-item <?= $isProgOpen ? 'open' : '' ?>">
                                    <div class="nav-prog-header">
                                        <span><?= htmlspecialchars($prog['name']) ?></span>
                                        <span class="arrow-icon">▶</span>
                                    </div>

                                    <!-- Level 3: Specific Manual Title (해당 프로그램의 매뉴얼 제목들) -->
                                    <div class="nav-manuals-group">
                                        <?php foreach ($prog['manuals'] as $m): 
                                            $isManualActive = ($m['id'] === $selectedManualId);
                                        ?>
                                            <a href="index.php?id=<?= urlencode($m['id']) ?>" 
                                               class="nav-manual-link <?= $isManualActive ? 'active' : '' ?>"
                                               data-title="<?= htmlspecialchars($m['title']) ?>"
                                               title="<?= htmlspecialchars($m['title']) ?>">
                                                <span>- <?= htmlspecialchars($m['title']) ?></span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </aside>

        <!-- Right Main Content: Manual View -->
        <main class="main-content">
            <div class="content-container">
                
                <?php if ($selectedManual): ?>
                    
                    <!-- Program & Manual Header Info -->
                    <div class="program-header">
                        <div class="program-badge-row">
                            <span class="badge badge-blue">📌 프로그램: <?= htmlspecialchars($selectedProg['name'] ?? '일반') ?></span>
                            <span class="badge badge-blue">⏱️ 소요시간: <?= htmlspecialchars($selectedManual['duration'] ?? '10분') ?></span>
                            <span class="badge badge-amber">난이도: <?= htmlspecialchars($selectedManual['difficulty'] ?? '★☆☆☆☆ (입문)') ?></span>
                        </div>
                        <h1 class="program-main-title"><?= htmlspecialchars($selectedManual['title'] ?? '제목 없음') ?></h1>
                        <p class="program-description"><?= htmlspecialchars($selectedManual['description'] ?? '') ?></p>
                        
                        <div style="margin-top: 1rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <a href="edit.php?prog_name=<?= urlencode($selectedProg['name'] ?? '') ?>&cat=<?= urlencode($selectedProg['category'] ?? '') ?>" class="btn btn-primary" style="font-size: 0.85rem; padding: 5px 12px;">
                                ➕ 이 프로그램에 새 주제 추가
                            </a>
                            <button onclick="openEditModal('<?= htmlspecialchars($selectedManual['id'] ?? '') ?>')" class="btn btn-secondary" style="font-size: 0.85rem; padding: 5px 12px; color: var(--accent-blue); border-color: var(--accent-blue);">
                                ✏️ 매뉴얼 수정 & 내용 추가
                            </button>
                            <button onclick="deleteManual('<?= htmlspecialchars($selectedManual['id'] ?? '') ?>')" class="btn btn-secondary" style="font-size: 0.85rem; padding: 5px 12px; color: var(--accent-rose);">
                                🗑️ 삭제
                            </button>
                        </div>
                    </div>

                    <!-- Target Info Callout -->
                    <div class="info-callout">
                        <strong>🎯 초보자 안내:</strong> <?= htmlspecialchars($selectedManual['target_users'] ?? '모든 초보자') ?><br>
                        <strong>📌 준비물:</strong> <?= htmlspecialchars($selectedManual['prerequisites'] ?? '기본 PC 사용 환경') ?>
                    </div>

                    <!-- Top Table of Contents (오른쪽 상단 매뉴얼 단계별 바로가기) -->
                    <div class="toc-bar">
                        <div class="toc-title">
                            <span>📌 매뉴얼 세부 단계 바로가기 (클릭 시 위치 이동)</span>
                        </div>
                        <div class="toc-links">
                            <?php foreach ($selectedManual['steps'] as $idx => $step): ?>
                                <a href="#step-<?= $step['step_no'] ?? ($idx + 1) ?>" class="toc-link">
                                    <span>Step <?= $step['step_no'] ?? ($idx + 1) ?>.</span>
                                    <span><?= htmlspecialchars($step['title']) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Detailed Steps Stream -->
                    <div class="sections-wrapper">
                        <?php foreach ($selectedManual['steps'] as $idx => $step): 
                            $stepId = 'step-' . ($step['step_no'] ?? ($idx + 1));
                        ?>
                            <div class="section-card" id="<?= $stepId ?>">
                                <div class="section-header">
                                    <div class="section-num"><?= $step['step_no'] ?? ($idx + 1) ?></div>
                                    <h3 class="section-title"><?= htmlspecialchars($step['title']) ?></h3>
                                </div>
                                <div class="section-body">
                                    <p><?= nl2br(htmlspecialchars($step['content'])) ?></p>

                                    <!-- Code snippet if present -->
                                    <?php if (!empty($step['code'])): ?>
                                        <div class="code-box">
                                            <div class="code-header">
                                                <span>명령어 / 입력 템플릿</span>
                                                <button class="btn-copy">복사하기</button>
                                            </div>
                                            <pre><code><?= htmlspecialchars($step['code']) ?></code></pre>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Tip Callout -->
                                    <?php if (!empty($step['tip'])): ?>
                                        <div class="tip-box">
                                            💡 <strong>초보자 팁:</strong> <?= htmlspecialchars($step['tip']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Warning Callout -->
                                    <?php if (!empty($step['warning'])): ?>
                                        <div class="warning-box">
                                            ⚠️ <strong>주의사항:</strong> <?= htmlspecialchars($step['warning']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Step Footer Action (오른쪽 끝 설정 톱니버튼) -->
                                    <div class="step-card-footer">
                                        <div class="step-gear-dropdown">
                                            <button type="button" class="btn-step-gear" title="이 단계 옵션 및 추가 설정">
                                                ⚙️
                                            </button>
                                            <div class="step-gear-menu">
                                                <button type="button" class="step-gear-item primary" onclick="openStepInsertModal('<?= htmlspecialchars($selectedManual['id'] ?? '') ?>', <?= $step['step_no'] ?? ($idx + 1) ?>)">
                                                    <span>➕ 이 단계(Step <?= $step['step_no'] ?? ($idx + 1) ?>) 하단에 추가하기</span>
                                                </button>
                                                <button type="button" class="step-gear-item" onclick="openEditModal('<?= htmlspecialchars($selectedManual['id'] ?? '') ?>')">
                                                    <span>✏️ 전체 매뉴얼 편집 및 순서 변경</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Quick Add Step Button at Bottom -->
                        <div style="text-align: center; margin-top: 1.5rem;">
                            <button onclick="openEditModal('<?= htmlspecialchars($selectedManual['id']) ?>', true)" class="btn btn-secondary" style="background: #f0f9ff; border: 2px dashed var(--accent-blue); color: var(--accent-blue); padding: 0.8rem 1.5rem; font-size: 0.95rem; width: 100%; justify-content: center;">
                                ➕ 이 매뉴얼에 새로운 단계(Step) 추가하기
                            </button>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Dashboard Main View -->
                    <div class="dashboard-container">
                        
                        <!-- Dashboard Welcome Header -->
                        <div class="dashboard-hero">
                            <div class="hero-text">
                                <h1 class="hero-title">📊 Study Box 프로그램 현황 대시보드</h1>
                                <p class="hero-subtitle">초보자를 위한 프로그램 가이드 & AI 매뉴얼 통합 학습 센터 현황입니다.</p>
                            </div>
                            <div class="hero-actions">
                                <a href="edit.php" class="btn btn-primary" style="background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%); color: #fff; padding: 0.75rem 1.2rem; font-size: 0.95rem;">
                                    ⚡ Gemini AI로 새 매뉴얼 생성
                                </a>
                            </div>
                        </div>

                        <!-- Stat Cards Grid -->
                        <div class="stat-cards-grid">
                            <div class="stat-card">
                                <div class="stat-icon-wrapper" style="background: #e0f2fe; color: #0284c7;">📁</div>
                                <div class="stat-info">
                                    <div class="stat-label">분류 카테고리</div>
                                    <div class="stat-value"><?= $totalCategories ?><span class="stat-unit">개</span></div>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon-wrapper" style="background: #f3e8ff; color: #7c3aed;">💻</div>
                                <div class="stat-info">
                                    <div class="stat-label">다루는 프로그램</div>
                                    <div class="stat-value"><?= $totalPrograms ?><span class="stat-unit">개</span></div>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon-wrapper" style="background: #dcfce7; color: #059669;">📖</div>
                                <div class="stat-info">
                                    <div class="stat-label">등록된 매뉴얼</div>
                                    <div class="stat-value"><?= $totalManuals ?><span class="stat-unit">개</span></div>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon-wrapper" style="background: #fef3c7; color: #d97706;">📍</div>
                                <div class="stat-info">
                                    <div class="stat-label">실습 단계 (Steps)</div>
                                    <div class="stat-value"><?= $totalSteps ?><span class="stat-unit">단계</span></div>
                                </div>
                            </div>
                        </div>

                        <!-- Program Status by Category -->
                        <div class="dashboard-section-title">
                            <h3>💻 카테고리별 프로그램 현황 및 매뉴얼 리스트</h3>
                            <p>원하시는 가이드 주제를 선택하거나 새 매뉴얼을 작성해 보세요.</p>
                        </div>

                        <div class="category-dashboard-list">
                            <?php foreach ($categories as $cat): 
                                $catProgs = array_filter($programs, function($p) use ($cat) {
                                    return $p['category'] === $cat['id'];
                                });
                            ?>
                                <div class="cat-dash-card">
                                    <div class="cat-dash-header">
                                        <span class="cat-dash-badge"><?= htmlspecialchars($cat['icon']) ?> <?= htmlspecialchars($cat['name']) ?></span>
                                        <span class="cat-dash-count">프로그램 <?= count($catProgs) ?>개</span>
                                    </div>
                                    
                                    <div class="prog-dash-grid">
                                        <?php if (!empty($catProgs)): ?>
                                            <?php foreach ($catProgs as $prog): 
                                                $mCount = count($prog['manuals'] ?? []);
                                            ?>
                                                <div class="prog-dash-card">
                                                    <div class="prog-dash-top">
                                                        <h4 class="prog-dash-name"><?= htmlspecialchars($prog['name']) ?></h4>
                                                        <span class="prog-dash-mcount"><?= $mCount ?>개 매뉴얼</span>
                                                    </div>
                                                    
                                                    <ul class="dash-manual-list">
                                                        <?php if (!empty($prog['manuals'])): ?>
                                                            <?php foreach ($prog['manuals'] as $m): ?>
                                                                <li>
                                                                    <a href="index.php?id=<?= urlencode($m['id']) ?>" class="dash-manual-item">
                                                                        <span class="dash-m-bullet">•</span>
                                                                        <span class="dash-m-title"><?= htmlspecialchars($m['title']) ?></span>
                                                                        <span class="dash-m-steps"><?= count($m['steps'] ?? []) ?> Steps</span>
                                                                    </a>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <li style="font-size: 0.82rem; color: var(--text-dim); padding: 0.3rem 0;">등록된 매뉴얼 없음</li>
                                                        <?php endif; ?>
                                                    </ul>

                                                    <div class="prog-dash-bottom">
                                                        <a href="edit.php?prog_name=<?= urlencode($prog['name']) ?>&cat=<?= urlencode($prog['category']) ?>" class="btn-dash-add">
                                                            ➕ 새 주제 추가
                                                        </a>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div style="grid-column: 1 / -1; padding: 1rem; color: var(--text-dim); font-size: 0.9rem;">
                                                등록된 프로그램이 없습니다.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    </div>
                <?php endif; ?>

            </div>
        </main>

    </div>

    <!-- Manual Edit Modal -->
    <div id="manualEditModal" class="modal-overlay" style="display: none;">
        <div class="modal-dialog">
            <div class="modal-header">
                <div class="modal-title">✏️ 매뉴얼 수정 및 내용 추가</div>
                <button type="button" class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editManualForm">
                    <input type="hidden" id="editManualId">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">소속 프로그램 명</label>
                            <input type="text" id="editProgramName" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">매뉴얼 제목 (주제)</label>
                            <input type="text" id="editManualTitle" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">한 줄 요약 (설명)</label>
                        <textarea id="editDescription" class="form-control" rows="2"></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">소요시간</label>
                            <input type="text" id="editDuration" class="form-control" placeholder="예: 10분">
                        </div>
                        <div class="form-group">
                            <label class="form-label">난이도</label>
                            <select id="editDifficulty" class="form-control">
                                <option value="★☆☆☆☆ (입문)">★☆☆☆☆ (입문)</option>
                                <option value="★★☆☆☆ (초급)">★★☆☆☆ (초급)</option>
                                <option value="★★★☆☆ (중급)">★★★☆☆ (중급)</option>
                                <option value="★★★★☆ (상급)">★★★★☆ (상급)</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">추천 대상</label>
                            <input type="text" id="editTargetUsers" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">준비물</label>
                            <input type="text" id="editPrerequisites" class="form-control">
                        </div>
                    </div>

                    <!-- Steps Section Header -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin: 1.5rem 0 1rem 0; border-top: 1px solid var(--border-color); padding-top: 1rem;">
                        <h4 style="font-size: 1.1rem; color: var(--accent-blue);">📍 단계별 가이드 내용 (Step 별 편집 & 순서 조정)</h4>
                        <button type="button" class="btn btn-secondary" onclick="addEditStepCard()" style="font-size: 0.85rem; background: #e0f2fe; color: var(--accent-blue);">
                            ➕ 직접 새 단계(Step) 추가
                        </button>
                    </div>

                    <!-- Step Insert Mode Quick Banner (이 단계 하단에 추가하기 클릭 시 표시) -->
                    <div id="stepInsertModeNotice" style="display: none; align-items: center; justify-content: space-between; background: linear-gradient(135deg, #e0f2fe 0%, #dbeafe 100%); border: 2px solid #38bdf8; padding: 0.9rem 1.2rem; border-radius: 10px; margin-bottom: 1.2rem; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.12); flex-wrap: wrap; gap: 0.75rem;">
                        <div>
                            <div style="font-size: 1rem; font-weight: 800; color: #0369a1; display: flex; align-items: center; gap: 6px;">
                                <span>📍 Step <span id="insertTargetStepNum">1</span>번 단계 바로 하단에 새 단계 추가 설정</span>
                            </div>
                            <div style="font-size: 0.85rem; color: #0284c7; margin-top: 3px;">
                                아래 버튼을 누르면 즉시 Step <span id="insertTargetStepSubNum">1</span> 바로 밑에 새 입력 카드가 생성되어 추가됩니다. (또는 하단 AI 자동 생성 활용)
                            </div>
                        </div>
                        <button type="button" class="btn btn-primary" onclick="quickInsertEmptyStepAtTarget()" style="font-size: 0.9rem; padding: 8px 18px; background: #0284c7; white-space: nowrap; font-weight: 700; box-shadow: 0 2px 6px rgba(2, 132, 199, 0.3);">
                            ➕ Step <span id="btnTargetStepNum">1</span> 바로 밑에 새 단계 즉시 추가
                        </button>
                    </div>

                    <!-- AI Step Assistant Box (내용 추가 프롬프트 생성 & 답변 코드 삽입) -->
                    <div class="ai-assistant-card theme-blue">
                        <div class="ai-assistant-title">
                            <span>🤖 AI 추가 설명/단계(Step) 프롬프트 생성 & 자동 삽입</span>
                        </div>
                        <p class="ai-assistant-desc">
                            기존 설명에 덧붙이고 싶은 새로운 단계나 작업 설명을 입력하면 ChatGPT, Gemini 등에 입력할 프롬프트를 자동으로 생성해 드립니다.<br>
                            AI가 답변해준 JSON 결과 코드를 아래에 넣으면 새로운 단계(Step)들이 즉시 자동으로 추가됩니다!
                        </p>
                        
                        <div class="form-group" style="margin-bottom: 0.6rem;">
                            <label class="form-label" style="font-size: 0.85rem; font-weight: 700;">추가 요청할 설명 / 작업 내용</label>
                            <textarea id="modalAiStepReq" class="form-control" rows="2" placeholder="예: 설정 완료 후 자주 발생하는 오류 해결법과 백업 가이드 단계를 추가해줘..."></textarea>
                        </div>

                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.75rem;">
                            <button type="button" class="btn btn-secondary" onclick="generateModalStepPrompt()" style="background: #ffffff; color: var(--accent-blue); border-color: #bae6fd; font-size: 0.85rem;">
                                ✨ 추가 단계 AI 프롬프트 생성하기
                            </button>
                            <button type="button" class="btn btn-primary" onclick="generateGeminiStepsDirect(event)" style="font-size: 0.85rem; background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%);">
                                ⚡ Gemini AI로 즉시 단계 생성
                            </button>
                        </div>

                        <!-- Generated Prompt Area -->
                        <div id="modalPromptOutputWrapper" style="display: none; margin-bottom: 1rem;">
                            <div class="code-box" style="margin-top: 0.5rem;">
                                <div class="code-header">
                                    <span>AI 프롬프트 (ChatGPT / Claude / Gemini 채팅창에 복사해 넣으세요)</span>
                                    <button type="button" class="btn-copy" id="btnCopyModalPrompt" onclick="copyModalStepPrompt()">프롬프트 복사하기</button>
                                </div>
                                <pre><code id="modalPromptCodeText"></code></pre>
                            </div>
                        </div>

                        <!-- AI Response Paste Area -->
                        <div class="form-group" style="margin-bottom: 0.5rem;">
                            <label class="form-label" style="font-size: 0.85rem; font-weight: 700; color: var(--accent-green);">
                                📥 AI 답변 코드(JSON) 붙여넣기 후 단계 삽입
                            </label>
                            <textarea id="modalAiResponsePayload" class="form-control" rows="3" placeholder="AI 채팅창의 답변(JSON 코드)을 이곳에 붙여넣으세요..."></textarea>
                        </div>
                        
                        <div style="display: flex; gap: 0.75rem; align-items: center; margin-top: 0.6rem; flex-wrap: wrap;">
                            <label style="font-size: 0.88rem; font-weight: 700; color: #1f2937; display: inline-flex; align-items: center; gap: 6px;">
                                <span>📍 삽입 위치 선택:</span>
                                <select id="modalInsertTargetStep" class="form-control" style="width: auto; min-width: 230px; padding: 6px 10px; font-size: 0.88rem; border-color: #38bdf8; font-weight: 600; background: #ffffff;">
                                    <option value="last">📌 맨 마지막 단계 밑에 추가 (기본)</option>
                                </select>
                            </label>
                            <button type="button" class="btn btn-primary" onclick="importModalStepPayload()" style="background: var(--accent-green); font-size: 0.88rem; padding: 7px 16px;">
                                ➕ AI 생성 코드로 단계(Step) 자동 추가하기
                            </button>
                        </div>
                    </div>

                    <div id="editStepsContainer"></div>
                </form>
            </div>
            <div class="modal-footer">
                <a id="btnOpenEditPage" href="edit.php" class="btn btn-secondary" style="margin-right: auto; font-size: 0.85rem;" target="_blank">
                    🖥️ 새 탭에서 전체화면 편집
                </a>
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">취소</button>
                <button type="button" class="btn btn-primary" onclick="saveManualEdit()">💾 변경사항 저장하기</button>
            </div>
        </div>
    </div>

    <script src="js/app.js"></script>
    <script>
        function deleteManual(id) {
            if (confirm('정말로 이 매뉴얼을 삭제하시겠습니까?')) {
                const formData = new FormData();
                formData.append('action', 'delete_manual');
                formData.append('manual_id', id);

                fetch('api.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert('삭제되었습니다.');
                        window.location.href = 'index.php';
                    } else {
                        alert('삭제 실패했습니다.');
                    }
                });
            }
        }

        let currentInsertTargetStepNo = null;

        // 단계 톱니바퀴 > "이 단계 하단에 추가하기" 클릭 시 모달 열기
        function openStepInsertModal(manualId, stepNo) {
            openEditModal(manualId, false, stepNo);
        }

        // 매뉴얼 수정 모달 열기 (insertAfterStepNo 지정 시 해당 단계 바로 밑에 추가 설정 모드)
        function openEditModal(manualId, focusNewStep = false, insertAfterStepNo = null) {
            currentInsertTargetStepNo = insertAfterStepNo;

            fetch(`api.php?action=get_manual&manual_id=${encodeURIComponent(manualId)}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    alert(data.message || '매뉴얼 데이터를 불러올 수 없습니다.');
                    return;
                }

                const m = data.manual;
                const p = data.program;

                document.getElementById('editManualId').value = m.id;
                document.getElementById('editProgramName').value = p ? p.name : '';
                document.getElementById('editManualTitle').value = m.title || '';
                document.getElementById('editDescription').value = m.description || '';
                document.getElementById('editDuration').value = m.duration || '10분';
                document.getElementById('editDifficulty').value = m.difficulty || '★☆☆☆☆ (입문)';
                document.getElementById('editTargetUsers').value = m.target_users || '';
                document.getElementById('editPrerequisites').value = m.prerequisites || '';

                // 새 탭 링크 설정
                const btnOpenEditPage = document.getElementById('btnOpenEditPage');
                if (btnOpenEditPage) {
                    btnOpenEditPage.href = `edit.php?id=${encodeURIComponent(m.id)}`;
                }

                // AI 프롬프트 영역 초기화
                const modalPromptOutputWrapper = document.getElementById('modalPromptOutputWrapper');
                if (modalPromptOutputWrapper) modalPromptOutputWrapper.style.display = 'none';
                const modalAiStepReq = document.getElementById('modalAiStepReq');
                if (modalAiStepReq) modalAiStepReq.value = '';
                const modalAiResponsePayload = document.getElementById('modalAiResponsePayload');
                if (modalAiResponsePayload) modalAiResponsePayload.value = '';

                const stepsContainer = document.getElementById('editStepsContainer');
                stepsContainer.innerHTML = '';

                if (m.steps && m.steps.length > 0) {
                    m.steps.forEach(step => addEditStepCard(step));
                } else {
                    addEditStepCard();
                }

                if (focusNewStep) {
                    addEditStepCard();
                }

                // 단계 하단 추가 배너 및 옵션 처리
                const noticeBanner = document.getElementById('stepInsertModeNotice');
                const sel = document.getElementById('modalInsertTargetStep');

                if (insertAfterStepNo) {
                    if (noticeBanner) {
                        noticeBanner.style.display = 'flex';
                        const elNum = document.getElementById('insertTargetStepNum');
                        const elSub = document.getElementById('insertTargetStepSubNum');
                        const elBtn = document.getElementById('btnTargetStepNum');
                        if (elNum) elNum.innerText = insertAfterStepNo;
                        if (elSub) elSub.innerText = insertAfterStepNo;
                        if (elBtn) elBtn.innerText = insertAfterStepNo;
                    }
                    if (sel) {
                        sel.value = insertAfterStepNo.toString();
                    }
                } else {
                    if (noticeBanner) noticeBanner.style.display = 'none';
                    if (sel) sel.value = 'last';
                }

                document.getElementById('manualEditModal').style.display = 'flex';

                if (insertAfterStepNo) {
                    setTimeout(() => {
                        if (noticeBanner) noticeBanner.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }, 200);
                }
            })
            .catch(err => {
                alert('매뉴얼 데이터 로딩 중 오류가 발생했습니다.');
            });
        }

        function closeEditModal() {
            document.getElementById('manualEditModal').style.display = 'none';
            currentInsertTargetStepNo = null;
        }

        // 모달에서 특정 Step 바로 밑에 빈 단계 카드 즉시 추가
        function quickInsertEmptyStepAtTarget() {
            const targetNo = currentInsertTargetStepNo;
            const container = document.getElementById('editStepsContainer');
            const cards = Array.from(container.querySelectorAll('.edit-step-card'));

            let insertIndex = cards.length;
            if (targetNo && targetNo >= 1 && targetNo <= cards.length) {
                insertIndex = targetNo; // Step targetNo 바로 다음 (0-indexed index = targetNo)
            }

            const newCard = createStepCardElement(null, insertIndex + 1);
            cards.splice(insertIndex, 0, newCard);
            cards.forEach(c => container.appendChild(c));
            updateStepNumbers();

            // 추가된 카드 강조 애니메이션 및 포커스
            newCard.classList.remove('step-card-highlight');
            void newCard.offsetWidth;
            newCard.classList.add('step-card-highlight');
            newCard.scrollIntoView({ behavior: 'smooth', block: 'center' });

            const titleInput = newCard.querySelector('.edit-step-title');
            if (titleInput) {
                setTimeout(() => titleInput.focus(), 300);
            }

            const alertTargetNo = targetNo || cards.length;
            // 배너 텍스트 갱신 (추가 완료 알림)
            const noticeBanner = document.getElementById('stepInsertModeNotice');
            if (noticeBanner) {
                noticeBanner.innerHTML = `
                    <div style="font-size: 0.95rem; font-weight: 800; color: #059669; display: flex; align-items: center; gap: 6px;">
                        <span>✓ Step ${alertTargetNo}번 바로 하단에 새 단계 카드가 성공적으로 삽입되었습니다! 내용을 입력해 주세요.</span>
                    </div>
                `;
            }
        }

        // 모달 내 단계(Step) 카드 DOM 요소 생성 함수
        function createStepCardElement(step = null, currentNum = 1) {
            const card = document.createElement('div');
            card.className = 'edit-step-card';
            card.innerHTML = `
                <div class="edit-step-header">
                    <div class="step-num-control">
                        <span class="step-prefix">Step</span>
                        <input type="number" class="step-num-input" value="${currentNum}" min="1" onchange="handleStepNumberChange(this)" onkeydown="if(event.key==='Enter'){event.preventDefault(); this.blur();}" title="번호를 변경하면 다른 단계들이 자동으로 밀립니다">
                        <span class="step-hint-badge">번호 수정 시 자동 밀림</span>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary" onclick="moveEditStep(this, -1)" style="padding: 2px 6px; font-size: 0.75rem;" title="위로 이동">▲ 위로</button>
                        <button type="button" class="btn btn-secondary" onclick="moveEditStep(this, 1)" style="padding: 2px 6px; font-size: 0.75rem;" title="아래로 이동">▼ 아래로</button>
                        <button type="button" class="btn btn-secondary" onclick="deleteEditStep(this)" style="padding: 2px 6px; font-size: 0.75rem; color: var(--accent-rose);" title="이 단계 삭제">🗑️ 삭제</button>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom: 0.5rem;">
                    <label class="form-label" style="font-size: 0.85rem;">단계 제목</label>
                    <input type="text" class="form-control edit-step-title" oninput="updateModalInsertTargetOptions()" value="${step ? (step.title || '') : ''}" placeholder="예: 프로그램 실행 및 메인 메뉴 접속" required>
                </div>
                <div class="form-group" style="margin-bottom: 0.5rem;">
                    <label class="form-label" style="font-size: 0.85rem;">상세 설명 내용</label>
                    <textarea class="form-control edit-step-content" rows="3" placeholder="초보자 눈높이에서 단계별 실행 가이드를 작성하세요..." required>${step ? (step.content || '') : ''}</textarea>
                </div>
                <div class="form-group" style="margin-bottom: 0.5rem;">
                    <label class="form-label" style="font-size: 0.85rem;">명령어 / 입력 템플릿 (선택)</label>
                    <textarea class="form-control edit-step-code" rows="2" placeholder="복사할 명령어 또는 URL 주소">${step ? (step.code || '') : ''}</textarea>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size: 0.85rem;">💡 초보자 팁 (선택)</label>
                        <input type="text" class="form-control edit-step-tip" value="${step ? (step.tip || '') : ''}" placeholder="꿀팁 한 줄">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size: 0.85rem;">⚠️ 주의사항 (선택)</label>
                        <input type="text" class="form-control edit-step-warning" value="${step ? (step.warning || '') : ''}" placeholder="주의할 점 한 줄">
                    </div>
                </div>
            `;
            return card;
        }

        // 모달 내 단계(Step) 카드 추가
        function addEditStepCard(step = null) {
            const stepsContainer = document.getElementById('editStepsContainer');
            const stepCount = stepsContainer.querySelectorAll('.edit-step-card').length + 1;
            const currentNum = step ? (step.step_no || stepCount) : stepCount;

            const card = createStepCardElement(step, currentNum);
            stepsContainer.appendChild(card);
            updateStepNumbers();
            if (!step) card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        // 단계 번호 직접 수정 시 나머지 자동 밀림 (Shift & Reorder)
        function handleStepNumberChange(input) {
            const card = input.closest('.edit-step-card');
            const container = document.getElementById('editStepsContainer');
            const cards = Array.from(container.querySelectorAll('.edit-step-card'));
            
            const currentIndex = cards.indexOf(card);
            if (currentIndex === -1) return;
            
            const totalCount = cards.length;
            let targetNo = parseInt(input.value, 10);
            
            if (isNaN(targetNo) || targetNo < 1) targetNo = 1;
            if (targetNo > totalCount) targetNo = totalCount;
            
            const targetIndex = targetNo - 1; // 0-indexed
            
            if (currentIndex === targetIndex) {
                input.value = targetIndex + 1;
                return;
            }
            
            // 기존 위치에서 제거 후 대상 위치로 삽입하여 나머지 단계들이 자동으로 밀리게 함
            cards.splice(currentIndex, 1);
            cards.splice(targetIndex, 0, card);
            
            // DOM 재정렬
            cards.forEach(c => container.appendChild(c));
            
            // 모든 카드의 번호 인풋을 1부터 N까지 연속적으로 자동 재조정
            updateStepNumbers();
            
            // 이동한 카드 시각적 강조 애니메이션
            card.classList.remove('step-card-highlight');
            void card.offsetWidth; // trigger reflow
            card.classList.add('step-card-highlight');
            card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function deleteEditStep(btn) {
            const card = btn.closest('.edit-step-card');
            card.remove();
            updateStepNumbers();
        }

        function moveEditStep(btn, dir) {
            const card = btn.closest('.edit-step-card');
            if (dir === -1 && card.previousElementSibling) {
                card.parentNode.insertBefore(card, card.previousElementSibling);
            } else if (dir === 1 && card.nextElementSibling) {
                card.parentNode.insertBefore(card.nextElementSibling, card);
            }
            updateStepNumbers();
            card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function updateStepNumbers() {
            const cards = document.querySelectorAll('#editStepsContainer .edit-step-card');
            cards.forEach((card, index) => {
                const input = card.querySelector('.step-num-input');
                if (input) input.value = index + 1;
            });
            updateModalInsertTargetOptions();
        }

        // 삽입 위치 셀렉트 박스 동적 갱신
        function updateModalInsertTargetOptions(preferredVal = null) {
            const sel = document.getElementById('modalInsertTargetStep');
            if (!sel) return;
            const currentVal = preferredVal !== null ? preferredVal : sel.value;
            const container = document.getElementById('editStepsContainer');
            if (!container) return;

            const cards = container.querySelectorAll('.edit-step-card');
            let html = '<option value="last">📌 맨 마지막 단계 밑에 추가 (기본)</option>';
            cards.forEach((card, idx) => {
                const stepNo = idx + 1;
                const titleInput = card.querySelector('.edit-step-title');
                const title = (titleInput && titleInput.value.trim()) ? titleInput.value.trim() : `단계 ${stepNo}`;
                const shortTitle = title.length > 25 ? title.substring(0, 25) + '...' : title;
                html += `<option value="${stepNo}">Step ${stepNo}. ${shortTitle} 밑에 추가</option>`;
            });
            sel.innerHTML = html;
            if (currentVal && (currentVal === 'last' || parseInt(currentVal, 10) <= cards.length)) {
                sel.value = currentVal;
            } else {
                sel.value = 'last';
            }
        }

        // 모달 내 AI 추가 단계 프롬프트 생성
        function generateModalStepPrompt() {
            const progName = document.getElementById('editProgramName').value.trim() || '프로그램';
            const manualTitle = document.getElementById('editManualTitle').value.trim() || '매뉴얼';
            const detail = document.getElementById('modalAiStepReq').value.trim();
            const targetVal = document.getElementById('modalInsertTargetStep') ? document.getElementById('modalInsertTargetStep').value : 'last';
            
            const cards = document.querySelectorAll('#editStepsContainer .edit-step-card');
            const lastStepNo = cards.length;
            
            let posInfo = `현재 기존 매뉴얼은 Step 1부터 Step ${lastStepNo}까지 작성되어 있어.`;
            let startNo = lastStepNo + 1;

            if (targetVal !== 'last') {
                const afterNo = parseInt(targetVal, 10);
                startNo = afterNo + 1;
                posInfo = `현재 기존 매뉴얼은 총 ${lastStepNo}단계까지 있으며, 이번에 추가할 단계는 Step ${afterNo}단계 바로 뒤(Step ${startNo}번부터)에 삽입될 예정이야.`;
            }

            const detailLine = detail ? `\n[추가 요청 설명 및 작업 내용]: ${detail}\n` : '\n[추가 요청 설명 및 작업 내용]: 기존 설명에 이어지는 다음 필수 단계 작성\n';

            const promptText = `너는 프로그램 매뉴얼 작성 전문가야.
기존에 작성된 [프로그램명]: ${progName}, [매뉴얼 제목]: ${manualTitle} 가이드에
새로운 단계(Step)를 추가하려고 해.
${posInfo}
${detailLine}
[출력 및 시스템 저장 규칙 - 필독]:
- 이 시스템은 데이터베이스에 JSON 저장방식으로 코딩되어 있으므로, 반드시 인사말이나 부연 설명 없이 오직 아래 JSON 배열 규격 형식으로만 답변해야 합니다.
- 단계 번호는 Step ${startNo}번부터 순차적으로 부여해주세요.
- [단계 수 무제한 원칙]: 작성할 단계 수에는 24단계, 30단계, 50단계, 100단계 등 아무런 제한이 없습니다. 필요한 모든 설명과 과정을 건너뛰지 말고 24단계 이상이라도 원하는 만큼 끝까지 상세하게 작성하세요.
- 초보자가 따라하기 쉽게 명확하고 친절한 설명과 필요한 명령어/팁/주의사항을 작성해주세요.
- [JSON 문법 준수]: 각 단계의 설명(content, code)에 줄바꿈이 필요한 경우 실제 엔터 대신 반드시 \n 이스케이프 문자를 사용하고, 마지막 요소 뒤에 불필요한 콤마(Trailing comma)를 남기지 마세요.

반드시 아래 JSON 배열 규격 그대로만 답변해줘:
[
  {
    "step_no": ${startNo},
    "title": "추가할 단계 제목",
    "content": "클릭할 위치와 실행 과정을 초보자 눈높이에서 친절하게 설명",
    "code": "복사할 명령어나 주소 (없으면 null)",
    "tip": "초보자를 위한 꿀팁 (없으면 null)",
    "warning": "주의사항 (없으면 null)"
  }
]`;

            document.getElementById('modalPromptCodeText').innerText = promptText;
            document.getElementById('modalPromptOutputWrapper').style.display = 'block';
            document.getElementById('modalPromptOutputWrapper').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function copyModalStepPrompt() {
            const code = document.getElementById('modalPromptCodeText').innerText;
            navigator.clipboard.writeText(code).then(() => {
                const btn = document.getElementById('btnCopyModalPrompt');
                btn.innerText = '복사 완료! (AI 채팅창에 붙여넣으세요) ✓';
                btn.style.background = '#059669';
                setTimeout(() => {
                    btn.innerText = '프롬프트 복사하기';
                    btn.style.background = '';
                }, 3000);
            });
        }

        // 스마트 JSON 파서 (24단계 이상 대용량, 비표준 개행/따옴표/트레일링콤마/잘린 괄호 자동 복구)
        function parseAiStepPayloadSmart(rawText) {
            if (!rawText || !rawText.trim()) return [];
            let text = rawText.trim();

            // 1. BOM 및 제어 공백 제거
            text = text.replace(/^\uFEFF/, '').replace(/[\u200B\u200C\u200D]/g, '').replace(/\u00A0/g, ' ');

            // 2. 스마트 따옴표를 표준 따옴표로 변환
            text = text.replace(/[“”„«»]/g, '"').replace(/[‘’‚]/g, "'");

            // 3. 마크다운 코드 블록 추출
            const mdMatch = text.match(/```(?:json)?\s*([\s\S]*?)(?:```|$)/i);
            if (mdMatch) {
                text = mdMatch[1].trim();
            }

            // 4. 시작 괄호({ 또는 [) 위치 찾기
            const posObj = text.indexOf('{');
            const posArr = text.indexOf('[');
            if (posObj === -1 && posArr === -1) return [];

            let startPos = 0;
            if (posObj !== -1 && posArr !== -1) {
                startPos = Math.min(posObj, posArr);
            } else if (posObj !== -1) {
                startPos = posObj;
            } else {
                startPos = posArr;
            }
            text = text.substring(startPos);

            // 5. 문자 단위 고급 정제 스캐너
            let clean = '';
            let inString = false;
            let quoteChar = '"';
            let isEscaped = false;
            const validEscapes = new Set(['"', '\\', '/', 'b', 'f', 'n', 'r', 't', 'u']);

            for (let i = 0; i < text.length; i++) {
                const ch = text[i];
                if (inString) {
                    if (isEscaped) {
                        if (!validEscapes.has(ch)) {
                            clean += '\\\\' + ch;
                        } else {
                            clean += '\\' + ch;
                        }
                        isEscaped = false;
                    } else if (ch === '\\') {
                        isEscaped = true;
                    } else if (ch === quoteChar) {
                        // 진정한 닫는 따옴표인지, 문자열 내부의 따옴표(예: HTML 속성)인지 판별
                        let nextIdx = i + 1;
                        while (nextIdx < text.length && /\s/.test(text[nextIdx])) {
                            nextIdx++;
                        }

                        let isRealClose = false;
                        if (nextIdx >= text.length) {
                            isRealClose = true;
                        } else {
                            const nextChar = text[nextIdx];
                            if (nextChar === ':' || nextChar === '}' || nextChar === ']') {
                                isRealClose = true;
                            } else if (nextChar === ',') {
                                let afterCommaIdx = nextIdx + 1;
                                while (afterCommaIdx < text.length && /\s/.test(text[afterCommaIdx])) {
                                    afterCommaIdx++;
                                }
                                if (afterCommaIdx < text.length) {
                                    const afterCommaChar = text[afterCommaIdx];
                                    if (afterCommaChar === '"' || afterCommaChar === '}' || afterCommaChar === ']' || afterCommaChar === '{') {
                                        isRealClose = true;
                                    }
                                } else {
                                    isRealClose = true;
                                }
                            }
                        }

                        if (isRealClose) {
                            inString = false;
                            clean += '"';
                        } else {
                            clean += '\\"';
                        }
                    } else if (ch === '\n') {
                        clean += '\\n';
                    } else if (ch === '\r') {
                        clean += '\\r';
                    } else if (ch === '\t') {
                        clean += '\\t';
                    } else {
                        clean += ch;
                    }
                } else {
                    if (ch === '"') {
                        inString = true;
                        quoteChar = '"';
                        isEscaped = false;
                        clean += '"';
                    } else if (ch === "'") {
                        inString = true;
                        quoteChar = "'";
                        isEscaped = false;
                        clean += '"';
                    } else if (ch === '/' && i + 1 < text.length && text[i + 1] === '/') {
                        while (i < text.length && text[i] !== '\n' && text[i] !== '\r') {
                            i++;
                        }
                        clean += '\n';
                    } else if (ch === '/' && i + 1 < text.length && text[i + 1] === '*') {
                        i += 2;
                        while (i + 1 < text.length && !(text[i] === '*' && text[i + 1] === '/')) {
                            i++;
                        }
                        i++;
                    } else {
                        clean += ch;
                    }
                }
            }

            if (isEscaped) clean += '\\\\';
            if (inString) clean += '"';

            // 6. 트레일링 콤마 제거
            clean = clean.replace(/,\s*([}\]])/g, '$1');

            // 1차 파싱 시도
            try {
                const parsed = JSON.parse(clean);
                const steps = normalizeStepsArray(parsed);
                if (steps.length > 0) return steps;
            } catch (e) {}

            // 8. 괄호 스택 닫는 헬퍼
            const getCloser = (str) => {
                const stack = [];
                let inStr = false;
                let esc = false;
                for (let j = 0; j < str.length; j++) {
                    const c = str[j];
                    if (inStr) {
                        if (esc) {
                            esc = false;
                        } else if (c === '\\') {
                            esc = true;
                        } else if (c === '"') {
                            inStr = false;
                        }
                    } else {
                        if (c === '"') {
                            inStr = true;
                        } else if (c === '{' || c === '[') {
                            stack.push(c);
                        } else if (c === '}') {
                            if (stack.length > 0 && stack[stack.length - 1] === '{') stack.pop();
                        } else if (c === ']') {
                            if (stack.length > 0 && stack[stack.length - 1] === '[') stack.pop();
                        }
                    }
                }
                let closer = '';
                while (stack.length > 0) {
                    const open = stack.pop();
                    if (open === '{') closer += '}';
                    if (open === '[') closer += ']';
                }
                return closer;
            };

            // 9-1. 열린 괄호 스택 닫기 시도
            const closer = getCloser(clean);
            if (closer) {
                try {
                    const fixed = clean.replace(/,\s*([}\]])/g, '$1') + closer;
                    const parsed = JSON.parse(fixed.replace(/,\s*([}\]])/g, '$1'));
                    const steps = normalizeStepsArray(parsed);
                    if (steps.length > 0) return steps;
                } catch (e) {}
            }

            // 9-2. 마지막 중괄호(})까지만 자른 후 스택 닫기 시도 (토큰 제한으로 중간에 잘린 미완성 필드 수습)
            const lastBrace = clean.lastIndexOf('}');
            if (lastBrace !== -1) {
                const sub = clean.substring(0, lastBrace + 1);
                const subCloser = getCloser(sub);
                try {
                    const fixed = (sub + subCloser).replace(/,\s*([}\]])/g, '$1');
                    const parsed = JSON.parse(fixed);
                    const steps = normalizeStepsArray(parsed);
                    if (steps.length > 0) return steps;
                } catch (e) {}
            }

            // 10. 정규식 개별 Step 객체 추출 폴백
            const objectRegex = /\{[^{}]*"(?:title|content)"[^{}]*\}/g;
            const matches = clean.match(objectRegex);
            if (matches && matches.length > 0) {
                const steps = [];
                matches.forEach(objStr => {
                    try {
                        const item = JSON.parse(objStr);
                        if (item && (item.title || item.content)) {
                            steps.push(item);
                        }
                    } catch (err) {}
                });
                if (steps.length > 0) return steps;
            }

            return [];
        }

        function normalizeStepsArray(parsed) {
            if (!parsed) return [];
            if (Array.isArray(parsed)) return parsed;
            if (parsed.steps && Array.isArray(parsed.steps)) return parsed.steps;
            if (parsed.title || parsed.content) return [parsed];
            return [];
        }

        // 모달 내 AI 답변 붙여넣고 단계 삽입 (지정된 Step 밑에 삽입 지원 및 무제한 추가)
        function importModalStepPayload() {
            let raw = document.getElementById('modalAiResponsePayload').value.trim();
            if (!raw) {
                alert('AI 채팅창에서 받은 답변(JSON 코드)을 붙여넣어 주세요.');
                return;
            }

            const stepsToAdd = parseAiStepPayloadSmart(raw);

            if (stepsToAdd.length === 0) {
                alert('JSON 형식을 파싱할 수 없습니다. AI가 답변한 JSON 코드를 올바르게 복사했는지 확인해주세요.\n(답변이 중간에 잘렸더라도 유효한 단계까지는 자동으로 복구하여 추가해 드립니다)');
                return;
            }

            const container = document.getElementById('editStepsContainer');
            const cards = Array.from(container.querySelectorAll('.edit-step-card'));
            const targetVal = document.getElementById('modalInsertTargetStep') ? document.getElementById('modalInsertTargetStep').value : 'last';

            let insertIndex = cards.length; // 기본: 맨 뒤
            if (targetVal !== 'last') {
                const targetNo = parseInt(targetVal, 10);
                if (!isNaN(targetNo) && targetNo >= 1 && targetNo <= cards.length) {
                    insertIndex = targetNo; // Step targetNo 바로 다음 위치 (0-indexed 상 targetNo)
                }
            }

            // 새 카드 생성
            const newCards = stepsToAdd.map(step => createStepCardElement(step));

            // insertIndex 위치에 삽입 (그 뒤의 기존 카드들은 자연스럽게 뒤로 밀림)
            cards.splice(insertIndex, 0, ...newCards);

            // DOM 재정렬
            cards.forEach(c => container.appendChild(c));

            // 전체 번호 갱신
            updateStepNumbers();
            document.getElementById('modalAiResponsePayload').value = '';

            const positionMsg = targetVal === 'last' 
                ? '맨 마지막 단계 밑에' 
                : `Step ${targetVal} 단계 밑에 (기존 단계들은 뒤로 자동 밀림)`;

            alert(`🎉 ${positionMsg} ${stepsToAdd.length}개의 새로운 단계가 성공적으로 추가되었습니다!\n아래에서 추가된 내용을 확인하시고 [💾 변경사항 저장하기]를 눌러주세요.`);

            // 추가된 첫 번째 새 카드로 스크롤 및 하이라이트
            newCards.forEach(c => {
                c.classList.remove('step-card-highlight');
                void c.offsetWidth;
                c.classList.add('step-card-highlight');
            });
            if (newCards.length > 0) {
                newCards[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        // 모달 내 Gemini 직접 단계 생성 호출 (지정 위치 밑에 삽입)
        function generateGeminiStepsDirect(event) {
            const progName = document.getElementById('editProgramName').value.trim() || '프로그램';
            const manualTitle = document.getElementById('editManualTitle').value.trim() || '매뉴얼';
            const detail = document.getElementById('modalAiStepReq').value.trim();
            const targetVal = document.getElementById('modalInsertTargetStep') ? document.getElementById('modalInsertTargetStep').value : 'last';

            if (!detail) {
                alert('추가 요청할 설명 또는 작업 내용을 입력해주세요.');
                document.getElementById('modalAiStepReq').focus();
                return;
            }

            const container = document.getElementById('editStepsContainer');
            const cards = Array.from(container.querySelectorAll('.edit-step-card'));
            const lastStepNo = cards.length;
            
            let startStepNo = lastStepNo + 1;
            let insertIndex = cards.length;

            if (targetVal !== 'last') {
                const targetNo = parseInt(targetVal, 10);
                if (!isNaN(targetNo) && targetNo >= 1 && targetNo <= cards.length) {
                    insertIndex = targetNo;
                    startStepNo = targetNo + 1;
                }
            }

            const btn = event.target;
            const originText = btn.innerText;
            btn.disabled = true;
            btn.innerText = '⚡ Gemini AI가 단계 작성 중...';

            const formData = new FormData();
            formData.append('action', 'generate_gemini_steps');
            formData.append('program_name', progName);
            formData.append('manual_title', manualTitle);
            formData.append('detail', detail);
            formData.append('start_step_no', startStepNo);

            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerText = originText;
                if (data.success && data.steps && data.steps.length > 0) {
                    const newCards = data.steps.map(s => createStepCardElement(s));
                    cards.splice(insertIndex, 0, ...newCards);
                    cards.forEach(c => container.appendChild(c));
                    updateStepNumbers();

                    newCards.forEach(c => {
                        c.classList.remove('step-card-highlight');
                        void c.offsetWidth;
                        c.classList.add('step-card-highlight');
                    });
                    if (newCards.length > 0) {
                        newCards[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }

                    const posMsg = targetVal === 'last' ? '맨 마지막' : `Step ${targetVal} 단계 밑`;
                    alert(`🎉 Gemini AI가 ${posMsg}에 ${data.steps.length}개의 단계를 작성하여 추가했습니다!\n확인 후 [💾 변경사항 저장하기]를 눌러주세요.`);
                } else {
                    alert('Gemini 단계 생성 실패: ' + (data.message || '오류가 발생했습니다. AI 프롬프트 생성 후 ChatGPT/Gemini에 직접 복사해 넣어보세요.'));
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerText = originText;
                alert('요청 중 네트워크 오류가 발생했습니다.');
            });
        }

        // 매뉴얼 변경사항 저장
        function saveManualEdit() {
            const manualId = document.getElementById('editManualId').value;
            const progName = document.getElementById('editProgramName').value.trim();
            const title = document.getElementById('editManualTitle').value.trim();
            const description = document.getElementById('editDescription').value.trim();
            const duration = document.getElementById('editDuration').value.trim();
            const difficulty = document.getElementById('editDifficulty').value;
            const targetUsers = document.getElementById('editTargetUsers').value.trim();
            const prerequisites = document.getElementById('editPrerequisites').value.trim();

            if (!progName || !title) {
                alert('프로그램 명과 매뉴얼 제목은 필수 항목입니다.');
                return;
            }

            const stepCards = document.querySelectorAll('.edit-step-card');
            const steps = [];
            stepCards.forEach((card, idx) => {
                const sTitle = card.querySelector('.edit-step-title').value.trim();
                const sContent = card.querySelector('.edit-step-content').value.trim();
                const sCode = card.querySelector('.edit-step-code').value.trim();
                const sTip = card.querySelector('.edit-step-tip').value.trim();
                const sWarning = card.querySelector('.edit-step-warning').value.trim();

                steps.push({
                    step_no: idx + 1,
                    title: sTitle || `단계 ${idx + 1}`,
                    content: sContent,
                    code: sCode || null,
                    tip: sTip || null,
                    warning: sWarning || null
                });
            });

            const manualData = {
                program_name: progName,
                title: title,
                description: description,
                duration: duration,
                difficulty: difficulty,
                target_users: targetUsers,
                prerequisites: prerequisites,
                steps: steps
            };

            const formData = new FormData();
            formData.append('action', 'update_manual');
            formData.append('manual_id', manualId);
            formData.append('manual_data', JSON.stringify(manualData));

            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('🎉 매뉴얼 내용이 성공적으로 수정 및 업데이트되었습니다!');
                    window.location.reload();
                } else {
                    alert('수정 실패: ' + (data.message || '오류가 발생했습니다.'));
                }
            })
            .catch(err => {
                alert('저장 처리 중 오류가 발생했습니다.');
            });
        }

        // URL 쿼리에 insert_step 파라미터가 있을 경우 자동 모달 오픈
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const insertStep = urlParams.get('insert_step');
            const manualId = urlParams.get('id');
            if (manualId && insertStep) {
                openStepInsertModal(manualId, parseInt(insertStep, 10));
            }
        });
    </script>
</body>
</html>
