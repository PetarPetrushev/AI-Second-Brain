<?php
/**
 * includes/openrouter.php
 * OpenRouter API client — embeddings, chat completions with tool calling,
 * and a fast metadata extraction helper.
 */

require_once __DIR__ . '/../config.php';

// ─── Low-level cURL helper ────────────────────────────────────────────────────

/**
 * Execute a JSON POST request to the OpenRouter API.
 *
 * @param  string $endpoint  Path after /api/v1  (e.g. '/embeddings')
 * @param  array  $payload   Data to JSON-encode
 * @param  string|null $api_key Override API key (uses config default otherwise)
 * @return array             Decoded JSON response
 * @throws RuntimeException  On cURL / HTTP / parse failure
 */
function openrouter_request(string $endpoint, array $payload, ?string $api_key = null): array
{
    $key = $api_key ?? get_config('OPENROUTER_API_KEY');
    $url = 'https://openrouter.ai/api/v1' . $endpoint;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
            'HTTP-Referer: http://localhost',
            'X-Title: Second Brain Engine',
        ],
        CURLOPT_TIMEOUT        => 300,
        CURLOPT_CONNECTTIMEOUT => 30,
    ]);

    $body  = curl_exec($ch);
    $errno = curl_errno($ch);
    $http  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) {
        throw new RuntimeException("cURL error ($errno) calling $endpoint: " . curl_strerror($errno));
    }

    $data = json_decode($body, true);
    if (!is_array($data)) {
        throw new RuntimeException("Non-JSON response from OpenRouter ($http): " . substr($body, 0, 300));
    }

    if ($http >= 400) {
        $msg = $data['error']['message'] ?? $body;
        throw new RuntimeException("OpenRouter API error $http: $msg");
    }

    return $data;
}

// ─── Embeddings ───────────────────────────────────────────────────────────────

/**
 * Generate a semantic embedding vector for a given text string.
 *
 * @param  string $text
 * @return float[]  Embedding vector
 */
function openrouter_embed(string $text): array
{
    $model = get_config('OPENROUTER_EMBED_MODEL');
    $resp  = openrouter_request('/embeddings', [
        'model' => $model,
        'input' => $text,
    ]);

    if (empty($resp['data'][0]['embedding'])) {
        throw new RuntimeException('Embedding response missing vector data.');
    }

    return $resp['data'][0]['embedding'];
}

// ─── Chat Completions ─────────────────────────────────────────────────────────

/**
 * Send a chat completion request, optionally with tool/function definitions.
 *
 * @param  array       $messages  OpenAI-format message array
 * @param  array       $tools     Tool definitions (empty = no tools)
 * @param  string|null $model     Model override; falls back to OPENROUTER_CHAT_MODEL
 * @return array                  Full decoded response
 */
function openrouter_chat_completion(array $messages, array $tools = [], ?string $model = null): array
{
    $model = $model ?? get_config('OPENROUTER_CHAT_MODEL');

    $payload = [
        'model'       => $model,
        'messages'    => $messages,
        'temperature' => 0.7,
    ];

    if (!empty($tools)) {
        $payload['tools']       = $tools;
        $payload['tool_choice'] = 'auto';
    }

    return openrouter_request('/chat/completions', $payload);
}

/**
 * Stream a chat completion request from OpenRouter, invoking $onToken callback
 * as content tokens arrive, and accumulating tool call deltas.
 *
 * @param  array          $messages OpenAI-format messages
 * @param  array          $tools    Tool definitions
 * @param  callable|null  $onToken  fn(string $token): void
 * @param  string|null    $model    Model override
 * @return array                    Assembled response choice structure
 */
function openrouter_chat_completion_stream(
    array $messages,
    array $tools = [],
    ?callable $onToken = null,
    ?callable $onReasoning = null,
    ?string $model = null
): array {
    $model = $model ?? get_config('OPENROUTER_CHAT_MODEL');
    $key   = get_config('OPENROUTER_API_KEY');
    $url   = 'https://openrouter.ai/api/v1/chat/completions';

    $payload = [
        'model'       => $model,
        'messages'    => $messages,
        'temperature' => 0.7,
        'stream'      => true,
    ];

    if (!empty($tools)) {
        $payload['tools']       = $tools;
        $payload['tool_choice'] = 'auto';
    }

    $accumulated_content    = '';
    $accumulated_reasoning  = '';
    $accumulated_tool_calls = [];
    $finish_reason          = null;
    $buffer                 = '';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
            'HTTP-Referer: http://localhost',
            'X-Title: Second Brain Engine',
            'Accept: text/event-stream',
        ],
        CURLOPT_TIMEOUT        => 300,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_WRITEFUNCTION  => function ($curl_handle, string $chunk) use (
            &$buffer,
            &$accumulated_content,
            &$accumulated_reasoning,
            &$accumulated_tool_calls,
            &$finish_reason,
            $onToken,
            $onReasoning
        ) {
            $buffer .= $chunk;
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);

                if ($line === '' || str_starts_with($line, ':')) {
                    continue; // Keep-alive / comment line
                }

                if (str_starts_with($line, 'data: ')) {
                    $json_str = substr($line, 6);
                    if ($json_str === '[DONE]') {
                        break;
                    }

                    $data = json_decode($json_str, true);
                    if (!is_array($data)) {
                        continue;
                    }

                    $choice = $data['choices'][0] ?? null;
                    if (!$choice) {
                        continue;
                    }

                    if (!empty($choice['finish_reason'])) {
                        $finish_reason = $choice['finish_reason'];
                    }

                    $delta = $choice['delta'] ?? [];

                    // 1. Reasoning delta (OpenRouter standard: delta.reasoning / delta.reasoning_content)
                    $reasoning_chunk = $delta['reasoning'] ?? $delta['reasoning_content'] ?? null;
                    if ($reasoning_chunk !== null && $reasoning_chunk !== '') {
                        $accumulated_reasoning .= $reasoning_chunk;
                        if ($onReasoning !== null) {
                            $onReasoning($reasoning_chunk);
                        }
                    }

                    // 2. Text token delta
                    if (!empty($delta['content'])) {
                        $token = $delta['content'];
                        $accumulated_content .= $token;
                        if ($onToken !== null) {
                            $onToken($token);
                        }
                    }

                    // 3. Tool call delta
                    if (!empty($delta['tool_calls'])) {
                        foreach ($delta['tool_calls'] as $tc_delta) {
                            $idx = $tc_delta['index'] ?? 0;
                            if (!isset($accumulated_tool_calls[$idx])) {
                                $accumulated_tool_calls[$idx] = [
                                    'id'       => $tc_delta['id'] ?? ('call_' . uniqid()),
                                    'type'     => $tc_delta['type'] ?? 'function',
                                    'function' => [
                                        'name'      => $tc_delta['function']['name'] ?? '',
                                        'arguments' => $tc_delta['function']['arguments'] ?? '',
                                    ],
                                ];
                            } else {
                                if (!empty($tc_delta['id'])) {
                                    $accumulated_tool_calls[$idx]['id'] = $tc_delta['id'];
                                }
                                if (!empty($tc_delta['function']['name'])) {
                                    $accumulated_tool_calls[$idx]['function']['name'] .= $tc_delta['function']['name'];
                                }
                                if (!empty($tc_delta['function']['arguments'])) {
                                    $accumulated_tool_calls[$idx]['function']['arguments'] .= $tc_delta['function']['arguments'];
                                }
                            }
                        }
                    }
                }
            }
            return strlen($chunk);
        },
    ]);

    $exec_ok = curl_exec($ch);
    $errno   = curl_errno($ch);
    $http    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) {
        throw new RuntimeException("cURL stream error ($errno): " . curl_strerror($errno));
    }

    if ($http >= 400) {
        throw new RuntimeException("OpenRouter stream returned HTTP $http: " . substr($accumulated_content, 0, 300));
    }

    return [
        'choices' => [
            [
                'message' => [
                    'role'       => 'assistant',
                    'content'    => $accumulated_content !== '' ? $accumulated_content : null,
                    'reasoning'  => $accumulated_reasoning !== '' ? $accumulated_reasoning : null,
                    'tool_calls' => !empty($accumulated_tool_calls) ? array_values($accumulated_tool_calls) : null,
                ],
                'finish_reason' => $finish_reason ?? 'stop',
            ]
        ],
        'usage' => [],
    ];
}

// ─── Metadata extraction ──────────────────────────────────────────────────────

/**
 * Use the fast model to extract a title, one-sentence summary, and 2-4 semantic
 * tags for a newly captured thought.
 *
 * @param  string $text  Raw thought content
 * @return array{title: string, summary: string, tags: string[]}
 */
function extract_thought_metadata(string $text): array
{
    $model = get_config('OPENROUTER_FAST_MODEL');

    $system = 'You are a metadata extraction assistant. Given a thought or note, respond ONLY with a JSON object (no markdown, no prose) with three keys: "title" (short 3-7 word title), "summary" (one sentence max 25 words), "tags" (array of 2-4 lowercase semantic tags). Example: {"title":"Morning reflection on habits","summary":"Thinking about how daily routines shape long-term identity and productivity.","tags":["habits","identity","productivity","routine"]}';

    $messages = [
        ['role' => 'system', 'content' => $system],
        ['role' => 'user',   'content' => "Extract metadata for this thought:\n\n" . substr($text, 0, 2000)],
    ];

    try {
        $resp    = openrouter_chat_completion($messages, [], $model);
        $content = $resp['choices'][0]['message']['content'] ?? '{}';

        // Strip possible markdown fences
        $content = preg_replace('/^```(?:json)?\s*/i', '', trim($content));
        $content = preg_replace('/\s*```$/', '', $content);

        $meta = json_decode($content, true);
        if (!is_array($meta)) {
            throw new RuntimeException('Invalid JSON metadata response.');
        }

        return [
            'title'   => substr($meta['title']   ?? 'Untitled Thought', 0, 120),
            'summary' => substr($meta['summary'] ?? '', 0, 300),
            'tags'    => array_slice(array_filter(array_map('strval', $meta['tags'] ?? [])), 0, 4),
        ];
    } catch (Throwable $e) {
        // Graceful fallback — never block a save
        return [
            'title'   => 'Untitled Thought',
            'summary' => substr($text, 0, 150),
            'tags'    => [],
        ];
    }
}
