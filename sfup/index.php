<?php
//--------------------------------------------------
//  SELF UPLOADER v0.2.1
//  by sakots https://dev.oekakibbs.net/
//--------------------------------------------------

//スクリプトのバージョン
define('SFUP_VER','v0.2.1'); //lot.250904.1

//設定の読み込み
require_once (__DIR__.'/config.php');
require_once (__DIR__.'/templates/'.THEME_DIR.'/theme.ini.php');

//タイムゾーン設定
date_default_timezone_set(DEFAULT_TIMEZONE);

//phpのバージョンが古い場合動作させない
if (($php_ver = phpversion()) < "7.4.0") {
  die("PHP version 7.4.0 or higher is required for this program to work. <br>\n(Current PHP version:{$php_ver})");
}
//コンフィグのバージョンが古くて互換性がない場合動作させない
if (CONF_VER < 20250904 || !defined('CONF_VER')) {
  die("コンフィグファイルに互換性がないようです。再設定をお願いします。<br>\n The configuration file is incompatible. Please reconfigure it.");
}
//管理パスが初期値(kanripass)の場合は動作させない
if ($admin_pass === 'kanripass' || $watchword === 'kanripass') {
  die("管理パス、または合言葉が初期設定値のままです！危険なので動かせません。<br>\n The admin pass or watchword is still at its default value! This program can't run it until you fix it.");
}

//BladeOne v4.18
include (__DIR__.'/BladeOne/lib/BladeOne.php');
use eftec\bladeone\BladeOne;

$views = __DIR__.'/templates/'.THEME_DIR; // テンプレートフォルダ
$cache = __DIR__.'/cache'; // キャッシュフォルダ
$blade = new BladeOne($views,$cache,BladeOne::MODE_AUTO); // MODE_DEBUGだと開発モード MODE_AUTOが速い。
$blade->pipeEnable = true; // パイプのフィルターを使えるようにする

$dat = array(); // bladeに格納する変数

//絶対パス取得
$up_path = realpath("./").UP_DIR.'/';
$temp_path = realpath("./").TEMP_DIR.'/';

$dat['path'] = UP_DIR;
$dat['ver'] = SFUP_VER;
$dat['title'] = SFUP_TITLE;
$dat['theme_dir'] = THEME_DIR;

define('UP_MAX_SIZE', UP_MAX_MB*1024*1024);
$dat['up_max_size'] = UP_MAX_SIZE;

$dat['type'] = ACCEPT_FILETYPE;

$dat['use_auth'] = UP_AUTH === '0' ? true : false ;

$dat['up_max_mb'] = UP_MAX_MB;

$dat['t_name'] = THEME_NAME;
$dat['t_ver'] = THEME_VER;

//データベース接続PDO
define('DB_PDO', 'sqlite:'.DB_NAME.'.db');
define('DB_TIMEOUT', 5000); // タイムアウト時間（ミリ秒）
define('DB_RETRY_COUNT', 3); // リトライ回数
define('DB_RETRY_DELAY', 100000); // リトライ間隔（マイクロ秒）

//初期設定
init();
deltemp();

$req_method = isset($_SERVER["REQUEST_METHOD"]) ? $_SERVER["REQUEST_METHOD"]: "";
//INPUT_SERVER が動作しないサーバがあるので$_SERVERを使う。

/*----------- mode -------------*/

//INPUT_POSTから変数を取得
$mode = filter_input(INPUT_POST, 'mode');
$mode = $mode ? $mode : filter_input(INPUT_GET, 'mode');

switch($mode) {
  case 'upload':
    return upload();
  case 'del':
    return del();
  default:
    return def();
}
exit;

/* ----------- main ------------- */

//初期作業
function init() {
  if(!is_writable(realpath("./"))) error("カレントディレクトリに書けません<br>");
  $err='';
  
  // データベース初期化
  try {
    if (!is_file(DB_NAME.'.db')) {
      // はじめての実行なら、テーブルを作成
      $db = new PDO(DB_PDO);
      $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
      $sql = "CREATE TABLE uplog (id integer primary key autoincrement, created timestamp, name VARCHAR(1000), sub VARCHAR(1000), com VARCHAR(10000), host TEXT, pwd TEXT, upfile TEXT, age INT, invz VARCHAR(1), file_hash TEXT, file_size INTEGER, scan_result TEXT)";
      $db = $db->query($sql);
      $db = null; //db切断
    } else {
      // 既存のデータベースを新しい形式に移行
      migrate_database();
    }
  } catch (PDOException $e) {
    echo "DB接続エラー:" .$e->getMessage();
  }
  
  // ディレクトリの作成と権限設定
  $directories = array(
    UP_DIR => 'アップロード',
    TEMP_DIR => '一時ファイル',
    './cache' => 'キャッシュ'
  );
  
  foreach ($directories as $dir => $dir_name) {
    if (!is_dir($dir)) {
      if (!mkdir($dir, PERMISSION_FOR_DIR)) {
        $err .= $dir_name . "ディレクトリの作成に失敗しました<br>";
        continue;
      }
    }
    
    // ディレクトリの権限を設定
    if (!chmod($dir, PERMISSION_FOR_DIR)) {
      $err .= $dir_name . "ディレクトリの権限設定に失敗しました<br>";
    }
    
    // ディレクトリの安全性をチェック（設定で無効化可能）
    if (!defined('DISABLE_DIRECTORY_PERMISSION_CHECK') || DISABLE_DIRECTORY_PERMISSION_CHECK !== '1') {
      $validation_errors = validate_directory($dir, $dir_name);
      foreach ($validation_errors as $error) {
        $err .= $error . "<br>";
      }
    }
  }
  
  if($err) error($err);
  
  // ファイル整合性チェック（定期的に実行）
  if (ENABLE_FILE_INTEGRITY_CHECK === '1') {
    check_file_integrity();
  }
}

//ユーザーip
function get_uip() {
  if ($userip = getenv("HTTP_CLIENT_IP")) {
    return $userip;
  } elseif ($userip = getenv("HTTP_X_FORWARDED_FOR")) {
    return $userip;
  } elseif ($userip = getenv("REMOTE_ADDR")) {
    return $userip;
  } else {
    return $userip;
  }
}

// エラー処理の改善

// エラーログの設定
define('ERROR_LOG_FILE', __DIR__ . '/error.log');
define('ERROR_LOG_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('ERROR_LOG_MAX_AGE', 30 * 24 * 3600); // 30日

// カスタムエラーハンドラー
function custom_error_handler($errno, $errstr, $errfile, $errline) {
  $error_types = array(
    E_ERROR => 'ERROR',
    E_WARNING => 'WARNING',
    E_PARSE => 'PARSE',
    E_NOTICE => 'NOTICE',
    E_CORE_ERROR => 'CORE_ERROR',
    E_CORE_WARNING => 'CORE_WARNING',
    E_COMPILE_ERROR => 'COMPILE_ERROR',
    E_COMPILE_WARNING => 'COMPILE_WARNING',
    E_USER_ERROR => 'USER_ERROR',
    E_USER_WARNING => 'USER_WARNING',
    E_USER_NOTICE => 'USER_NOTICE',
    E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
    E_DEPRECATED => 'DEPRECATED',
    E_USER_DEPRECATED => 'USER_DEPRECATED'
  );
  
  $error_type = isset($error_types[$errno]) ? $error_types[$errno] : 'UNKNOWN';
  $timestamp = date('Y-m-d H:i:s');
  $ip = get_uip();
  $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';
  
  $log_message = sprintf(
    "[%s] [%s] [IP: %s] [UA: %s] %s in %s on line %d\n",
    $timestamp,
    $error_type,
    $ip,
    substr($user_agent, 0, 100),
    $errstr,
    $errfile,
    $errline
  );
  
  // エラーログファイルに書き込み
  if (is_writable(dirname(ERROR_LOG_FILE)) && ENABLE_ERROR_LOGGING === '1') {
    file_put_contents(ERROR_LOG_FILE, $log_message, FILE_APPEND | LOCK_EX);
    
    // ログファイルのサイズと古いログの管理
    manage_error_log();
  }
  
  // 致命的なエラーの場合はユーザーに表示
  if (in_array($errno, array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR))) {
    if (defined('DISPLAY_ERRORS') && DISPLAY_ERRORS === '1') {
      echo "<div style='color: red; border: 1px solid red; padding: 10px; margin: 10px;'>";
      echo "<strong>システムエラーが発生しました</strong><br>";
      echo "エラー: " . htmlspecialchars($errstr) . "<br>";
      echo "ファイル: " . htmlspecialchars($errfile) . "<br>";
      echo "行: " . $errline;
      echo "</div>";
    } else {
      echo "<div style='color: red; border: 1px solid red; padding: 10px; margin: 10px;'>";
      echo "<strong>システムエラーが発生しました</strong><br>";
      echo "システム管理者にお問い合わせください。";
      echo "</div>";
    }
  }
  
  return true; // 内部エラーハンドラーを実行しない
}

// エラーログの管理
function manage_error_log() {
  if (!file_exists(ERROR_LOG_FILE)) {
    return;
  }
  
  $file_size = filesize(ERROR_LOG_FILE);
  $file_time = filemtime(ERROR_LOG_FILE);
  $current_time = time();
  
  // ファイルサイズが上限を超えた場合
  if ($file_size > ERROR_LOG_MAX_SIZE) {
    $backup_file = ERROR_LOG_FILE . '.backup.' . date('Y-m-d-H-i-s');
    rename(ERROR_LOG_FILE, $backup_file);
  }
  
  // 古いログファイルを削除
  $log_dir = dirname(ERROR_LOG_FILE);
  $files = glob($log_dir . '/error.log.backup.*');
  foreach ($files as $file) {
    if (filemtime($file) < ($current_time - ERROR_LOG_MAX_AGE)) {
      unlink($file);
    }
  }
}

// 例外ハンドラー
function custom_exception_handler($exception) {
  $timestamp = date('Y-m-d H:i:s');
  $ip = get_uip();
  $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';
  
  $log_message = sprintf(
    "[%s] [EXCEPTION] [IP: %s] [UA: %s] %s in %s on line %d\nStack trace:\n%s\n",
    $timestamp,
    $ip,
    substr($user_agent, 0, 100),
    $exception->getMessage(),
    $exception->getFile(),
    $exception->getLine(),
    $exception->getTraceAsString()
  );
  
  // エラーログファイルに書き込み
  if (is_writable(dirname(ERROR_LOG_FILE)) && ENABLE_ERROR_LOGGING === '1') {
    file_put_contents(ERROR_LOG_FILE, $log_message, FILE_APPEND | LOCK_EX);
    manage_error_log();
  }
  
  // ユーザーに表示
  if (defined('DISPLAY_ERRORS') && DISPLAY_ERRORS === '1') {
    echo "<div style='color: red; border: 1px solid red; padding: 10px; margin: 10px;'>";
    echo "<strong>例外が発生しました</strong><br>";
    echo "メッセージ: " . htmlspecialchars($exception->getMessage()) . "<br>";
    echo "ファイル: " . htmlspecialchars($exception->getFile()) . "<br>";
    echo "行: " . $exception->getLine();
    echo "</div>";
  } else {
    echo "<div style='color: red; border: 1px solid red; padding: 10px; margin: 10px;'>";
    echo "<strong>システムエラーが発生しました</strong><br>";
    echo "システム管理者にお問い合わせください。";
    echo "</div>";
  }
}

// 致命的エラーハンドラー
function fatal_error_handler() {
  $error = error_get_last();
  if ($error !== null && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR))) {
    $timestamp = date('Y-m-d H:i:s');
    $ip = get_uip();
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';
    
    $log_message = sprintf(
      "[%s] [FATAL_ERROR] [IP: %s] [UA: %s] %s in %s on line %d\n",
      $timestamp,
      $ip,
      substr($user_agent, 0, 100),
      $error['message'],
      $error['file'],
      $error['line']
    );
    
      // エラーログファイルに書き込み
  if (is_writable(dirname(ERROR_LOG_FILE)) && ENABLE_ERROR_LOGGING === '1') {
    file_put_contents(ERROR_LOG_FILE, $log_message, FILE_APPEND | LOCK_EX);
    manage_error_log();
  }
  }
}

  // セキュリティログ関数
  function log_security_event($event_type, $details, $severity = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $ip = get_uip();
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';
    
    $log_message = sprintf(
      "[%s] [SECURITY_%s] [IP: %s] [UA: %s] %s: %s\n",
      $timestamp,
      strtoupper($severity),
      $ip,
      substr($user_agent, 0, 100),
      $event_type,
      $details
    );
    
    $security_log_file = __DIR__ . '/security.log';
    if (is_writable(dirname($security_log_file)) && ENABLE_SECURITY_LOGGING === '1') {
      file_put_contents($security_log_file, $log_message, FILE_APPEND | LOCK_EX);
    }
  }

// データベースエラーハンドラー
function handle_database_error($exception, $operation = 'unknown') {
  $error_message = sprintf(
    "Database error in operation '%s': %s (Code: %s)",
    $operation,
    $exception->getMessage(),
    $exception->getCode()
  );
  
  // セキュリティログに記録
  log_security_event('DATABASE_ERROR', $error_message, 'ERROR');
  
  // エラーログに記録
  $timestamp = date('Y-m-d H:i:s');
  $ip = get_uip();
  $log_message = sprintf(
    "[%s] [DB_ERROR] [IP: %s] %s\n",
    $timestamp,
    $ip,
    $error_message
  );
  
  if (is_writable(dirname(ERROR_LOG_FILE)) && ENABLE_ERROR_LOGGING === '1') {
    file_put_contents(ERROR_LOG_FILE, $log_message, FILE_APPEND | LOCK_EX);
  }
  
  // ユーザーに表示
  if (defined('DISPLAY_ERRORS') && DISPLAY_ERRORS === '1') {
    return "データベースエラーが発生しました: " . htmlspecialchars($exception->getMessage());
  } else {
    return "データベースエラーが発生しました。システム管理者にお問い合わせください。";
  }
}

// ファイル操作エラーハンドラー
function handle_file_error($operation, $file_path, $error_message) {
  $timestamp = date('Y-m-d H:i:s');
  $ip = get_uip();
  
  $log_message = sprintf(
    "[%s] [FILE_ERROR] [IP: %s] Operation: %s, File: %s, Error: %s\n",
    $timestamp,
    $ip,
    $operation,
    $file_path,
    $error_message
  );
  
  if (is_writable(dirname(ERROR_LOG_FILE)) && ENABLE_ERROR_LOGGING === '1') {
    file_put_contents(ERROR_LOG_FILE, $log_message, FILE_APPEND | LOCK_EX);
  }
  
  // セキュリティログに記録
  log_security_event('FILE_OPERATION_ERROR', "Operation: $operation, File: $file_path, Error: $error_message", 'WARNING');
}

// 入力検証エラーハンドラー
function handle_validation_error($field, $value, $rule) {
  $timestamp = date('Y-m-d H:i:s');
  $ip = get_uip();
  
  $log_message = sprintf(
    "[%s] [VALIDATION_ERROR] [IP: %s] Field: %s, Value: %s, Rule: %s\n",
    $timestamp,
    $ip,
    $field,
    substr($value, 0, 100), // 値の一部のみ記録
    $rule
  );
  
  if (is_writable(dirname(ERROR_LOG_FILE)) && ENABLE_ERROR_LOGGING === '1') {
    file_put_contents(ERROR_LOG_FILE, $log_message, FILE_APPEND | LOCK_EX);
  }
  
  // セキュリティログに記録
  log_security_event('INPUT_VALIDATION_ERROR', "Field: $field, Rule: $rule", 'WARNING');
}

// エラーハンドラーの設定
set_error_handler('custom_error_handler');
set_exception_handler('custom_exception_handler');
register_shutdown_function('fatal_error_handler');

// パスワードのハッシュ化
function hash_password($password) {
  return password_hash($password, PASSWORD_BCRYPT, ['cost' => AUTH_HASH_COST]);
}

// パスワードの検証
function verify_password($password, $hash) {
  return password_verify($password, $hash);
}

// レート制限チェック
function check_rate_limit($ip_address, $username = '') {
  try {
    $result = execute_db_operation(function($db) use ($ip_address, $username) {
      // 最近の失敗した試行回数をカウント
      $stmt = $db->prepare("SELECT COUNT(*) FROM auth_attempts WHERE ip_address = ? AND success = 0 AND attempt_time > datetime('now', '-' || ? || ' seconds')");
      $stmt->execute([$ip_address, AUTH_LOCKOUT_TIME]);
      $failed_attempts = $stmt->fetchColumn();
      
      return $failed_attempts >= AUTH_MAX_ATTEMPTS;
    });
    return $result;
  } catch (Exception $e) {
    error_log("Rate limit check error: " . $e->getMessage());
    return false; // エラーの場合は制限しない
  }
}

// ログイン試行を記録
function log_auth_attempt($ip_address, $username, $success) {
  try {
    execute_db_operation(function($db) use ($ip_address, $username, $success) {
      $stmt = $db->prepare("INSERT INTO auth_attempts (ip_address, username, attempt_time, success) VALUES (?, ?, datetime('now', 'localtime'), ?)");
      return $stmt->execute([$ip_address, $username, $success ? 1 : 0]);
    });
  } catch (Exception $e) {
    error_log("Auth attempt log error: " . $e->getMessage());
  }
}

// セッションの作成
function create_session($user_id, $ip_address) {
  try {
    $session_id = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', time() + AUTH_SESSION_TIMEOUT);
    
    execute_db_operation(function($db) use ($session_id, $user_id, $ip_address, $expires_at) {
      $stmt = $db->prepare("INSERT INTO auth_sessions (session_id, user_id, ip_address, created_at, expires_at) VALUES (?, ?, ?, datetime('now', 'localtime'), ?)");
      return $stmt->execute([$session_id, $user_id, $ip_address, $expires_at]);
    });
    
    return $session_id;
  } catch (Exception $e) {
    error_log("Session creation error: " . $e->getMessage());
    return false;
  }
}

// セッションの検証
function validate_session($session_id, $ip_address) {
  try {
    $result = execute_db_operation(function($db) use ($session_id, $ip_address) {
      $stmt = $db->prepare("SELECT user_id FROM auth_sessions WHERE session_id = ? AND ip_address = ? AND expires_at > datetime('now', 'localtime') AND is_valid = 1");
      $stmt->execute([$session_id, $ip_address]);
      return $stmt->fetchColumn();
    });
    return $result;
  } catch (Exception $e) {
    error_log("Session validation error: " . $e->getMessage());
    return false;
  }
}

// セッションの無効化
function invalidate_session($session_id) {
  try {
    execute_db_operation(function($db) use ($session_id) {
      $stmt = $db->prepare("UPDATE auth_sessions SET is_valid = 0 WHERE session_id = ?");
      return $stmt->execute([$session_id]);
    });
  } catch (Exception $e) {
    error_log("Session invalidation error: " . $e->getMessage());
  }
}

// ユーザー認証
function authenticate_user($username, $password, $ip_address) {
  // レート制限チェック
  if (check_rate_limit($ip_address, $username)) {
    log_auth_attempt($ip_address, $username, false);
    return ['success' => false, 'message' => 'アカウントが一時的にロックされています。しばらく時間をおいてから再試行してください。'];
  }
  
  try {
    $user = execute_db_operation(function($db) use ($username) {
      $stmt = $db->prepare("SELECT id, password_hash FROM auth_users WHERE username = ? AND is_active = 1");
      $stmt->execute([$username]);
      return $stmt->fetch(PDO::FETCH_ASSOC);
    });
    
    if (!$user) {
      log_auth_attempt($ip_address, $username, false);
      return ['success' => false, 'message' => 'ユーザー名またはパスワードが正しくありません。'];
    }
    
    if (!verify_password($password, $user['password_hash'])) {
      log_auth_attempt($ip_address, $username, false);
      return ['success' => false, 'message' => 'ユーザー名またはパスワードが正しくありません。'];
    }
    
    // 成功したログイン
    log_auth_attempt($ip_address, $username, true);
    
    // セッション作成
    $session_id = create_session($user['id'], $ip_address);
    if (!$session_id) {
      return ['success' => false, 'message' => 'セッションの作成に失敗しました。'];
    }
    
    // 最終ログイン時刻を更新
    $user_id = $user['id'];
    execute_db_operation(function($db) use ($user_id) {
      $stmt = $db->prepare("UPDATE auth_users SET last_login = datetime('now', 'localtime') WHERE id = ?");
      return $stmt->execute([$user_id]);
    });
    
    return ['success' => true, 'session_id' => $session_id, 'user_id' => $user['id']];
    
  } catch (Exception $e) {
    error_log("Authentication error: " . $e->getMessage());
    return ['success' => false, 'message' => '認証処理中にエラーが発生しました。'];
  }
}

// 強化されたCSRFトークン作成
function get_csrf_token() {
  if(!isset($_SESSION)) {
    ini_set('session.use_strict_mode', 1);
    session_start();
    header('Expires:');
    header('Cache-Control:');
    header('Pragma:');
  }
  
  // より安全なトークン生成
  if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
  }
  
  // トークンの有効期限チェック（1時間）
  if (time() - $_SESSION['csrf_token_time'] > 3600) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
  }
  
  return $_SESSION['csrf_token'];
}

// 強化されたCSRFトークンチェック
function check_csrf_token() {
  session_start();
  $token = filter_input(INPUT_POST, 'token');
  $session_token = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
  
  if (!$session_token || $token !== $session_token) {
    error('無効なアクセスです');
  }
  
  // トークンの有効期限チェック
  if (time() - $_SESSION['csrf_token_time'] > 3600) {
    error('セッションが期限切れです。再度お試しください。');
  }
}

// データベース接続を取得する関数
function get_db_connection() {
  try {
    $db = new PDO(DB_PDO);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_TIMEOUT, DB_TIMEOUT);
    return $db;
  } catch (PDOException $e) {
    handle_database_error($e, 'connection');
    throw $e;
  }
}

// データベース操作を実行する関数
function execute_db_operation($operation) {
  $retry_count = 0;
  $last_error = null;

  while ($retry_count < DB_RETRY_COUNT) {
    try {
      $db = get_db_connection();
      $result = $operation($db);
      $db = null; // 明示的に接続を閉じる
      return $result;
    } catch (PDOException $e) {
      $last_error = $e;
      handle_database_error($e, 'operation');
      
      if (strpos($e->getMessage(), 'database is locked') !== false) {
        $retry_count++;
        if ($retry_count < DB_RETRY_COUNT) {
          usleep(DB_RETRY_DELAY); // 少し待機してからリトライ
          continue;
        }
      }
      throw $e; // ロック以外のエラー、またはリトライ上限に達した場合は例外を投げる
    }
  }
  throw $last_error;
}

//アップロードしてデータベースへ保存する
function upload() {
  global $req_method;
  global $admin_pass, $up_path, $watchword;

  //CSRFトークンをチェック
  if(CHECK_CSRF_TOKEN){
    check_csrf_token();
  }
  $upfile  = '';
  $invz = '0';

  if($req_method !== "POST") {error('投稿形式が不正です。'); }

  if(UP_AUTH === '0' && (filter_input(INPUT_POST, 'authword') !== $admin_pass && filter_input(INPUT_POST, 'authword') !== $watchword)) {
    error('合言葉が違います。アップロードできません。');
  }
  $userip = get_uip();

  //アップロード処理
  $dest = '';
  $ok_message = '';
  $ng_message = '';
  if(count($_FILES['upfile']['name']) < 1) {
    error('ファイルがないです。');
    exit;
  }
  $ok_num = 0; // 成功したファイル数をカウント
  for ($i = 0; $i < count($_FILES['upfile']['name']); $i++) {
    $origin_file = isset($_FILES['upfile']['name'][$i]) ? basename($_FILES['upfile']['name'][$i]) : "";
    $tmp_file = isset($_FILES['upfile']['tmp_name'][$i]) ? $_FILES['upfile']['tmp_name'][$i] : "";
    
    // ファイル名のサニタイズ
    $origin_file = sanitize_filename($origin_file);
    
    // 拡張子の取得
    $extension = pathinfo($origin_file, PATHINFO_EXTENSION);
    
    // 包括的なファイル検証
    $validation_result = validate_uploaded_file($tmp_file, $origin_file, $extension);
    if (!$validation_result['valid']) {
      $ng_message .= $origin_file.'(' . $validation_result['message'] . '), ';
      continue;
    }
    
    // 安全なファイル名の生成
    $upfile = date("Ymd_His").mt_rand(1000,9999).'.'.$extension;
    $dest = UP_DIR.'/'.$upfile;
    
    // ファイル移動（追加のセキュリティチェック付き）
    if(!move_uploaded_file($tmp_file, $dest)) {
      $ng_message .= $origin_file.'(正常にコピーできませんでした。), ';
      continue;
    }
    
    // ファイル権限の設定
    chmod($dest, PERMISSION_FOR_DEST);
    
    // データベースに保存
    try {
      // ファイルハッシュの計算
      $file_hash = hash_file('sha256', $dest);
      $file_size = filesize($dest);
      
      // 追加のセキュリティチェック
      if (ENABLE_ANTIVIRUS_SCAN === '1') {
        $av_result = antivirus_scan($dest);
        if (!$av_result['valid']) {
          safe_delete_file($dest);
          $ng_message .= $origin_file.'(' . $av_result['message'] . '), ';
          continue;
        }
      }
      
      // 重複チェック
      if (ENABLE_DUPLICATE_CHECK === '1') {
        $dup_result = check_duplicate_file($dest);
        if (!$dup_result['valid']) {
          safe_delete_file($dest);
          $ng_message .= $origin_file.'(' . $dup_result['message'] . '), ';
          continue;
        }
      }
      
      execute_db_operation(function($db) use ($userip, $upfile, $invz, $file_hash, $file_size) {
        $stmt = $db->prepare("INSERT INTO uplog (created, host, upfile, invz, file_hash, file_size) VALUES (datetime('now', 'localtime'), :host, :upfile, :invz, :file_hash, :file_size)");
        $stmt->bindParam(':host', $userip, PDO::PARAM_STR);
        $stmt->bindParam(':upfile', $upfile, PDO::PARAM_STR);
        $stmt->bindParam(':invz', $invz, PDO::PARAM_STR);
        $stmt->bindParam(':file_hash', $file_hash, PDO::PARAM_STR);
        $stmt->bindParam(':file_size', $file_size, PDO::PARAM_INT);
        return $stmt->execute();
      });
      $ok_num++;
      
      // 最終的なセキュリティチェック：アップロードされたファイルの再検証
      if (!file_exists($dest) || !is_readable($dest)) {
        throw new Exception('アップロードされたファイルの検証に失敗しました');
      }
      
      // ファイルサイズの最終確認
      $final_size = filesize($dest);
      if ($final_size === false || $final_size >= UP_MAX_SIZE) {
        unlink($dest);
        throw new Exception('アップロード後のファイルサイズ検証に失敗しました');
      }
      
         } catch (PDOException $e) {
       // データベースエラーの場合はファイルも削除
       if (file_exists($dest)) {
         safe_delete_file($dest);
       }
       error("データベースエラーが発生しました。");
     } catch (Exception $e) {
       // その他のエラーの場合もファイルを削除
       if (file_exists($dest)) {
         safe_delete_file($dest);
       }
       $ng_message .= $origin_file.'(' . $e->getMessage() . '), ';
       continue;
     }
  }
  //ログ行数オーバー処理
  try {
    $th_cnt = execute_db_operation(function($db) {
      $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM uplog");
      $stmt->execute();
      $result = $stmt->fetch();
      return $result["cnt"];
    });
    
    if($th_cnt > LOG_MAX) {
      log_del();
    }
  } catch (PDOException $e) {
    echo "DB接続エラー:" .$e->getMessage();
  }
  result($ok_num,$ng_message);
}

//削除
function del() {

}

//通常表示モード
function def() {
	global $dat,$blade;

  //csrfトークンをセット
	$dat['token']='';
	if(CHECK_CSRF_TOKEN){
		$token = get_csrf_token();
		$_SESSION['token'] = $token;
		$dat['token'] = $token;
	}

  //ファイル数カウント
  try {
    $th_count = execute_db_operation(function($db) {
      $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM uplog");
      $stmt->execute();
      $result = $stmt->fetch();
      return $result["cnt"];
    });

    //ファイル数が圧倒的に多いときは通常表示の時にも消す
    if($th_count > LOG_MAX) {
      log_del();
    }

    //ファイル一覧を取得（実際に存在するファイルのみ）
    $file_list = execute_db_operation(function($db) {
      $stmt = $db->prepare("SELECT * FROM uplog WHERE invz = :invz ORDER BY id DESC");
      $invz = '0';
      $stmt->bindParam(':invz', $invz, PDO::PARAM_STR);
      $stmt->execute();
      
      $files = array();
      while ($row = $stmt->fetch()) {
        $file_path = UP_DIR . '/' . $row['upfile'];
        if (file_exists($file_path)) {
          $files[] = $row;
        }
      }
      return $files;
    });

    //総サイズを計算
    $total_size = calculate_total_size($file_list);
    $dat['total_size'] = $total_size;
    $dat['total_size_formatted'] = format_file_size($total_size);
    
    //ファイル数を計算
    $file_count = count($file_list);
    $dat['file_count'] = $file_count;

    $dat['file_list'] = $file_list;
    echo $blade->run(MAINFILE,$dat);
  } catch (PDOException $e) {
    echo "DB接続エラー:" .$e->getMessage();
  }
}

/* ---------- 細かい関数 ---------- */

/* テンポラリ内のゴミ除去 */
function deltemp() {
  if (!is_dir(TEMP_DIR) || !is_readable(TEMP_DIR)) {
    return false;
  }
  
  $handle = opendir(TEMP_DIR);
  if ($handle === false) {
    return false;
  }
  
  $cleaned_count = 0;
  $error_count = 0;
  
  while (($file = readdir($handle)) !== false) {
    // . と .. をスキップ
    if ($file === '.' || $file === '..') {
      continue;
    }
    
    $file_path = TEMP_DIR . '/' . $file;
    
    // ファイルかどうかを確認
    if (is_file($file_path)) {
      $file_time = filemtime($file_path);
      $current_time = time();
      
      // 24時間以上古いファイルを削除
      if (($current_time - $file_time) > (24 * 3600)) {
        if (unlink($file_path)) {
          $cleaned_count++;
        } else {
          $error_count++;
        }
      }
    }
  }
  closedir($handle);
  
  // クリーンアップ結果をログに記録（オプション）
  if ($cleaned_count > 0 || $error_count > 0) {
    error_log("TEMP cleanup: {$cleaned_count} files cleaned, {$error_count} errors");
  }
  
  return true;
}

// 安全なファイル削除関数
function safe_delete_file($file_path) {
  if (!file_exists($file_path)) {
    return true; // ファイルが存在しない場合は成功とみなす
  }
  
  // ファイルパスの安全性チェック
  $real_path = realpath($file_path);
  $up_dir_real = realpath(UP_DIR);
  
  // アップロードディレクトリ外のファイルは削除しない
  if ($real_path === false || $up_dir_real === false || strpos($real_path, $up_dir_real) !== 0) {
    $error_msg = "Attempted to delete file outside upload directory: " . $file_path;
    handle_file_error('delete', $file_path, $error_msg);
    return false;
  }
  
  // ファイルの権限を確認
  if (!is_writable($file_path)) {
    $error_msg = "Cannot delete file (not writable): " . $file_path;
    handle_file_error('delete', $file_path, $error_msg);
    return false;
  }
  
  $result = unlink($file_path);
  if (!$result) {
    $error_msg = "Failed to delete file: " . $file_path;
    handle_file_error('delete', $file_path, $error_msg);
  }
  
  return $result;
}

// データベース移行関数
function migrate_database() {
  try {
    $db = new PDO(DB_PDO);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // テーブル構造を確認
    $stmt = $db->prepare("PRAGMA table_info(uplog)");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
    
    $migrations_needed = array();
    
    // 新しいカラムが存在するかチェック
    if (!in_array('file_hash', $columns)) {
      $migrations_needed[] = 'file_hash';
    }
    if (!in_array('file_size', $columns)) {
      $migrations_needed[] = 'file_size';
    }
    if (!in_array('scan_result', $columns)) {
      $migrations_needed[] = 'scan_result';
    }
    
    // 認証テーブルが存在するかチェック
    $stmt = $db->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name='auth_users'");
    $stmt->execute();
    $auth_users_exists = $stmt->fetchColumn() !== false;
    
    if (!$auth_users_exists) {
      $migrations_needed[] = 'auth_tables';
    }
    
    // 移行が必要な場合のみ実行
    if (!empty($migrations_needed)) {
      execute_migrations($db, $migrations_needed);
    }
    
    $db = null;
  } catch (PDOException $e) {
    error_log("Database migration error: " . $e->getMessage());
    throw $e;
  }
}

// 移行処理を実行
function execute_migrations($db, $migrations) {
  foreach ($migrations as $migration) {
    switch ($migration) {
      case 'file_hash':
        $db->exec("ALTER TABLE uplog ADD COLUMN file_hash TEXT");
        break;
      case 'file_size':
        $db->exec("ALTER TABLE uplog ADD COLUMN file_size INTEGER");
        break;
      case 'scan_result':
        $db->exec("ALTER TABLE uplog ADD COLUMN scan_result TEXT");
        break;
      case 'auth_tables':
        // 認証テーブルの作成
        $db->exec("CREATE TABLE auth_users (id integer primary key autoincrement, username VARCHAR(100) UNIQUE, password_hash TEXT, created_at timestamp, last_login timestamp, is_active INTEGER DEFAULT 1)");
        $db->exec("CREATE TABLE auth_attempts (id integer primary key autoincrement, ip_address TEXT, username VARCHAR(100), attempt_time timestamp, success INTEGER DEFAULT 0)");
        $db->exec("CREATE TABLE auth_sessions (id integer primary key autoincrement, session_id TEXT UNIQUE, user_id INTEGER, ip_address TEXT, created_at timestamp, expires_at timestamp, is_valid INTEGER DEFAULT 1)");
        break;
    }
  }
  
  // 既存のファイルに対してハッシュとサイズを計算
  if (in_array('file_hash', $migrations) || in_array('file_size', $migrations)) {
    update_existing_files($db);
  }
}

// 既存のファイルのハッシュとサイズを更新
function update_existing_files($db) {
  try {
    $stmt = $db->prepare("SELECT id, upfile FROM uplog WHERE file_hash IS NULL OR file_size IS NULL");
    $stmt->execute();
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($files as $file) {
      $file_path = UP_DIR . '/' . $file['upfile'];
      if (file_exists($file_path)) {
        $file_hash = hash_file('sha256', $file_path);
        $file_size = filesize($file_path);
        
        $update_stmt = $db->prepare("UPDATE uplog SET file_hash = ?, file_size = ? WHERE id = ?");
        $update_stmt->execute([$file_hash, $file_size, $file['id']]);
      }
    }
  } catch (Exception $e) {
    error_log("Error updating existing files: " . $e->getMessage());
  }
}

// ディレクトリの安全性チェック
function validate_directory($dir_path, $dir_name) {
  $errors = array();
  
  if (!is_dir($dir_path)) {
    $errors[] = $dir_name . "ディレクトリが存在しません";
  } else {
    if (!is_writable($dir_path)) {
      $errors[] = $dir_name . "ディレクトリに書き込み権限がありません";
    }
    if (!is_readable($dir_path)) {
      $errors[] = $dir_name . "ディレクトリに読み取り権限がありません";
    }
    
    // ディレクトリの権限をチェック（より柔軟な判定）
    $perms = fileperms($dir_path);
    $owner_writable = ($perms & 0x0200) !== 0; // 所有者の書き込み権限
    $group_writable = ($perms & 0x0010) !== 0; // グループの書き込み権限
    $other_writable = ($perms & 0x0002) !== 0; // その他の書き込み権限
    
    // 少なくとも何らかの書き込み権限があるかチェック
    if (!$owner_writable && !$group_writable && !$other_writable) {
      $errors[] = $dir_name . "ディレクトリに書き込み権限がありません（権限: " . substr(sprintf('%o', $perms), -3) . "）";
    }
    
    // セキュリティログに記録（デバッグ用）
    if (defined('ENABLE_SECURITY_LOGGING') && ENABLE_SECURITY_LOGGING === '1') {
      log_security_event('DIRECTORY_PERMISSION_CHECK', 
        "Directory: $dir_path, Permissions: " . substr(sprintf('%o', $perms), -3) . 
        " (Owner: " . ($owner_writable ? 'W' : '-') . 
        ", Group: " . ($group_writable ? 'W' : '-') . 
        ", Other: " . ($other_writable ? 'W' : '-') . ")", 
        'INFO');
    }
  }
  
  return $errors;
}

// ファイル管理の整合性チェック
function check_file_integrity() {
  try {
    $orphaned_files = 0;
    $missing_files = 0;
    
    // データベースに記録されているが実際には存在しないファイルをチェック
    $db_files = execute_db_operation(function($db) {
      $stmt = $db->prepare("SELECT upfile FROM uplog WHERE invz = '0'");
      $stmt->execute();
      return $stmt->fetchAll(PDO::FETCH_COLUMN);
    });
    
    foreach ($db_files as $db_file) {
      $file_path = UP_DIR . '/' . $db_file;
      if (!file_exists($file_path)) {
        $missing_files++;
        // データベースから該当レコードを削除
        execute_db_operation(function($db) use ($db_file) {
          $stmt = $db->prepare("DELETE FROM uplog WHERE upfile = :upfile");
          $stmt->bindParam(':upfile', $db_file, PDO::PARAM_STR);
          return $stmt->execute();
        });
      }
    }
    
    // アップロードディレクトリ内の孤立したファイルをチェック
    if (is_dir(UP_DIR) && is_readable(UP_DIR)) {
      $handle = opendir(UP_DIR);
      if ($handle !== false) {
        while (($file = readdir($handle)) !== false) {
          if ($file === '.' || $file === '..') {
            continue;
          }
          
          $file_path = UP_DIR . '/' . $file;
          if (is_file($file_path)) {
            // データベースに記録されているかチェック
            $exists_in_db = execute_db_operation(function($db) use ($file) {
              $stmt = $db->prepare("SELECT COUNT(*) FROM uplog WHERE upfile = :upfile");
              $stmt->bindParam(':upfile', $file, PDO::PARAM_STR);
              $stmt->execute();
              return $stmt->fetchColumn() > 0;
            });
            
            if (!$exists_in_db) {
              $orphaned_files++;
              // 孤立したファイルを削除（オプション）
              if (ENABLE_ORPHANED_FILE_CLEANUP === '1') {
                safe_delete_file($file_path);
              }
            }
          }
        }
        closedir($handle);
      }
    }
    
    // 整合性チェックの結果をログに記録
    if ($missing_files > 0 || $orphaned_files > 0) {
      error_log("File integrity check: {$missing_files} missing files, {$orphaned_files} orphaned files");
    }
    
    return array('missing' => $missing_files, 'orphaned' => $orphaned_files);
  } catch (Exception $e) {
    error_log("File integrity check error: " . $e->getMessage());
    return array('missing' => 0, 'orphaned' => 0);
  }
}

//ログの行数が最大値を超えていたら削除
function log_del() {
  try {
    execute_db_operation(function($db) {
      // 最も古いレコードのIDを取得
      $stmt = $db->prepare("SELECT id, upfile FROM uplog ORDER BY id LIMIT 1");
      $stmt->execute();
      $msg = $stmt->fetch();
      
      if ($msg) {
        $dt_id = (int)$msg["id"];
        $upfile = $msg["upfile"];
        
        // 該当IDのレコード数をカウント
        $stmt = $db->prepare("SELECT COUNT(*) as cnti FROM uplog WHERE id = :id");
        $stmt->bindParam(':id', $dt_id, PDO::PARAM_INT);
        $stmt->execute();
        $count_res = $stmt->fetch();
        $log_count = $count_res["cnti"];
        
        // レコードが存在する場合のみ削除
        if($log_count !== 0) {
          // データベースからレコードを削除
          $stmt = $db->prepare("DELETE FROM uplog WHERE id = :id");
          $stmt->bindParam(':id', $dt_id, PDO::PARAM_INT);
          $stmt->execute();
          
          // 対応するファイルも削除
          if (!empty($upfile)) {
            $file_path = UP_DIR . '/' . $upfile;
            safe_delete_file($file_path);
          }
        }
      }
      return true;
    });
  } catch (PDOException $e) {
    echo "DB接続エラー:" .$e->getMessage();
  }
}

//文字コード変換
function charconvert($str) {
  mb_language(LANG);
  return mb_convert_encoding($str, "UTF-8", "auto");
}

//総サイズを計算する関数
function calculate_total_size($file_list) {
  $total_size = 0;
  foreach ($file_list as $file) {
    $file_path = UP_DIR . '/' . $file['upfile'];
    if (file_exists($file_path)) {
      $total_size += filesize($file_path);
    }
  }
  return $total_size;
}

//ファイルサイズをフォーマットする関数
function format_file_size($size) {
  $units = array('B', 'KB', 'MB', 'GB', 'TB');
  $i = 0;
  while ($size >= 1024 && $i < count($units) - 1) {
    $size /= 1024;
    $i++;
  }
  return round($size, 2) . ' ' . $units[$i];
}

//リザルト画面
function result($ok,$err) {
  global $blade,$dat;
  $dat['oknum'] = $ok;
  $dat['errmes'] = $err;
  $dat['othermode'] = 'result';
  echo $blade->run(OTHERFILE,$dat);
}

//OK画面
function ok($mes) {
	global $blade,$dat;
  $dat['okmes'] = $mes;
	$dat['othermode'] = 'ok';
	echo $blade->run(OTHERFILE,$dat);
}
//エラー画面
function error($mes) {
  global $db;
	global $blade,$dat;
  $db = null; //db切断
  $dat['errmes'] = $mes;
	$dat['othermode'] = 'err';
  echo $blade->run(OTHERFILE,$dat);
  exit;
}

// ファイルアップロードのセキュリティ検証関数
function validate_uploaded_file($tmp_file, $origin_file, $extension) {
  // 1. ファイルサイズの基本チェック
  if (!is_uploaded_file($tmp_file)) {
    handle_validation_error('file', $origin_file, 'not_uploaded_file');
    return array('valid' => false, 'message' => '不正なアップロードファイルです');
  }
  
  // 2. ファイルサイズの詳細チェック
  $file_size = filesize($tmp_file);
  if ($file_size === false || $file_size === 0) {
    handle_validation_error('file_size', $origin_file, 'invalid_size');
    return array('valid' => false, 'message' => 'ファイルサイズの取得に失敗しました');
  }
  
  if ($file_size >= UP_MAX_SIZE) {
    handle_validation_error('file_size', $origin_file, 'size_exceeded');
    return array('valid' => false, 'message' => '設定されたファイルサイズをオーバーしています');
  }
  
  // 3. 拡張子の厳密なチェック
  $extension = strtolower($extension);
  $allowed_extensions = explode('|', strtolower(ACCEPT_FILE_EXTN));
  if (!in_array($extension, $allowed_extensions)) {
    handle_validation_error('extension', $extension, 'not_allowed');
    return array('valid' => false, 'message' => '規定外の拡張子です');
  }
  
  // 4. ファイルヘッダーの検証
  $file_handle = fopen($tmp_file, 'rb');
  if ($file_handle === false) {
    handle_file_error('open', $tmp_file, 'Failed to open file for header validation');
    return array('valid' => false, 'message' => 'ファイルの読み込みに失敗しました');
  }
  
  // ファイルの先頭部分を読み込み
  $header = fread($file_handle, 16);
  fclose($file_handle);
  
  // 5. 危険なファイルシグネチャの検出
  $dangerous_signatures = array(
    // PHPファイル
    '<?php', '<?=', '<? ',
    // 実行可能ファイル
    "\x4D\x5A", // MZ (Windows PE)
    "\x7F\x45\x4C\x46", // ELF (Linux)
    "\xFE\xED\xFA", // Mach-O (macOS)
    // スクリプトファイル
    '#!', // Shebang
    // その他の危険なファイル
    "\x50\x4B\x03\x04", // ZIP (潜在的に危険)
  );
  
  foreach ($dangerous_signatures as $signature) {
    if (strpos($header, $signature) === 0) {
      handle_validation_error('file_signature', $origin_file, 'dangerous_signature_detected');
      return array('valid' => false, 'message' => '危険なファイル形式が検出されました');
    }
  }
  
  // 6. より厳密なMIME typeチェック
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mime_type = finfo_file($finfo, $tmp_file);
  finfo_close($finfo);
  
  if ($mime_type === false) {
    handle_validation_error('mime_type', $origin_file, 'mime_detection_failed');
    return array('valid' => false, 'message' => 'MIME typeの取得に失敗しました');
  }
  
  $allowed_mimes = explode(', ', ACCEPT_FILETYPE);
  $mime_found = false;
  
  foreach ($allowed_mimes as $allowed_mime) {
    if (trim($allowed_mime) === $mime_type) {
      $mime_found = true;
      break;
    }
  }
  
  if (!$mime_found) {
    handle_validation_error('mime_type', $mime_type, 'not_allowed_mime');
    return array('valid' => false, 'message' => '規定外のMIME type: ' . $mime_type);
  }
  
  // 7. ファイル拡張子とMIME typeの整合性チェック
  $expected_mime_map = array(
    'mp3' => 'audio/mpeg',
    'm4a' => 'audio/mp4',
    'aac' => 'audio/aac',
    'opus' => 'audio/ogg',
    'ogg' => 'audio/ogg',
    'flac' => 'audio/flac'
  );
  
  if (isset($expected_mime_map[$extension]) && $expected_mime_map[$extension] !== $mime_type) {
    handle_validation_error('mime_consistency', $origin_file, 'extension_mime_mismatch');
    return array('valid' => false, 'message' => 'ファイル拡張子とMIME typeが一致しません');
  }
  
  // 8. ファイル内容の追加検証（オーディオファイルの場合）
  if (strpos($mime_type, 'audio/') === 0) {
    $validation_result = validate_audio_file($tmp_file, $extension);
    if (!$validation_result['valid']) {
      return $validation_result;
    }
  }
  
  return array('valid' => true, 'message' => '検証完了');
}

// オーディオファイルの詳細検証
function validate_audio_file($tmp_file, $extension) {
  $file_handle = fopen($tmp_file, 'rb');
  if ($file_handle === false) {
    return array('valid' => false, 'message' => 'ファイルの読み込みに失敗しました');
  }
  
  $header = fread($file_handle, 32);
  fclose($file_handle);
  
  // 各オーディオ形式のシグネチャをチェック
  $audio_signatures = array(
    'mp3' => array("\xFF\xFB", "\xFF\xF3", "\xFF\xF2", "\x49\x44\x33"), // MP3
    'm4a' => array("\x00\x00\x00\x20\x66\x74\x79\x70", "\x00\x00\x00\x18\x66\x74\x79\x70"), // M4A
    'aac' => array("\xFF\xF1", "\xFF\xF9"), // AAC
    'opus' => array("OggS"), // OGG
    'flac' => array("fLaC") // FLAC
  );
  
  if (isset($audio_signatures[$extension])) {
    $signature_found = false;
    foreach ($audio_signatures[$extension] as $signature) {
      if (strpos($header, $signature) !== false) {
        $signature_found = true;
        break;
      }
    }
    
    if (!$signature_found) {
      return array('valid' => false, 'message' => 'オーディオファイルの形式が正しくありません');
    }
  }
  
  return array('valid' => true, 'message' => 'オーディオファイル検証完了');
}

// ファイル名のサニタイズ
function sanitize_filename($filename) {
  // 危険な文字を除去
  $filename = preg_replace('/[^\w\-\.]/', '_', $filename);
  // 連続するアンダースコアを単一に
  $filename = preg_replace('/_+/', '_', $filename);
  // 先頭と末尾のアンダースコアを除去
  $filename = trim($filename, '_');
  
  return $filename;
}

// ファイルの深層スキャン（新機能）
function deep_scan_file($tmp_file, $extension) {
  // ファイル全体をスキャンして危険なパターンを検出
  $file_handle = fopen($tmp_file, 'rb');
  if ($file_handle === false) {
    return array('valid' => false, 'message' => 'ファイルの読み込みに失敗しました');
  }
  
  $file_size = filesize($tmp_file);
  $chunk_size = 8192; // 8KBずつ読み込み
  $dangerous_patterns = array(
    '<?php', '<?=', '<? ', '<?php', '<?php ',
    'eval(', 'exec(', 'system(', 'shell_exec(',
    'base64_decode(', 'gzinflate(', 'str_rot13(',
    'javascript:', 'vbscript:', 'data:text/html',
    'onload=', 'onerror=', 'onclick=',
    'document.cookie', 'window.location',
    'ActiveXObject', 'WScript.Shell'
  );
  
  $position = 0;
  while ($position < $file_size) {
    $chunk = fread($file_handle, $chunk_size);
    if ($chunk === false) {
      fclose($file_handle);
      return array('valid' => false, 'message' => 'ファイルの読み込み中にエラーが発生しました');
    }
    
    // 危険なパターンをチェック
    foreach ($dangerous_patterns as $pattern) {
      if (stripos($chunk, $pattern) !== false) {
        fclose($file_handle);
        return array('valid' => false, 'message' => '危険なコードパターンが検出されました');
      }
    }
    
    $position += strlen($chunk);
  }
  
  fclose($file_handle);
  return array('valid' => true, 'message' => '深層スキャン完了');
}

// 高度なファイル整合性チェック（新機能）
function check_file_integrity_advanced($tmp_file, $extension) {
  // ファイルの構造を詳細にチェック
  $file_handle = fopen($tmp_file, 'rb');
  if ($file_handle === false) {
    return array('valid' => false, 'message' => 'ファイルの読み込みに失敗しました');
  }
  
  $file_size = filesize($tmp_file);
  
  // 最小ファイルサイズチェック
  $min_sizes = array(
    'mp3' => 100, // 最小100バイト
    'm4a' => 100,
    'aac' => 100,
    'opus' => 100,
    'ogg' => 100,
    'flac' => 100
  );
  
  if (isset($min_sizes[$extension]) && $file_size < $min_sizes[$extension]) {
    fclose($file_handle);
    return array('valid' => false, 'message' => 'ファイルサイズが小さすぎます');
  }
  
  // ファイル構造の検証
  $header = fread($file_handle, 128);
  fclose($file_handle);
  
  // オーディオファイルの構造チェック
  if ($extension === 'mp3') {
    if (!preg_match('/^\xFF[\xFB\xF3\xF2]/', $header) && !preg_match('/^ID3/', $header)) {
      return array('valid' => false, 'message' => 'MP3ファイルの構造が正しくありません');
    }
  } elseif ($extension === 'm4a') {
    if (strpos($header, 'ftyp') === false) {
      return array('valid' => false, 'message' => 'M4Aファイルの構造が正しくありません');
    }
  } elseif ($extension === 'flac') {
    if (strpos($header, 'fLaC') === false) {
      return array('valid' => false, 'message' => 'FLACファイルの構造が正しくありません');
    }
  } elseif ($extension === 'ogg') {
    if (strpos($header, 'OggS') === false) {
      return array('valid' => false, 'message' => 'OGGファイルの構造が正しくありません');
    }
  }
  
  return array('valid' => true, 'message' => 'ファイル整合性チェック完了');
}

// アンチウイルススキャン（新機能）
function antivirus_scan($tmp_file) {
  // カスタムウイルスシグネチャチェック
  $virus_signatures = array(
    // 一般的なマルウェアパターン
    "\x4D\x5A\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00",
    "\x7F\x45\x4C\x46\x01\x01\x01\x00",
    // その他の危険なパターン
  );
  
  $file_handle = fopen($tmp_file, 'rb');
  if ($file_handle === false) {
    return array('valid' => false, 'message' => 'ファイルの読み込みに失敗しました');
  }
  
  $header = fread($file_handle, 64);
  fclose($file_handle);
  
  foreach ($virus_signatures as $signature) {
    if (strpos($header, $signature) !== false) {
      return array('valid' => false, 'message' => 'マルウェアの可能性が検出されました');
    }
  }
  
  return array('valid' => true, 'message' => 'アンチウイルススキャン完了');
}

// ファイルの重複チェック（新機能）
function check_duplicate_file($tmp_file) {
  $file_hash = hash_file('sha256', $tmp_file);
  
  try {
    $result = execute_db_operation(function($db) use ($file_hash) {
      $stmt = $db->prepare("SELECT COUNT(*) FROM uplog WHERE file_hash = ?");
      $stmt->execute([$file_hash]);
      return $stmt->fetchColumn() > 0;
    });
    
    if ($result) {
      return array('valid' => false, 'message' => '同じ内容のファイルが既にアップロードされています');
    }
  } catch (Exception $e) {
    // エラーの場合は重複チェックをスキップ
    error_log("Duplicate check error: " . $e->getMessage());
  }
  
  return array('valid' => true, 'message' => '重複チェック完了');
}
