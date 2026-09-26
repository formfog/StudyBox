<?php
$manualsFile = __DIR__ . '/data/manuals.json';
$categoriesFile = __DIR__ . '/data/categories.json';

$manuals = json_decode(file_get_contents($manualsFile), true) ?? [];
$categories = json_decode(file_get_contents($categoriesFile), true) ?? [];

$id = $_GET['id'] ?? '';
$item = null;

foreach ($manuals as $m) {
    if ($m['id'] === $id) {
        $item = $m;
        break;
    }
}

if (!$item) {
    header("Location: index.php");
    exit;
}

// 카테고리 정보 찾기
$catInfo = null;
foreach ($categories as $c) {
    if ($c['id'] === $item['category']) {
        $catInfo = $c;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($item['title']) ?> - Study Box</title>
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
                <a href="index.php" class="btn btn-secondary">← 목록으로 돌아가기</a>
                <a href="edit.php?id=<?= urlencode($item['id']) ?>" class="btn btn-secondary">✏️ 수정하기</a>
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="container" style="max-width: 900px;">
        
        <!-- Detail Header Banner -->
        <div class="detail-header">
            <div class="detail-badge-group">
                <span class="cat-chip" style="pointer-events: none; background: rgba(56,189,248,0.2); color: var(--accent-blue);">
                    <?= htmlspecialchars($catInfo['icon'] ?? '🏷️') ?> <?= htmlspecialchars($catInfo['name'] ?? '일반') ?>
                </span>
                <span class="cat-chip" style="pointer-events: none;">⏱️ 소요시간: <?= htmlspecialchars($item['duration'] ?? '10분') ?></span>
                <span class="cat-chip" style="pointer-events: none; color: var(--accent-amber);">난이도: <?= htmlspecialchars($item['difficulty'] ?? '★☆☆☆☆ (입문)') ?></span>
            </div>
            
            <h1 class="detail-title"><?= htmlspecialchars($item['title'] ?? '제목 없음') ?></h1>
            <p class="detail-intro"><?= htmlspecialchars($item['description'] ?? '') ?></p>

            <!-- Beginner Friendly Notice Box -->
            <div class="beginner-notice">
                <h4>🎯 추천 대상 및 준비물</h4>
                <p><strong>[대상]:</strong> <?= htmlspecialchars($item['target_users'] ?? '모든 초보자') ?></p>
                <p><strong>[준비물]:</strong> <?= htmlspecialchars($item['prerequisites'] ?? '기본 PC 사용 환경') ?></p>
            </div>
        </div>

        <!-- Step By Step Section -->
        <h2 style="font-size: 1.5rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px;">
            <span>📍 Step-by-Step 단계별 따라하기</span>
            <span style="font-size: 0.9rem; color: var(--text-dim); font-weight: normal;">(총 <?= count($item['steps']) ?>단계)</span>
        </h2>

        <div class="step-container">
            <?php foreach ($item['steps'] as $index => $step): ?>
                <div class="step-card">
                    <div class="step-header">
                        <div class="step-number"><?= $step['step_no'] ?? ($index + 1) ?></div>
                        <h3 class="step-title"><?= htmlspecialchars($step['title']) ?></h3>
                    </div>
                    <div class="step-body">
                        <p><?= nl2br(htmlspecialchars($step['content'])) ?></p>

                        <!-- Code snippet if exists -->
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
                            <div class="callout callout-tip">
                                💡 <strong>초보자 꿀팁:</strong> <?= htmlspecialchars($step['tip']) ?>
                            </div>
                        <?php endif; ?>

                        <!-- Warning Callout -->
                        <?php if (!empty($step['warning'])): ?>
                            <div class="callout callout-warning">
                                ⚠️ <strong>주의 사항:</strong> <?= htmlspecialchars($step['warning']) ?>
                            </div>
                        <?php endif; ?>

                        <!-- Step Footer Action (오른쪽 끝 설정 톱니버튼) -->
                        <div class="step-card-footer">
                            <div class="step-gear-dropdown">
                                <button type="button" class="btn-step-gear" title="이 단계 옵션 및 추가 설정">
                                    ⚙️
                                </button>
                                <div class="step-gear-menu">
                                    <a href="index.php?id=<?= urlencode($item['id']) ?>&insert_step=<?= $step['step_no'] ?? ($index + 1) ?>" class="step-gear-item primary" style="text-decoration: none;">
                                        <span>➕ 이 단계(Step <?= $step['step_no'] ?? ($index + 1) ?>) 하단에 추가하기</span>
                                    </a>
                                    <a href="edit.php?id=<?= urlencode($item['id']) ?>" class="step-gear-item" style="text-decoration: none;">
                                        <span>✏️ 전체 매뉴얼 편집 및 순서 변경</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Bottom Action Bar -->
        <div style="margin-top: 3rem; text-align: center; display: flex; justify-content: center; gap: 1rem;">
            <a href="index.php" class="btn btn-secondary">← 목록으로</a>
            <button onclick="deleteManual('<?= htmlspecialchars($item['id']) ?>')" class="btn btn-secondary" style="color: var(--accent-rose); border-color: rgba(244,63,94,0.3);">
                🗑️ 이 매뉴얼 삭제하기
            </button>
        </div>

    </main>

    <!-- Footer -->
    <footer class="footer">
        <p>© 2026 Study Box | 초보자를 위한 프로그램 & AI 활용 가이드 모음집</p>
    </footer>

    <script src="js/app.js"></script>
    <script>
        function deleteManual(id) {
            if (confirm('정말로 이 매뉴얼을 삭제하시겠습니까?')) {
                const formData = new FormData();
                formData.append('action', 'delete_manual');
                formData.append('id', id);

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
                        alert('삭제 도중 오류가 발생했습니다.');
                    }
                });
            }
        }
    </script>
</body>
</html>
