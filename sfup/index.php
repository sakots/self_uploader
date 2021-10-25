<!DOCTYPE html>
<?php
    //--------------------------------------------------
    //  SELF UPLOADER v0.0.1
    //  by sakots https://dev.oekakibbs.net/
    //--------------------------------------------------

    //スクリプトのバージョン
    define('SFUP_VER','v0.0.1'); //lot.211026.0
    //設定の読み込み
    require(__DIR__.'/config.php');
    //タイムゾーン設定
    date_default_timezone_set(DEFAULT_TIMEZONE);

    //phpのバージョンが古い場合動かさせない
    if (($phpver = phpversion()) < "5.6.0") {
	    die("PHP version 5.6.0 or higher is required for this program to work. <br>\n(Current PHP version:{$phpver})");
    }
    //コンフィグのバージョンが古くて互換性がない場合動かさせない
    if (CONF_VER < 1 || !defined('CONF_VER')) {
	    die("コンフィグファイルに互換性がないようです。再設定をお願いします。<br>\n The configuration file is incompatible. Please reconfigure it.");
    }
    //管理パスが初期値(kanripass)の場合は動作させない
    if ($admin_pass === 'kanripass') {
        die("管理パスが初期設定値のままです！危険なので動かせません。<br>\n The admin pass is still at its default value! This program can't run it until you fix it.");
    }

    //絶対パス取得
    $up_path = realpath("./").'/'.UP_DIR;
    $temp_path = realpath("./").'/'.'temp';

    //データベース接続PDO
    define('DB_PDO', 'sqlite:'.DB_NAME.'.db');

    //初期設定
    init();

    $req_method = isset($_SERVER["REQUEST_METHOD"]) ? $_SERVER["REQUEST_METHOD"]: "";
    //INPUT_SERVER が動作しないサーバがあるので$_SERVERを使う。

    /* ----------- main ------------- */

    function init() {
        global $up_path,$temp_path;
        try {
            if (!is_file(DB_NAME.'.db')) {
                // はじめての実行なら、テーブルを作成
                $db = new PDO(DB_PDO);
                $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
                $sql = "CREATE TABLE tablelog (tid integer primary key autoincrement, created timestamp, name VARCHAR(1000), sub VARCHAR(1000), com VARCHAR(10000), host TEXT, pwd TEXT, upfile TEXT, age INT, invz VARCHAR(1) )";
                $db = $db->query($sql);
                $db = null; //db切断
            }
        } catch (PDOException $e) {
            echo "DB接続エラー:" .$e->getMessage();
        }
        if (!file_exists(UP_DIR)) {
            mkdir($up_path, PERMISSION_FOR_DIR);
        }
        if (!file_exists('temp')) {
            mkdir($temp_path, PERMISSION_FOR_DIR);
        }
    }

    //ユーザーip
    function get_uip(){
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
    function get_csrf_token(){
        if(!isset($_SESSION)){
            session_start();
        }
        header('Expires:');
        header('Cache-Control:');
        header('Pragma:');
        return hash('sha256', session_id(), false);
    }
    //csrfトークンをチェック
    function check_csrf_token(){
        session_start();
        $token=filter_input(INPUT_POST,'token');
        $session_token=isset($_SESSION['token']) ? $_SESSION['token'] : '';
        if(!$session_token||$token!==$session_token){
            error('無効なアクセスです');
        }
    }

    /* ---------- 細かい関数 ---------- */

    // 文字コード変換
    function charconvert($str){
        mb_language(LANG);
            return mb_convert_encoding($str, "UTF-8", "auto");
    }

    //エラー画面
    function error($mes) {
        global $db;
        $db = null; //db切断
        echo($mes.'<br><a href="./">戻る</a>');
        exit;
    }

?>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><? echo(SFUP_TITLE); ?></title>
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/color.css">
</head>

<body>
<header>
    <h1><? echo(SFUP_TITLE); ?></h1>
</header>
<main>
    <form action="" method="POST" enctype="multipart/form-data">
        <div class="upload-area">
            <img src="./img/cloud-upload-alt.svg" alt="">
            <p>Drag and drop a file or click</p>
            <input type="file" name="upfile[]" id="input-files" multiple>
        </div>
        <input type="submit" id="submit-btn" value="送信">
    </form>
</main>
<footer>
    <!-- 著作権表示 -->
    <div class="copy">
        <p>
            SELF UPLOADER <? echo(SFUP_VER); ?> by sakots
            <a href="https://github.com/sakots/self_uploader" class="github">sauce flom github <img src="./img/github.svg" alt=""></a>
        </p>
    </div>
</footer>
</body>
</html>