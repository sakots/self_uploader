<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{$title}}</title>
  <link rel="stylesheet" href="templates/{{$theme_dir}}/css/base.min.css">
  <script src="templates/{{$theme_dir}}/js/upload.js"></script>
</head>
<body>
<header>
  <div>
    <h1>{{$title}}</h1>
  </div>
</header>
<main>
  <section id="up">
    <h2>ろだ</h2>
    <p>最大アップロードサイズ: {{$up_max_mb}}MB</p>
    <form action="index.php?mode=upload" method="POST" enctype="multipart/form-data">
      <div class="upload-area">
        <div class="dropbox">
          <svg viewBox="0 0 640 512"><use href="templates/{{$theme_dir}}/icons/cloud-upload-alt.svg#cloud-upload"></svg>
        </div>
        <p>Drag and drop a file or click</p>
        <input type="file" name="upfile[]" id="input-files" accept="{{$type}}" multiple>
      </div>
      <div>
        @if ($use_auth)
        <input type="text" placeholder="あいことば" autocomplete="current-password" name="authword">
        @endif
        <input type="submit" id="submit-btn" value=" うp ">
        @if ($token != null)
        <input type="hidden" name="token" value="{{$token}}">
        @else
        <input type="hidden" name="token" value="">
        @endif
      </div>
    </form>
  </section>
  <section id="filesec">
    <h2>ファイル</h2>
    <div>
      <div class="files">
        <div class="contain">
          <p>ファイル数: {{$file_count}}個 / 総サイズ: {{$total_size_formatted}}</p>
          <ul>
            @foreach ($file_list as $files)
            <li><a href="{{$path}}/{{$files['upfile']}}" target="_top" rel="noopener noreferrer">{{$files['upfile']}}</a></li>
            @endforeach
          </ul>
        </div>
      </div>
    </div>
  </section>
</main>
<footer>
  <!-- 著作権表示 -->
  <div class="copy">
    <p>
      SELF UPLOADER {{$ver}} &copy; 2021-2025 sakots
      <a href="https://github.com/sakots/self_uploader" class="github"><svg viewBox="0 0 496 512"><use href="templates/{{$theme_dir}}/icons/github.svg#github"></svg>github</a>
    </p>
    <p>
      theme - {{$t_name}} {{$t_ver}} by sakots
    </p>
    <p>
      used function -
      <a href="https://github.com/EFTEC/BladeOne" target="_top" rel="noopener noreferrer">BladeOne</a>
    </p>
  </div>
</footer>
</body>
</html>