<?php
// Cliente simples para Supabase REST (PostgREST) usando service role key
// Uso: require_once 'includes/supabase_api.php'; then call supabase_get/post/patch/delete

function supabase_config()
{
    $url = getenv('SUPABASE_URL') ?: '';
    $service_role = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: '';
    $anon = getenv('SUPABASE_ANON_KEY') ?: '';
    $key = $service_role ?: $anon;
    return ['url' => rtrim($url, '/'), 'key' => $key, 'is_service_role' => !empty($service_role)];
}

function supabase_request($method, $path, $opts = [])
{
    $cfg = supabase_config();
    if (empty($cfg['url']) || empty($cfg['key'])) {
        throw new Exception('Supabase URL ou chave não configurada. Verifique .env');
    }

    $url = $cfg['url'] . '/rest/v1/' . ltrim($path, '/');

    $ch = curl_init();
    $headers = [
        'apikey: ' . $cfg['key'],
        'Authorization: Bearer ' . $cfg['key'],
        'Accept: application/json'
    ];

    if (!empty($opts['headers']) && is_array($opts['headers'])) {
        $headers = array_merge($headers, $opts['headers']);
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    // permitir desabilitar verificação SSL em ambientes locais via .env
    $skipVerify = getenv('SUPABASE_SKIP_SSL_VERIFY') ?: '';
    if ($skipVerify === '1') {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }

    if ($method === 'GET' && !empty($opts['query'])) {
        $q = http_build_query($opts['query']);
        curl_setopt($ch, CURLOPT_URL, $url . '?' . $q);
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($opts['body'] ?? []));
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    if (in_array($method, ['PATCH', 'DELETE', 'PUT'], true)) {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if (!empty($opts['body'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($opts['body']));
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
    }

    $result = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($result === false) {
        throw new Exception('Erro na requisição Supabase: ' . $err);
    }

    $decoded = json_decode($result, true);
    return ['status' => $code, 'body' => $decoded, 'raw' => $result];
}

function supabase_select($table, $select = '*', $queryString = '')
{
    $path = $table;
    if ($select) $path .= '?select=' . rawurlencode($select) . ($queryString ? '&' . ltrim($queryString, '&') : '');
    $res = supabase_request('GET', $path, []);
    if ($res['status'] >= 200 && $res['status'] < 300) return $res['body'];
    throw new Exception('Supabase select failed: HTTP ' . $res['status'] . ' ' . ($res['raw'] ?? ''));
}

function supabase_insert($table, $data, $returning = '*')
{
    $cfg = supabase_config();
    if (empty($cfg['is_service_role'])) {
        throw new Exception('Operação de escrita negada: é necessária SUPABASE_SERVICE_ROLE_KEY.');
    }
    // Use Prefer header to request returned representation (PostgREST expects Prefer, not query param)
    $headers = ['Prefer: return=representation'];
    $res = supabase_request('POST', $table, ['body' => $data, 'headers' => $headers]);
    if ($res['status'] >= 200 && $res['status'] < 300) return $res['body'];
    throw new Exception('Supabase insert failed: HTTP ' . $res['status'] . ' ' . ($res['raw'] ?? ''));
}

function supabase_update($table, $queryString, $data)
{
    $cfg = supabase_config();
    if (empty($cfg['is_service_role'])) {
        throw new Exception('Operação de escrita negada: é necessária SUPABASE_SERVICE_ROLE_KEY.');
    }
    $path = $table . '?' . ltrim($queryString, '?');
    $res = supabase_request('PATCH', $path, ['body' => $data]);
    if ($res['status'] >= 200 && $res['status'] < 300) return $res['body'];
    throw new Exception('Supabase update failed: HTTP ' . $res['status'] . ' ' . ($res['raw'] ?? ''));
}

// helper to build date range for a day: returns string like "data_hora=gte.2026-06-15T00:00:00&data_hora=lt.2026-06-16T00:00:00"
function supabase_day_range_query($date)
{
    $start = $date . 'T00:00:00';
    $tomorrow = date('Y-m-d', strtotime($date . ' +1 day')) . 'T00:00:00';
    return 'data_hora=gte.' . rawurlencode($start) . '&data_hora=lt.' . rawurlencode($tomorrow);
}
