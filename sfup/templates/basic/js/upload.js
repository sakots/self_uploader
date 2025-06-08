document.addEventListener('DOMContentLoaded', function() {
    const dropbox = document.querySelector('.dropbox');
    const inputFiles = document.getElementById('input-files');
    const uploadArea = document.querySelector('.upload-area');

    // ドラッグオーバー時のイベント
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    // ドラッグオーバー時のスタイル変更
    ['dragenter', 'dragover'].forEach(eventName => {
        uploadArea.addEventListener(eventName, highlight, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, unhighlight, false);
    });

    function highlight(e) {
        uploadArea.classList.add('highlight');
    }

    function unhighlight(e) {
        uploadArea.classList.remove('highlight');
    }

    // ファイルドロップ時の処理
    uploadArea.addEventListener('drop', handleDrop, false);

    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        inputFiles.files = files;
    }

    // クリックでファイル選択
    dropbox.addEventListener('click', () => {
        inputFiles.click();
    });
}); 