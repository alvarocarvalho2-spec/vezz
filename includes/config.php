<?php
/**
 * Configuração de conexão PDO (PostgreSQL) usando variáveis em .env
 * Arquivo central de conexão — todos os scripts devem incluir este arquivo
 */

// Carrega variáveis do .env simples (não usa libs externas)
function load_env($path)
{
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '') continue;
        $value = $value ?? '';
        $value = trim($value, "\"'");
        if (getenv($key) === false) putenv($key . '=' . $value);
        if (!isset($_ENV[$key])) $_ENV[$key] = $value;
        if (!isset($_SERVER[$key])) $_SERVER[$key] = $value;
    }
}

load_env(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

// Permitir forçar o uso da API via variável USE_SUPABASE_API=1
$force_use_api = false;
$use_api_env = getenv('USE_SUPABASE_API');
if ($use_api_env !== false) {
    $ue = strtolower(trim($use_api_env));
    if (in_array($ue, ['1','true','yes','on'], true)) $force_use_api = true;
}

// Variáveis de configuração (permite SUPABASE_* ou DB_*)
$DB_HOST = getenv('DB_HOST') ?: getenv('SUPABASE_HOST') ?: '127.0.0.1';
$DB_PORT = getenv('DB_PORT') ?: getenv('SUPABASE_PORT') ?: '5432';
$DB_NAME = getenv('DB_NAME') ?: getenv('SUPABASE_DB') ?: 'postgres';
$DB_USER = getenv('DB_USER') ?: getenv('SUPABASE_USER') ?: 'postgres';
$DB_PASS = getenv('DB_PASSWORD') ?: getenv('SUPABASE_PASSWORD') ?: '';
$BASE_URL = getenv('BASE_URL') ?: '/VEZZ';

define('BASE_URL', $BASE_URL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Se SUPABASE_URL + SUPABASE_SERVICE_ROLE_KEY estiverem definidos, usar API em vez de PDO
$use_supabase_api = false;
$supabase_url = getenv('SUPABASE_URL') ?: '';
$supabase_key = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: getenv('SUPABASE_ANON_KEY') ?: '';

// Decisão: usar API se for forçado ou se as variáveis essencias estiverem presentes
if ($force_use_api) {
    if (empty($supabase_url) || empty($supabase_key)) {
        die('USE_SUPABASE_API está definido, mas SUPABASE_URL ou SUPABASE_ANON_KEY/SUPABASE_SERVICE_ROLE_KEY não estão configuradas. Verifique suas variáveis de ambiente.');
    }
    $use_supabase_api = true;
    $pdo = null; // não inicializa PDO quando operando via API
} elseif (!empty($supabase_url) && !empty($supabase_key)) {
    $use_supabase_api = true;
    // não inicializa PDO para evitar erro de conexão quando só houver API disponível
    $pdo = null;
} else {
    try {
        // Forçar SSL/TLS para conexões externas (ex: Supabase)
        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s;sslmode=require', $DB_HOST, $DB_PORT, $DB_NAME);

        $pdo = new PDO(
            $dsn,
            $DB_USER,
            $DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );

        // garantir UTF-8
        $pdo->exec("SET NAMES 'UTF8'");

    } catch (PDOException $e) {
        // Falha na conexão — mostrar mensagem útil em ambiente local
        die('Erro de conexão com o banco de dados (Postgres): ' . $e->getMessage());
    }
}

// Disponibilidade da API Supabase para outras partes do código
define('USE_SUPABASE_API', $use_supabase_api);
