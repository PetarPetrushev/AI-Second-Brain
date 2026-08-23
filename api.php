<?php
/**
 * api.php — Backend REST/JSON API router for the Second Brain Engine.
 * All responses are JSON. POST body or GET params supply `action`.
 */

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/openrouter.php';
require_once __DIR__ . '/includes/storage.php';
require_once __DIR__ . '/includes/mailer.php';

// ─── Request Parsing ──────────────────────────────────────────────────────────

$method  = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$raw     = file_get_contents('php://input');
$body    = ($raw && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json'))
    ? (json_decode($raw, true) ?? [])
    : [];

$action  = $body['action'] ?? $_GET['action'] ?? '';

// ─── Response Helpers ─────────────────────────────────────────────────────────

function api_ok(mixed $data): never
{
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

function api_err(string $message, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

// ─── Tool Definitions ─────────────────────────────────────────────────────────

function get_agent_tools(): array
{
    return [
        [
            'type' => 'function',
            'function' => [
                'name'        => 'search_thoughts',
                'description' => 'Search your thought database using semantic similarity. Use this to find related memories and past ideas.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'query'         => ['type' => 'string',  'description' => 'The search query text'],
                        'max_results'   => ['type' => 'integer', 'description' => 'Max results to return (1-20)', 'default' => 5],
                        'recency_bias'  => ['type' => 'boolean', 'description' => 'Weight recent entries higher', 'default' => true],
                        'exclude_today' => ['type' => 'boolean', 'description' => 'Exclude today\'s entries from results', 'default' => false],
                    ],
                    'required' => ['query'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name'        => 'read_thought_entry',
                'description' => 'Read the full content of a specific thought entry by its ID. Use read_entry_safe behaviour — large entries return a preview.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'entry_id' => ['type' => 'string', 'description' => 'The unique entry ID (e.g. entry_20240821_abc123)'],
                    ],
                    'required' => ['entry_id'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name'        => 'search_inside_entry',
                'description' => 'Search for a keyword or phrase within a specific large entry, returning matching lines with surrounding context.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'entry_id'      => ['type' => 'string',  'description' => 'Entry ID to search inside'],
                        'query'         => ['type' => 'string',  'description' => 'Keyword or phrase to search for'],
                        'context_lines' => ['type' => 'integer', 'description' => 'Lines of context around each match (default 2)', 'default' => 2],
                    ],
                    'required' => ['entry_id', 'query'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name'        => 'read_entry_lines',
                'description' => 'Read an exact line range from a specific thought entry (max 100 lines per call).',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'entry_id'   => ['type' => 'string',  'description' => 'Entry ID'],
                        'start_line' => ['type' => 'integer', 'description' => '1-indexed start line'],
                        'end_line'   => ['type' => 'integer', 'description' => '1-indexed end line (inclusive, max start+99)'],
                    ],
                    'required' => ['entry_id', 'start_line', 'end_line'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name'        => 'list_thoughts_by_date',
                'description' => 'List all thought entries recorded on a specific date.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'date' => ['type' => 'string', 'description' => 'Date in YYYY-MM-DD format'],
                    ],
                    'required' => ['date'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name'        => 'save_new_thought',
                'description' => 'Save a new thought entry to the database. Only call this if the user explicitly asks to save something.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'content' => ['type' => 'string', 'description' => 'The full thought content to save'],
                    ],
                    'required' => ['content'],
                ],
            ],
        ],
    ];
}

// ─── Tool Executor ────────────────────────────────────────────────────────────

function execute_tool(string $name, array $args): string
{
    try {
        return match ($name) {
            'search_thoughts' => (function () use ($args) {
                $query   = $args['query'] ?? '';
                $max     = min(20, max(1, (int)($args['max_results'] ?? 5)));
                $recency = (bool)($args['recency_bias']  ?? true);
                $excl    = (bool)($args['exclude_today'] ?? false);
                $vector  = openrouter_embed($query);
                $results = search_vector_index($vector, $max, $recency, $excl);
                return json_encode(['results' => $results, 'count' => count($results)]);
            })(),

            'read_thought_entry' => (function () use ($args) {
                $id = $args['entry_id'] ?? '';
                return json_encode(read_entry_safe($id));
            })(),

            'search_inside_entry' => (function () use ($args) {
                $id      = $args['entry_id']      ?? '';
                $query   = $args['query']          ?? '';
                $context = (int)($args['context_lines'] ?? 2);
                return json_encode(search_inside_entry($id, $query, $context));
            })(),

            'read_entry_lines' => (function () use ($args) {
                $id    = $args['entry_id']   ?? '';
                $start = (int)($args['start_line'] ?? 1);
                $end   = (int)($args['end_line']   ?? 20);
                return json_encode(read_entry_lines($id, $start, $end));
            })(),

            'list_thoughts_by_date' => (function () use ($args) {
                $date = $args['date'] ?? date('Y-m-d');
                return json_encode(list_thoughts_by_date($date));
            })(),

            'save_new_thought' => (function () use ($args) {
                $content = $args['content'] ?? '';
                if (trim($content) === '') return json_encode(['error' => 'Content is empty.']);
                $entry = save_thought_entry($content);
                return json_encode(['saved' => true, 'entry_id' => $entry['id'], 'title' => $entry['title']]);
            })(),

            default => json_encode(['error' => "Unknown tool: $name"]),
        };
    } catch (Throwable $e) {
        return json_encode(['error' => $e->getMessage()]);
    }
}

// ─── Agentic Tool Loop ────────────────────────────────────────────────────────

/**
 * Run the agentic tool-calling loop until the model produces a final text response
 * or the max turn limit is reached.
 *
 * @param  array  $messages    Initial message array
 * @param  array  $tools       Tool definitions
 * @param  int    $max_turns   Safety cap on recursive tool rounds
 * @param  string|null $model  Override model
 * @return array{reply: string, tool_calls: array[], usage: array}
 */
function execute_agent_loop(
    array $messages,
    array $tools,
    int $max_turns = 8,
    ?string $model = null
): array {
    $tool_log = [];
    $sources  = [];
    $turns    = 0;

    while ($turns < $max_turns) {
        $resp   = openrouter_chat_completion($messages, $tools, $model);
        $choice = $resp['choices'][0] ?? null;

        if (!$choice) {
            throw new RuntimeException('OpenRouter returned no choices.');
        }

        $msg    = $choice['message'];
        $reason = $choice['finish_reason'] ?? 'stop';

        // Append assistant message to history
        $messages[] = $msg;

        // No tool calls → final answer
        if (empty($msg['tool_calls'])) {
            return [
                'reply'      => $msg['content'] ?? '',
                'tool_calls' => $tool_log,
                'sources'    => array_values($sources),
                'usage'      => $resp['usage'] ?? [],
            ];
        }

        // Execute each tool call
        foreach ($msg['tool_calls'] as $tc) {
            $fn_name = $tc['function']['name'];
            $fn_args = json_decode($tc['function']['arguments'] ?? '{}', true) ?: [];
            $call_id = $tc['id'] ?? ('call_' . uniqid());

            $result     = execute_tool($fn_name, $fn_args);
            $parsed_res = json_decode($result, true);

            $tool_log[] = [
                'id'       => $call_id,
                'tool'     => $fn_name,
                'args'     => $fn_args,
                'result'   => $parsed_res,
            ];

            // Collect referenced sources
            if (is_array($parsed_res)) {
                if ($fn_name === 'search_thoughts' && !empty($parsed_res['results'])) {
                    foreach ($parsed_res['results'] as $r) {
                        if (!empty($r['id']) && !isset($sources[$r['id']])) {
                            $sources[$r['id']] = [
                                'id'      => $r['id'],
                                'title'   => $r['title'] ?? 'Untitled Thought',
                                'date'    => $r['date'] ?? '',
                                'tags'    => $r['tags'] ?? [],
                                'preview' => $r['preview'] ?? '',
                            ];
                        }
                    }
                } elseif (in_array($fn_name, ['read_thought_entry', 'search_inside_entry', 'read_entry_lines'])) {
                    $id = $fn_args['entry_id'] ?? $parsed_res['id'] ?? $parsed_res['entry_id'] ?? '';
                    if ($id && !isset($sources[$id])) {
                        $entry = get_entry_by_id($id);
                        if ($entry) {
                            $sources[$id] = [
                                'id'      => $id,
                                'title'   => $entry['title'] ?? 'Untitled Thought',
                                'date'    => $entry['date'] ?? '',
                                'tags'    => $entry['tags'] ?? [],
                                'preview' => substr($entry['content'] ?? '', 0, 220),
                            ];
                        }
                    }
                } elseif ($fn_name === 'list_thoughts_by_date' && !empty($parsed_res['entries'])) {
                    foreach ($parsed_res['entries'] as $e) {
                        if (!empty($e['id']) && !isset($sources[$e['id']])) {
                            $sources[$e['id']] = [
                                'id'      => $e['id'],
                                'title'   => $e['title'] ?? 'Untitled Thought',
                                'date'    => $e['date'] ?? ($parsed_res['date'] ?? ''),
                                'tags'    => $e['tags'] ?? [],
                                'preview' => $e['preview'] ?? '',
                            ];
                        }
                    }
                }
            }

            $messages[] = [
                'role'         => 'tool',
                'tool_call_id' => $call_id,
                'content'      => $result,
            ];
        }

        $turns++;
    }

    return [
        'reply'      => 'Maximum tool turns reached. Please try a more specific question.',
        'tool_calls' => $tool_log,
        'sources'    => array_values($sources),
        'usage'      => [],
    ];
}

/**
 * Execute the agentic loop with real-time SSE streaming callback events.
 *
 * @param  array     $messages  Message history
 * @param  array     $tools     Tools available to agent
 * @param  callable  $sendEvent fn(string $event, mixed $data): void
 * @param  int       $max_turns Max recursive loops
 * @param  string|null $model   Model override
 * @return array
 */
function execute_agent_loop_stream(
    array $messages,
    array $tools,
    callable $sendEvent,
    int $max_turns = 8,
    ?string $model = null
): array {
    $tool_log        = [];
    $sources         = [];
    $total_reasoning = '';
    $turns           = 0;

    while ($turns < $max_turns) {
        $turn_content   = '';
        $turn_reasoning = '';

        $sendEvent('status', ['status' => 'thinking', 'message' => $turns === 0 ? 'Analyzing thoughts & context...' : 'Synthesizing findings...']);

        $onToken = function (string $token) use ($sendEvent, &$turn_content) {
            $turn_content .= $token;
            $sendEvent('token', ['token' => $token]);
        };

        $onReasoning = function (string $chunk) use ($sendEvent, &$turn_reasoning, &$total_reasoning) {
            $turn_reasoning  .= $chunk;
            $total_reasoning .= $chunk;
            $sendEvent('reasoning', ['chunk' => $chunk]);
        };

        $resp   = openrouter_chat_completion_stream($messages, $tools, $onToken, $onReasoning, $model);
        $choice = $resp['choices'][0] ?? null;

        if (!$choice) {
            throw new RuntimeException('OpenRouter returned no response choice.');
        }

        $msg = $choice['message'];
        $messages[] = $msg;

        // No tool calls means this was the final response
        if (empty($msg['tool_calls'])) {
            return [
                'reply'      => $msg['content'] ?? $turn_content,
                'reasoning'  => $total_reasoning,
                'tool_calls' => $tool_log,
                'sources'    => array_values($sources),
                'usage'      => $resp['usage'] ?? [],
            ];
        }

        // Execute each tool call and broadcast events
        foreach ($msg['tool_calls'] as $tc) {
            $fn_name = $tc['function']['name'] ?? '';
            $fn_args = json_decode($tc['function']['arguments'] ?? '{}', true) ?: [];
            $call_id = $tc['id'] ?? ('call_' . uniqid());

            $query_label = !empty($fn_args['query']) ? " \"{$fn_args['query']}\"" : '';
            $sendEvent('status', ['status' => 'tool', 'tool' => $fn_name, 'message' => "Running tool: {$fn_name}{$query_label}"]);

            // Notify client tool started
            $sendEvent('tool_start', [
                'id'   => $call_id,
                'tool' => $fn_name,
                'args' => $fn_args,
            ]);

            $result     = execute_tool($fn_name, $fn_args);
            $parsed_res = json_decode($result, true);

            $tool_log[] = [
                'id'       => $call_id,
                'tool'     => $fn_name,
                'args'     => $fn_args,
                'result'   => $parsed_res,
            ];

            // Notify client tool completed
            $sendEvent('tool_result', [
                'id'     => $call_id,
                'tool'   => $fn_name,
                'result' => $parsed_res,
            ]);

            // Collect referenced sources
            if (is_array($parsed_res)) {
                if ($fn_name === 'search_thoughts' && !empty($parsed_res['results'])) {
                    foreach ($parsed_res['results'] as $r) {
                        if (!empty($r['id']) && !isset($sources[$r['id']])) {
                            $sources[$r['id']] = [
                                'id'      => $r['id'],
                                'title'   => $r['title'] ?? 'Untitled Thought',
                                'date'    => $r['date'] ?? '',
                                'tags'    => $r['tags'] ?? [],
                                'preview' => $r['preview'] ?? '',
                            ];
                        }
                    }
                } elseif (in_array($fn_name, ['read_thought_entry', 'search_inside_entry', 'read_entry_lines'])) {
                    $id = $fn_args['entry_id'] ?? $parsed_res['id'] ?? $parsed_res['entry_id'] ?? '';
                    if ($id && !isset($sources[$id])) {
                        $entry = get_entry_by_id($id);
                        if ($entry) {
                            $sources[$id] = [
                                'id'      => $id,
                                'title'   => $entry['title'] ?? 'Untitled Thought',
                                'date'    => $entry['date'] ?? '',
                                'tags'    => $entry['tags'] ?? [],
                                'preview' => substr($entry['content'] ?? '', 0, 220),
                            ];
                        }
                    }
                } elseif ($fn_name === 'list_thoughts_by_date' && !empty($parsed_res['entries'])) {
                    foreach ($parsed_res['entries'] as $e) {
                        if (!empty($e['id']) && !isset($sources[$e['id']])) {
                            $sources[$e['id']] = [
                                'id'      => $e['id'],
                                'title'   => $e['title'] ?? 'Untitled Thought',
                                'date'    => $e['date'] ?? ($parsed_res['date'] ?? ''),
                                'tags'    => $e['tags'] ?? [],
                                'preview' => $e['preview'] ?? '',
                            ];
                        }
                    }
                }
                // Send updated sources list to client
                $sendEvent('sources', ['sources' => array_values($sources)]);
            }

            $messages[] = [
                'role'         => 'tool',
                'tool_call_id' => $call_id,
                'content'      => $result,
            ];
        }

        $turns++;
    }

    return [
        'reply'      => 'Maximum tool turns reached. Please try a more specific question.',
        'reasoning'  => $total_reasoning,
        'tool_calls' => $tool_log,
        'sources'    => array_values($sources),
        'usage'      => [],
    ];
}

// ═══════════════════════════════════════════════════════════════════════════════
// ACTION ROUTER
// ═══════════════════════════════════════════════════════════════════════════════

try {
    // ── Authentication Protection ─────────────────────────────────────────────
    if (!in_array($action, ['login', 'check_auth', 'logout'], true)) {
        if (!is_authenticated()) {
            api_err('Unauthorized. Please log in.', 401);
        }
    }

    switch ($action) {

        // ── Authentication Actions ─────────────────────────────────────────
        case 'login':
            $user = trim((string)($body['username'] ?? $_POST['username'] ?? ''));
            $pass = (string)($body['password'] ?? $_POST['password'] ?? '');
            if (!$user || !$pass) {
                api_err('Username and password are required.');
            }
            if (login_user($user, $pass)) {
                api_ok([
                    'authenticated' => true,
                    'username'      => $user,
                    'expires_in'    => SESSION_LIFETIME,
                ]);
            } else {
                api_err('Invalid username or password.', 401);
            }

        case 'logout':
            logout_user();
            api_ok(['authenticated' => false]);

        case 'check_auth':
            api_ok([
                'authenticated' => is_authenticated(),
                'username'      => $_SESSION['username'] ?? null,
            ]);

        // ── Capture ────────────────────────────────────────────────────────
        case 'capture_thought':
            $content = trim($body['content'] ?? '');
            $tags    = (array)($body['tags'] ?? []);
            if (!$content) api_err('Content is required.');

            $now      = time();
            $date     = date('Y-m-d', $now);
            [$y, $m, $d] = explode('-', $date);
            $uid      = uniqid('', true);
            $entry_id = "entry_{$y}{$m}{$d}_{$uid}";

            // Fast initial title from first line of text
            $lines = explode("\n", $content);
            $first_line = trim($lines[0] ?? '');
            $first_line = preg_replace('/^#+\s*/', '', $first_line);
            $initial_title = ($first_line !== '') ? substr($first_line, 0, 100) : 'Thought on ' . date('M j, Y H:i', $now);

            $entry = [
                'id'         => $entry_id,
                'content'    => $content,
                'title'      => $initial_title,
                'summary'    => substr($content, 0, 200),
                'tags'       => $tags,
                'date'       => $date,
                'timestamp'  => $now,
                'created_at' => date('c', $now),
            ];

            // Save raw entry to disk immediately
            $dir = DATA_DIR . "/entries/{$y}/{$m}/{$d}";
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            write_json_file("{$dir}/{$entry_id}.json", $entry);

            // Output JSON response immediately
            $response_data = json_encode(['ok' => true, 'data' => ['entry' => $entry]], JSON_UNESCAPED_UNICODE);
            header('Content-Type: application/json; charset=UTF-8');
            header('Content-Length: ' . strlen($response_data));
            header('Connection: close');
            echo $response_data;

            // Immediately finish the client connection if fastcgi is running
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            } else {
                if (ob_get_level() > 0) {
                    ob_end_flush();
                }
                flush();
            }

            // Continue background processing for AI metadata & vector embedding
            ignore_user_abort(true);
            set_time_limit(180);

            try {
                $meta = extract_thought_metadata($content);
                if (!empty($meta['title']) && $meta['title'] !== 'Untitled Thought') {
                    $entry['title'] = $meta['title'];
                }
                if (!empty($meta['summary'])) {
                    $entry['summary'] = $meta['summary'];
                }
                if (!empty($meta['tags'])) {
                    $entry['tags'] = array_unique(array_merge($entry['tags'], $meta['tags']));
                }
                write_json_file("{$dir}/{$entry_id}.json", $entry);

                // Compute embedding and update vector index atomically
                $vector     = openrouter_embed($content);
                $index_path = DATA_DIR . '/vectors/index.json';
                modify_json_file($index_path, function (&$index) use ($entry_id, $date, $now, $vector) {
                    $index[] = [
                        'id'        => $entry_id,
                        'date'      => $date,
                        'timestamp' => $now,
                        'vector'    => $vector,
                    ];
                }, []);
            } catch (Throwable $bg_e) {
                $log_dir = DATA_DIR . '/logs';
                if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
                file_put_contents(
                    $log_dir . '/background_tasks.log',
                    date('[Y-m-d H:i:s]') . " [BG ERROR] {$entry_id}: " . $bg_e->getMessage() . "\n",
                    FILE_APPEND | LOCK_EX
                );
            }
            exit;

        // ── Read entries ───────────────────────────────────────────────────
        case 'get_thought_map':
            api_ok(get_thought_map_data());

        case 'get_entries':
            $filters = [
                'date'   => $body['date']   ?? $_GET['date']   ?? '',
                'tag'    => $body['tag']    ?? $_GET['tag']    ?? '',
                'search' => $body['search'] ?? $_GET['search'] ?? '',
                'limit'  => (int)($body['limit']  ?? $_GET['limit']  ?? 50),
                'offset' => (int)($body['offset'] ?? $_GET['offset'] ?? 0),
            ];
            api_ok(get_all_entries($filters));

        case 'get_entry':
            $id = $body['entry_id'] ?? $_GET['entry_id'] ?? '';
            if (!$id) api_err('entry_id is required.');
            $entry = get_entry_by_id($id);
            if (!$entry) api_err('Entry not found.', 404);
            api_ok($entry);

        case 'update_entry':
            $id      = $body['entry_id'] ?? '';
            if (!is_string($id) || !preg_match('/^[A-Za-z0-9_.-]+$/', $id)) api_err('Invalid entry_id.');
            $content = trim($body['content'] ?? '');
            $title   = isset($body['title']) ? trim((string)$body['title']) : null;
            $tags    = isset($body['tags']) ? (array)$body['tags'] : null;
            if (!$id || !$content) api_err('entry_id and content are required.');
            $updated = update_thought_entry($id, $content, $title, $tags);
            if (!$updated) api_err('Entry not found.', 404);
            api_ok($updated);

        case 'get_entry':
            $id = $body['entry_id'] ?? $_GET['entry_id'] ?? '';
            if (!is_string($id) || !preg_match('/^[A-Za-z0-9_.-]+$/', $id)) api_err('Invalid entry_id.');
            $entry = get_entry_by_id($id);
            if (!$entry) api_err('Entry not found.', 404);
            api_ok($entry);

        case 'delete_entry':
            $id = $body['entry_id'] ?? '';
            if (!is_string($id) || !preg_match('/^[A-Za-z0-9_.-]+$/', $id)) api_err('Invalid entry_id.');
            $path = entry_id_to_path($id);
            if (!$path) api_err('Entry not found.', 404);
            // Remove from vector index atomically
            modify_json_file(DATA_DIR . '/vectors/index.json', function (&$index) use ($id) {
                $index = array_values(array_filter($index, fn($v) => ($v['id'] ?? '') !== $id));
            }, []);
            unlink($path);
            api_ok(['deleted' => $id]);

        // ── Digests ────────────────────────────────────────────────────────
        case 'get_digests':
            api_ok(get_all_digests());

        case 'get_digest':
            $date = $body['date'] ?? $_GET['date'] ?? date('Y-m-d');
            $dig  = get_digest($date);
            if (!$dig) api_err("No digest found for $date.", 404);
            api_ok($dig);

        // ── Memories ───────────────────────────────────────────────────────
        case 'get_memories':
            api_ok(get_memories());

        case 'add_memory':
            $text = trim($body['text'] ?? '');
            $cat  = trim($body['category'] ?? 'general');
            if (!$text) api_err('Memory text is required.');
            api_ok(save_memory($text, $cat));

        case 'update_memory':
            $id   = $body['id']       ?? '';
            $text = $body['text']     ?? '';
            $cat  = $body['category'] ?? 'general';
            if (!$id || !$text) api_err('id and text are required.');
            $updated = update_memory($id, $text, $cat);
            if (!$updated) api_err('Memory not found.', 404);
            api_ok($updated);

        case 'delete_memory':
            $id = $body['id'] ?? '';
            if (!$id) api_err('id is required.');
            if (!delete_memory($id)) api_err('Memory not found.', 404);
            api_ok(['deleted' => $id]);

        // ── Settings ───────────────────────────────────────────────────────
        case 'get_settings':
            $settings = read_json_file(DATA_DIR . '/settings.json', (object)[]);
            // Mask passwords before sending to client
            $safe = (array) $settings;
            if (!empty($safe['SMTP_PASS'])) $safe['SMTP_PASS'] = '••••••••';
            if (!empty($safe['OPENROUTER_API_KEY'])) $safe['OPENROUTER_API_KEY'] = substr($safe['OPENROUTER_API_KEY'], 0, 8) . '••••••••';
            api_ok($safe);

        case 'save_settings':
            $allowed = [
                'OPENROUTER_API_KEY', 'OPENROUTER_CHAT_MODEL', 'OPENROUTER_FAST_MODEL', 'OPENROUTER_EMBED_MODEL',
                'SMTP_HOST', 'SMTP_PORT', 'SMTP_USER', 'SMTP_PASS', 'SMTP_FROM', 'SMTP_FROM_NAME', 'SMTP_TO',
                'CRON_SECRET_KEY',
            ];
            $existing = read_json_file(DATA_DIR . '/settings.json', []);
            foreach ($allowed as $key) {
                if (isset($body[$key])) {
                    $val = trim((string)$body[$key]);
                    // Don't overwrite passwords with masked placeholder
                    if (in_array($key, ['SMTP_PASS', 'OPENROUTER_API_KEY']) && str_contains($val, '••••')) {
                        continue;
                    }
                    if ($val !== '') {
                        $existing[$key] = $val;
                    }
                }
            }
            write_json_file(DATA_DIR . '/settings.json', $existing);
            api_ok(['saved' => true]);

        // ── SMTP Test ──────────────────────────────────────────────────────
        case 'test_smtp':
            $custom = array_filter([
                'host'      => $body['host']      ?? null,
                'port'      => $body['port']      ?? null,
                'user'      => $body['user']      ?? null,
                'pass'      => $body['pass']      ?? null,
                'from'      => $body['from']      ?? null,
                'from_name' => $body['from_name'] ?? null,
            ]);
            $result = test_smtp_connection($custom ?: null);
            api_ok($result);

        // ── Agentic Chat (SSE Stream) ───────────────────────────────────────
        case 'chat_stream':
            $user_message = trim($body['message'] ?? '');
            $session_id   = trim((string)($body['session_id'] ?? ''));
            $history      = (array)($body['history'] ?? []);
            $save_turns   = (bool)($body['save_turns']   ?? true);
            $allow_save   = (bool)($body['allow_save']   ?? false);
            $use_memories = (bool)($body['use_memories'] ?? true);

            if (!$user_message) {
                header('Content-Type: application/json; charset=UTF-8');
                api_err('message is required.');
            }

            if (!$session_id) {
                $session_id = 'session_' . date('Ymd') . '_' . uniqid('', true);
            }

            // Save user turn to session
            append_chat_session_turn($session_id, ['role' => 'user', 'content' => $user_message]);

            // Disable output buffering and start SSE stream
            while (ob_get_level()) {
                ob_end_clean();
            }

            header('Content-Type: text/event-stream; charset=UTF-8');
            header('Cache-Control: no-cache, no-transform');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');

            $send_sse = function (string $event, mixed $data): void {
                echo "event: {$event}\n";
                echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
                flush();
            };

            // Send session info immediately
            $send_sse('session', ['session_id' => $session_id]);

            // Build system prompt
            $memories_text = '';
            if ($use_memories) {
                $mems = get_memories();
                if ($mems) {
                    $mem_lines = array_map(fn($m) => "- [{$m['category']}] {$m['text']}", $mems);
                    $memories_text = "\n\n## Core Memories & Context\n" . implode("\n", $mem_lines);
                }
            }

            $today_str = date('Y-m-d');
            $system = "You are a private Second Brain AI assistant. You have access to the user's personal thought database and can search, read, and analyse their logged ideas, notes, and reflections.\n\nYour role:\n- Answer questions by searching the thought database first before responding.\n- Make connections between ideas across different time periods.\n- Help the user think more clearly and discover patterns in their thinking.\n- Be concise, insightful, and intellectually honest.\n- When uncertain, say so and suggest what to search for.{$memories_text}\n\nToday's date: {$today_str}";

            $messages = [['role' => 'system', 'content' => $system]];

            foreach (array_slice($history, -10) as $turn) {
                if (!empty($turn['role']) && !empty($turn['content'])) {
                    $messages[] = ['role' => $turn['role'], 'content' => $turn['content']];
                }
            }

            $messages[] = ['role' => 'user', 'content' => $user_message];

            $tools = get_agent_tools();
            if (!$allow_save) {
                $tools = array_values(array_filter($tools, fn($t) => $t['function']['name'] !== 'save_new_thought'));
            }

            try {
                $result = execute_agent_loop_stream($messages, $tools, $send_sse);

                // Format tool calls with names and parameters only (excluding execution response payloads)
                $tool_calls_logged = array_map(function ($tc) {
                    return [
                        'tool'   => $tc['tool'] ?? $tc['function']['name'] ?? $tc['name'] ?? '',
                        'params' => $tc['args'] ?? (isset($tc['function']['arguments']) ? (json_decode($tc['function']['arguments'], true) ?: $tc['function']['arguments']) : []),
                    ];
                }, $result['tool_calls'] ?? []);

                // Save assistant turn to session
                $session = append_chat_session_turn($session_id, [
                    'role'       => 'assistant',
                    'content'    => $result['reply'],
                    'reasoning'  => $result['reasoning'] ?? null,
                    'tool_calls' => $tool_calls_logged,
                    'sources'    => $result['sources'] ?? [],
                ]);

                if ($save_turns) {
                    $today = date('Y-m-d');
                    save_chat_turn($today, 'user',      $user_message);
                    save_chat_turn($today, 'assistant', $result['reply'], [
                        'tool_calls' => $tool_calls_logged,
                    ]);
                }

                $send_sse('done', [
                    'reply'      => $result['reply'],
                    'reasoning'  => $result['reasoning'] ?? '',
                    'tool_calls' => $result['tool_calls'],
                    'sources'    => $result['sources'] ?? [],
                    'session_id' => $session_id,
                    'session'    => $session,
                    'usage'      => $result['usage'] ?? [],
                ]);
            } catch (Throwable $e) {
                $send_sse('error', ['error' => $e->getMessage()]);
            }
            exit;

        // ── Agentic Chat (Non-streaming fallback) ───────────────────────────
        case 'chat':
            $user_message = trim($body['message'] ?? '');
            $session_id   = trim((string)($body['session_id'] ?? ''));
            $history      = (array)($body['history'] ?? []);
            $save_turns   = (bool)($body['save_turns']   ?? true);
            $allow_save   = (bool)($body['allow_save']   ?? false);
            $use_memories = (bool)($body['use_memories'] ?? true);

            if (!$user_message) api_err('message is required.');

            if (!$session_id) {
                $session_id = 'session_' . date('Ymd') . '_' . uniqid('', true);
            }

            append_chat_session_turn($session_id, ['role' => 'user', 'content' => $user_message]);

            // Build system prompt
            $memories_text = '';
            if ($use_memories) {
                $mems = get_memories();
                if ($mems) {
                    $mem_lines = array_map(fn($m) => "- [{$m['category']}] {$m['text']}", $mems);
                    $memories_text = "\n\n## Core Memories & Context\n" . implode("\n", $mem_lines);
                }
            }

            $today_str = date('Y-m-d');
            $system = "You are a private Second Brain AI assistant. You have access to the user's personal thought database and can search, read, and analyse their logged ideas, notes, and reflections.\n\nYour role:\n- Answer questions by searching the thought database first before responding.\n- Make connections between ideas across different time periods.\n- Help the user think more clearly and discover patterns in their thinking.\n- Be concise, insightful, and intellectually honest.\n- When uncertain, say so and suggest what to search for.{$memories_text}\n\nToday's date: {$today_str}";

            $messages = [['role' => 'system', 'content' => $system]];

            foreach (array_slice($history, -10) as $turn) {
                if (!empty($turn['role']) && !empty($turn['content'])) {
                    $messages[] = ['role' => $turn['role'], 'content' => $turn['content']];
                }
            }

            $messages[] = ['role' => 'user', 'content' => $user_message];

            $tools = get_agent_tools();
            if (!$allow_save) {
                $tools = array_values(array_filter($tools, fn($t) => $t['function']['name'] !== 'save_new_thought'));
            }

            $result = execute_agent_loop($messages, $tools);

            $tool_calls_logged = array_map(function ($tc) {
                return [
                    'tool'   => $tc['tool'] ?? $tc['function']['name'] ?? $tc['name'] ?? '',
                    'params' => $tc['args'] ?? (isset($tc['function']['arguments']) ? (json_decode($tc['function']['arguments'], true) ?: $tc['function']['arguments']) : []),
                ];
            }, $result['tool_calls'] ?? []);

            $session = append_chat_session_turn($session_id, [
                'role'       => 'assistant',
                'content'    => $result['reply'],
                'tool_calls' => $tool_calls_logged,
                'sources'    => $result['sources'] ?? [],
            ]);

            if ($save_turns) {
                $today = date('Y-m-d');
                save_chat_turn($today, 'user',      $user_message);
                save_chat_turn($today, 'assistant', $result['reply'], [
                    'tool_calls' => $tool_calls_logged,
                ]);
            }

            api_ok([
                'reply'      => $result['reply'],
                'tool_calls' => $result['tool_calls'],
                'sources'    => $result['sources'] ?? [],
                'session_id' => $session_id,
                'session'    => $session,
                'usage'      => $result['usage'],
            ]);

        // ── Chat Sessions CRUD ─────────────────────────────────────────────
        case 'get_chat_sessions':
            api_ok(get_chat_sessions());

        case 'get_chat_session':
            $id = $body['session_id'] ?? $_GET['session_id'] ?? '';
            if (!$id) api_err('session_id is required.');
            $session = get_chat_session($id);
            if (!$session) api_err('Session not found.', 404);
            api_ok($session);

        case 'delete_chat_session':
            $id = $body['session_id'] ?? '';
            if (!$id) api_err('session_id is required.');
            if (!delete_chat_session($id)) api_err('Session not found.', 404);
            api_ok(['deleted' => $id]);

        case 'clone_chat_session':
            $id = $body['session_id'] ?? '';
            if (!$id) api_err('session_id is required.');
            $cloned = clone_chat_session($id);
            if (!$cloned) api_err('Failed to clone session.', 404);
            api_ok($cloned);

        case 'rename_chat_session':
            $id    = $body['session_id'] ?? '';
            $title = trim((string)($body['title'] ?? ''));
            if (!$id || !$title) api_err('session_id and title are required.');
            $renamed = rename_chat_session($id, $title);
            if (!$renamed) api_err('Session not found.', 404);
            api_ok($renamed);

        case 'edit_chat_turn':
            $id       = $body['session_id'] ?? '';
            $idx      = (int)($body['turn_index'] ?? -1);
            $content  = (string)($body['content'] ?? '');
            if (!$id || $idx < 0) api_err('session_id and valid turn_index are required.');
            $edited = edit_chat_session_turn($id, $idx, $content);
            if (!$edited) api_err('Failed to edit turn.', 404);
            api_ok($edited);

        // ── Manual cron trigger ────────────────────────────────────────────
        case 'trigger_cron':
            $key      = trim((string)($body['key'] ?? $_GET['key'] ?? ''));
            $expected = trim((string)get_config('CRON_SECRET_KEY', ''));

            if ($expected !== '' && $key !== '' && !hash_equals($expected, $key)) {
                api_err('Invalid cron secret key.', 403);
            }

            if (!defined('CRON_INTERNAL_EXEC')) {
                define('CRON_INTERNAL_EXEC', true);
            }

            ob_start();
            include __DIR__ . '/cron.php';
            $out = ob_get_clean();

            $today  = date('Y-m-d');
            $digest = get_digest($today);

            api_ok([
                'success' => true,
                'date'    => $today,
                'digest'  => $digest ? ['title' => $digest['title'], 'entry_count' => $digest['entry_count'] ?? 0] : null,
                'output'  => $out ?: 'Synthesis completed.',
            ]);

        // ── Chat history ───────────────────────────────────────────────────
        case 'get_chat_log':
            $date = $body['date'] ?? $_GET['date'] ?? date('Y-m-d');
            api_ok(get_chat_log($date));

        // ── Default ────────────────────────────────────────────────────────
        default:
            api_err("Unknown action: '$action'", 400);
    }

} catch (Throwable $e) {
    api_err($e->getMessage(), 500);
}
