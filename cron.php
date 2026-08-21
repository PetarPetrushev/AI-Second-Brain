<?php
/**
 * cron.php — Nightly Synthesis Engine for the Second Brain.
 *
 * Run via CLI:   php cron.php
 * Run via Web:   http://localhost/cron.php?key=CRON_SECRET_KEY
 *
 * Reads today's thought entries, runs an agentic synthesis loop that
 * searches historical context, generates a structured daily digest,
 * saves it to data/digests/YYYY-MM-DD.json, and emails it via SSL SMTP.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/openrouter.php';
require_once __DIR__ . '/includes/storage.php';
require_once __DIR__ . '/includes/mailer.php';

// Determine execution context
$is_cli      = php_sapi_name() === 'cli';
$is_internal = defined('CRON_INTERNAL_EXEC') && CRON_INTERNAL_EXEC;

if (!$is_cli && !$is_internal) {
    // Web execution: extract and validate secret key
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $bearer_token = '';
    if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $m)) {
        $bearer_token = trim($m[1]);
    }

    $provided_key = trim((string)(
        $_GET['key']
        ?? $_POST['key']
        ?? $_REQUEST['key']
        ?? $_SERVER['HTTP_X_CRON_KEY']
        ?? $bearer_token
        ?? ''
    ));

    $expected_key = trim((string)get_config('CRON_SECRET_KEY', ''));

    $authorized = false;
    if ($expected_key !== '' && $provided_key !== '' && hash_equals($expected_key, $provided_key)) {
        $authorized = true;
    }

    if (!$authorized) {
        http_response_code(403);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'ok'    => false,
            'error' => 'Forbidden: Invalid or missing cron secret key.',
        ], JSON_PRETTY_PRINT);
        exit;
    }
}

// ─── Logging ──────────────────────────────────────────────────────────────────

function cron_log(string $message): void
{
    $ts   = date('[Y-m-d H:i:s]');
    $line = "$ts $message\n";
    if (php_sapi_name() === 'cli') {
        echo $line;
    }
    // Always append to log file
    $log_dir = DATA_DIR . '/logs';
    if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
    file_put_contents($log_dir . '/cron.log', $line, FILE_APPEND | LOCK_EX);
}

// ─── Load Today's Entries ─────────────────────────────────────────────────────

$today    = date('Y-m-d');
$day_data = list_thoughts_by_date($today);

cron_log("Starting nightly synthesis for $today");

if ($day_data['count'] === 0) {
    cron_log("No thoughts found for $today — synthesis skipped.");
    if (!$is_cli && !$is_internal) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'ok'      => true,
            'date'    => $today,
            'message' => "No thoughts found for $today — synthesis skipped.",
        ], JSON_PRETTY_PRINT);
    }
    if (!$is_internal) {
        exit(0);
    } else {
        return;
    }
}

cron_log("Found {$day_data['count']} thought(s) for today.");

// ─── Build Context String ─────────────────────────────────────────────────────

$today_context_parts = [];
foreach ($day_data['entries'] as $e) {
    $full = get_entry_by_id($e['id']);
    $content = $full['content'] ?? $e['preview'];
    $time    = date('H:i', strtotime($full['created_at'] ?? ''));
    $today_context_parts[] = "## Entry [{$e['id']}] at {$time}\n**Title:** {$e['title']}\n**Tags:** " . implode(', ', $e['tags']) . "\n\n{$content}";
}
$today_context = implode("\n\n---\n\n", $today_context_parts);

// ─── Synthesis Tool Definitions ───────────────────────────────────────────────

$synthesis_tools = [
    [
        'type' => 'function',
        'function' => [
            'name'        => 'search_thoughts',
            'description' => 'Search historical thought entries semantically to find related past ideas, patterns, and recurring themes.',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'query'         => ['type' => 'string',  'description' => 'Semantic search query'],
                    'max_results'   => ['type' => 'integer', 'description' => 'Max results (1-10)', 'default' => 5],
                    'recency_bias'  => ['type' => 'boolean', 'description' => 'Weight recent entries higher', 'default' => true],
                    'exclude_today' => ['type' => 'boolean', 'description' => 'Exclude today\'s entries', 'default' => true],
                ],
                'required' => ['query'],
            ],
        ],
    ],
    [
        'type' => 'function',
        'function' => [
            'name'        => 'read_thought_entry',
            'description' => 'Read a specific historical thought entry by ID.',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'entry_id' => ['type' => 'string', 'description' => 'Entry ID'],
                ],
                'required' => ['entry_id'],
            ],
        ],
    ],
    [
        'type' => 'function',
        'function' => [
            'name'        => 'list_thoughts_by_date',
            'description' => 'List all thoughts from a specific past date for deep context.',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'date' => ['type' => 'string', 'description' => 'Date in YYYY-MM-DD format'],
                ],
                'required' => ['date'],
            ],
        ],
    ],
];

// ─── Tool Executor (Cron) ─────────────────────────────────────────────────────

function cron_execute_tool(string $name, array $args): string
{
    try {
        return match ($name) {
            'search_thoughts' => (function () use ($args) {
                $query   = $args['query']   ?? '';
                $max     = min(10, max(1, (int)($args['max_results'] ?? 5)));
                $recency = (bool)($args['recency_bias']  ?? true);
                $excl    = (bool)($args['exclude_today'] ?? true);
                $vector  = openrouter_embed($query);
                $results = search_vector_index($vector, $max, $recency, $excl);
                return json_encode(['results' => $results]);
            })(),
            'read_thought_entry' => (function () use ($args) {
                return json_encode(read_entry_safe($args['entry_id'] ?? ''));
            })(),
            'list_thoughts_by_date' => (function () use ($args) {
                return json_encode(list_thoughts_by_date($args['date'] ?? date('Y-m-d')));
            })(),
            default => json_encode(['error' => "Unknown tool: $name"]),
        };
    } catch (Throwable $e) {
        return json_encode(['error' => $e->getMessage()]);
    }
}

// ─── Agentic Synthesis Loop ───────────────────────────────────────────────────

$system_prompt = <<<SYSTEM
You are a private Second Brain synthesis engine. Your job is to analyse today's thought logs and generate a comprehensive, insightful daily digest.

You have access to the thought history database and should:
1. Read through today's entries (provided in the first user message).
2. Identify the 2-4 most significant themes and concepts from today.
3. For each major concept, use search_thoughts (with exclude_today=true) to find related historical entries, then read those entries for deeper context.
4. Identify recurring ideas, evolving themes, and conceptual evolution over time.

Today's date: $today

Generate your final synthesis as a well-structured HTML report with the following sections (use <h2> tags for section headings):

<h2>Executive Summary</h2>
<p>A 2-4 sentence narrative summary of today's intellectual output and its significance.</p>

<h2>Key Ideas & Insights</h2>
<ul><li>...</li></ul>

<h2>Idea Evolution & Historical Echoes</h2>
<p>Connect today's thoughts to specific past entries with dates. Be explicit about how ideas have grown, changed, or deepened.</p>

<h2>Action Items & Open Questions</h2>
<ul><li>...</li></ul>

<h2>Mental & Conceptual Patterns</h2>
<p>Reflect on recurring themes, cognitive habits, or meta-patterns visible in today's thinking.</p>

Write in first person (e.g. "You explored..."). Be specific. Cite entry dates when referencing historical connections.
SYSTEM;

$messages = [
    ['role' => 'system', 'content' => $system_prompt],
    ['role' => 'user',   'content' => "Here are today's thought entries for $today:\n\n$today_context\n\nPlease analyse these, search for related historical context, and generate the daily digest."],
];

@ini_set('max_execution_time', '0');
@set_time_limit(0);

$max_turns  = 4;
$turns      = 0;
$tool_log   = [];
$final_html = '';

while ($turns < $max_turns) {
    @set_time_limit(300);
    $turns++;
    try {
        $is_last_turn  = ($turns >= $max_turns);
        $current_tools = $is_last_turn ? [] : $synthesis_tools;

        if ($is_last_turn) {
            $messages[] = [
                'role'    => 'user',
                'content' => "Based on all the historical context gathered and today's thoughts, please now generate the final structured HTML daily digest report.",
            ];
        }

        $resp   = openrouter_chat_completion($messages, $current_tools);
        $choice = $resp['choices'][0] ?? null;

        if (!$choice) {
            cron_log("No choice returned from OpenRouter.");
            break;
        }

        $msg    = $choice['message'];
        $reason = $choice['finish_reason'] ?? 'stop';
        $messages[] = $msg;

        if (empty($msg['tool_calls'])) {
            $final_html = $msg['content'] ?? '';
            cron_log("Synthesis complete after $turns turn(s).");
            break;
        }

        foreach ($msg['tool_calls'] as $tc) {
            $fn_name = $tc['function']['name'];
            $fn_args = json_decode($tc['function']['arguments'] ?? '{}', true) ?: [];
            $call_id = $tc['id'];

            cron_log("Tool call: $fn_name(" . json_encode($fn_args) . ")");
            $result = cron_execute_tool($fn_name, $fn_args);

            $tool_log[] = ['tool' => $fn_name, 'args' => $fn_args, 'result' => json_decode($result, true)];

            $messages[] = [
                'role'         => 'tool',
                'tool_call_id' => $call_id,
                'content'      => $result,
            ];
        }
    } catch (Throwable $e) {
        cron_log("ERROR in synthesis loop: " . $e->getMessage());
        break;
    }
}

// Strip markdown code fences if present
$final_html = preg_replace('/^```(?:html)?\s*/i', '', trim($final_html));
$final_html = preg_replace('/\s*```$/', '', $final_html);

if (!$final_html) {
    cron_log("No synthesis output generated. Aborting.");
    if (!$is_cli && !$is_internal) {
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => false, 'error' => 'No synthesis output generated.'], JSON_PRETTY_PRINT);
        exit;
    }
    if (!$is_internal) exit(1);
    return;
}

// ─── Collect and Format Sources ───────────────────────────────────────────────

$sources = [];

// 1. All today's entries
foreach ($day_data['entries'] as $e) {
    $sources[$e['id']] = [
        'id'       => $e['id'],
        'title'    => $e['title'] ?? 'Untitled Thought',
        'date'     => $e['date'] ?? $today,
        'tags'     => $e['tags'] ?? [],
        'preview'  => $e['preview'] ?? '',
        'is_today' => true,
    ];
}

// 2. Historical entries referenced via tool calls
foreach ($tool_log as $tl) {
    $tool_name = $tl['tool'];
    $res_data  = $tl['result'];
    if ($tool_name === 'search_thoughts' && !empty($res_data['results'])) {
        foreach ($res_data['results'] as $r) {
            if (!empty($r['id']) && !isset($sources[$r['id']])) {
                $sources[$r['id']] = [
                    'id'       => $r['id'],
                    'title'    => $r['title'] ?? 'Untitled Thought',
                    'date'     => $r['date'] ?? '',
                    'tags'     => $r['tags'] ?? [],
                    'preview'  => $r['preview'] ?? '',
                    'is_today' => false,
                ];
            }
        }
    } elseif ($tool_name === 'read_thought_entry' && !empty($tl['args']['entry_id'])) {
        $id = $tl['args']['entry_id'];
        if (!isset($sources[$id])) {
            $entry = get_entry_by_id($id);
            if ($entry) {
                $sources[$id] = [
                    'id'       => $id,
                    'title'    => $entry['title'] ?? 'Untitled Thought',
                    'date'     => $entry['date'] ?? '',
                    'tags'     => $entry['tags'] ?? [],
                    'preview'  => substr($entry['content'] ?? '', 0, 220),
                    'is_today' => ($entry['date'] ?? '') === $today,
                ];
            }
        }
    } elseif ($tool_name === 'list_thoughts_by_date' && !empty($res_data['entries'])) {
        foreach ($res_data['entries'] as $e) {
            if (!empty($e['id']) && !isset($sources[$e['id']])) {
                $sources[$e['id']] = [
                    'id'       => $e['id'],
                    'title'    => $e['title'] ?? 'Untitled Thought',
                    'date'     => $e['date'] ?? '',
                    'tags'     => $e['tags'] ?? [],
                    'preview'  => $e['preview'] ?? '',
                    'is_today' => ($e['date'] ?? '') === $today,
                ];
            }
        }
    }
}

// Build sources HTML section if not already present
$sources_count = count($sources);
$sources_html  = "<h2>Sources & Referenced Memories ({$sources_count})</h2>\n<div class=\"sources-container\">\n";
foreach ($sources as $src) {
    $tag_badges = '';
    if (!empty($src['tags'])) {
        foreach ($src['tags'] as $tg) {
            $tag_badges .= '<span class="tag">' . htmlspecialchars($tg) . '</span> ';
        }
    }
    $badge_class = $src['is_today'] ? 'today' : 'past';
    $badge_label = $src['is_today'] ? 'Today' : 'Historical';
    $title_clean = htmlspecialchars($src['title']);
    $date_clean  = htmlspecialchars($src['date']);
    $id_clean    = htmlspecialchars($src['id']);
    $prev_clean  = htmlspecialchars($src['preview']);

    $sources_html .= <<<SRC
  <div class="source-card">
    <div class="source-header">
      <span class="source-title">{$title_clean}</span>
      <span class="source-badge {$badge_class}">{$badge_label}</span>
      <span class="source-date">{$date_clean}</span>
    </div>
    <div class="source-snippet">{$prev_clean}</div>
    <div class="source-meta">{$tag_badges} <span class="source-id">ID: {$id_clean}</span></div>
  </div>
SRC;
}
$sources_html .= "</div>\n";

if (!str_contains($final_html, '<h2>Sources') && !str_contains($final_html, '<h2>Referenced')) {
    $final_html .= "\n" . $sources_html;
}

// ─── Save Digest ──────────────────────────────────────────────────────────────

$digest_data = [
    'title'       => "Daily Digest — $today",
    'html'        => $final_html,
    'entry_count' => $day_data['count'],
    'tool_calls'  => count($tool_log),
    'sources'     => array_values($sources),
];

save_digest($today, $digest_data);
cron_log("Digest saved to data/digests/$today.json");

// ─── Send Email ───────────────────────────────────────────────────────────────

$email_html = build_digest_email($today, $final_html);
$to         = get_config('SMTP_TO');
$subject    = "🧠 Second Brain Digest — " . date('l, F j', strtotime($today));

try {
    send_smtp_ssl($to, $subject, $email_html);
    cron_log("Digest email sent to $to successfully.");
} catch (Throwable $e) {
    cron_log("EMAIL FAILED: " . $e->getMessage());
    // Log failure to digest data without overwriting the digest itself
    $fail_path = DATA_DIR . '/logs/email_failures.log';
    file_put_contents(
        $fail_path,
        date('[Y-m-d H:i:s]') . " [FAIL] $today: " . $e->getMessage() . "\n",
        FILE_APPEND | LOCK_EX
    );
}

cron_log("Cron complete for $today.");

if (!$is_cli && !$is_internal) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'ok'          => true,
        'date'        => $today,
        'entry_count' => $day_data['count'],
        'digest'      => 'saved',
        'sources'     => count($sources),
        'tool_calls'  => count($tool_log),
    ], JSON_PRETTY_PRINT);
}

if (!$is_internal) {
    exit(0);
}
