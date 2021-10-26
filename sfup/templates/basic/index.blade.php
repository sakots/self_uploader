<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{$title}}</title>
    <link rel="stylesheet" href="templates/{{$themedir}}/scss/base.min.css">
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
        <form action="" method="POST" enctype="multipart/form-data">
            <div class="upload-area">
                <div class="dropbox">
                    <svg viewBox="0 0 640 512"><use href="templates/{{$themedir}}/icons/cloud-upload-alt.svg#cloud-upload"></svg>
                </div>
                <p>Drag and drop a file or click</p>
                <input type="file" name="upfile[]" id="input-files" accept="{{$type}}" multiple>
            </div>
            <div>
                @if ($use_auth) <input type="text" placeholder="あいことば" autocomplete="current-password" name="authword"> @endif
                <input type="submit" id="submit-btn" value=" うp ">
            </div>
        </form>
    </section>
    <section id="files">
        <h2>ファイル</h2>
        <div>

        </div>
    </section>
</main>
<footer>
    <!-- 著作権表示 -->
    <div class="copy">
        <p>
            SELF UPLOADER {{$ver}} &copy; 2021 sakots
            <a href="https://github.com/sakots/self_uploader" class="github"><svg viewBox="0 0 496 512"><use href="templates/{{$themedir}}/icons/github.svg#github"></svg>github</a>
        </p>
        <p>
            theme - {{$t_name}} {{$t_ver}} by sakots
        </p>
    </div>
</footer>
</body>
</html>