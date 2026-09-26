// Study Box Static App Manager
const StudyBoxApp = {
    categories: [],
    programs: [],

    // 데이터 로드 (localStorage 우선, 없으면 data/*.json 파일 fetch)
    async loadData() {
        const savedCategories = localStorage.getItem('studybox_categories');
        const savedPrograms = localStorage.getItem('studybox_programs');

        if (savedCategories && savedPrograms) {
            try {
                this.categories = JSON.parse(savedCategories);
                this.programs = JSON.parse(savedPrograms);
                return;
            } catch (e) {
                console.error("LocalStorage parse error, fallback to json files", e);
            }
        }

        try {
            const [catRes, progRes] = await Promise.all([
                fetch('data/categories.json').then(r => r.json()),
                fetch('data/programs.json').then(r => r.json())
            ]);
            this.categories = catRes || [];
            this.programs = progRes || [];
            this.saveData();
        } catch (e) {
            console.error("Failed to fetch JSON data", e);
            this.categories = [];
            this.programs = [];
        }
    },

    saveData() {
        localStorage.setItem('studybox_categories', JSON.stringify(this.categories));
        localStorage.setItem('studybox_programs', JSON.stringify(this.programs));
    },

    // 관리자 인증 관련 메소드 (SHA-256 해시 검증으로 평문 비밀번호 노출 방지)
    // 'ks9325!!'의 SHA-256 해시값: 15b7fbdd71f54be6f851eb3360b64be8fb1c539df04a8b7ddcbf2f0df77eec8a
    ADMIN_HASH: '15b7fbdd71f54be6f851eb3360b64be8fb1c539df04a8b7ddcbf2f0df77eec8a',
    
    isAdminLoggedIn() {
        return sessionStorage.getItem('studybox_admin_auth') === 'true';
    },

    async hashPassword(password) {
        const encoder = new TextEncoder();
        const data = encoder.encode(password);
        const hashBuffer = await crypto.subtle.digest('SHA-256', data);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    },

    async loginAdmin(inputPass) {
        const inputHash = await this.hashPassword(inputPass);
        if (inputHash === this.ADMIN_HASH) {
            sessionStorage.setItem('studybox_admin_auth', 'true');
            this.renderAdminUI();
            return true;
        }
        return false;
    },

    logoutAdmin() {
        sessionStorage.removeItem('studybox_admin_auth');
        this.renderAdminUI();
    },

    renderAdminUI() {
        const area = document.getElementById('adminStatusArea');
        if (!area) return;

        if (this.isAdminLoggedIn()) {
            area.innerHTML = `
                <div style="display: flex; align-items: center; gap: 8px; font-size: 0.82rem; background: #e0f2fe; padding: 4px 10px; border-radius: 20px; border: 1px solid #38bdf8;">
                    <span style="color: #0284c7; font-weight: 700;">🟢 관리자 로그인 됨</span>
                    <button onclick="handleAdminLogout()" style="background: none; border: none; color: #e11d48; font-weight: 600; cursor: pointer; text-decoration: underline; font-size: 0.8rem; padding: 0;">로그아웃</button>
                </div>
            `;
        } else {
            area.innerHTML = '';
        }
    },

    async checkAdminPermission() {
        if (this.isAdminLoggedIn()) {
            return true;
        }
        const input = prompt("🔐 관리자 전용 기능입니다.\n비밀번호를 입력하세요:");
        if (input === null) {
            return false;
        }
        const success = await this.loginAdmin(input);
        if (success) {
            alert("✅ 관리자 인증이 완료되었습니다.");
            return true;
        } else {
            alert("❌ 비밀번호가 올바르지 않습니다.");
            return false;
        }
    },

    resetToDefaultData(categories, programs) {
        this.categories = categories;
        this.programs = programs;
        this.saveData();
    },

    // index.html 초기화 및 랜더링
    async initIndexPage() {
        await this.loadData();
        this.renderAdminUI();
        
        const urlParams = new URLSearchParams(window.location.search);
        const selectedManualId = urlParams.get('id') || '';
        const insertStepNo = urlParams.get('insert_step') ? parseInt(urlParams.get('insert_step'), 10) : null;

        let selectedManual = null;
        let selectedProg = null;

        if (selectedManualId) {
            for (const p of this.programs) {
                for (const m of (p.manuals || [])) {
                    if (m.id === selectedManualId) {
                        selectedManual = m;
                        selectedProg = p;
                        break;
                    }
                }
                if (selectedManual) break;
            }
        }

        this.renderSidebar(selectedManualId, selectedProg);
        this.renderMainContent(selectedManual, selectedProg);

        if (selectedManualId && insertStepNo) {
            openEditModal(selectedManualId, false, insertStepNo);
        }
    },

    // 사이드바 렌더링
    renderSidebar(selectedManualId, selectedProg) {
        const sidebarTree = document.getElementById('sidebarTree');
        if (!sidebarTree) return;

        let totalPrograms = this.programs.length;
        let html = `
            <a href="index.html" class="nav-dashboard-link" style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; border-radius: var(--radius-sm); font-weight: 700; color: ${!selectedManualId ? '#0284c7' : 'var(--text-main)'}; text-decoration: none; background: ${!selectedManualId ? '#e0f2fe' : 'var(--bg-secondary)'}; margin-bottom: 0.75rem; border: 1px solid ${!selectedManualId ? '#0284c7' : 'var(--border-color)'}; box-shadow: var(--shadow-sm);">
                <span>📊 전체 대시보드 (프로그램 현황)</span>
                <span style="font-size: 0.75rem; background: var(--accent-blue); color: #fff; padding: 2px 7px; border-radius: 10px;">${totalPrograms}개</span>
            </a>
        `;

        this.categories.forEach(cat => {
            const catProgs = this.programs.filter(p => p.category === cat.id);
            const isCatOpen = selectedProg && selectedProg.category === cat.id;

            html += `
                <div class="nav-cat-item ${isCatOpen ? 'open' : ''}">
                    <div class="nav-cat-header">
                        <span>${this.escapeHtml(cat.icon)} ${this.escapeHtml(cat.name)}</span>
                        <span class="arrow-icon">▶</span>
                    </div>
                    <div class="nav-program-group">
            `;

            catProgs.forEach(prog => {
                const isProgOpen = selectedProg && selectedProg.id === prog.id;

                html += `
                    <div class="nav-prog-item ${isProgOpen ? 'open' : ''}">
                        <div class="nav-prog-header">
                            <span>${this.escapeHtml(prog.name)}</span>
                            <span class="arrow-icon">▶</span>
                        </div>
                        <div class="nav-manuals-group">
                `;

                (prog.manuals || []).forEach(m => {
                    const isManualActive = m.id === selectedManualId;
                    html += `
                        <a href="index.html?id=${encodeURIComponent(m.id)}" 
                           class="nav-manual-link ${isManualActive ? 'active' : ''}"
                           data-title="${this.escapeHtml(m.title)}"
                           title="${this.escapeHtml(m.title)}">
                            <span>- ${this.escapeHtml(m.title)}</span>
                        </a>
                    `;
                });

                html += `
                        </div>
                    </div>
                `;
            });

            html += `
                    </div>
                </div>
            `;
        });

        sidebarTree.innerHTML = html;
        this.bindSidebarEvents();
    },

    bindSidebarEvents() {
        const catHeaders = document.querySelectorAll('.nav-cat-header');
        catHeaders.forEach(header => {
            header.addEventListener('click', () => {
                const catItem = header.closest('.nav-cat-item');
                catItem.classList.toggle('open');
            });
        });

        const progHeaders = document.querySelectorAll('.nav-prog-header');
        progHeaders.forEach(header => {
            header.addEventListener('click', () => {
                const progItem = header.closest('.nav-prog-item');
                progItem.classList.toggle('open');
            });
        });
    },

    // 메인 컨텐츠 (대시보드 또는 매뉴얼 상세) 렌더링
    renderMainContent(manual, program) {
        const container = document.getElementById('mainContent');
        if (!container) return;

        if (manual && program) {
            // 매뉴얼 상세 뷰
            let stepsHtml = '';
            (manual.steps || []).forEach((step, idx) => {
                const stepNo = step.step_no || (idx + 1);
                const stepId = `step-${stepNo}`;
                stepsHtml += `
                    <div class="section-card" id="${stepId}">
                        <div class="section-header">
                            <div class="section-num">${stepNo}</div>
                            <h3 class="section-title">${this.escapeHtml(step.title)}</h3>
                        </div>
                        <div class="section-body">
                            <p>${this.nl2br(this.escapeHtml(step.content))}</p>
                            ${step.code ? `
                                <div class="code-box">
                                    <div class="code-header">
                                        <span>명령어 / 입력 템플릿</span>
                                        <button class="btn-copy">복사하기</button>
                                    </div>
                                    <pre><code>${this.escapeHtml(step.code)}</code></pre>
                                </div>
                            ` : ''}
                            ${step.tip ? `
                                <div class="tip-box">
                                    💡 <strong>초보자 팁:</strong> ${this.escapeHtml(step.tip)}
                                </div>
                            ` : ''}
                            ${step.warning ? `
                                <div class="warning-box">
                                    ⚠️ <strong>주의사항:</strong> ${this.escapeHtml(step.warning)}
                                </div>
                            ` : ''}
                            <div class="step-card-footer">
                                <div class="step-gear-dropdown">
                                    <button type="button" class="btn-step-gear" title="이 단계 옵션 및 추가 설정">⚙️</button>
                                    <div class="step-gear-menu">
                                        <button type="button" class="step-gear-item primary" onclick="openStepInsertModal('${manual.id}', ${stepNo})">
                                            <span>➕ 이 단계(Step ${stepNo}) 하단에 추가하기</span>
                                        </button>
                                        <button type="button" class="step-gear-item" onclick="openEditModal('${manual.id}')">
                                            <span>✏️ 전체 매뉴얼 편집 및 순서 변경</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });

            let tocHtml = (manual.steps || []).map((step, idx) => {
                const stepNo = step.step_no || (idx + 1);
                return `
                    <a href="#step-${stepNo}" class="toc-link">
                        <span>Step ${stepNo}.</span>
                        <span>${this.escapeHtml(step.title)}</span>
                    </a>
                `;
            }).join('');

            container.innerHTML = `
                <div class="program-header">
                    <div class="program-badge-row">
                        <span class="badge badge-blue">📌 프로그램: ${this.escapeHtml(program.name || '일반')}</span>
                        <span class="badge badge-blue">⏱️ 소요시간: ${this.escapeHtml(manual.duration || '10분')}</span>
                        <span class="badge badge-amber">난이도: ${this.escapeHtml(manual.difficulty || '★☆☆☆☆ (입문)')}</span>
                    </div>
                    <h1 class="program-main-title">${this.escapeHtml(manual.title || '제목 없음')}</h1>
                    <p class="program-description">${this.escapeHtml(manual.description || '')}</p>
                    <div style="margin-top: 1rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <a href="edit.html?prog_name=${encodeURIComponent(program.name || '')}&cat=${encodeURIComponent(program.category || '')}" class="btn btn-primary" style="font-size: 0.85rem; padding: 5px 12px;">
                            ➕ 이 프로그램에 새 주제 추가
                        </a>
                        <button onclick="openEditModal('${manual.id}')" class="btn btn-secondary" style="font-size: 0.85rem; padding: 5px 12px; color: var(--accent-blue); border-color: var(--accent-blue);">
                            ✏️ 매뉴얼 수정 & 내용 추가
                        </button>
                        <button onclick="deleteManual('${manual.id}')" class="btn btn-secondary" style="font-size: 0.85rem; padding: 5px 12px; color: var(--accent-rose);">
                            🗑️ 삭제
                        </button>
                    </div>
                </div>
                <div class="info-callout">
                    <strong>🎯 초보자 안내:</strong> ${this.escapeHtml(manual.target_users || '모든 초보자')}<br>
                    <strong>📌 준비물:</strong> ${this.escapeHtml(manual.prerequisites || '기본 PC 사용 환경')}
                </div>
                <div class="toc-bar">
                    <div class="toc-title"><span>📌 매뉴얼 세부 단계 바로가기 (클릭 시 위치 이동)</span></div>
                    <div class="toc-links">${tocHtml}</div>
                </div>
                <div class="sections-wrapper">
                    ${stepsHtml}
                    <div style="text-align: center; margin-top: 1.5rem;">
                        <button onclick="openEditModal('${manual.id}', true)" class="btn btn-secondary" style="background: #f0f9ff; border: 2px dashed var(--accent-blue); color: var(--accent-blue); padding: 0.8rem 1.5rem; font-size: 0.95rem; width: 100%; justify-content: center;">
                            ➕ 이 매뉴얼에 새로운 단계(Step) 추가하기
                        </button>
                    </div>
                </div>
            `;
            this.bindCopyButtons();
            this.bindTocScroll();
        } else {
            // 대시보드 뷰
            let totalCategories = this.categories.length;
            let totalPrograms = this.programs.length;
            let totalManuals = 0;
            let totalSteps = 0;

            this.programs.forEach(p => {
                const mList = p.manuals || [];
                totalManuals += mList.length;
                mList.forEach(m => {
                    totalSteps += (m.steps || []).length;
                });
            });

            let catCardsHtml = '';
            this.categories.forEach(cat => {
                const catProgs = this.programs.filter(p => p.category === cat.id);
                let progCardsHtml = '';

                if (catProgs.length > 0) {
                    catProgs.forEach(prog => {
                        const mList = prog.manuals || [];
                        let manualItemsHtml = '';
                        if (mList.length > 0) {
                            mList.forEach(m => {
                                manualItemsHtml += `
                                    <li>
                                        <a href="index.html?id=${encodeURIComponent(m.id)}" class="dash-manual-item">
                                            <span class="dash-m-bullet">•</span>
                                            <span class="dash-m-title">${this.escapeHtml(m.title)}</span>
                                            <span class="dash-m-steps">${(m.steps || []).length} Steps</span>
                                        </a>
                                    </li>
                                `;
                            });
                        } else {
                            manualItemsHtml = `<li style="font-size: 0.82rem; color: var(--text-dim); padding: 0.3rem 0;">등록된 매뉴얼 없음</li>`;
                        }

                        progCardsHtml += `
                            <div class="prog-dash-card">
                                <div class="prog-dash-top">
                                    <h4 class="prog-dash-name">${this.escapeHtml(prog.name)}</h4>
                                    <span class="prog-dash-mcount">${mList.length}개 매뉴얼</span>
                                </div>
                                <ul class="dash-manual-list">${manualItemsHtml}</ul>
                                <div class="prog-dash-bottom">
                                    <a href="edit.html?prog_name=${encodeURIComponent(prog.name)}&cat=${encodeURIComponent(prog.category)}" class="btn-dash-add">
                                        ➕ 새 주제 추가
                                    </a>
                                </div>
                            </div>
                        `;
                    });
                } else {
                    progCardsHtml = `<div style="grid-column: 1 / -1; padding: 1rem; color: var(--text-dim); font-size: 0.9rem;">등록된 프로그램이 없습니다.</div>`;
                }

                catCardsHtml += `
                    <div class="cat-dash-card">
                        <div class="cat-dash-header">
                            <span class="cat-dash-badge">${this.escapeHtml(cat.icon)} ${this.escapeHtml(cat.name)}</span>
                            <span class="cat-dash-count">프로그램 ${catProgs.length}개</span>
                        </div>
                        <div class="prog-dash-grid">${progCardsHtml}</div>
                    </div>
                `;
            });

            container.innerHTML = `
                <div class="dashboard-container">
                    <div class="dashboard-hero">
                        <div class="hero-text">
                            <h1 class="hero-title">📊 Study Box 프로그램 현황 대시보드</h1>
                            <p class="hero-subtitle">초보자를 위한 프로그램 가이드 & AI 매뉴얼 통합 학습 센터 현황입니다.</p>
                        </div>
                        <div class="hero-actions">
                            <a href="edit.html" class="btn btn-primary" style="background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%); color: #fff; padding: 0.75rem 1.2rem; font-size: 0.95rem;">
                                ⚡ 새 매뉴얼 AI 프롬프트 생성
                            </a>
                        </div>
                    </div>
                    <div class="stat-cards-grid">
                        <div class="stat-card">
                            <div class="stat-icon-wrapper" style="background: #e0f2fe; color: #0284c7;">📁</div>
                            <div class="stat-info"><div class="stat-label">분류 카테고리</div><div class="stat-value">${totalCategories}<span class="stat-unit">개</span></div></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon-wrapper" style="background: #f3e8ff; color: #7c3aed;">💻</div>
                            <div class="stat-info"><div class="stat-label">다루는 프로그램</div><div class="stat-value">${totalPrograms}<span class="stat-unit">개</span></div></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon-wrapper" style="background: #dcfce7; color: #059669;">📖</div>
                            <div class="stat-info"><div class="stat-label">등록된 매뉴얼</div><div class="stat-value">${totalManuals}<span class="stat-unit">개</span></div></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon-wrapper" style="background: #fef3c7; color: #d97706;">📍</div>
                            <div class="stat-info"><div class="stat-label">실습 단계 (Steps)</div><div class="stat-value">${totalSteps}<span class="stat-unit">단계</span></div></div>
                        </div>
                    </div>
                    <div class="dashboard-section-title">
                        <h3>💻 카테고리별 프로그램 현황 및 매뉴얼 리스트</h3>
                        <p>원하시는 가이드 주제를 선택하거나 새 매뉴얼을 작성해 보세요.</p>
                    </div>
                    <div class="category-dashboard-list">${catCardsHtml}</div>
                </div>
            `;
        }
    },

    bindCopyButtons() {
        const copyBtns = document.querySelectorAll('.btn-copy');
        copyBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const codeBox = btn.closest('.code-box');
                const codeText = codeBox.querySelector('pre code, pre').innerText;
                navigator.clipboard.writeText(codeText).then(() => {
                    const originalText = btn.innerText;
                    btn.innerText = '복사 완료! ✓';
                    btn.style.background = '#059669';
                    btn.style.color = '#fff';
                    setTimeout(() => {
                        btn.innerText = originalText;
                        btn.style.background = '';
                        btn.style.color = '';
                    }, 2000);
                });
            });
        });
    },

    bindTocScroll() {
        const tocLinks = document.querySelectorAll('.toc-link');
        tocLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                const href = link.getAttribute('href');
                if (href && href.startsWith('#')) {
                    const targetElement = document.querySelector(href);
                    if (targetElement) {
                        e.preventDefault();
                        targetElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        targetElement.style.transition = 'background-color 0.5s ease';
                        const origBg = targetElement.style.backgroundColor;
                        targetElement.style.backgroundColor = '#e0f2fe';
                        setTimeout(() => {
                            targetElement.style.backgroundColor = origBg;
                        }, 1200);
                    }
                }
            });
        });
    },

    async initViewPage() {
        await this.loadData();
        this.renderAdminUI();
        const urlParams = new URLSearchParams(window.location.search);
        const id = urlParams.get('id');

        let item = null;
        if (id) {
            for (const p of this.programs) {
                for (const m of (p.manuals || [])) {
                    if (m.id === id) {
                        item = m;
                        break;
                    }
                }
                if (item) break;
            }
        }

        if (!item) {
            window.location.href = 'index.html';
            return;
        }

        const catInfo = this.categories.find(c => c.id === item.category) || { name: '일반', icon: '🏷️' };
        const btnEditView = document.getElementById('btnEditView');
        if (btnEditView) btnEditView.href = `edit.html?id=${encodeURIComponent(item.id)}`;

        let stepsHtml = '';
        (item.steps || []).forEach((step, index) => {
            const stepNo = step.step_no || (index + 1);
            stepsHtml += `
                <div class="step-card">
                    <div class="step-header">
                        <div class="step-number">${stepNo}</div>
                        <h3 class="step-title">${this.escapeHtml(step.title)}</h3>
                    </div>
                    <div class="step-body">
                        <p>${this.nl2br(this.escapeHtml(step.content))}</p>
                        ${step.code ? `
                            <div class="code-box">
                                <div class="code-header"><span>명령어 / 입력 템플릿</span><button class="btn-copy">복사하기</button></div>
                                <pre><code>${this.escapeHtml(step.code)}</code></pre>
                            </div>
                        ` : ''}
                        ${step.tip ? `<div class="callout callout-tip">💡 <strong>초보자 꿀팁:</strong> ${this.escapeHtml(step.tip)}</div>` : ''}
                        ${step.warning ? `<div class="callout callout-warning">⚠️ <strong>주의 사항:</strong> ${this.escapeHtml(step.warning)}</div>` : ''}
                        <div class="step-card-footer">
                            <div class="step-gear-dropdown">
                                <button type="button" class="btn-step-gear">⚙️</button>
                                <div class="step-gear-menu">
                                    <a href="index.html?id=${encodeURIComponent(item.id)}&insert_step=${stepNo}" class="step-gear-item primary" style="text-decoration: none;">
                                        <span>➕ 이 단계(Step ${stepNo}) 하단에 추가하기</span>
                                    </a>
                                    <a href="edit.html?id=${encodeURIComponent(item.id)}" class="step-gear-item" style="text-decoration: none;">
                                        <span>✏️ 전체 매뉴얼 편집 및 순서 변경</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        const viewContainer = document.getElementById('viewContainer');
        if (viewContainer) {
            viewContainer.innerHTML = `
                <div class="detail-header">
                    <div class="detail-badge-group">
                        <span class="cat-chip" style="pointer-events: none; background: rgba(56,189,248,0.2); color: var(--accent-blue);">
                            ${this.escapeHtml(catInfo.icon || '🏷️')} ${this.escapeHtml(catInfo.name || '일반')}
                        </span>
                        <span class="cat-chip" style="pointer-events: none;">⏱️ 소요시간: ${this.escapeHtml(item.duration || '10분')}</span>
                        <span class="cat-chip" style="pointer-events: none; color: var(--accent-amber);">난이도: ${this.escapeHtml(item.difficulty || '★☆☆☆☆ (입문)')}</span>
                    </div>
                    <h1 class="detail-title">${this.escapeHtml(item.title || '제목 없음')}</h1>
                    <p class="detail-intro">${this.escapeHtml(item.description || '')}</p>
                    <div class="beginner-notice">
                        <h4>🎯 추천 대상 및 준비물</h4>
                        <p><strong>[대상]:</strong> ${this.escapeHtml(item.target_users || '모든 초보자')}</p>
                        <p><strong>[준비물]:</strong> ${this.escapeHtml(item.prerequisites || '기본 PC 사용 환경')}</p>
                    </div>
                </div>
                <h2 style="font-size: 1.5rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px;">
                    <span>📍 Step-by-Step 단계별 따라하기</span>
                    <span style="font-size: 0.9rem; color: var(--text-dim); font-weight: normal;">(총 ${(item.steps || []).length}단계)</span>
                </h2>
                <div class="step-container">${stepsHtml}</div>
                <div style="margin-top: 3rem; text-align: center; display: flex; justify-content: center; gap: 1rem;">
                    <a href="index.html" class="btn btn-secondary">← 목록으로</a>
                    <button onclick="deleteManual('${item.id}')" class="btn btn-secondary" style="color: var(--accent-rose); border-color: rgba(244,63,94,0.3);">
                        🗑️ 이 매뉴얼 삭제하기
                    </button>
                </div>
            `;
            this.bindCopyButtons();
        }
    },

    async initEditPage() {
        await this.loadData();
        this.renderAdminUI();
        if (!this.checkAdminPermission()) {
            window.location.href = 'index.html';
            return;
        }
        const urlParams = new URLSearchParams(window.location.search);
        const manualId = urlParams.get('id') || '';
        const defaultProgName = urlParams.get('prog_name') || '';
        const defaultCatId = urlParams.get('cat') || '';

        let targetManual = null;
        let targetProgram = null;

        if (manualId) {
            for (const p of this.programs) {
                for (const m of (p.manuals || [])) {
                    if (m.id === manualId) {
                        targetManual = m;
                        targetProgram = p;
                        break;
                    }
                }
                if (targetManual) break;
            }
        }

        const isEditMode = !!targetManual;
        const editContainer = document.getElementById('editContainer');
        const btnBackToApp = document.getElementById('btnBackToApp');

        if (btnBackToApp) {
            btnBackToApp.href = isEditMode ? `index.html?id=${encodeURIComponent(targetManual.id)}` : 'index.html';
            btnBackToApp.innerText = isEditMode ? '← 해당 매뉴얼로 돌아가기' : '← 스터디박스로 돌아가기';
        }

        if (!editContainer) return;

        if (isEditMode) {
            // 수정 모드 HTML
            editContainer.innerHTML = `
                <div class="program-header" style="text-align: center; margin-bottom: 2rem;">
                    <h1 style="font-size: 2rem; font-weight: 800; color: var(--accent-blue); margin-bottom: 0.5rem;">
                        ✏️ 매뉴얼 수정 & 설명 내용 추가
                    </h1>
                    <p style="color: var(--text-muted); font-size: 1rem;">
                        기존 매뉴얼의 단계를 수정하거나, 채팅형 AI에게 추가 설명을 요청하는 프롬프트를 생성해 새 단계를 추가할 수 있습니다.
                    </p>
                </div>
                <div class="section-card">
                    <form id="editManualFormFull">
                        <input type="hidden" id="editManualId" value="${targetManual.id}">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label class="form-label" style="font-weight: 700;">소속 프로그램 명</label>
                                <input type="text" id="editProgramName" class="form-control" value="${this.escapeHtml(targetProgram.name || '')}" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="font-weight: 700;">매뉴얼 제목 (주제)</label>
                                <input type="text" id="editManualTitle" class="form-control" value="${this.escapeHtml(targetManual.title || '')}" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 700;">한 줄 요약 (설명)</label>
                            <textarea id="editDescription" class="form-control" rows="2">${this.escapeHtml(targetManual.description || '')}</textarea>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label class="form-label">소요시간</label>
                                <input type="text" id="editDuration" class="form-control" value="${this.escapeHtml(targetManual.duration || '10분')}">
                            </div>
                            <div class="form-group">
                                <label class="form-label">난이도</label>
                                <select id="editDifficulty" class="form-control">
                                    <option value="★☆☆☆☆ (입문)" ${targetManual.difficulty === '★☆☆☆☆ (입문)' ? 'selected' : ''}>★☆☆☆☆ (입문)</option>
                                    <option value="★★☆☆☆ (초급)" ${targetManual.difficulty === '★★☆☆☆ (초급)' ? 'selected' : ''}>★★☆☆☆ (초급)</option>
                                    <option value="★★★☆☆ (중급)" ${targetManual.difficulty === '★★★☆☆ (중급)' ? 'selected' : ''}>★★★☆☆ (중급)</option>
                                    <option value="★★★★☆ (상급)" ${targetManual.difficulty === '★★★★☆ (상급)' ? 'selected' : ''}>★★★★☆ (상급)</option>
                                </select>
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label class="form-label">추천 대상</label>
                                <input type="text" id="editTargetUsers" class="form-control" value="${this.escapeHtml(targetManual.target_users || '')}">
                            </div>
                            <div class="form-group">
                                <label class="form-label">준비물</label>
                                <input type="text" id="editPrerequisites" class="form-control" value="${this.escapeHtml(targetManual.prerequisites || '')}">
                            </div>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin: 2rem 0 1rem 0; border-top: 1px solid var(--border-color); padding-top: 1.2rem;">
                            <h3 style="font-size: 1.2rem; color: var(--accent-blue);">📍 단계별 가이드 내용 (Step 별 편집 & 순서 조정)</h3>
                            <button type="button" class="btn btn-secondary" onclick="addEditStepCard()" style="font-size: 0.85rem; background: #e0f2fe; color: var(--accent-blue);">
                                ➕ 직접 새 단계(Step) 추가
                            </button>
                        </div>
                        <!-- AI Step Assistant Card -->
                        <div class="ai-assistant-card theme-blue">
                            <div class="ai-assistant-title"><span>🤖 AI 추가 설명/단계(Step) 프롬프트 생성 & 자동 삽입</span></div>
                            <p class="ai-assistant-desc">기존 설명에 덧붙이고 싶은 새로운 단계나 작업 설명을 입력하면 ChatGPT, Gemini 등에 입력할 프롬프트를 자동으로 생성해 드립니다.</p>
                            <div class="form-group" style="margin-bottom: 0.6rem;">
                                <label class="form-label" style="font-size: 0.85rem; font-weight: 700;">추가 요청할 설명 / 작업 내용</label>
                                <textarea id="pageAiStepReq" class="form-control" rows="2" placeholder="예: 설정 완료 후 자주 발생하는 오류 해결법과 백업 가이드 단계를 추가해줘..."></textarea>
                            </div>
                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.75rem;">
                                <button type="button" class="btn btn-secondary" onclick="generatePageStepPrompt()" style="background: #ffffff; color: var(--accent-blue); border-color: #bae6fd; font-size: 0.85rem;">
                                    ✨ 추가 단계 AI 프롬프트 생성하기
                                </button>
                            </div>
                            <div id="pagePromptOutputWrapper" style="display: none; margin-bottom: 1rem;">
                                <div class="code-box" style="margin-top: 0.5rem;">
                                    <div class="code-header">
                                        <span>AI 프롬프트 (ChatGPT / Claude / Gemini 채팅창에 복사해 넣으세요)</span>
                                        <button type="button" class="btn-copy" id="btnCopyPagePrompt" onclick="copyPageStepPrompt()">프롬프트 복사하기</button>
                                    </div>
                                    <pre><code id="pagePromptCodeText"></code></pre>
                                </div>
                            </div>
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
                        <div id="editStepsContainer"></div>
                        <div style="margin-top: 2rem; display: flex; justify-content: flex-end; gap: 1rem;">
                            <a href="index.html?id=${encodeURIComponent(targetManual.id)}" class="btn btn-secondary">취소</a>
                            <button type="button" class="btn btn-primary" onclick="saveManualEditPage()">💾 변경사항 저장하기</button>
                        </div>
                    </form>
                </div>
            `;

            const stepsContainer = document.getElementById('editStepsContainer');
            if (targetManual.steps && targetManual.steps.length > 0) {
                targetManual.steps.forEach(step => addEditStepCard(step));
            } else {
                addEditStepCard();
            }
        } else {
            // 신규 생성 모드 HTML
            let catOptions = this.categories.map(c => `<option value="${c.id}" ${c.id === defaultCatId ? 'selected' : ''}>${c.icon} ${c.name}</option>`).join('');

            editContainer.innerHTML = `
                <div class="program-header" style="text-align: center; margin-bottom: 2rem;">
                    <h1 style="font-size: 2rem; font-weight: 800; color: var(--accent-blue); margin-bottom: 0.5rem;">
                        🤖 새 매뉴얼 AI 프롬프트 제작 & 자동 등록
                    </h1>
                    <p style="color: var(--text-muted); font-size: 1rem;">
                        초보자를 위한 가이드 주제를 입력하면 AI(ChatGPT, Gemini 등) 프롬프트를 자동으로 만들어 드립니다.<br>
                        AI 답변 코드를 붙여넣으면 새 매뉴얼이 스터디 박스에 즉시 등록됩니다.
                    </p>
                </div>
                <div class="section-card" style="margin-bottom: 2rem;">
                    <h3 style="font-size: 1.2rem; color: var(--accent-blue); margin-bottom: 1rem;">1️⃣ 매뉴얼 기본 정보 입력 & 프롬프트 생성</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 700;">분류 카테고리</label>
                            <select id="newCatId" class="form-control">${catOptions}</select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 700;">프로그램 이름</label>
                            <input type="text" id="newProgName" class="form-control" value="${this.escapeHtml(defaultProgName)}" placeholder="예: VS Code, Photoshop, Cursor AI..." required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 700;">매뉴얼 주제 (제목)</label>
                        <input type="text" id="newManualTitle" class="form-control" placeholder="예: 초보자를 위한 Git 기초 사용법 및 설치 가이드" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">매뉴얼 관련 상세 요청사항 (선택)</label>
                        <textarea id="newManualReq" class="form-control" rows="2" placeholder="특별히 다루고 싶은 내용이나 강조하고 싶은 옵션을 입력하세요..."></textarea>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="generateNewManualPrompt()" style="width: 100%; justify-content: center; padding: 0.8rem; font-size: 1rem; margin-top: 0.5rem;">
                        ✨ AI 프롬프트 생성하기
                    </button>
                    <div id="newPromptWrapper" style="display: none; margin-top: 1.5rem;">
                        <div class="code-box">
                            <div class="code-header">
                                <span>생성된 프롬프트 (ChatGPT / Claude / Gemini 등에 복사해 넣으세요)</span>
                                <button type="button" class="btn-copy" id="btnCopyNewPrompt" onclick="copyNewManualPrompt()">프롬프트 복사하기</button>
                            </div>
                            <pre><code id="newPromptCodeText"></code></pre>
                        </div>
                    </div>
                </div>
                <div class="section-card">
                    <h3 style="font-size: 1.2rem; color: var(--accent-green); margin-bottom: 1rem;">2️⃣ AI 답변 코드(JSON) 붙여넣기 & 스터디박스에 등록</h3>
                    <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1rem;">
                        AI 채팅창에서 출력된 JSON 응답 코드를 복사하여 아래 상자에 붙여넣고 [등록하기] 버튼을 누르세요.
                    </p>
                    <div class="form-group">
                        <textarea id="newAiResponseJson" class="form-control" rows="8" placeholder="AI가 생성해준 JSON 코드를 여기에 붙여넣으세요..."></textarea>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="saveNewManualFromAi()" style="width: 100%; justify-content: center; padding: 0.8rem; font-size: 1rem; background: var(--accent-green);">
                        📦 Study Box에 매뉴얼 즉시 등록하기
                    </button>
                </div>
            `;
        }
    },

    escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    },

    nl2br(str) {
        if (!str) return '';
        return str.replace(/\n/g, '<br>');
    }
};

// Global Admin Action Handlers
function handleAdminLogout() {
    StudyBoxApp.logoutAdmin();
    alert("🔒 로그아웃 되었습니다.");
}

async function handleAdminNavLink(event, href) {
    const hasPerm = await StudyBoxApp.checkAdminPermission();
    if (!hasPerm) {
        if (event) event.preventDefault();
        return false;
    }
    if (href && href !== '#' && !event.defaultPrevented) {
        window.location.href = href;
    }
    return true;
}

// Global Actions & Functions
async function deleteManual(id) {
    const hasPerm = await StudyBoxApp.checkAdminPermission();
    if (!hasPerm) return;

    if (confirm('정말로 이 매뉴얼을 삭제하시겠습니까?')) {
        let found = false;
        StudyBoxApp.programs.forEach(p => {
            if (p.manuals) {
                const idx = p.manuals.findIndex(m => m.id === id);
                if (idx !== -1) {
                    p.manuals.splice(idx, 1);
                    found = true;
                }
            }
        });
        if (found) {
            StudyBoxApp.saveData();
            alert('삭제되었습니다.');
            window.location.href = 'index.html';
        } else {
            alert('매뉴얼을 찾을 수 없습니다.');
        }
    }
}

let currentInsertTargetStepNo = null;

async function openStepInsertModal(manualId, stepNo) {
    const hasPerm = await StudyBoxApp.checkAdminPermission();
    if (!hasPerm) return;
    openEditModal(manualId, false, stepNo, true);
}

async function openEditModal(manualId, focusNewStep = false, insertAfterStepNo = null, skipCheck = false) {
    if (!skipCheck) {
        const hasPerm = await StudyBoxApp.checkAdminPermission();
        if (!hasPerm) return;
    }
    currentInsertTargetStepNo = insertAfterStepNo;

    let targetManual = null;
    let targetProgram = null;

    for (const p of StudyBoxApp.programs) {
        for (const m of (p.manuals || [])) {
            if (m.id === manualId) {
                targetManual = m;
                targetProgram = p;
                break;
            }
        }
        if (targetManual) break;
    }

    if (!targetManual) {
        alert('매뉴얼 데이터를 찾을 수 없습니다.');
        return;
    }

    document.getElementById('editManualId').value = targetManual.id;
    document.getElementById('editProgramName').value = targetProgram ? targetProgram.name : '';
    document.getElementById('editManualTitle').value = targetManual.title || '';
    document.getElementById('editDescription').value = targetManual.description || '';
    document.getElementById('editDuration').value = targetManual.duration || '10분';
    document.getElementById('editDifficulty').value = targetManual.difficulty || '★☆☆☆☆ (입문)';
    document.getElementById('editTargetUsers').value = targetManual.target_users || '';
    document.getElementById('editPrerequisites').value = targetManual.prerequisites || '';

    const btnOpenEditPage = document.getElementById('btnOpenEditPage');
    if (btnOpenEditPage) {
        btnOpenEditPage.href = `edit.html?id=${encodeURIComponent(targetManual.id)}`;
    }

    const modalPromptOutputWrapper = document.getElementById('modalPromptOutputWrapper');
    if (modalPromptOutputWrapper) modalPromptOutputWrapper.style.display = 'none';
    const modalAiStepReq = document.getElementById('modalAiStepReq');
    if (modalAiStepReq) modalAiStepReq.value = '';
    const modalAiResponsePayload = document.getElementById('modalAiResponsePayload');
    if (modalAiResponsePayload) modalAiResponsePayload.value = '';

    const stepsContainer = document.getElementById('editStepsContainer');
    stepsContainer.innerHTML = '';

    if (targetManual.steps && targetManual.steps.length > 0) {
        targetManual.steps.forEach(step => addEditStepCard(step));
    } else {
        addEditStepCard();
    }

    if (focusNewStep) {
        addEditStepCard();
    }

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
        if (sel) sel.value = insertAfterStepNo.toString();
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
}

function closeEditModal() {
    document.getElementById('manualEditModal').style.display = 'none';
    currentInsertTargetStepNo = null;
}

function quickInsertEmptyStepAtTarget() {
    const targetNo = currentInsertTargetStepNo;
    const container = document.getElementById('editStepsContainer');
    const cards = Array.from(container.querySelectorAll('.edit-step-card'));

    let insertIndex = cards.length;
    if (targetNo && targetNo >= 1 && targetNo <= cards.length) {
        insertIndex = targetNo;
    }

    const newCard = createStepCardElement(null, insertIndex + 1);
    cards.splice(insertIndex, 0, newCard);
    cards.forEach(c => container.appendChild(c));
    updateStepNumbers();

    newCard.classList.remove('step-card-highlight');
    void newCard.offsetWidth;
    newCard.classList.add('step-card-highlight');
    newCard.scrollIntoView({ behavior: 'smooth', block: 'center' });

    const titleInput = newCard.querySelector('.edit-step-title');
    if (titleInput) setTimeout(() => titleInput.focus(), 300);

    const alertTargetNo = targetNo || cards.length;
    const noticeBanner = document.getElementById('stepInsertModeNotice');
    if (noticeBanner) {
        noticeBanner.innerHTML = `
            <div style="font-size: 0.95rem; font-weight: 800; color: #059669; display: flex; align-items: center; gap: 6px;">
                <span>✓ Step ${alertTargetNo}번 바로 하단에 새 단계 카드가 성공적으로 삽입되었습니다! 내용을 입력해 주세요.</span>
            </div>
        `;
    }
}

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
            <input type="text" class="form-control edit-step-title" oninput="updateModalInsertTargetOptions()" value="${step ? StudyBoxApp.escapeHtml(step.title || '') : ''}" placeholder="예: 프로그램 실행 및 메인 메뉴 접속" required>
        </div>
        <div class="form-group" style="margin-bottom: 0.5rem;">
            <label class="form-label" style="font-size: 0.85rem;">상세 설명 내용</label>
            <textarea class="form-control edit-step-content" rows="3" placeholder="초보자 눈높이에서 단계별 실행 가이드를 작성하세요..." required>${step ? StudyBoxApp.escapeHtml(step.content || '') : ''}</textarea>
        </div>
        <div class="form-group" style="margin-bottom: 0.5rem;">
            <label class="form-label" style="font-size: 0.85rem;">명령어 / 입력 템플릿 (선택)</label>
            <textarea class="form-control edit-step-code" rows="2" placeholder="복사할 명령어 또는 URL 주소">${step ? StudyBoxApp.escapeHtml(step.code || '') : ''}</textarea>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.85rem;">💡 초보자 팁 (선택)</label>
                <input type="text" class="form-control edit-step-tip" value="${step ? StudyBoxApp.escapeHtml(step.tip || '') : ''}" placeholder="꿀팁 한 줄">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.85rem;">⚠️ 주의사항 (선택)</label>
                <input type="text" class="form-control edit-step-warning" value="${step ? StudyBoxApp.escapeHtml(step.warning || '') : ''}" placeholder="주의할 점 한 줄">
            </div>
        </div>
    `;
    return card;
}

function addEditStepCard(step = null) {
    const stepsContainer = document.getElementById('editStepsContainer');
    if (!stepsContainer) return;
    const stepCount = stepsContainer.querySelectorAll('.edit-step-card').length + 1;
    const currentNum = step ? (step.step_no || stepCount) : stepCount;

    const card = createStepCardElement(step, currentNum);
    stepsContainer.appendChild(card);
    updateStepNumbers();
    if (!step) card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

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

    const targetIndex = targetNo - 1;
    if (currentIndex === targetIndex) {
        input.value = targetIndex + 1;
        return;
    }

    cards.splice(currentIndex, 1);
    cards.splice(targetIndex, 0, card);
    cards.forEach(c => container.appendChild(c));
    updateStepNumbers();

    card.classList.remove('step-card-highlight');
    void card.offsetWidth;
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

function updateModalInsertTargetOptions(preferredVal = null) {
    const sel = document.getElementById('modalInsertTargetStep') || document.getElementById('pageInsertTargetStep');
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
- [단계 수 무제한 원칙]: 필요 설명에 맞춰 단계 수 제한 없이 작성해주세요.

[JSON 출력 형식 예시]:
[
  {
    "step_no": ${startNo},
    "title": "추가 단계 제목",
    "content": "초보자 눈높이에서 따라하기 쉬운 상세 설명 내용",
    "code": "명령어 또는 클릭 위치 (선택 사항)",
    "tip": "초보자 꿀팁 (선택 사항)",
    "warning": "주의 사항 (선택 사항)"
  }
]`;

    document.getElementById('modalPromptCodeText').innerText = promptText;
    document.getElementById('modalPromptOutputWrapper').style.display = 'block';
}

function generatePageStepPrompt() {
    const progName = document.getElementById('editProgramName').value.trim() || '프로그램';
    const manualTitle = document.getElementById('editManualTitle').value.trim() || '매뉴얼';
    const detail = document.getElementById('pageAiStepReq').value.trim();
    const targetVal = document.getElementById('pageInsertTargetStep') ? document.getElementById('pageInsertTargetStep').value : 'last';
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

[JSON 출력 형식 예시]:
[
  {
    "step_no": ${startNo},
    "title": "추가 단계 제목",
    "content": "상세 설명 내용",
    "code": "",
    "tip": "",
    "warning": ""
  }
]`;

    document.getElementById('pagePromptCodeText').innerText = promptText;
    document.getElementById('pagePromptOutputWrapper').style.display = 'block';
}

function copyModalStepPrompt() {
    const text = document.getElementById('modalPromptCodeText').innerText;
    navigator.clipboard.writeText(text).then(() => {
        const btn = document.getElementById('btnCopyModalPrompt');
        btn.innerText = '복사 완료! ✓';
        setTimeout(() => btn.innerText = '프롬프트 복사하기', 2000);
    });
}

function copyPageStepPrompt() {
    const text = document.getElementById('pagePromptCodeText').innerText;
    navigator.clipboard.writeText(text).then(() => {
        const btn = document.getElementById('btnCopyPagePrompt');
        btn.innerText = '복사 완료! ✓';
        setTimeout(() => btn.innerText = '프롬프트 복사하기', 2000);
    });
}

function parseJsonFromText(rawText) {
    if (!rawText) return null;
    let text = rawText.trim();
    
    // 마크다운 코드 블록 제거
    if (text.startsWith('```json')) text = text.replace(/^```json/i, '');
    if (text.startsWith('```')) text = text.replace(/^```/, '');
    if (text.endsWith('```')) text = text.replace(/```$/, '');
    text = text.trim();

    // 1차 표준 파싱 시도
    try {
        return JSON.parse(text);
    } catch (e) {}

    // [ ... ] 또는 { ... } 구간 잘라내기
    const startObj = text.indexOf('{');
    const startArr = text.indexOf('[');
    let startPos = -1;
    if (startObj !== -1 && startArr !== -1) {
        startPos = Math.min(startObj, startArr);
    } else if (startObj !== -1) {
        startPos = startObj;
    } else {
        startPos = startArr;
    }

    if (startPos !== -1) {
        const isArr = text[startPos] === '[';
        const endPos = isArr ? text.lastIndexOf(']') : text.lastIndexOf('}');
        if (endPos > startPos) {
            text = text.substring(startPos, endPos + 1);
            try {
                return JSON.parse(text);
            } catch (e) {}
        }
    }

    // 2차 고급 정제: 스마트 따옴표 변환 및 문자열 내부 따옴표/엔터 이스케이프 보정
    let sanitized = text
        .replace(/[“”„«»]/g, '"')
        .replace(/[‘’‚]/g, "'");

    // 문자 단위 스캐너로 문자열 내부의 미이스케이프 따옴표(\") 및 개행(\n) 보정
    let clean = '';
    let inString = false;
    let isEscaped = false;

    for (let i = 0; i < sanitized.length; i++) {
        const ch = sanitized[i];
        if (inString) {
            if (isEscaped) {
                clean += ch;
                isEscaped = false;
            } else if (ch === '\\') {
                clean += ch;
                isEscaped = true;
            } else if (ch === '"') {
                // 진정한 닫는 따옴표인지 판별
                let nextIdx = i + 1;
                while (nextIdx < sanitized.length && /\s/.test(sanitized[nextIdx])) {
                    nextIdx++;
                }
                const nextCh = sanitized[nextIdx];
                if (nextIdx >= sanitized.length || [':', '}', ']', ','].includes(nextCh)) {
                    inString = false;
                    clean += '"';
                } else {
                    clean += '\\"'; // 문자열 내부 따옴표 이스케이프
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
                clean += '"';
            } else {
                clean += ch;
            }
        }
    }

    try {
        return JSON.parse(clean);
    } catch (e) {
        console.error("JSON parse failed after sanitizing:", e);
    }

    return null;
}

function importModalStepPayload() {
    const payload = document.getElementById('modalAiResponsePayload').value;
    const targetVal = document.getElementById('modalInsertTargetStep').value;
    importStepPayloadCommon(payload, targetVal);
}

function importPageStepPayload() {
    const payload = document.getElementById('pageAiResponsePayload').value;
    const targetVal = document.getElementById('pageInsertTargetStep').value;
    importStepPayloadCommon(payload, targetVal);
}

function importStepPayloadCommon(payload, targetVal) {
    const newSteps = parseJsonFromText(payload);
    if (!newSteps || !Array.isArray(newSteps)) {
        alert('올바른 JSON 배열 형식이 아닙니다. AI 답변을 확인해주세요.');
        return;
    }

    const container = document.getElementById('editStepsContainer');
    const cards = Array.from(container.querySelectorAll('.edit-step-card'));

    let insertIndex = cards.length;
    if (targetVal !== 'last') {
        const afterNo = parseInt(targetVal, 10);
        if (afterNo >= 1 && afterNo <= cards.length) {
            insertIndex = afterNo;
        }
    }

    const newCards = newSteps.map(step => createStepCardElement(step, 1));
    cards.splice(insertIndex, 0, ...newCards);
    cards.forEach(c => container.appendChild(c));
    updateStepNumbers();

    alert(`${newSteps.length}개의 새로운 단계가 성공적으로 삽입되었습니다.`);
}

function extractManualFormData() {
    const manualId = document.getElementById('editManualId').value;
    const progName = document.getElementById('editProgramName').value.trim();
    const manualTitle = document.getElementById('editManualTitle').value.trim();
    const description = document.getElementById('editDescription').value.trim();
    const duration = document.getElementById('editDuration').value.trim();
    const difficulty = document.getElementById('editDifficulty').value;
    const targetUsers = document.getElementById('editTargetUsers').value.trim();
    const prerequisites = document.getElementById('editPrerequisites').value.trim();

    const cards = document.querySelectorAll('#editStepsContainer .edit-step-card');
    const steps = [];

    cards.forEach((card, index) => {
        const title = card.querySelector('.edit-step-title').value.trim();
        const content = card.querySelector('.edit-step-content').value.trim();
        const code = card.querySelector('.edit-step-code').value.trim();
        const tip = card.querySelector('.edit-step-tip').value.trim();
        const warning = card.querySelector('.edit-step-warning').value.trim();
        const numInput = card.querySelector('.step-num-input');
        const stepNo = numInput ? parseInt(numInput.value, 10) : (index + 1);

        if (title || content) {
            steps.push({
                step_no: stepNo,
                title: title,
                content: content,
                code: code,
                tip: tip,
                warning: warning
            });
        }
    });

    return { manualId, progName, manualTitle, description, duration, difficulty, targetUsers, prerequisites, steps };
}

function saveManualEdit() {
    const data = extractManualFormData();
    saveManualDataToStorage(data);
    closeEditModal();
    StudyBoxApp.initIndexPage();
}

function saveManualEditPage() {
    const data = extractManualFormData();
    saveManualDataToStorage(data);
    window.location.href = `index.html?id=${encodeURIComponent(data.manualId)}`;
}

async function saveManualDataToStorage(data) {
    const hasPerm = await StudyBoxApp.checkAdminPermission();
    if (!hasPerm) return;
    let targetProg = null;
    let targetManual = null;

    for (const p of StudyBoxApp.programs) {
        for (const m of (p.manuals || [])) {
            if (m.id === data.manualId) {
                targetManual = m;
                targetProg = p;
                break;
            }
        }
        if (targetManual) break;
    }

    if (targetManual) {
        targetManual.title = data.manualTitle;
        targetManual.description = data.description;
        targetManual.duration = data.duration;
        targetManual.difficulty = data.difficulty;
        targetManual.target_users = data.targetUsers;
        targetManual.prerequisites = data.prerequisites;
        targetManual.steps = data.steps;

        if (targetProg && data.progName && targetProg.name !== data.progName) {
            targetProg.name = data.progName;
        }
        StudyBoxApp.saveData();
        alert('저장되었습니다.');
    }
}

function generateNewManualPrompt() {
    const catId = document.getElementById('newCatId').value;
    const progName = document.getElementById('newProgName').value.trim();
    const manualTitle = document.getElementById('newManualTitle').value.trim();
    const detail = document.getElementById('newManualReq').value.trim();

    if (!progName || !manualTitle) {
        alert('프로그램 이름과 매뉴얼 주제를 입력해 주세요.');
        return;
    }

    const detailLine = detail ? `\n[추가 요청사항]: ${detail}\n` : '';

    const promptText = `너는 초보자를 위한 IT 및 프로그램 매뉴얼 전문 작성자야.
[프로그램명]: ${progName}
[매뉴얼 주제]: ${manualTitle}
${detailLine}
위 주제에 대해 초보자가 한 단계씩 순서대로 쉽게 따라할 수 있도록 자세한 매뉴얼 데이터를 JSON 형식으로 작성해줘.

[출력 및 시스템 저장 규칙 - 필독]:
- 이 시스템은 데이터베이스에 JSON 저장방식으로 코딩되어 있으므로, 반드시 인사말이나 부연 설명 없이 오직 아래 JSON 객체 규격 형식으로만 답변해야 합니다.

[JSON 출력 형식 예시]:
{
  "id": "manual_${Date.now()}",
  "category": "${catId}",
  "title": "${manualTitle}",
  "description": "한 줄 요약 설명",
  "duration": "15분",
  "difficulty": "★☆☆☆☆ (입문)",
  "target_users": "초보자 누구나",
  "prerequisites": "기본 환경 준비",
  "steps": [
    {
      "step_no": 1,
      "title": "첫 번째 단계 제목",
      "content": "상세한 실행 및 안내 설명",
      "code": "필요시 복사할 명령어",
      "tip": "초보자 팁",
      "warning": "주의사항"
    }
  ]
}`;

    document.getElementById('newPromptCodeText').innerText = promptText;
    document.getElementById('newPromptWrapper').style.display = 'block';
}

function copyNewManualPrompt() {
    const text = document.getElementById('newPromptCodeText').innerText;
    navigator.clipboard.writeText(text).then(() => {
        const btn = document.getElementById('btnCopyNewPrompt');
        btn.innerText = '복사 완료! ✓';
        setTimeout(() => btn.innerText = '프롬프트 복사하기', 2000);
    });
}

async function saveNewManualFromAi() {
    const hasPerm = await StudyBoxApp.checkAdminPermission();
    if (!hasPerm) return;
    const catId = document.getElementById('newCatId').value;
    const progName = document.getElementById('newProgName').value.trim();
    const jsonText = document.getElementById('newAiResponseJson').value;

    let manualData = parseJsonFromText(jsonText);
    if (!manualData || typeof manualData !== 'object') {
        alert('올바른 JSON 객체 형식이 아닙니다. AI 답변 코드를 확인해 주세요.');
        return;
    }

    if (!manualData.id) manualData.id = 'm_' + Date.now();
    if (!manualData.category) manualData.category = catId;

    let targetProg = StudyBoxApp.programs.find(p => p.name === progName);
    if (!targetProg) {
        targetProg = {
            id: 'prog_' + Date.now(),
            name: progName,
            category: catId,
            manuals: []
        };
        StudyBoxApp.programs.push(targetProg);
    }

    if (!targetProg.manuals) targetProg.manuals = [];
    targetProg.manuals.push(manualData);

    StudyBoxApp.saveData();
    alert('새 매뉴얼이 스터디 박스에 성공적으로 등록되었습니다!');
    window.location.href = `index.html?id=${encodeURIComponent(manualData.id)}`;
}

// 글로벌 검색 및 이벤트 초기화
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('sidebarTree')) {
        StudyBoxApp.initIndexPage();
    }

    const sidebarSearch = document.getElementById('sidebarSearch');
    if (sidebarSearch) {
        sidebarSearch.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            const catItems = document.querySelectorAll('.nav-cat-item');

            if (query === '') {
                catItems.forEach(cat => {
                    cat.classList.remove('open');
                    const progs = cat.querySelectorAll('.nav-prog-item');
                    progs.forEach(p => p.classList.remove('open'));
                    const activeLink = cat.querySelector('.nav-manual-link.active');
                    if (activeLink) {
                        cat.classList.add('open');
                        const parentProg = activeLink.closest('.nav-prog-item');
                        if (parentProg) parentProg.classList.add('open');
                    }
                });
                return;
            }

            catItems.forEach(cat => {
                let catMatch = false;
                const progs = cat.querySelectorAll('.nav-prog-item');

                progs.forEach(prog => {
                    let progMatch = false;
                    const progName = prog.querySelector('.nav-prog-header span').innerText.toLowerCase();
                    const manualLinks = prog.querySelectorAll('.nav-manual-link');

                    manualLinks.forEach(link => {
                        const mTitle = link.dataset.title ? link.dataset.title.toLowerCase() : '';
                        if (mTitle.includes(query) || progName.includes(query)) {
                            link.style.display = 'flex';
                            progMatch = true;
                        } else {
                            link.style.display = 'none';
                        }
                    });

                    if (progMatch) {
                        prog.style.display = 'block';
                        prog.classList.add('open');
                        catMatch = true;
                    } else {
                        prog.style.display = 'none';
                    }
                });

                if (catMatch) {
                    cat.style.display = 'block';
                    cat.classList.add('open');
                } else {
                    cat.style.display = 'none';
                }
            });
        });
    }

    document.addEventListener('click', (e) => {
        const gearBtn = e.target.closest('.btn-step-gear');
        if (gearBtn) {
            e.preventDefault();
            e.stopPropagation();
            const currentDropdown = gearBtn.closest('.step-gear-dropdown');
            document.querySelectorAll('.step-gear-dropdown.active').forEach(dropdown => {
                if (dropdown !== currentDropdown) dropdown.classList.remove('active');
            });
            if (currentDropdown) currentDropdown.classList.toggle('active');
            return;
        }

        const gearItem = e.target.closest('.step-gear-item');
        if (gearItem) {
            const dropdown = gearItem.closest('.step-gear-dropdown');
            if (dropdown) dropdown.classList.remove('active');
            return;
        }

        document.querySelectorAll('.step-gear-dropdown.active').forEach(dropdown => {
            dropdown.classList.remove('active');
        });
    });
});
