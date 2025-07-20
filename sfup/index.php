<?php
//--------------------------------------------------
//  SELF UPLOADER v0.1.0
//  by sakots https://dev.oekakibbs.net/
//--------------------------------------------------

//スクリプトのバージョン
define('SFUP_VER','v0.1.3'); //lot.250714.0

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
if (CONF_VER < 20250714 || !defined('CONF_VER')) {
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
  try {
    if (!is_file(DB_NAME.'.db')) {
      // はじめての実行なら、テーブルを作成
      $db = new PDO(DB_PDO);
      $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
      $sql = "CREATE TABLE uplog (id integer primary key autoincrement, created timestamp, name VARCHAR(1000), sub VARCHAR(1000), com VARCHAR(10000), host TEXT, pwd TEXT, upfile TEXT, age INT, invz VARCHAR(1) )";
      $db = $db->query($sql);
      $db = null; //db切断
    }
  } catch (PDOException $e) {
    echo "DB接続エラー:" .$e->getMessage();
  }
  if (!is_dir(UP_DIR)) {
    mkdir(UP_DIR, PERMISSION_FOR_DIR);
    chmod(UP_DIR, PERMISSION_FOR_DIR);
  }
  if(!is_dir(UP_DIR)) $err.= UP_DIR."がありません<br>";
  if(!is_writable(UP_DIR)) $err.= UP_DIR."を書けません<br>";
  if(!is_readable(UP_DIR)) $err.= UP_DIR."を読めません<br>";

  if (!is_dir(TEMP_DIR)) {
    mkdir(TEMP_DIR, PERMISSION_FOR_DIR);
    chmod(TEMP_DIR, PERMISSION_FOR_DIR);
  }
  if(!is_dir(TEMP_DIR)) $err.= TEMP_DIR."がありません<br>";
  if(!is_writable(TEMP_DIR)) $err.= TEMP_DIR."を書けません<br>";
  if(!is_readable(TEMP_DIR)) $err.= TEMP_DIR."を読めません<br>";
  if($err) error($err);
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
//csrfトークンを作成
function get_csrf_token() {
  if(!isset($_SESSION)) {
    ini_set('session.use_strict_mode', 1);
    session_start();
    header('Expires:');
    header('Cache-Control:');
    header('Pragma:');
  }
  return hash('sha256', session_id(), false);
}
//csrfトークンをチェック
function check_csrf_token() {
  session_start();
  $token=filter_input(INPUT_POST,'token');
  $session_token=isset($_SESSION['token']) ? $_SESSION['token'] : '';
  if(!$session_token||$token!==$session_token){
    error('無効なアクセスです');
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
    
    // ファイルサイズチェック
    if($_FILES['upfile']['size'][$i] >= UP_MAX_SIZE) {
      $ng_message .= $origin_file.'(設定されたファイルサイズをオーバー), ';
      continue;
    }
    
    // 拡張子チェック
    $extension = pathinfo($origin_file, PATHINFO_EXTENSION);
    if(!preg_match('/\A('.ACCEPT_FILE_EXTN.')\z/i', $extension)) {
      $ng_message .= $origin_file.'(規定外の拡張子), ';
      continue;
    }
    
    // MIME typeチェック
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $tmp_file);
    finfo_close($finfo);
    
    $allowed_mimes = explode(', ', ACCEPT_FILETYPE);
    if(!in_array($mime_type, $allowed_mimes)) {
      $ng_message .= $origin_file.'(規定外のMIME type: ' . $mime_type . '), ';
      continue;
    }
    
    // ファイル移動
    $upfile = date("Ymd_His").mt_rand(1000,9999).'.'.$extension;
    $dest = UP_DIR.'/'.$upfile;
    
    if(!move_uploaded_file($tmp_file, $dest)) {
      $ng_message .= $origin_file.'(正常にコピーできませんでした。), ';
      continue;
    }
    
    chmod($dest, PERMISSION_FOR_DEST);
    
    // データベースに保存
    try {
      execute_db_operation(function($db) use ($userip, $upfile, $invz) {
        $stmt = $db->prepare("INSERT INTO uplog (created, host, upfile, invz) VALUES (datetime('now', 'localtime'), :host, :upfile, :invz)");
        $stmt->bindParam(':host', $userip, PDO::PARAM_STR);
        $stmt->bindParam(':upfile', $upfile, PDO::PARAM_STR);
        $stmt->bindParam(':invz', $invz, PDO::PARAM_STR);
        return $stmt->execute();
      });
      $ok_num++;
    } catch (PDOException $e) {
      // データベースエラーの場合はファイルも削除
      unlink($dest);
      error("データベースエラーが発生しました。");
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
  $handle = opendir(TEMP_DIR);
  while ($file = readdir($handle)) {
    if(!is_dir($file)) {
      $lapse = time() - filemtime(TEMP_DIR.'/'.$file);
      if($lapse > (24*3600)){
        unlink(TEMP_DIR.'/'.$file);
      }
    }
  }
  closedir($handle);
}

//ログの行数が最大値を超えていたら削除
function log_del() {
  try {
    execute_db_operation(function($db) {
      // 最も古いレコードのIDを取得
      $stmt = $db->prepare("SELECT id FROM uplog ORDER BY id LIMIT 1");
      $stmt->execute();
      $msg = $stmt->fetch();
      
      if ($msg) {
        $dt_id = (int)$msg["id"];
        
        // 該当IDのレコード数をカウント
        $stmt = $db->prepare("SELECT COUNT(*) as cnti FROM uplog WHERE id = :id");
        $stmt->bindParam(':id', $dt_id, PDO::PARAM_INT);
        $stmt->execute();
        $count_res = $stmt->fetch();
        $log_count = $count_res["cnti"];
        
        // レコードが存在する場合のみ削除
        if($log_count !== 0) {
          $stmt = $db->prepare("DELETE FROM uplog WHERE id = :id");
          $stmt->bindParam(':id', $dt_id, PDO::PARAM_INT);
          $stmt->execute();
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
