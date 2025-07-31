<?php
session_save_path('/tmp');
session_start();

require_once __DIR__ . '/getid3/getid3.php';

$selectedFolder = isset($_GET['folder']) ? basename($_GET['folder']) : null;
$songData = [];

if (!isset($_SESSION['can_download'])) {
    $_SESSION['can_download'] = false;
}

if ($selectedFolder) {
    $dir = __DIR__ . "/list_folder/$selectedFolder";
    if (is_dir($dir)) {
        $files = scandir($dir);
        $getID3 = new getID3;
        foreach ($files as $file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, ['mp3', 'm4a'])) {
                $path = "$dir/$file";
                $info = $getID3->analyze($path);
                getid3_lib::CopyTagsToComments($info);

                $durationSeconds = isset($info['playtime_seconds']) ? round($info['playtime_seconds']) : 0;
                $durationFormatted = gmdate("i:s", $durationSeconds);
                $artist = $info['comments']['artist'][0] ?? '';

                $songData[] = [
                    'filename' => $file,
                    'duration' => $durationFormatted,
                    'artist' => $artist
                ];
            }
            if (count($songData) >= 20) break;
        }
    }
}

if (isset($_GET['unlock']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $_SESSION['can_download'] = true;
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>player — Version 5</title>
  <style>
    #controls {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 1rem;
      margin-top: 1rem;
    }
    #modeToggle {
      margin-top: 1rem;
    }
    li {
      cursor: grab;
      margin-bottom: 0.5em;
    }
    li.playing {
      font-weight: bold;
      color: darkblue;
    }
    audio {
      width: 75%;
    }
    #waveform {
      width: 75%;
      margin: 20px auto;
    }
    .download-button {
      margin-left: 10px;
    }
  </style>
  <script src="https://unpkg.com/wavesurfer.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
</head>
<body>
  <h1>player — Version 5</h1>

<?php if (!$selectedFolder): ?>
  <h2>Select a Folder to Play:</h2>
  <ul>
    <?php
    $listDir = __DIR__ . '/list_folder';
    $folders = array_filter(glob("$listDir/*"), 'is_dir');
    foreach ($folders as $folder) {
        $name = basename($folder);
        echo "<li><a href='?folder=$name'>$name</a></li>";
    }
    ?>
  </ul>
<?php else: ?>
  <button onclick="window.location.href='?';">Return to list of song folders</button>
  <h2>Now Playing From: <?= htmlspecialchars($selectedFolder) ?>
  <strong>         </strong>[ click song to start, Toggle Mode, scroll down for controls]</h2>
  <p>🔀 Drag songs to reorder playback.</p>

  <!-- Wombat Icon -->
  <img src="wombat.png" alt="wombat" width="50" style="cursor:pointer" onclick="triggerDownloadUnlock()">

  <!-- Play Mode Toggle -->
  <button id="modeToggle">Mode: Play One</button>

  <!-- Song List -->
  <ul id="songList"></ul>

  <!-- Waveform Container -->
  <div id="waveform"></div>

  <!-- Audio Controls -->
  <div id="controls">
    <button id="leftBtn">⏪</button>
    <audio id="player" controls></audio>
    <button id="rightBtn">⏩</button>
  </div>

  <script>
    const selectedFolder = <?= json_encode($selectedFolder) ?>;
    let songData = <?= json_encode($songData) ?>;
    let downloadsUnlocked = <?= $_SESSION['can_download'] ? 'true' : 'false' ?>;

    const player = document.getElementById('player');
    const songList = document.getElementById('songList');
    const leftBtn = document.getElementById('leftBtn');
    const rightBtn = document.getElementById('rightBtn');
    const modeToggle = document.getElementById('modeToggle');

    let currentIndex = -1;
    let playAll = false;
    let lastLeftClick = 0;
    let lastRightClick = 0;
    let atSongEnd = false;

    const waveContainer = document.getElementById('waveform');
    const wavesurfer = WaveSurfer.create({
      container: waveContainer,
      waveColor: '#ddd',
      progressColor: '#76c7c0',
      height: 100,
      responsive: true,
    });

    function renderSongList() {
      songList.innerHTML = '';
      songData.forEach((song, index) => {
        const li = document.createElement('li');
        li.setAttribute('data-index', index);
        li.innerHTML = '<strong>' + song.filename + '</strong>' +
                       (song.artist ? ' — ' + song.artist : '') +
                       (song.duration ? ' (' + song.duration + ')' : '');
        li.onclick = () => playSong(index);

        if (downloadsUnlocked) {
          const dlBtn = document.createElement('button');
          dlBtn.textContent = 'Download';
          dlBtn.className = 'download-button';
          dlBtn.onclick = (e) => {
            e.stopPropagation();
            window.location.href = `download.php?folder=${encodeURIComponent(selectedFolder)}&file=${encodeURIComponent(song.filename)}`;
          };
          li.appendChild(dlBtn);
        }

        songList.appendChild(li);
      });
    }

    function triggerDownloadUnlock() {
      const pw = prompt("Enter download password:");
      if (pw === 'letmein') {
        fetch(window.location.pathname + "?folder=" + encodeURIComponent(selectedFolder) + "&unlock=true")
          .then(() => {
            downloadsUnlocked = true;
            renderSongList();
          });
      } else {
        alert("Incorrect password.");
      }
    }

    function updateSongOrderFromDOM() {
      const newOrder = [];
      const items = songList.querySelectorAll('li');
      items.forEach(li => {
        const index = parseInt(li.getAttribute('data-index'));
        newOrder.push(songData[index]);
      });
      songData = newOrder;
      renderSongList();
    }

    function playSong(index) {
      if (index < 0 || index >= songData.length) return;
      currentIndex = index;
      const src = `list_folder/${selectedFolder}/` + songData[index].filename;
      player.src = src;
      wavesurfer.stop();
      wavesurfer.empty();
      wavesurfer.load(src);
      player.play();
      atSongEnd = false;
      highlightCurrent();
    }

    function highlightCurrent() {
      const items = songList.getElementsByTagName('li');
      for (let i = 0; i < items.length; i++) {
        items[i].classList.toggle('playing', i === currentIndex);
      }
    }

    player.addEventListener('play', () => wavesurfer.play());
    player.addEventListener('pause', () => wavesurfer.pause());
    player.addEventListener('seeked', () => {
      if (player.duration) {
        wavesurfer.seekTo(player.currentTime / player.duration);
      }
    });
    player.addEventListener('ended', () => {
      atSongEnd = true;
      if (playAll && currentIndex < songData.length - 1) {
        playSong(currentIndex + 1);
      }
    });

    modeToggle.onclick = () => {
      playAll = !playAll;
      modeToggle.textContent = 'Mode: ' + (playAll ? 'Play All' : 'Play One');
    };

    leftBtn.onclick = () => {
      const now = Date.now();
      if (now - lastLeftClick < 1000) {
        if (currentIndex > 0) playSong(currentIndex - 1);
      } else {
        player.currentTime = 0;
        player.play();
      }
      lastLeftClick = now;
    };

    rightBtn.onclick = () => {
      const now = Date.now();
      if (playAll) {
        if (currentIndex < songData.length - 1) playSong(currentIndex + 1);
      } else {
        if (now - lastRightClick < 1000 || atSongEnd) {
          if (currentIndex < songData.length - 1) playSong(currentIndex + 1);
        } else {
          player.currentTime = player.duration || 0;
        }
      }
      lastRightClick = now;
    };

    renderSongList();

    Sortable.create(songList, {
      animation: 150,
      onEnd: updateSongOrderFromDOM
    });
  </script>
<?php endif; ?>
</body>
</html>
