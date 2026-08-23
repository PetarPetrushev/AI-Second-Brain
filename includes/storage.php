<?php
/**
 * includes/storage.php
 * Flat-file JSON storage layer: thought entries, vector index, memories,
 * digests, chat logs, and all vector math utilities.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/openrouter.php';

// ═══════════════════════════════════════════════════════════════════════════════
// FILE LOCKING HELPERS
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Atomically read a JSON file with a shared lock.
 */
function read_json_file(string $path, mixed $default = []): mixed
{
    if (!file_exists($path)) {
        return $default;
    }
    $fp = fopen($path, 'r');
    if (!$fp) return $default;
    flock($fp, LOCK_SH);
    $content = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $decoded = json_decode($content, true);
    return is_array($decoded) ? $decoded : $default;
}

/**
 * Atomically write data to a JSON file with an exclusive lock.
 */
function write_json_file(string $path, mixed $data): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $tmp = $path . '.tmp.' . uniqid();
    file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    rename($tmp, $path);
}

// ═══════════════════════════════════════════════════════════════════════════════
// VECTOR MATH
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Compute cosine similarity between two float vectors.
 *
 * @param  float[] $vecA
 * @param  float[] $vecB
 * @return float  0.0–1.0 (or -1.0–1.0 for signed embeddings)
 */
function cosine_similarity(array $vecA, array $vecB): float
{
    $dot  = 0.0;
    $magA = 0.0;
    $magB = 0.0;
    $len  = min(count($vecA), count($vecB));

    for ($i = 0; $i < $len; $i++) {
        $dot  += $vecA[$i] * $vecB[$i];
        $magA += $vecA[$i] * $vecA[$i];
        $magB += $vecB[$i] * $vecB[$i];
    }

    $denom = sqrt($magA) * sqrt($magB);
    return $denom > 0.0 ? $dot / $denom : 0.0;
}

/**
 * Compute an exponential recency score for a Unix timestamp.
 * Score = e^(-λ × Δt_days)
 *
 * @param  int   $timestamp  Unix timestamp of the entry
 * @return float             0.0–1.0 (1.0 = right now)
 */
function calculate_recency_score(int $timestamp): float
{
    $lambda     = (float) get_config('RECENCY_DECAY_LAMBDA', 0.015);
    $delta_days = max(0, (time() - $timestamp) / 86400.0);
    return exp(-$lambda * $delta_days);
}

/**
 * Search the vector index using a hybrid semantic + recency score.
 *
 * Hybrid score = (cosine_sim × SEMANTIC_WEIGHT) + (recency × RECENCY_WEIGHT)
 *
 * @param  float[] $query_vector   Embedding of the search query
 * @param  int     $max_results    Maximum number of results to return
 * @param  bool    $recency_bias   Include recency weighting
 * @param  bool    $exclude_today  Skip entries from today
 * @return array                   Sorted result entries with scores and content
 */
function search_vector_index(
    array $query_vector,
    int $max_results = 5,
    bool $recency_bias = true,
    bool $exclude_today = false
): array {
    $sem_w   = (float) get_config('SEMANTIC_WEIGHT', 0.75);
    $rec_w   = (float) get_config('RECENCY_WEIGHT', 0.25);
    $index   = read_json_file(DATA_DIR . '/vectors/index.json', []);
    $today   = date('Y-m-d');
    $scored  = [];

    foreach ($index as $record) {
        if (empty($record['vector']) || empty($record['id'])) continue;
        if ($exclude_today && ($record['date'] ?? '') === $today) continue;

        $cos      = cosine_similarity($query_vector, $record['vector']);
        $recency  = $recency_bias ? calculate_recency_score($record['timestamp'] ?? 0) : 1.0;
        $score    = ($cos * $sem_w) + ($recency * $rec_w);

        $scored[] = [
            'id'        => $record['id'],
            'date'      => $record['date'] ?? '',
            'timestamp' => $record['timestamp'] ?? 0,
            'score'     => $score,
            'cos_sim'   => $cos,
            'recency'   => $recency,
        ];
    }

    usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
    $top = array_slice($scored, 0, $max_results);

    // Hydrate with entry content
    foreach ($top as &$item) {
        $entry = get_entry_by_id($item['id']);
        if ($entry) {
            $item['title']   = $entry['title']   ?? 'Untitled';
            $item['summary'] = $entry['summary']  ?? '';
            $item['tags']    = $entry['tags']     ?? [];
            $item['preview'] = substr($entry['content'] ?? '', 0, 300);
        }
    }
    unset($item);

    return $top;
}

// ═══════════════════════════════════════════════════════════════════════════════
// ENTRY STORAGE
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Resolve the filesystem path for a given entry ID.
 * Entry IDs encode the date: entry_YYYYMMDD_uniqid
 */
function entry_id_to_path(string $entry_id): ?string
{
    // Try to parse date from ID prefix: entry_YYYYMMDD_...
    if (preg_match('/^entry_(\d{4})(\d{2})(\d{2})_/', $entry_id, $m)) {
        $path = DATA_DIR . "/entries/{$m[1]}/{$m[2]}/{$m[3]}/{$entry_id}.json";
        return file_exists($path) ? $path : null;
    }

    // Fallback: scan all entries for the ID
    $pattern = DATA_DIR . '/entries/*/*/*/*' . $entry_id . '*.json';
    $files   = glob($pattern);
    return $files ? $files[0] : null;
}

/**
 * Save a new thought entry, generate embeddings and metadata, update vector index.
 *
 * @param  string   $content  Raw thought text
 * @param  string[] $tags     Optional pre-supplied tags (metadata will be extracted anyway)
 * @return array              The saved entry data
 */
function save_thought_entry(string $content, array $tags = []): array
{
    $content = trim($content);
    if ($content === '') {
        throw new InvalidArgumentException('Thought content cannot be empty.');
    }

    $now      = time();
    $date     = date('Y-m-d', $now);
    [$y, $m, $d] = explode('-', $date);
    $uid      = uniqid('', true);
    $entry_id = "entry_{$y}{$m}{$d}_{$uid}";

    // Extract AI metadata
    $meta = extract_thought_metadata($content);
    if (!empty($tags)) {
        $meta['tags'] = array_unique(array_merge($meta['tags'], $tags));
    }

    // Generate embedding (catch errors if API key is invalid/missing)
    $vector = [];
    try {
        $vector = openrouter_embed($content);
    } catch (Throwable $e) {
        // Fallback gracefully if embedding service is offline or unconfigured
    }

    $entry = [
        'id'        => $entry_id,
        'content'   => $content,
        'title'     => $meta['title'],
        'summary'   => $meta['summary'],
        'tags'      => $meta['tags'],
        'date'      => $date,
        'timestamp' => $now,
        'created_at'=> date('c', $now),
    ];

    // Save entry file
    $dir  = DATA_DIR . "/entries/{$y}/{$m}/{$d}";
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    write_json_file("{$dir}/{$entry_id}.json", $entry);

    // Append to vector index
    $index_path = DATA_DIR . '/vectors/index.json';
    $index      = read_json_file($index_path, []);
    $index[]    = [
        'id'        => $entry_id,
        'date'      => $date,
        'timestamp' => $now,
        'vector'    => $vector,
    ];
    write_json_file($index_path, $index);

    return $entry;
}

/**
 * Retrieve a single entry by its ID.
 *
 * @param  string $entry_id
 * @return array|null
 */
function get_entry_by_id(string $entry_id): ?array
{
    $path = entry_id_to_path($entry_id);
    if (!$path) return null;
    $data = read_json_file($path, null);
    return is_array($data) ? $data : null;
}

/**
 * Update an existing thought entry.
 *
 * @param  string      $entry_id
 * @param  string      $content
 * @param  string|null $title
 * @param  array|null  $tags
 * @return array|null  Updated entry array or null if not found
 */
function update_thought_entry(string $entry_id, string $content, ?string $title = null, ?array $tags = null): ?array
{
    $path = entry_id_to_path($entry_id);
    if (!$path) return null;

    $entry = read_json_file($path, null);
    if (!$entry || !is_array($entry)) return null;

    $content = trim($content);
    if ($content === '') {
        throw new InvalidArgumentException('Thought content cannot be empty.');
    }

    $entry['content']    = $content;
    $entry['updated_at'] = date('c');

    if ($title !== null && trim($title) !== '') {
        $entry['title'] = trim($title);
    } else {
        // Automatically compute title if missing
        $meta = extract_thought_metadata($content);
        if (!empty($meta['title'])) {
            $entry['title'] = $meta['title'];
        }
    }

    if ($tags !== null) {
        $entry['tags'] = array_values(array_unique(array_filter(array_map('trim', $tags))));
    }

    $entry['summary'] = substr($content, 0, 200);

    write_json_file($path, $entry);

    // Re-generate vector embedding if possible and update vector index
    try {
        $vector     = openrouter_embed($content);
        $index_path = DATA_DIR . '/vectors/index.json';
        $index      = read_json_file($index_path, []);
        $found      = false;

        foreach ($index as &$rec) {
            if (($rec['id'] ?? '') === $entry_id) {
                $rec['vector']    = $vector;
                $rec['timestamp'] = time();
                $found            = true;
                break;
            }
        }
        unset($rec);

        if (!$found) {
            $index[] = [
                'id'        => $entry_id,
                'date'      => $entry['date'] ?? date('Y-m-d'),
                'timestamp' => time(),
                'vector'    => $vector,
            ];
        }
        write_json_file($index_path, $index);
    } catch (Throwable $e) {
        // Vector update failure shouldn't abort entry saving
    }

    return $entry;
}

/**
 * Safe entry reader — protects against huge token loads.
 * Returns full content when size is under MAX_SAFE_CHARS; otherwise returns
 * statistics, a 20-line preview, and windowing instructions.
 *
 * @param  string $entry_id
 * @return array
 */
function read_entry_safe(string $entry_id): array
{
    $entry = get_entry_by_id($entry_id);
    if (!$entry) {
        return ['error' => "Entry not found: $entry_id"];
    }

    $content = $entry['content'] ?? '';
    $max     = (int) get_config('MAX_SAFE_CHARS', 6000);

    if (strlen($content) <= $max) {
        return [
            'id'           => $entry_id,
            'title'        => $entry['title']    ?? '',
            'date'         => $entry['date']     ?? '',
            'tags'         => $entry['tags']     ?? [],
            'is_truncated' => false,
            'content'      => $content,
        ];
    }

    $lines      = explode("\n", $content);
    $line_count = count($lines);
    $char_count = strlen($content);
    $preview    = implode("\n", array_slice($lines, 0, 20));

    return [
        'id'           => $entry_id,
        'title'        => $entry['title'] ?? '',
        'date'         => $entry['date']  ?? '',
        'tags'         => $entry['tags']  ?? [],
        'is_truncated' => true,
        'char_count'   => $char_count,
        'line_count'   => $line_count,
        'preview'      => $preview,
        'instructions' => "This entry is large ($char_count chars, $line_count lines). Use search_inside_entry(entry_id, query) to search for specific content, or read_entry_lines(entry_id, start_line, end_line) to read a specific range (max 100 lines per call).",
    ];
}

/**
 * Search within a single entry using line-by-line substring matching.
 *
 * @param  string $entry_id
 * @param  string $query         Search term (case-insensitive)
 * @param  int    $context_lines Lines of context above and below each match
 * @return array
 */
function search_inside_entry(string $entry_id, string $query, int $context_lines = 2): array
{
    $entry = get_entry_by_id($entry_id);
    if (!$entry) {
        return ['error' => "Entry not found: $entry_id"];
    }

    $lines   = explode("\n", $entry['content'] ?? '');
    $matches = [];

    foreach ($lines as $i => $line) {
        if (stripos($line, $query) !== false) {
            $start   = max(0, $i - $context_lines);
            $end     = min(count($lines) - 1, $i + $context_lines);
            $snippet = [];
            for ($j = $start; $j <= $end; $j++) {
                $snippet[] = ['line' => $j + 1, 'text' => $lines[$j]];
            }
            $matches[] = [
                'match_line' => $i + 1,
                'context'    => $snippet,
            ];
        }
    }

    return [
        'entry_id'     => $entry_id,
        'query'        => $query,
        'match_count'  => count($matches),
        'matches'      => $matches,
    ];
}

/**
 * Read an exact line slice of a large entry (capped at 100 lines).
 *
 * @param  string $entry_id
 * @param  int    $start_line  1-indexed start
 * @param  int    $end_line    1-indexed end (inclusive)
 * @return array
 */
function read_entry_lines(string $entry_id, int $start_line, int $end_line): array
{
    $entry = get_entry_by_id($entry_id);
    if (!$entry) {
        return ['error' => "Entry not found: $entry_id"];
    }

    $lines     = explode("\n", $entry['content'] ?? '');
    $total     = count($lines);
    $start_idx = max(0, $start_line - 1);
    $end_idx   = min($total - 1, $end_line - 1, $start_idx + 99); // max 100 lines

    $slice = [];
    for ($i = $start_idx; $i <= $end_idx; $i++) {
        $slice[] = ['line' => $i + 1, 'text' => $lines[$i]];
    }

    return [
        'entry_id'   => $entry_id,
        'total_lines'=> $total,
        'start_line' => $start_idx + 1,
        'end_line'   => $end_idx + 1,
        'lines'      => $slice,
    ];
}

/**
 * List all thought entries for a specific date (YYYY-MM-DD).
 *
 * @param  string $date_str  Format: YYYY-MM-DD
 * @return array             Array of entry data
 */
function list_thoughts_by_date(string $date_str): array
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_str)) {
        return ['error' => 'Invalid date format. Use YYYY-MM-DD.'];
    }

    [$y, $m, $d] = explode('-', $date_str);
    $dir         = DATA_DIR . "/entries/{$y}/{$m}/{$d}";

    if (!is_dir($dir)) {
        return ['date' => $date_str, 'count' => 0, 'entries' => []];
    }

    $files   = glob($dir . '/entry_*.json');
    $entries = [];
    foreach ($files as $file) {
        $data = read_json_file($file, null);
        if ($data) {
            $entries[] = [
                'id'      => $data['id'],
                'title'   => $data['title']   ?? 'Untitled',
                'summary' => $data['summary'] ?? '',
                'tags'    => $data['tags']    ?? [],
                'date'    => $data['date'],
                'created_at' => $data['created_at'] ?? '',
                'preview' => substr($data['content'] ?? '', 0, 200),
            ];
        }
    }

    usort($entries, fn($a, $b) => strcmp($a['created_at'], $b['created_at']));

    return ['date' => $date_str, 'count' => count($entries), 'entries' => $entries];
}

/**
 * Retrieve all entries with optional filtering.
 *
 * @param  array $filters  Keys: date, tag, search, limit, offset
 * @return array
 */
function get_all_entries(array $filters = []): array
{
    $limit   = (int) ($filters['limit']  ?? 50);
    $offset  = (int) ($filters['offset'] ?? 0);
    $tag     = $filters['tag']    ?? '';
    $search  = $filters['search'] ?? '';
    $date    = $filters['date']   ?? '';

    $all_files = [];

    if ($date) {
        [$y, $m, $d] = array_pad(explode('-', $date), 3, '');
        $pattern = DATA_DIR . "/entries/{$y}/{$m}/{$d}/entry_*.json";
        $all_files = glob($pattern) ?: [];
    } else {
        // Collect from all dates
        foreach (glob(DATA_DIR . '/entries/*/*/*/entry_*.json') ?: [] as $f) {
            $all_files[] = $f;
        }
        rsort($all_files); // newest first
    }

    $results = [];
    foreach ($all_files as $file) {
        $entry = read_json_file($file, null);
        if (!$entry) continue;

        // Tag filter
        if ($tag && !in_array($tag, $entry['tags'] ?? [])) continue;

        // Search filter
        if ($search) {
            $haystack = strtolower($entry['content'] ?? '') . ' ' . strtolower($entry['title'] ?? '');
            if (stripos($haystack, $search) === false) continue;
        }

        $results[] = [
            'id'         => $entry['id'],
            'title'      => $entry['title']   ?? 'Untitled',
            'summary'    => $entry['summary'] ?? '',
            'tags'       => $entry['tags']    ?? [],
            'date'       => $entry['date'],
            'created_at' => $entry['created_at'] ?? '',
            'preview'    => substr($entry['content'] ?? '', 0, 280),
        ];
    }

    $total = count($results);
    $page  = array_slice($results, $offset, $limit);

    return ['total' => $total, 'offset' => $offset, 'limit' => $limit, 'entries' => $page];
}

// ═══════════════════════════════════════════════════════════════════════════════
// MEMORIES CRUD
// ═══════════════════════════════════════════════════════════════════════════════

function get_memories(): array
{
    return read_json_file(DATA_DIR . '/memories.json', []);
}

function save_memory(string $text, string $category = 'general'): array
{
    $memories = get_memories();
    $memory   = [
        'id'         => 'mem_' . uniqid(),
        'text'       => trim($text),
        'category'   => $category,
        'created_at' => date('c'),
        'updated_at' => date('c'),
    ];
    $memories[] = $memory;
    write_json_file(DATA_DIR . '/memories.json', $memories);
    return $memory;
}

function update_memory(string $id, string $text, string $category = 'general'): ?array
{
    $memories = get_memories();
    foreach ($memories as &$mem) {
        if ($mem['id'] === $id) {
            $mem['text']       = trim($text);
            $mem['category']   = $category;
            $mem['updated_at'] = date('c');
            write_json_file(DATA_DIR . '/memories.json', $memories);
            return $mem;
        }
    }
    return null;
}

function delete_memory(string $id): bool
{
    $memories = get_memories();
    $filtered = array_values(array_filter($memories, fn($m) => $m['id'] !== $id));
    if (count($filtered) === count($memories)) return false;
    write_json_file(DATA_DIR . '/memories.json', $filtered);
    return true;
}

// ═══════════════════════════════════════════════════════════════════════════════
// DIGESTS
// ═══════════════════════════════════════════════════════════════════════════════

function save_digest(string $date, array $data): void
{
    $path = DATA_DIR . '/digests/' . $date . '.json';
    write_json_file($path, array_merge(['date' => $date, 'generated_at' => date('c')], $data));
}

function get_digest(string $date): ?array
{
    $path = DATA_DIR . '/digests/' . $date . '.json';
    if (!file_exists($path)) return null;
    return read_json_file($path, null);
}

function get_all_digests(): array
{
    $files  = glob(DATA_DIR . '/digests/*.json') ?: [];
    rsort($files);
    $result = [];
    foreach ($files as $f) {
        $data     = read_json_file($f, null);
        $date     = basename($f, '.json');
        $result[] = [
            'date'         => $date,
            'generated_at' => $data['generated_at'] ?? '',
            'title'        => $data['title'] ?? "Daily Digest — $date",
        ];
    }
    return $result;
}

// ═══════════════════════════════════════════════════════════════════════════════
// CHAT LOGS & SESSIONS
// ═══════════════════════════════════════════════════════════════════════════════

function save_chat_turn(string $date, string $role, string $content, array $metadata = []): void
{
    $path  = DATA_DIR . '/chats/' . $date . '.json';
    $turns = read_json_file($path, []);
    $turns[] = array_merge([
        'role'      => $role,
        'content'   => $content,
        'timestamp' => time(),
        'created_at'=> date('c'),
    ], $metadata);
    write_json_file($path, $turns);
}

function get_chat_log(string $date): array
{
    $path = DATA_DIR . '/chats/' . $date . '.json';
    return read_json_file($path, []);
}

function get_chat_sessions(): array
{
    $dir = DATA_DIR . '/chats/sessions';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $files = glob($dir . '/*.json') ?: [];
    $sessions = [];

    foreach ($files as $f) {
        $data = read_json_file($f, null);
        if (!$data || !is_array($data)) continue;

        $turns = $data['turns'] ?? [];
        $first_user_turn = '';
        foreach ($turns as $t) {
            if (($t['role'] ?? '') === 'user') {
                $first_user_turn = $t['content'] ?? '';
                break;
            }
        }

        $sessions[] = [
            'id'          => $data['id'] ?? basename($f, '.json'),
            'title'       => $data['title'] ?? 'Untitled Conversation',
            'created_at'  => $data['created_at'] ?? date('c'),
            'updated_at'  => $data['updated_at'] ?? $data['created_at'] ?? date('c'),
            'date'        => $data['date'] ?? date('Y-m-d'),
            'turn_count'  => count($turns),
            'preview'     => substr(trim($first_user_turn), 0, 140),
        ];
    }

    usort($sessions, fn($a, $b) => strcmp($b['updated_at'], $a['updated_at']));
    return $sessions;
}

function get_chat_session(string $id): ?array
{
    $clean_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $id);
    if (!$clean_id) return null;
    $path = DATA_DIR . '/chats/sessions/' . $clean_id . '.json';
    if (!file_exists($path)) return null;
    return read_json_file($path, null);
}

function save_chat_session(array $session): array
{
    $dir = DATA_DIR . '/chats/sessions';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $now = time();
    $date = date('Y-m-d', $now);

    if (empty($session['id'])) {
        $uid = uniqid('', true);
        $session['id'] = 'session_' . str_replace('-', '', $date) . '_' . $uid;
        $session['created_at'] = date('c', $now);
    }

    $clean_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $session['id']);
    $session['id'] = $clean_id;
    $session['updated_at'] = date('c', $now);
    if (empty($session['date'])) $session['date'] = $date;
    if (empty($session['title'])) $session['title'] = 'New Conversation';
    if (!isset($session['turns'])) $session['turns'] = [];

    $path = $dir . '/' . $clean_id . '.json';
    write_json_file($path, $session);
    return $session;
}

function delete_chat_session(string $id): bool
{
    $clean_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $id);
    if (!$clean_id) return false;
    $path = DATA_DIR . '/chats/sessions/' . $clean_id . '.json';
    if (file_exists($path)) {
        return unlink($path);
    }
    return false;
}

function clone_chat_session(string $id): ?array
{
    $orig = get_chat_session($id);
    if (!$orig) return null;

    $now = time();
    $date = date('Y-m-d', $now);
    $uid = uniqid('', true);
    $new_id = 'session_' . str_replace('-', '', $date) . '_' . $uid;

    $cloned = [
        'id'         => $new_id,
        'title'      => ($orig['title'] ?? 'Conversation') . ' (Clone)',
        'created_at' => date('c', $now),
        'updated_at' => date('c', $now),
        'date'       => $date,
        'turns'      => $orig['turns'] ?? [],
    ];

    return save_chat_session($cloned);
}

function rename_chat_session(string $id, string $title): ?array
{
    $session = get_chat_session($id);
    if (!$session) return null;
    $session['title'] = trim($title) ?: 'Untitled Conversation';
    return save_chat_session($session);
}

function edit_chat_session_turn(string $id, int $turn_index, string $new_content): ?array
{
    $session = get_chat_session($id);
    if (!$session || !isset($session['turns'][$turn_index])) return null;

    $session['turns'][$turn_index]['content'] = $new_content;
    $session['turns'][$turn_index]['edited_at'] = date('c');
    return save_chat_session($session);
}

function append_chat_session_turn(string $id, array $turn): array
{
    $session = get_chat_session($id);
    if (!$session) {
        $session = [
            'id'    => $id,
            'title' => 'New Conversation',
            'turns' => [],
        ];
    }

    $session['turns'][] = array_merge([
        'timestamp' => time(),
        'created_at'=> date('c'),
    ], $turn);

    // If title is default and this is first user message, generate title from content
    if (($session['title'] === 'New Conversation' || $session['title'] === 'Untitled Conversation') && ($turn['role'] ?? '') === 'user') {
        $first_line = trim(explode("\n", $turn['content'] ?? '')[0]);
        $session['title'] = substr($first_line, 0, 60) ?: 'Conversation';
    }

    return save_chat_session($session);
}

// ═══════════════════════════════════════════════════════════════════════════════
// THOUGHT MAP / VECTOR GRAPH
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Generate full network map data of thought nodes and similarity/tag connections.
 *
 * @return array{nodes: array, edges: array, tags: array}
 */
function get_thought_map_data(): array
{
    $entries_data = get_all_entries(['limit' => 500]);
    $entries      = $entries_data['entries'] ?? [];

    $index_path   = DATA_DIR . '/vectors/index.json';
    $vector_index = read_json_file($index_path, []);
    $vector_map   = [];

    foreach ($vector_index as $rec) {
        if (!empty($rec['id']) && !empty($rec['vector']) && is_array($rec['vector'])) {
            $vector_map[$rec['id']] = $rec['vector'];
        }
    }

    $color_palette = [
        '#7c6af7', '#34d399', '#f87171', '#fbbf24', '#60a5fa',
        '#a78bfa', '#f472b6', '#38bdf8', '#4ade80', '#fb923c',
        '#e879f9', '#a3e635', '#2dd4bf', '#facc15', '#c084fc',
    ];

    $tag_counts = [];
    $tag_colors = [];

    // Count tags and assign colors
    foreach ($entries as $e) {
        foreach ($e['tags'] ?? [] as $t) {
            $t_clean = strtolower(trim($t));
            if ($t_clean === '') continue;
            $tag_counts[$t_clean] = ($tag_counts[$t_clean] ?? 0) + 1;
        }
    }

    arsort($tag_counts);
    $palette_idx = 0;
    foreach (array_keys($tag_counts) as $t_clean) {
        $tag_colors[$t_clean] = $color_palette[$palette_idx % count($color_palette)];
        $palette_idx++;
    }

    $nodes = [];
    $node_index_map = [];

    foreach ($entries as $idx => $e) {
        $id       = $e['id'];
        $tags     = $e['tags'] ?? [];
        $primary  = !empty($tags) ? strtolower(trim($tags[0])) : 'general';
        $color    = $tag_colors[$primary] ?? '#7c6af7';
        $has_vec  = isset($vector_map[$id]);

        $nodes[] = [
            'id'          => $id,
            'title'       => $e['title'] ?? 'Untitled',
            'summary'     => $e['summary'] ?? '',
            'tags'        => $tags,
            'primaryTag'  => $primary,
            'date'        => $e['date'] ?? '',
            'created_at'  => $e['created_at'] ?? '',
            'preview'     => $e['preview'] ?? '',
            'color'       => $color,
            'hasVector'   => $has_vec,
        ];
        $node_index_map[$id] = count($nodes) - 1;
    }

    $edges = [];
    $edge_keys = [];
    $node_count = count($nodes);

    for ($i = 0; $i < $node_count; $i++) {
        for ($j = $i + 1; $j < $node_count; $j++) {
            $nodeA = $nodes[$i];
            $nodeB = $nodes[$j];

            $idA = $nodeA['id'];
            $idB = $nodeB['id'];

            $sim = 0.0;
            $type = 'tag';

            // 1. Vector similarity if vectors exist
            if (!empty($vector_map[$idA]) && !empty($vector_map[$idB])) {
                $cos_sim = cosine_similarity($vector_map[$idA], $vector_map[$idB]);
                if ($cos_sim > $sim) {
                    $sim  = $cos_sim;
                    $type = 'vector';
                }
            }

            // 2. Tag similarity overlap
            $tagsA = array_map('strtolower', $nodeA['tags']);
            $tagsB = array_map('strtolower', $nodeB['tags']);
            $intersection = array_intersect($tagsA, $tagsB);

            if (!empty($intersection)) {
                $min_len = max(1, min(count($tagsA), count($tagsB)));
                $tag_sim = count($intersection) / $min_len; // Overlap coefficient
                if ($tag_sim > $sim) {
                    $sim  = $tag_sim;
                    $type = 'tag';
                } elseif ($type === 'vector' && $sim > 0) {
                    // Boost vector similarity if they also share tags
                    $sim = min(1.0, $sim + (0.15 * count($intersection)));
                }
            }

            // Include edges above threshold (0.20)
            if ($sim >= 0.20) {
                $edges[] = [
                    'source'     => $idA,
                    'target'     => $idB,
                    'similarity' => round($sim, 4),
                    'type'       => $type,
                ];
            }
        }
    }

    $tags_output = [];
    foreach ($tag_counts as $t_clean => $cnt) {
        $tags_output[] = [
            'name'  => $t_clean,
            'count' => $cnt,
            'color' => $tag_colors[$t_clean],
        ];
    }

    return [
        'nodes' => $nodes,
        'edges' => $edges,
        'tags'  => $tags_output,
        'count' => count($nodes),
    ];
}
