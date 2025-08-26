<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="refresh" content="7;url=./">
  <title>{{$title}}</title>
  <link rel="stylesheet" href="templates/{{$theme_dir}}/css/base.min.css">
</head>
<body>
  <header>
    <div>
      <h1>{{$title}}</h1>
    </div>
  </header>
  {{-- リザルト画面 --}}
  @if ($othermode == 'result')
  <main>
    <section>
      <h2>結果ログ</h2>
        <p>
          <strong>{{$oknum}}個</strong>のファイルアップロードに成功しました。
        </p>
        @if($errmes)
        <p>
          {{$errmes}}は、失敗しました。
        </p>
        @endif
    </section>
    <section>
      <p>
        <a href="./">[戻る]</a>
      </p>
    </section>
  </main>
  @endif
  {{-- リザルト画面終わり --}}
  {{-- エラー画面 --}}
  @if ($othermode == 'err')
  <main>
    <section>
      <h2>エラーです！</h2>
      <p>
        {{$errmes}}
      </p>
    </section>
    <section>
      <p>
        <a href="./">[戻る]</a>
      </p>
    </section>
  </main>
  @endif
  {{-- エラー画面おわり --}}
  <footer>
    <!-- 著作権表示 -->
    <div class="copy">
      <p>
        SELF UPLOADER {{$ver}} &copy; 2021 sakots
        <a href="https://github.com/sakots/self_uploader" class="github"><svg viewBox="0 0 496 512"><use href="templates/{{$theme_dir}}/icons/github.svg#github"></svg>github</a>
      </p>
      <p>
        theme - {{$t_name}} {{$t_ver}} by sakots
      </p>
    </div>
  </footer>
</body>