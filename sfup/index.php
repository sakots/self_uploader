<?php
//--------------------------------------------------
//  SELF UPLOADER v0.1.0
//  by sakots https://dev.oekakibbs.net/
//--------------------------------------------------

//スクリプトのバージョン
define('SFUP_VER','v0.1.0'); //lot.211027.0

//設定の読み込み
require_once (__DIR__.'/config.php');
require_once (__DIR__.'/templates/'.THEME_DIR.'/theme.ini.php');

//タイムゾーン設定
date_default_timezone_set(DEFAULT_TIMEZONE);

//phpのバージョンが古い場合動作させない
if (($phpver = phpversion()) < "5.6.0") {
    die("PHP version 5.6.0 or higher is required for this program to work. <br>\n(Current PHP version:{$phpver})");
}
//コンフィグのバージョンが古くて互換性がない場合動作させない
if (CONF_VER < 1 || !defined('CONF_VER')) {
    die("コンフィグファイルに互換性がないようです。再設定をお願いします。<br>\n The configuration file is incompatible. Please reconfigure it.");
}
//管理パスが初期値(kanripass)の場合は動作させない
if ($admin_pass === 'kanripass' || $watchword === 'kanripass') {
    die("管理パス、または合言葉が初期設定値のままです！危険なので動かせません。<br>\n The admin pass or watchword is still at its default value! This program can't run it until you fix it.");
}

//BladeOne v4.1
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
$dat['themedir'] = THEME_DIR;

define('UP_MAX_SIZE', UP_MAX_MB*1024*1024);
$dat['up_max_size'] = UP_MAX_SIZE;

$dat['type'] = ACCEPT_FILETYPE;

$dat['use_auth'] = UP_AUTH === '0' ? true : false ;


$dat['t_name'] = THEME_NAME;
$dat['t_ver'] = THEME_VER;

//データベース接続PDO
define('DB_PDO', 'sqlite:'.DB_NAME.'.db');

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
    //$pwd = filter_input(INPUT_POST, 'pwd');
    //$pwdh = password_hash($pwd,PASSWORD_DEFAULT);

    if($req_method !== "POST") {error('投稿形式が不正です。'); }

    if(UP_AUTH === '0' && (filter_input(INPUT_POST, 'authword') !== $admin_pass || filter_input(INPUT_POST, 'authword') !== $watchword)) {
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
    for ($i = 0; $i < count($_FILES['upfile']['name']); $i++) {
        $orgin_file = isset($_FILES['upfile']['name'][$i]) ? basename($_FILES['upfile']['name'][$i]) : "";
	    $tmp_file = isset($_FILES['upfile']['tmp_name'][$i]) ? $_FILES['upfile']['tmp_name'][$i] : "";
        $oknum = 0;
        if($_FILES['upfile']['size'][$i] < UP_MAX_SIZE) {
            $extn = pathinfo($orgin_file, PATHINFO_EXTENSION);
            $upfile = date("Ymd_His").mt_rand(1000,9999).'.'.$extn;
            $dest = UP_DIR.'/'.$upfile;
            move_uploaded_file($tmp_file, $dest);
            chmod($dest, PERMISSION_FOR_DEST);
            if(!is_file($dest)) {
                $ng_message .= $orgin_file.'(正常にコピーできませんでした。), ';
            }
            //拡張子チェック
            if(preg_match('/\A('.ACCEPT_FILE_EXTN.')\z/i', pathinfo($orgin_file, PATHINFO_EXTENSION))) {
                try {
                    $db = new PDO(DB_PDO);
                    $sql = "INSERT INTO uplog (created, host, upfile, invz) VALUES (datetime('now', 'localtime'), '$userip', '$upfile', '$invz')";
                    $db->exec($sql);
                    $db = null; //db切断
                } catch (PDOException $e) {
                    echo "DB接続エラー:" .$e->getMessage();
                }
                $oknum++;
			} else {
                $ng_message .= $orgin_file.'(規定外の拡張子なので削除), ';
                unlink($dest);
            }
        } else {
            $ng_message .= $orgin_file.'(設定されたファイルサイズをオーバー), ';
        }
    }
    //ログ行数オーバー処理
	//ファイル数カウント
	try {
		$db = new PDO(DB_PDO);
		$sqlth = "SELECT COUNT(*) as cnt FROM uplog";
		$countth = $db->query("$sqlth");
		$countth = $countth->fetch();
		$th_cnt = $countth["cnt"];
        $db = null; //db切断
	} catch (PDOException $e) {
		echo "DB接続エラー:" .$e->getMessage();
	}
	if($th_cnt > LOG_MAX) {
		logdel();
	}
    result($oknum,$ng_message);
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
		$db = new PDO(DB_PDO);
		$sqlth = "SELECT COUNT(*) as cnt FROM uplog";
		$countth = $db->query("$sqlth");
		$countth = $countth->fetch();
		$th_cnt = $countth["cnt"];
        $db = null; //db切断
	} catch (PDOException $e) {
		echo "DB接続エラー:" .$e->getMessage();
	}

    //ファイル数が圧倒的に多いときは通常表示の時にも消す
    if($th_cnt > LOG_MAX) {
		logdel();
	}

    //ならべる
	try {
		$db = new PDO(DB_PDO);
		$sql = "SELECT * FROM uplog WHERE invz=0 ORDER BY id DESC";
        $filelist = array();
        $post = $db->query($sql);
        while ($files = $post->fetch()) {
			$filelist[] = $files;
		}

        $dat['filelist'] = $filelist;

        echo $blade->run(MAINFILE,$dat);

		$db = null; //db切断
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
function logdel() {
	//オーバーした行のスレ番号
	try {
		$db = new PDO(DB_PDO);
		$sqlimg = "SELECT * FROM uplog ORDER BY tid LIMIT 1";
		$msgs = $db->prepare($sqlimg);
		$msgs->execute();
		$msg = $msgs->fetch();

		$dtid = (int)$msg["tid"]; //消す行のスレ番号

		//カウント
		$sqlc = "SELECT COUNT(*) as cnti FROM uplog WHERE tid = $dtid";
		$countres = $db->query("$sqlc");
		$countres = $countres->fetch();
		$logcount = $countres["cnti"];
		//削除
		if($logcount !== 0) {
			$delres = "DELETE FROM uplog WHERE tid = $dtid";
			$db->exec($delres);
		}

		$msg = null;
		$dtid = null;
		$db = null; //db切断
	} catch (PDOException $e) {
		echo "DB接続エラー:" .$e->getMessage();
	}
}

//文字コード変換
function charconvert($str) {
    mb_language(LANG);
    return mb_convert_encoding($str, "UTF-8", "auto");
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
