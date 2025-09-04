<?php
//--------------------------------------------------
//  SELF UPLOADER v0.1.6
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

//アップロードファイル保存ディレクトリ名
define('UP_DIR', 'upfile');

//一時ファイル保存ディレクトリ名
define('TEMP_DIR', 'tmp');

//アップロードに管理パス、もしくは合言葉が必要 必要:0 不要:1
define('UP_AUTH', '0');

//アップロードできるファイルの最大サイズ(MB)
define('UP_MAX_MB', '15');

//アップロード可能なファイルのmimetype。', 'で区切ってください。
define('ACCEPT_FILETYPE', 'audio/mpeg, audio/aac, audio/mp4, audio/ogg, audio/flac');

//アップロード可能なファイルの拡張子。'|'（パイプ）で区切ってください。
define('ACCEPT_FILE_EXTN', 'mp3|m4a|aac|opus|ogg|flac');

// タイムゾーン
define('DEFAULT_TIMEZONE','Asia/Tokyo');

// 言語設定
define('LANG', 'Japanese');

//ここまで

/* ------------- トラブルシューティング 問題なく動作している時は変更しない。 ------------- */

//アップロードされたファイルのパーミッション。
define('PERMISSION_FOR_DEST', 0644);//初期値 0644 (所有者読み書き、他者読み取りのみ)
//ブラウザから直接呼び出さないログファイルのパーミッション
define('PERMISSION_FOR_LOG', 0600);//初期値 0600
//アップロードされたファイルを保存するディレクトリのパーミッション
define('PERMISSION_FOR_DIR', 0755);//初期値 0755 (所有者読み書き実行、他者読み取り実行のみ)

//csrfトークンを使って不正な投稿を拒絶する する:1 しない:0
//する:1 にすると外部サイトからの不正な投稿を拒絶することができます
define('CHECK_CSRF_TOKEN', '1');

// セキュリティ強化設定
// ファイルアップロードの詳細検証を有効にする 有効:1 無効:0
define('ENABLE_DETAILED_VALIDATION', '1');

// 危険なファイルの検出を有効にする 有効:1 無効:0
define('ENABLE_DANGEROUS_FILE_DETECTION', '1');

// ファイルヘッダー検証を有効にする 有効:1 無効:0
define('ENABLE_HEADER_VALIDATION', '1');

// オーディオファイルの詳細検証を有効にする 有効:1 無効:0
define('ENABLE_AUDIO_VALIDATION', '1');

// ファイル整合性チェックを有効にする 有効:1 無効:0
define('ENABLE_FILE_INTEGRITY_CHECK', '1');

// 孤立したファイルの自動削除を有効にする 有効:1 無効:0
define('ENABLE_ORPHANED_FILE_CLEANUP', '1');

// 強化されたパスワード認証を使用する 使用:1 従来方式:0
define('ENABLE_ENHANCED_AUTH', '1');

// 認証システムの定数
define('AUTH_MAX_ATTEMPTS', 5);
define('AUTH_LOCKOUT_TIME', 900);
define('AUTH_SESSION_TIMEOUT', 3600);
define('AUTH_PASSWORD_MIN_LENGTH', 8);
define('AUTH_HASH_COST', 12);

// ファイルアップロード強化設定
// 深層スキャンを有効にする 有効:1 無効:0
define('ENABLE_DEEP_SCAN', '1');

// アンチウイルススキャンを有効にする 有効:1 無効:0
define('ENABLE_ANTIVIRUS_SCAN', '1');

// ファイル重複チェックを有効にする 有効:1 無効:0
define('ENABLE_DUPLICATE_CHECK', '1');

// 高度なファイル整合性チェックを有効にする 有効:1 無効:0
define('ENABLE_ADVANCED_INTEGRITY_CHECK', '1');

// ファイルスキャンの最大サイズ（MB）
define('MAX_SCAN_SIZE', 50);

// 危険なファイルの自動削除を有効にする 有効:1 無効:0
define('ENABLE_AUTO_DELETE_DANGEROUS', '1');

/* ------------- できれば変更してほしくないところ ------------- */
//スクリプト名
define('PHP_SELF', 'index.php');

/* ------------- コンフィグ互換性管理 ------------- */

define('CONF_VER', 20250903);
