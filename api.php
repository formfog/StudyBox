<?php
header('Content-Type: application/json; charset=utf-8');

// 대용량 단계(24단계, 50단계, 100단계 이상) 제한 없는 처리를 위한 PHP 환경 설정 확장
@ini_set('memory_limit', '512M');
@ini_set('max_execution_time', '300');
@ini_set('pcre.backtrack_limit', '10000000');

$categoriesFile = __DIR__ . '/data/categories.json';
$programsFile = __DIR__ . '/data/programs.json';

function getJsonData($filepath) {
    if (!file_exists($filepath)) return [];
    $json = file_get_contents($filepath);
    return json_decode($json, true) ?? [];
}

function saveJsonData($filepath, $data) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($filepath, $json);
}

function extractJson($text) {
    if (empty($text)) return null;
    $text = trim($text);

    // 1. BOM 및 제어 공백 제거
    $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
    $text = str_replace(["\u{200B}", "\u{200C}", "\u{200D}", "\u{FEFF}"], '', $text);
    $text = str_replace("\u{00A0}", ' ', $text);

    // 2. 스마트 따옴표를 표준 따옴표로 변환
    $text = str_replace(['“', '”', '„', '«', '»'], '"', $text);
    $text = str_replace(['‘', '’', '‚'], "'", $text);

    // 3. 마크다운 코드 블록(```json ... ``` 또는 닫는 기호가 누락된 경우) 추출
    if (preg_match('/```(?:json)?\s*([\s\S]*?)(?:```|$)/i', $text, $matches)) {
        $text = trim($matches[1]);
    }

    // 4. JSON 시작점({ 또는 [) 찾기
    $posObj = strpos($text, '{');
    $posArr = strpos($text, '[');

    if ($posObj === false && $posArr === false) {
        return null;
    }

    $startPos = 0;
    if ($posObj !== false && $posArr !== false) {
        $startPos = min($posObj, $posArr);
    } elseif ($posObj !== false) {
        $startPos = $posObj;
    } else {
        $startPos = $posArr;
    }

    $text = substr($text, $startPos);

    // 5. 문자 단위 고급 정제 스캐너
    // - 따옴표 밖의 주석(//, /* */)만 안전 제거 (문자열 내부의 // WIP 등 완벽 보존)
    // - 따옴표 안의 실제 엔터/탭 이스케이프 (\n, \t)
    // - 따옴표 안의 잘못된 이스케이프(\P 등) 안전 보정
    // - 따옴표 안의 이스케이프 누락된 내부 따옴표(예: <meta charset="UTF-8">) 자동 이스케이프
    $len = strlen($text);
    $clean = '';
    $inString = false;
    $quoteChar = '"';
    $isEscaped = false;
    $validEscapes = ['"', '\\', '/', 'b', 'f', 'n', 'r', 't', 'u'];

    for ($i = 0; $i < $len; $i++) {
        $ch = $text[$i];

        if ($inString) {
            if ($isEscaped) {
                if (!in_array($ch, $validEscapes)) {
                    $clean .= "\\\\" . $ch;
                } else {
                    $clean .= "\\" . $ch;
                }
                $isEscaped = false;
            } elseif ($ch === '\\') {
                $isEscaped = true;
            } elseif ($ch === $quoteChar) {
                // 이것이 진정한 닫는 따옴표인지, 문자열 내부의 미이스케이프 따옴표인지 판별
                $nextIdx = $i + 1;
                while ($nextIdx < $len && ctype_space($text[$nextIdx])) {
                    $nextIdx++;
                }

                $isRealClose = false;
                if ($nextIdx >= $len) {
                    $isRealClose = true;
                } else {
                    $nextChar = $text[$nextIdx];
                    if ($nextChar === ':' || $nextChar === '}' || $nextChar === ']') {
                        $isRealClose = true;
                    } elseif ($nextChar === ',') {
                        $afterCommaIdx = $nextIdx + 1;
                        while ($afterCommaIdx < $len && ctype_space($text[$afterCommaIdx])) {
                            $afterCommaIdx++;
                        }
                        if ($afterCommaIdx < $len) {
                            $afterCommaChar = $text[$afterCommaIdx];
                            if ($afterCommaChar === '"' || $afterCommaChar === '}' || $afterCommaChar === ']' || $afterCommaChar === '{') {
                                $isRealClose = true;
                            }
                        } else {
                            $isRealClose = true;
                        }
                    }
                }

                if ($isRealClose) {
                    $inString = false;
                    $clean .= '"';
                } else {
                    // 문자열 내부의 따옴표 자동 이스케이프
                    $clean .= '\\"';
                }
            } elseif ($ch === "\n") {
                $clean .= "\\n";
            } elseif ($ch === "\r") {
                $clean .= "\\r";
            } elseif ($ch === "\t") {
                $clean .= "\\t";
            } else {
                $clean .= $ch;
            }
        } else {
            // 따옴표 밖
            if ($ch === '"') {
                $inString = true;
                $quoteChar = '"';
                $isEscaped = false;
                $clean .= '"';
            } elseif ($ch === "'") {
                $inString = true;
                $quoteChar = "'";
                $isEscaped = false;
                $clean .= '"';
            } elseif ($ch === '/' && $i + 1 < $len && $text[$i + 1] === '/') {
                // 한 줄 주석 건너뛰기
                while ($i < $len && $text[$i] !== "\n" && $text[$i] !== "\r") {
                    $i++;
                }
                $clean .= "\n";
            } elseif ($ch === '/' && $i + 1 < $len && $text[$i + 1] === '*') {
                // 블록 주석 건너뛰기
                $i += 2;
                while ($i + 1 < $len && !($text[$i] === '*' && $text[$i + 1] === '/')) {
                    $i++;
                }
                $i++;
            } else {
                $clean .= $ch;
            }
        }
    }

    if ($isEscaped) {
        $clean .= '\\\\';
    }
    if ($inString) {
        $clean .= '"';
    }

    // 6. 트레일링 콤마 제거
    $clean = preg_replace('/,\s*([}\]])/', '$1', $clean);

    // 1차 디코드 시도
    $decoded = json_decode($clean, true);
    if ($decoded !== null) return $decoded;

    // 8. 괄호 스택 추적 헬퍼
    $getCloser = function($str) {
        $stack = [];
        $len = strlen($str);
        $inStr = false;
        $esc = false;

        for ($j = 0; $j < $len; $j++) {
            $c = $str[$j];
            if ($inStr) {
                if ($esc) {
                    $esc = false;
                } elseif ($c === '\\') {
                    $esc = true;
                } elseif ($c === '"') {
                    $inStr = false;
                }
            } else {
                if ($c === '"') {
                    $inStr = true;
                } elseif ($c === '{' || $c === '[') {
                    $stack[] = $c;
                } elseif ($c === '}') {
                    if (!empty($stack) && end($stack) === '{') array_pop($stack);
                } elseif ($c === ']') {
                    if (!empty($stack) && end($stack) === '[') array_pop($stack);
                }
            }
        }

        $closer = '';
        while (!empty($stack)) {
            $open = array_pop($stack);
            if ($open === '{') $closer .= '}';
            if ($open === '[') $closer .= ']';
        }
        return $closer;
    };

    // 9-1. 열린 괄호 스택을 닫아 즉시 복구 시도
    $closer = $getCloser($clean);
    if (!empty($closer)) {
        $candidate = preg_replace('/,\s*([}\]])/', '$1', $clean . $closer);
        $decoded = json_decode($candidate, true);
        if ($decoded !== null) return $decoded;
    }

    // 9-2. 마지막 완성된 중괄호(})까지만 자른 후 스택 닫기 시도 (토큰 제한으로 중간에 잘린 불완전 필드 수습)
    $lastBrace = strrpos($clean, '}');
    if ($lastBrace !== false) {
        $sub = substr($clean, 0, $lastBrace + 1);
        $subCloser = $getCloser($sub);
        $candidate = preg_replace('/,\s*([}\]])/', '$1', $sub . $subCloser);
        $decoded = json_decode($candidate, true);
        if ($decoded !== null) return $decoded;
    }

    // 10. 정규식 폴백: steps 배열 내 개별 Step 객체들을 직접 스캔하여 복원
    $stepRegex = '/\{[^{}]*"(?:title|content)"[^{}]*\}/s';
    if (preg_match_all($stepRegex, $clean, $matches)) {
        $extractedSteps = [];
        foreach ($matches[0] as $objStr) {
            $item = json_decode($objStr, true);
            if ($item && (isset($item['title']) || isset($item['content']))) {
                $extractedSteps[] = $item;
            }
        }
        if (!empty($extractedSteps)) {
            return [
                'category' => 'dev-env',
                'title' => '자동 복구된 매뉴얼',
                'steps' => $extractedSteps
            ];
        }
    }

    return null;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_categories':
        echo json_encode(getJsonData($categoriesFile));
        break;

    case 'get_programs':
        echo json_encode(getJsonData($programsFile));
        break;

    case 'parse_steps_payload':
        // AI 답변 텍스트에서 Step 목록 파싱 및 반환
        $payloadRaw = $_POST['payload'] ?? '';
        $decoded = extractJson($payloadRaw);
        if (!$decoded) {
            echo json_encode(['success' => false, 'message' => '올바른 JSON 형식이 아닙니다. AI의 답변 코드를 전체 복사했는지 확인해주세요.']);
            exit;
        }

        // steps 키가 있는 객체일 경우 배열로 추출
        $steps = [];
        if (is_array($decoded)) {
            if (isset($decoded['steps']) && is_array($decoded['steps'])) {
                $steps = $decoded['steps'];
            } elseif (isset($decoded[0]) && is_array($decoded[0])) {
                $steps = $decoded;
            } elseif (isset($decoded['title']) || isset($decoded['content'])) {
                // 단일 step 객체일 경우
                $steps = [$decoded];
            }
        }

        if (empty($steps)) {
            echo json_encode(['success' => false, 'message' => '유효한 단계(Step) 데이터를 찾을 수 없습니다.']);
            exit;
        }

        echo json_encode(['success' => true, 'steps' => $steps]);
        break;

    case 'save_manual_payload':
        // AI가 생성한 payload JSON을 받아 데이터베이스에 바로 등록/수정
        $payloadRaw = $_POST['payload'] ?? '';
        if (empty($payloadRaw)) {
            echo json_encode(['success' => false, 'message' => '전송된 데이터가 없습니다.']);
            exit;
        }

        $decoded = extractJson($payloadRaw);
        if (!$decoded || !is_array($decoded)) {
            echo json_encode(['success' => false, 'message' => '올바른 JSON 형식이 아닙니다. 코드 복사를 다시 확인해주세요.']);
            exit;
        }

        $programs = getJsonData($programsFile);

        $category = $decoded['category'] ?? 'dev-env';
        $progName = $decoded['program_name'] ?? '미지정 프로그램';

        $manual = [
            'id' => 'manual-' . time(),
            'title' => $decoded['title'] ?? '새 매뉴얼',
            'description' => $decoded['description'] ?? '',
            'difficulty' => $decoded['difficulty'] ?? '★☆☆☆☆ (입문)',
            'duration' => $decoded['duration'] ?? '10분',
            'target_users' => $decoded['target_users'] ?? '모든 초보자',
            'prerequisites' => $decoded['prerequisites'] ?? '기본 PC 사용 환경',
            'steps' => $decoded['steps'] ?? []
        ];

        // 기존 프로그램 탐색 및 업데이트
        $foundProg = false;
        foreach ($programs as &$p) {
            if (mb_strtolower($p['name']) === mb_strtolower($progName) || $p['id'] === ($decoded['program_id'] ?? '')) {
                $foundProg = true;
                $p['manuals'][] = $manual;
                break;
            }
        }

        // 프로그램이 없으면 신규 생성
        if (!$foundProg) {
            $programs[] = [
                'id' => 'prog-' . time(),
                'category' => $category,
                'name' => $progName,
                'manuals' => [$manual]
            ];
        }

        saveJsonData($programsFile, $programs);
        echo json_encode(['success' => true, 'manual_id' => $manual['id']]);
        break;

    case 'delete_manual':
        $manualId = $_POST['manual_id'] ?? '';
        $programs = getJsonData($programsFile);
        
        foreach ($programs as &$p) {
            $p['manuals'] = array_values(array_filter($p['manuals'], function($m) use ($manualId) {
                return $m['id'] !== $manualId;
            }));
        }

        saveJsonData($programsFile, $programs);
        echo json_encode(['success' => true]);
        break;

    case 'generate_gemini_manual':
        $category = $_POST['category'] ?? 'dev-env';
        $progName = trim($_POST['program_name'] ?? '');
        $title = trim($_POST['title'] ?? '');

        if (empty($progName) || empty($title)) {
            echo json_encode(['success' => false, 'message' => '프로그램 명과 매뉴얼 제목을 입력해주세요.']);
            exit;
        }

        $apiKey = 'YOUR_API_KEY';
        
        $prompt = "너는 완전 초보자를 위한 프로그램 매뉴얼 작성 전문가야.
다음 정보에 맞춰 초보자의 눈높이에서 친절하고 명확한 Step-by-Step 가이드를 작성해줘.

[프로그램 명]: {$progName}
[매뉴얼 제목]: {$title}
[카테고리 ID]: {$category}

[출력 및 시스템 저장 규칙 - 필독]:
- 이 시스템은 데이터베이스에 JSON 저장방식으로 코딩되어 자동 등록되므로, 인사말이나 부연 설명 없이 오직 아래 JSON 규격 형식 그대로만 답변해야 합니다.
- steps 배열의 각 단계는 누락 없이 알차고 구체적으로 작성해주세요.

반드시 아래 JSON 규격 형식 그대로만 답변해줘:

{
  \"category\": \"{$category}\",
  \"program_name\": \"{$progName}\",
  \"title\": \"{$title}\",
  \"description\": \"초보자가 이 매뉴얼을 다 읽었을 때 달성할 수 있는 결과 한 줄 요약\",
  \"difficulty\": \"★☆☆☆☆ (입문)\",
  \"duration\": \"10분\",
  \"target_users\": \"추천 대상 작성\",
  \"prerequisites\": \"준비물 작성\",
  \"steps\": [
    {
      \"step_no\": 1,
      \"title\": \"첫 번째 단계 제목\",
      \"content\": \"클릭할 위치와 동작을 초보자 눈높이에서 친절하게 설명\",
      \"code\": \"복사할 명령어나 주소 (없으면 null)\",
      \"tip\": \"초보자를 위한 꿀팁 (없으면 null)\",
      \"warning\": \"주의사항 (없으면 null)\"
    },
    {
      \"step_no\": 2,
      \"title\": \"두 번째 단계 제목\",
      \"content\": \"상세한 실행 가이드 설명\",
      \"code\": null,
      \"tip\": null,
      \"warning\": null
    }
  ]
}";

        $models = ['gemini-2.5-flash-lite', 'gemini-2.0-flash-lite', 'gemini-1.5-flash', 'gemini-2.0-flash'];
        $rawResult = null;
        $lastError = '';

        foreach ($models as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
            
            $payloadData = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                    'maxOutputTokens' => 8192
                ]
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payloadData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);

            if ($httpCode === 200 && !empty($response)) {
                $resArr = json_decode($response, true);
                if (isset($resArr['candidates'][0]['content']['parts'][0]['text'])) {
                    $rawResult = $resArr['candidates'][0]['content']['parts'][0]['text'];
                    break;
                }
            } else {
                $lastError = "HTTP {$httpCode}: " . ($response ?: $curlErr);
            }
        }

        if (!$rawResult) {
            echo json_encode(['success' => false, 'message' => 'Gemini API 호출 실패: ' . $lastError]);
            exit;
        }

        $decoded = extractJson($rawResult);
        if (!$decoded || !is_array($decoded)) {
            echo json_encode(['success' => false, 'message' => 'Gemini가 응답한 JSON 파싱 실패', 'raw' => $rawResult]);
            exit;
        }

        $programs = getJsonData($programsFile);
        $manual = [
            'id' => 'manual-' . time(),
            'title' => $decoded['title'] ?? $title,
            'description' => $decoded['description'] ?? '',
            'difficulty' => $decoded['difficulty'] ?? '★☆☆☆☆ (입문)',
            'duration' => $decoded['duration'] ?? '10분',
            'target_users' => $decoded['target_users'] ?? '모든 초보자',
            'prerequisites' => $decoded['prerequisites'] ?? '기본 PC 사용 환경',
            'steps' => $decoded['steps'] ?? []
        ];

        $foundProg = false;
        foreach ($programs as &$p) {
            if (mb_strtolower($p['name']) === mb_strtolower($progName) || $p['id'] === ($decoded['program_id'] ?? '')) {
                $foundProg = true;
                $p['manuals'][] = $manual;
                break;
            }
        }

        if (!$foundProg) {
            $programs[] = [
                'id' => 'prog-' . time(),
                'category' => $category,
                'name' => $progName,
                'manuals' => [$manual]
            ];
        }

        saveJsonData($programsFile, $programs);
        echo json_encode(['success' => true, 'manual_id' => $manual['id'], 'payload' => json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)]);
        break;

    case 'generate_gemini_steps':
        // 기존 매뉴얼에 추가할 단계 생성
        $progName = trim($_POST['program_name'] ?? '프로그램');
        $title = trim($_POST['manual_title'] ?? '매뉴얼');
        $detail = trim($_POST['detail'] ?? '추가 필수 단계');
        $startStepNo = intval($_POST['start_step_no'] ?? 1);
        if ($startStepNo < 1) $startStepNo = 1;

        $apiKey = 'YOUR_API_KEY';

        $prompt = "너는 프로그램 매뉴얼 작성 전문가야.
기존에 작성된 [프로그램명]: {$progName}, [매뉴얼 제목]: {$title} 가이드에
새로운 단계(Step)를 추가하려고 해.
현재 시작 단계 번호는 Step {$startStepNo}번이야.

[추가 요청 설명 및 작업 내용]:
{$detail}

[출력 및 시스템 저장 규칙 - 필독]:
- 이 시스템은 데이터베이스에 JSON 저장방식으로 코딩되어 있으므로, 반드시 다른 부연 설명이나 인사말 없이 오직 아래 JSON 배열 규격 형식으로만 답변해야 합니다.
- 단계 번호는 Step {$startStepNo}번부터 순차적으로 부여해주세요.

반드시 아래 JSON 배열 규격 그대로만 답변해줘:
[
  {
    \"step_no\": {$startStepNo},
    \"title\": \"추가할 단계 제목\",
    \"content\": \"클릭할 위치와 실행 과정을 초보자 눈높이에서 친절하게 설명\",
    \"code\": \"복사할 명령어나 주소 (없으면 null)\",
    \"tip\": \"초보자를 위한 꿀팁 (없으면 null)\",
    \"warning\": \"주의사항 (없으면 null)\"
  }
]";

        $models = ['gemini-2.5-flash-lite', 'gemini-2.0-flash-lite', 'gemini-1.5-flash', 'gemini-2.0-flash'];
        $rawResult = null;
        $lastError = '';

        foreach ($models as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
            
            $payloadData = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                    'maxOutputTokens' => 8192
                ]
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payloadData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);

            if ($httpCode === 200 && !empty($response)) {
                $resArr = json_decode($response, true);
                if (isset($resArr['candidates'][0]['content']['parts'][0]['text'])) {
                    $rawResult = $resArr['candidates'][0]['content']['parts'][0]['text'];
                    break;
                }
            } else {
                $lastError = "HTTP {$httpCode}: " . ($response ?: $curlErr);
            }
        }

        if (!$rawResult) {
            echo json_encode(['success' => false, 'message' => 'Gemini API 호출 실패: ' . $lastError]);
            exit;
        }

        $decoded = extractJson($rawResult);
        $steps = [];
        if (is_array($decoded)) {
            if (isset($decoded['steps']) && is_array($decoded['steps'])) {
                $steps = $decoded['steps'];
            } elseif (isset($decoded[0]) && is_array($decoded[0])) {
                $steps = $decoded;
            } elseif (isset($decoded['title']) || isset($decoded['content'])) {
                $steps = [$decoded];
            }
        }

        if (empty($steps)) {
            echo json_encode(['success' => false, 'message' => 'Gemini 응답에서 유효한 단계 데이터를 추출하지 못했습니다.', 'raw' => $rawResult]);
            exit;
        }

        echo json_encode(['success' => true, 'steps' => $steps]);
        break;

    case 'get_manual':
        $manualId = $_GET['manual_id'] ?? $_POST['manual_id'] ?? '';
        $programs = getJsonData($programsFile);
        $foundManual = null;
        $foundProg = null;

        foreach ($programs as $p) {
            foreach ($p['manuals'] as $m) {
                if ($m['id'] === $manualId) {
                    $foundManual = $m;
                    $foundProg = $p;
                    break 2;
                }
            }
        }

        if ($foundManual) {
            echo json_encode([
                'success' => true,
                'manual' => $foundManual,
                'program' => [
                    'id' => $foundProg['id'],
                    'name' => $foundProg['name'],
                    'category' => $foundProg['category']
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => '매뉴얼을 찾을 수 없습니다.']);
        }
        break;

    case 'update_manual':
        $manualId = $_POST['manual_id'] ?? '';
        $manualDataRaw = $_POST['manual_data'] ?? '';

        if (empty($manualId) || empty($manualDataRaw)) {
            echo json_encode(['success' => false, 'message' => '전송된 데이터가 올바르지 않습니다.']);
            exit;
        }

        $manualData = json_decode($manualDataRaw, true);
        if (!$manualData) {
            $manualData = json_decode(stripslashes($manualDataRaw), true);
        }
        if (!$manualData) {
            echo json_encode(['success' => false, 'message' => 'JSON 데이터 파싱에 실패했습니다. (전송 데이터 크기 또는 특수문자 오류)']);
            exit;
        }

        $programs = getJsonData($programsFile);
        $updated = false;

        foreach ($programs as &$p) {
            foreach ($p['manuals'] as &$m) {
                if ($m['id'] === $manualId) {
                    $m['title'] = $manualData['title'] ?? $m['title'];
                    $m['description'] = $manualData['description'] ?? $m['description'];
                    $m['difficulty'] = $manualData['difficulty'] ?? $m['difficulty'];
                    $m['duration'] = $manualData['duration'] ?? $m['duration'];
                    $m['target_users'] = $manualData['target_users'] ?? $m['target_users'];
                    $m['prerequisites'] = $manualData['prerequisites'] ?? $m['prerequisites'];
                    $m['steps'] = $manualData['steps'] ?? $m['steps'];

                    if (!empty($manualData['program_name'])) {
                        $p['name'] = $manualData['program_name'];
                    }

                    $updated = true;
                    break 2;
                }
            }
        }

        if ($updated) {
            saveJsonData($programsFile, $programs);
            echo json_encode(['success' => true, 'manual_id' => $manualId]);
        } else {
            echo json_encode(['success' => false, 'message' => '해당 매뉴얼을 찾을 수 없습니다.']);
        }
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}
