<?php
$categoriesFile = __DIR__ . '/data/categories.json';
$programsFile = __DIR__ . '/data/programs.json';

$categories = json_decode(file_get_contents($categoriesFile), true) ?? [];
$programs = json_decode(file_get_contents($programsFile), true) ?? [];

$manualId = $_GET['id'] ?? '';
$targetManual = null;
$targetProgram = null;

if (!empty($manualId)) {
    foreach ($programs as $p) {
        foreach ($p['manuals'] as $m) {
            if ($m['id'] === $manualId) {
                $targetManual = $m;
                $targetProgram = $p;
                break 2;
            }
        }
    }
}

$isEditMode = !empty($targetManual);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEditMode ? '매뉴얼 수정 & 내용 추가' : '새 매뉴얼 AI 프롬프트 제작 & 자동 등록' ?> - Study Box</title>
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
            </a>
            <div class="nav-actions">
                <a href="index.php<?= $isEditMode ? '?id=' . urlencode($targetManual['id']) : '' ?>" class="btn btn-secondary">
                    ← <?= $isEditMode ? '해당 매뉴얼로 돌아가기' : '스터디박스로 돌아가기' ?>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="container" style="max-width: 900px; padding: 2rem 1.5rem;">
        
        <?php if ($isEditMode): ?>
            <!-- ================= EDIT MODE: 매뉴얼 수정 및 내용 추가 ================= -->
            <div class="program-header" style="text-align: center; margin-bottom: 2rem;">
                <h1 style="font-size: 2rem; font-weight: 800; color: var(--accent-blue); margin-bottom: 0.5rem;">
                    ✏️ 매뉴얼 수정 & 설명 내용 추가
                </h1>
                <p style="color: var(--text-muted); font-size: 1rem;">
                    기존 매뉴얼의 단계를 수정하거나, 채팅형 AI에게 추가 설명을 요청하는 프롬프트를 생성해 새 단계를 추가할 수 있습니다.<br>
                    순서 번호(Step 1, 2...)를 수정하면 나머지 단계들이 자동으로 밀리며 깔끔하게 재정렬됩니다.
                </p>
            </div>

            <div class="section-card">
                <form id="editManualFormFull">
                    <input type="hidden" id="editManualId" value="<?= htmlspecialchars($targetManual['id']) ?>">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 700;">소속 프로그램 명</label>
                            <input type="text" id="editProgramName" class="form-control" value="<?= htmlspecialchars($targetProgram['name'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 700;">매뉴얼 제목 (주제)</label>
                            <input type="text" id="editManualTitle" class="form-control" value="<?= htmlspecialchars($targetManual['title'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 700;">한 줄 요약 (설명)</label>
                        <textarea id="editDescription" class="form-control" rows="2"><?= htmlspecialchars($targetManual['description'] ?? '') ?></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">소요시간</label>
                            <input type="text" id="editDuration" class="form-control" value="<?= htmlspecialchars($targetManual['duration'] ?? '10분') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">난이도</label>
                            <select id="editDifficulty" class="form-control">
                                <?php
                                $diffs = ['★☆☆☆☆ (입문)', '★★☆☆☆ (초급)', '★★★☆☆ (중급)', '★★★★☆ (상급)'];
                                $curDiff = $targetManual['difficulty'] ?? '★☆☆☆☆ (입문)';
                                foreach ($diffs as $d):
                                ?>
                                    <option value="<?= $d ?>" <?= $d === $curDiff ? 'selected' : '' ?>><?= $d ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">추천 대상</label>
                            <input type="text" id="editTargetUsers" class="form-control" value="<?= htmlspecialchars($targetManual['target_users'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">준비물</label>
                            <input type="text" id="editPrerequisites" class="form-control" value="<?= htmlspecialchars($targetManual['prerequisites'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Steps Section Header -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin: 2rem 0 1rem 0; border-top: 1px solid var(--border-color); padding-top: 1.2rem;">
                        <h3 style="font-size: 1.2rem; color: var(--accent-blue);">📍 단계별 가이드 내용 (Step 별 편집 & 순서 조정)</h3>
                        <button type="button" class="btn btn-secondary" onclick="addEditStepCard()" style="font-size: 0.85rem; background: #e0f2fe; color: var(--accent-blue);">
                            ➕ 직접 새 단계(Step) 추가
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
                            <textarea id="pageAiStepReq" class="form-control" rows="2" placeholder="예: 설정 완료 후 자주 발생하는 오류 해결법과 백업 가이드 단계를 추가해줘..."></textarea>
                        </div>

                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.75rem;">
                            <button type="button" class="btn btn-secondary" onclick="generatePageStepPrompt()" style="background: #ffffff; color: var(--accent-blue); border-color: #bae6fd; font-size: 0.85rem;">
                                ✨ 추가 단계 AI 프롬프트 생성하기
                            </button>
                            <button type="button" class="btn btn-primary" onclick="generateGeminiStepsPage(event)" style="font-size: 0.85rem; background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%);">
                                ⚡ Gemini AI로 즉시 단계 생성
                            </button>
                        </div>

                        <!-- Generated Prompt Area -->
                        <div id="pagePromptOutputWrapper" style="display: none; margin-bottom: 1rem;">
                            <div class="code-box" style="margin-top: 0.5rem;">
                                <div class="code-header">
                                    <span>AI 프롬프트 (ChatGPT / Claude / Gemini 채팅창에 복사해 넣으세요)</span>
                                    <button type="button" class="btn-copy" id="btnCopyPagePrompt" onclick="copyPageStepPrompt()">프롬프트 복사하기</button>
                                </div>
                                <pre><code id="pagePromptCodeText"></code></pre>
                            </div>
                        </div>

                        <!-- AI Response Paste Area -->
                        <div class="form-group" style="margin-bottom: 0.5rem;">
                            <label class="form-label" style="font-size: 0.85rem; font-weight: 700; color: var(--accent-green);">
                                📥 AI 답변 코드(JSON) 붙여넣기 후 단계 삽입
                            </label>
                            <textarea id="pageAiResponsePayload" class="form-control" rows="3" placeholder="AI 채팅창의 답변(JSON 코드)을 이곳에 붙여넣으세요..."></textarea>
                        </div>
                        
                        <div style="display: flex; gap: 0.75rem; align-items: center; margin-top: 0.6rem; flex-wrap: wrap;">
                            <label style="font-size: 0.88rem; font-weight: 700; color: #1f2937; display: inline-flex; align-items: center; gap: 6px;">
                                <span>📍 삽입 위치 선택:</span>
                                <select id="pageInsertTargetStep" class="form-control" style="width: auto; min-width: 230px; padding: 6px 10px; font-size: 0.88rem; border-color: #38bdf8; font-weight: 600; background: #ffffff;">
                                    <option value="last">📌 맨 마지막 단계 밑에 추가 (기본)</option>
                                </select>
                            </label>
                            <button type="button" class="btn btn-primary" onclick="importPageStepPayload()" style="background: var(--accent-green); font-size: 0.88rem; padding: 7px 16px;">
                                ➕ AI 생성 코드로 단계(Step) 자동 추가하기
                            </button>
                        </div>
                    </div>

                    <!-- Steps List Container -->
                    <div id="editStepsContainer"></div>

                    <!-- Submit Actions -->
                    <div style="margin-top: 2rem; display: flex; justify-content: flex-end; gap: 0.75rem;">
                        <a href="index.php?id=<?= urlencode($targetManual['id']) ?>" class="btn btn-secondary">취소</a>
                        <button type="button" class="btn btn-primary" onclick="saveManualEditFull()" style="padding: 0.8rem 1.5rem; font-size: 1rem;">
                            💾 변경사항 저장하기
                        </button>
                    </div>
                </form>
            </div>

            <!-- Existing Manual Data JSON for JS Initialization -->
            <script>
                const INITIAL_STEPS = <?= json_encode($targetManual['steps'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
            </script>

        <?php else: ?>
            <!-- ================= NEW MODE: 새 매뉴얼 AI 프롬프트 생성 & 자동 등록 ================= -->
            <div class="program-header" style="text-align: center; margin-bottom: 2rem;">
                <h1 style="font-size: 2rem; font-weight: 800; color: var(--accent-blue); margin-bottom: 0.5rem;">
                    🤖 AI 매뉴얼 프롬프트 생성 & 자동 등록
                </h1>
                <p style="color: var(--text-muted); font-size: 1rem;">
                    프로그램 명과 원하시는 가이드 제목만 입력하면 AI 전용 프롬프트를 자동으로 만들어 드립니다.<br>
                    생성형 AI(ChatGPT, Claude 등)의 답변을 복사해 넣으면 1초 만에 스터디박스에 자동으로 등록됩니다!
                </p>
            </div>

            <!-- 2-Step Interactive Wizard Card -->
            <div style="display: grid; grid-template-columns: 1fr; gap: 2rem;">
                
                <!-- Step 1: Prompt Generator Form -->
                <div class="section-card">
                    <div class="section-header">
                        <div class="section-num">1</div>
                        <h3 class="section-title">AI 프롬프트 생성하기</h3>
                    </div>
                    <div class="section-body">
                        <form id="promptGenForm">
                            <div class="form-group">
                                <label class="form-label">분류 카테고리</label>
                                <select id="inputCategory" class="form-control">
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat['id']) ?>"><?= htmlspecialchars($cat['icon'] . ' ' . $cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label" style="display: flex; justify-content: space-between; align-items: center;">
                                    <span>프로그램 명 (기존 프로그램 선택 또는 신규 입력)</span>
                                    <span style="font-size: 0.8rem; font-weight: normal; color: var(--accent-blue);">
                                        📌 기존 등록된 프로그램에 추가 등록 가능
                                    </span>
                                </label>
                                <select id="selectExistingProgram" class="form-control" style="margin-bottom: 0.5rem; background: #f0f9ff; border-color: var(--accent-blue);">
                                    <option value="">✨ [신규 프로그램 직접 입력하기]</option>
                                    <?php foreach ($programs as $prog): ?>
                                        <option value="<?= htmlspecialchars($prog['name']) ?>" data-cat="<?= htmlspecialchars($prog['category']) ?>">
                                            📌 <?= htmlspecialchars($prog['name']) ?> (등록된 매뉴얼 <?= count($prog['manuals'] ?? []) ?>개)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" id="inputProgramName" class="form-control" placeholder="예: VS Code, Laragon, Figma, ChatGPT 등" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">원하는 매뉴얼 제목 (주제)</label>
                                <input type="text" id="inputManualTitle" class="form-control" placeholder="예: 설치 및 한글 패치 100% 가이드, 기초 단축키 모음 등" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">매뉴얼 상세 요청 내용 (어떤 학습이 필요하고 어떤 부분을 알아야 하는지...)</label>
                                <textarea id="inputManualDetail" class="form-control" rows="3" placeholder="예: 초보자가 꼭 알아야 할 필수 설치 과정, 기초 주요 기능 사용법, 자주 발생하는 오류 및 꿀팁 단축키 위주로 자세하게 설명해줘."></textarea>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-top: 1.5rem;">
                                <button type="submit" class="btn btn-secondary" style="justify-content: center; padding: 0.8rem; font-size: 0.95rem;">
                                    ✨ AI 전용 프롬프트 생성하기
                                </button>
                                <button type="button" id="btnGeminiGenerate" class="btn btn-primary" style="justify-content: center; padding: 0.8rem; font-size: 0.95rem; background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%); color: #fff;">
                                    ⚡ Gemini 2.5 Flash-Lite 직접 코딩 & 자동 등록
                                </button>
                            </div>
                        </form>

                        <!-- Generated Prompt Output Area -->
                        <div id="promptOutputWrapper" style="display: none; margin-top: 1.5rem;">
                            <label class="form-label" style="color: var(--accent-blue); font-weight: 700;">
                                📋 생성된 프롬프트 (아래 버튼을 눌러 복사 후 ChatGPT/Claude에 붙여넣으세요!)
                            </label>
                            <div class="code-box">
                                <div class="code-header">
                                    <span>AI Prompt Template</span>
                                    <button type="button" class="btn-copy" id="btnCopyPrompt">프롬프트 복사하기</button>
                                </div>
                                <pre><code id="promptCodeText"></code></pre>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Paste AI Response & Import -->
                <div class="section-card" id="step2Card" style="opacity: 0.7; transition: opacity 0.3s;">
                    <div class="section-header">
                        <div class="section-num">2</div>
                        <h3 class="section-title">AI 답변 복사해 붙여넣고 자동 등록하기</h3>
                    </div>
                    <div class="section-body">
                        <p style="margin-bottom: 1rem;">ChatGPT나 Claude가 생성해 준 답변(JSON 코드)을 아래에 그대로 붙여넣고 [스터디 박스에 즉시 등록] 버튼을 누르세요.</p>
                        
                        <div class="form-group">
                            <textarea id="aiResponsePayload" class="form-control" rows="8" placeholder="AI가 답변해준 결과(JSON 코드)를 이곳에 컨트롤+V 로 붙여넣으세요..."></textarea>
                        </div>

                        <button type="button" id="btnImportPayload" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 0.8rem; font-size: 1rem; background: var(--accent-green);">
                            🚀 스터디 박스에 자동 등록하기
                        </button>
                    </div>
                </div>

            </div>
        <?php endif; ?>

    </main>

    <footer class="footer">
        <p>© 2026 Study Box | 초보자를 위한 프로그램 & AI 활용 가이드 모음집</p>
    </footer>

    <script src="js/app.js"></script>
    <script>
    <?php if ($isEditMode): ?>
        // ================= EDIT MODE JAVASCRIPT =================
        const container = document.getElementById('editStepsContainer');

        // 단계(Step) 카드 DOM 요소 생성 함수
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
                    <input type="text" class="form-control edit-step-title" oninput="updatePageInsertTargetOptions()" value="${step ? (step.title || '') : ''}" placeholder="예: 프로그램 실행 및 메인 메뉴 접속" required>
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

        // 단계(Step) 카드 추가
        function addEditStepCard(step = null) {
            const stepCount = container.querySelectorAll('.edit-step-card').length + 1;
            const currentNum = step ? (step.step_no || stepCount) : stepCount;

            const card = createStepCardElement(step, currentNum);
            container.appendChild(card);
            updateStepNumbers();
            if (!step) card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        // 단계 번호 직접 수정 시 나머지 자동 밀림 (Shift & Reorder)
        function handleStepNumberChange(input) {
            const card = input.closest('.edit-step-card');
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
            void card.offsetWidth; // reflow
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
            const cards = container.querySelectorAll('.edit-step-card');
            cards.forEach((card, index) => {
                const input = card.querySelector('.step-num-input');
                if (input) input.value = index + 1;
            });
            updatePageInsertTargetOptions();
        }

        // 삽입 위치 셀렉트 박스 동적 갱신
        function updatePageInsertTargetOptions() {
            const sel = document.getElementById('pageInsertTargetStep');
            if (!sel) return;
            const currentVal = sel.value;
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

        // 페이지 내 AI 추가 단계 프롬프트 생성
        function generatePageStepPrompt() {
            const progName = document.getElementById('editProgramName').value.trim() || '프로그램';
            const manualTitle = document.getElementById('editManualTitle').value.trim() || '매뉴얼';
            const detail = document.getElementById('pageAiStepReq').value.trim();
            const targetVal = document.getElementById('pageInsertTargetStep') ? document.getElementById('pageInsertTargetStep').value : 'last';
            
            const cards = container.querySelectorAll('.edit-step-card');
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

            document.getElementById('pagePromptCodeText').innerText = promptText;
            document.getElementById('pagePromptOutputWrapper').style.display = 'block';
            document.getElementById('pagePromptOutputWrapper').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function copyPageStepPrompt() {
            const code = document.getElementById('pagePromptCodeText').innerText;
            navigator.clipboard.writeText(code).then(() => {
                const btn = document.getElementById('btnCopyPagePrompt');
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

        // 페이지 내 AI 답변 붙여넣고 단계 삽입 (지정 Step 밑에 삽입 지원 및 무제한 추가)
        function importPageStepPayload() {
            let raw = document.getElementById('pageAiResponsePayload').value.trim();
            if (!raw) {
                alert('AI 채팅창에서 받은 답변(JSON 코드)을 붙여넣어 주세요.');
                return;
            }

            const stepsToAdd = parseAiStepPayloadSmart(raw);

            if (stepsToAdd.length === 0) {
                alert('JSON 형식을 파싱할 수 없습니다. AI가 답변한 JSON 코드를 올바르게 복사했는지 확인해주세요.\n(답변이 중간에 잘렸더라도 유효한 단계까지는 자동으로 복구하여 추가해 드립니다)');
                return;
            }

            const cards = Array.from(container.querySelectorAll('.edit-step-card'));
            const targetVal = document.getElementById('pageInsertTargetStep') ? document.getElementById('pageInsertTargetStep').value : 'last';

            let insertIndex = cards.length; // 기본: 맨 뒤
            if (targetVal !== 'last') {
                const targetNo = parseInt(targetVal, 10);
                if (!isNaN(targetNo) && targetNo >= 1 && targetNo <= cards.length) {
                    insertIndex = targetNo; // Step targetNo 바로 다음 위치
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
            document.getElementById('pageAiResponsePayload').value = '';

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

        // 페이지 내 Gemini 직접 단계 생성 호출 (지정 위치 밑에 삽입)
        function generateGeminiStepsPage(event) {
            const progName = document.getElementById('editProgramName').value.trim() || '프로그램';
            const manualTitle = document.getElementById('editManualTitle').value.trim() || '매뉴얼';
            const detail = document.getElementById('pageAiStepReq').value.trim();
            const targetVal = document.getElementById('pageInsertTargetStep') ? document.getElementById('pageInsertTargetStep').value : 'last';

            if (!detail) {
                alert('추가 요청할 설명 또는 작업 내용을 입력해주세요.');
                document.getElementById('pageAiStepReq').focus();
                return;
            }

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

        // 매뉴얼 변경사항 전체 저장
        function saveManualEditFull() {
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

            const stepCards = container.querySelectorAll('.edit-step-card');
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
                    window.location.href = 'index.php?id=' + encodeURIComponent(manualId);
                } else {
                    alert('수정 실패: ' + (data.message || '오류가 발생했습니다.'));
                }
            })
            .catch(err => {
                alert('저장 처리 중 오류가 발생했습니다.');
            });
        }

        // 초기 단계 데이터 렌더링
        if (typeof INITIAL_STEPS !== 'undefined' && INITIAL_STEPS.length > 0) {
            INITIAL_STEPS.forEach(step => addEditStepCard(step));
        } else {
            addEditStepCard();
        }

        // URL 파라미터에 insert_after가 있을 경우 해당 단계 밑으로 타깃 지정
        const urlParamsEdit = new URLSearchParams(window.location.search);
        const insertAfterParam = urlParamsEdit.get('insert_after');
        if (insertAfterParam) {
            updatePageInsertTargetOptions();
            const selTarget = document.getElementById('pageInsertTargetStep');
            if (selTarget) selTarget.value = insertAfterParam;
            const aiCard = document.querySelector('.ai-assistant-card');
            if (aiCard) {
                setTimeout(() => aiCard.scrollIntoView({ behavior: 'smooth', block: 'center' }), 200);
            }
        }

    <?php else: ?>
        // ================= NEW MANUAL MODE JAVASCRIPT =================
        const promptGenForm = document.getElementById('promptGenForm');
        const promptOutputWrapper = document.getElementById('promptOutputWrapper');
        const promptCodeText = document.getElementById('promptCodeText');
        const btnCopyPrompt = document.getElementById('btnCopyPrompt');
        const step2Card = document.getElementById('step2Card');
        const aiResponsePayload = document.getElementById('aiResponsePayload');
        const btnImportPayload = document.getElementById('btnImportPayload');

        const selectExistingProgram = document.getElementById('selectExistingProgram');
        const inputProgramName = document.getElementById('inputProgramName');
        const inputCategory = document.getElementById('inputCategory');

        // 기존 프로그램 선택 시 자동 채우기
        if (selectExistingProgram) {
            selectExistingProgram.addEventListener('change', () => {
                const val = selectExistingProgram.value;
                if (val) {
                    inputProgramName.value = val;
                    const selectedOpt = selectExistingProgram.options[selectExistingProgram.selectedIndex];
                    const cat = selectedOpt.getAttribute('data-cat');
                    if (cat) inputCategory.value = cat;
                } else {
                    inputProgramName.value = '';
                }
            });
        }

        // URL 파라미터 자동 반영 (?prog_name=...&cat=...)
        const urlParams = new URLSearchParams(window.location.search);
        const paramProgName = urlParams.get('prog_name');
        const paramCat = urlParams.get('cat');
        if (paramProgName) {
            if (selectExistingProgram) selectExistingProgram.value = paramProgName;
            inputProgramName.value = paramProgName;
        }
        if (paramCat) {
            inputCategory.value = paramCat;
        }

        // 프롬프트 생성 함수
        promptGenForm.addEventListener('submit', (e) => {
            e.preventDefault();

            const cat = document.getElementById('inputCategory').value;
            const progName = document.getElementById('inputProgramName').value.trim();
            const manualTitle = document.getElementById('inputManualTitle').value.trim();
            const manualDetail = document.getElementById('inputManualDetail').value.trim();

            const detailLine = manualDetail ? `\n[상세 요청 및 필요 학습 내용]: ${manualDetail}\n* 위 [상세 요청 및 필요 학습 내용]에서 요청한 구체적 학습 포인트와 알아야 할 지식들을 각 Step에 알차게 포함시켜 작성해줘.\n` : '';

            const promptText = `너는 완전 초보자를 위한 프로그램 매뉴얼 작성 전문가야.
다음 정보에 맞춰 초보자의 눈높이에서 친절하고 명확한 Step-by-Step 가이드를 작성해줘.

[프로그램 명]: ${progName}
[매뉴얼 제목]: ${manualTitle}
[카테고리 ID]: ${cat}${detailLine}

[출력 및 시스템 저장 규칙 - 필독]:
- 이 시스템은 데이터베이스에 JSON 저장방식으로 코딩되어 자동 등록되므로, 인사말이나 부연 설명 없이 오직 아래 JSON 규격 형식 그대로만 답변해야 합니다.
- [단계 수 무제한 원칙]: 작성할 단계 수에는 24단계, 30단계, 50단계 등 아무런 제한이 없습니다. 필요한 모든 설명과 과정을 건너뛰지 말고 24단계 이상이라도 원하는 만큼 끝까지 상세하게 작성하세요.
- steps 배열의 각 단계는 누락 없이 알차고 구체적으로 작성해주세요.
- [JSON 문법 준수]: content나 code 내 줄바꿈은 실제 줄바꿈 대신 \n 을 사용하고, 마지막 항목 뒤 콤마(Trailing comma)가 없도록 순수 유효 JSON 규격을 지켜주세요.

반드시 아래 JSON 규격 형식 그대로만 답변해줘:

{
  "category": "${cat}",
  "program_name": "${progName}",
  "title": "${manualTitle}",
  "description": "초보자가 이 매뉴얼을 다 읽었을 때 달성할 수 있는 결과 한 줄 요약",
  "difficulty": "★☆☆☆☆ (입문)",
  "duration": "10분",
  "target_users": "추천 대상 작성",
  "prerequisites": "준비물 작성",
  "steps": [
    {
      "step_no": 1,
      "title": "첫 번째 단계 제목",
      "content": "클릭할 위치와 동작을 초보자 눈높이에서 친절하게 설명",
      "code": "복사할 명령어나 주소 (없으면 null)",
      "tip": "초보자를 위한 꿀팁 (없으면 null)",
      "warning": "주의사항 (없으면 null)"
    },
    {
      "step_no": 2,
      "title": "두 번째 단계 제목",
      "content": "상세한 실행 가이드 설명",
      "code": null,
      "tip": null,
      "warning": null
    }
  ]
}`;

            promptCodeText.innerText = promptText;
            promptOutputWrapper.style.display = 'block';
            step2Card.style.opacity = '1';
            promptOutputWrapper.scrollIntoView({ behavior: 'smooth' });
        });

        // 프롬프트 복사
        btnCopyPrompt.addEventListener('click', () => {
            navigator.clipboard.writeText(promptCodeText.innerText).then(() => {
                btnCopyPrompt.innerText = '복사 완료! (AI 채팅창에 붙여넣으세요) ✓';
                btnCopyPrompt.style.background = '#059669';
                setTimeout(() => {
                    btnCopyPrompt.innerText = '프롬프트 복사하기';
                    btnCopyPrompt.style.background = '';
                }, 3000);
            });
        });

        // AI 답변 JSON 서버 전송 및 자동 등록
        btnImportPayload.addEventListener('click', () => {
            const payload = aiResponsePayload.value.trim();
            if (!payload) {
                alert('AI 채팅창에서 생성된 답변(JSON)을 붙여넣어 주세요.');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'save_manual_payload');
            formData.append('payload', payload);

            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('🎉 스터디 박스에 새 매뉴얼이 성공적으로 자동 등록되었습니다!');
                    window.location.href = 'index.php?id=' + encodeURIComponent(data.manual_id);
                } else {
                    alert('등록 실패: ' + (data.message || '데이터 양식을 확인해주세요.'));
                }
            })
            .catch(err => {
                alert('처리 중 오류가 발생했습니다. AI 답변(JSON 코드) 복사가 올바른지 확인해주세요.');
            });
        });

        // Gemini 2.5 Flash-Lite 직접 코딩 및 자동 등록
        const btnGeminiGenerate = document.getElementById('btnGeminiGenerate');
        btnGeminiGenerate.addEventListener('click', () => {
            const cat = document.getElementById('inputCategory').value;
            const progName = document.getElementById('inputProgramName').value.trim();
            const manualTitle = document.getElementById('inputManualTitle').value.trim();
            const manualDetail = document.getElementById('inputManualDetail').value.trim();

            if (!progName || !manualTitle) {
                alert('프로그램 명과 원하시는 매뉴얼 제목을 먼저 입력해 주세요.');
                return;
            }

            const origText = btnGeminiGenerate.innerText;
            btnGeminiGenerate.innerText = '⚡ Gemini AI 코딩 중... (약 3~5초 소요)';
            btnGeminiGenerate.disabled = true;

            const formData = new FormData();
            formData.append('action', 'generate_gemini_manual');
            formData.append('category', cat);
            formData.append('program_name', progName);
            formData.append('title', manualTitle);
            formData.append('detail', manualDetail);

            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btnGeminiGenerate.innerText = origText;
                btnGeminiGenerate.disabled = false;

                if (data.success) {
                    if (data.payload) {
                        aiResponsePayload.value = data.payload;
                    }
                    alert('🎉 Gemini 2.5 Flash-Lite가 매뉴얼을 직접 코딩하여 성공적으로 등록하였습니다!');
                    window.location.href = 'index.php?id=' + encodeURIComponent(data.manual_id);
                } else {
                    alert('Gemini 자동 생성 실패: ' + (data.message || '오류가 발생했습니다.'));
                }
            })
            .catch(err => {
                btnGeminiGenerate.innerText = origText;
                btnGeminiGenerate.disabled = false;
                alert('요청 처리 중 오류가 발생했습니다. 통신 상태를 확인해주세요.');
            });
        });
    <?php endif; ?>
    </script>
</body>
</html>
