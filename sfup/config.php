<?php
//--------------------------------------------------
//  SELF UPLOADER v0.0.1～
//  by sakots >> https://dev.oekakibbs.net/
//
//  SELF UPLOADER の設定ファイルです。
//
//--------------------------------------------------

/* ---------- 最低限設定する項目 ---------- */

//管理者パスワード
//必ず変更してください ! kanripass のままではプログラムは動作しません !
$admin_pass = 'kanripass';

//アップロード合言葉
//デフォルトでは管理者パスワードと同一です。
//設定するとこれ、もしくは管理者パスワードでアップロードできるようになります
//さらに設定で不要にもできます。
//こちらもkanripass のままではプログラムは動作しません。
$watchword = 'kanripass';

//以上です

/*----------絶対に設定が必要な項目はここまでです。ここから下は必要に応じて。----------*/

//アップローダーのタイトル
define('SFUP_TITLE', 'self uploader');

//初期設定のままの場合、sfup.dbとなります。
//拡張子は.dbで固定です。
define('DB_NAME', 'sfup');

//テーマのディレクトリ名
//見た目が変わります。いまはこれしかないです。
define('THEME_DIR', 'basic');

//最大ファイル保持数
//古いファイルから順番に消えます
define('LOG_MAX', '75');

//1ページに表示するファイル数
define('PAGE_DEF', '20');

//アップロードファイル保存ディレクトリ名
define('UP_DIR', 'upfile');

//一時ファイル保存ディレクトリ名
define('TEMP_DIR', 'tmp');

//アップロードに管理パス、もしくは合言葉が必要 必要:0 不要:1
define('UP_AUTH', '0');

//アップロードできるファイルの最大サイズ(MB)
define('UP_MAX_MB', '15');

//アップロード可能なファイルの種類(mimetypeです)
define('ACCEPT_FILETYPE', 'audio/mpeg, audio/aac, audio/mp4, audio/ogg, audio/flac, audio/x-flac');

// タイムゾーン
define('DEFAULT_TIMEZONE','Asia/Tokyo');

// 言語設定
define('LANG', 'Japanese');

//ここまで

/* ------------- トラブルシューティング 問題なく動作している時は変更しない。 ------------- */

//アップロードされたファイルのパーミッション。
define('PERMISSION_FOR_DEST', 0606);//初期値 0606
//ブラウザから直接呼び出さないログファイルのパーミッション
define('PERMISSION_FOR_LOG', 0600);//初期値 0600
//アップロードされたファイルを保存するディレクトリのパーミッション
define('PERMISSION_FOR_DIR', 0707);//初期値 0707

//csrfトークンを使って不正な投稿を拒絶する する:1 しない:0
//する:1 にすると外部サイトからの不正な投稿を拒絶することができます
define('CHECK_CSRF_TOKEN', '1');

/* ------------- できれば変更してほしくないところ ------------- */
//スクリプト名
define('PHP_SELF', 'index.php');

/* ------------- コンフィグ互換性管理 ------------- */

define('CONF_VER', 1);
