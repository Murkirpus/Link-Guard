<?php
// re.php - Рекапча для защиты переходов по ссылкам
session_start();

// Получаем URL или путь
$link = isset($_GET['file']) ? $_GET['file'] : '';

// Функция для проверки URL (поддерживает кириллицу и процентную кодировку)
function isValidUrl($url) {
    // Проверяем, начинается ли с http:// или https://
    if (!preg_match('/^https?:\/\//i', $url)) {
        return false;
    }
    
    // Парсим URL
    $parsed = parse_url($url);
    
    // Проверяем наличие хоста
    if (!isset($parsed['host']) || empty($parsed['host'])) {
        return false;
    }
    
    return true;
}

// Определяем тип пути
$is_full_url = isValidUrl($link);
$is_absolute_path = !empty($link) && $link[0] === '/' && !$is_full_url;
$redirect_url = '';
$link_name = '';

if ($is_full_url) {
    // Это полный URL (http://... или https://...)
    $redirect_url = $link;
    $parsed_path = parse_url($link, PHP_URL_PATH);
    $link_name = $parsed_path ? basename(urldecode($parsed_path)) : parse_url($link, PHP_URL_HOST);
} elseif ($is_absolute_path) {
    // Это абсолютный путь от корня сайта (/sound/...)
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $redirect_url = $protocol . '://' . $host . $link;
    $link_name = basename(urldecode($link));
} else {
    // Неподдерживаемый формат
    die("Неверный формат ссылки! Используйте полный URL или абсолютный путь от корня сайта.");
}

// Генерация токена для защиты от CSRF
function generateToken() {
    if (!isset($_SESSION['form_token'])) {
        $_SESSION['form_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['form_token'];
}

// Генерация времени начала
function setStartTime() {
    if (!isset($_SESSION['form_start_time'])) {
        $_SESSION['form_start_time'] = time();
    }
}

// Проверка на бота
function validateCaptcha() {
    $errors = [];
    
    // 1. Проверка токена
    if (!isset($_POST['token']) || !isset($_SESSION['form_token']) || 
        $_POST['token'] !== $_SESSION['form_token']) {
        $errors[] = "Неверный токен безопасности";
    }
    
    // 2. Проверка чекбокса
    if (!isset($_POST['human_check']) || $_POST['human_check'] !== 'verified') {
        $errors[] = "Пожалуйста, подтвердите, что вы не робот";
    }
    
    // 3. Проверка honeypot
    if (!empty($_POST['website']) || !empty($_POST['email_confirm'])) {
        $errors[] = "Обнаружена подозрительная активность";
    }
    
    // 4. Проверка времени заполнения
    if (isset($_SESSION['form_start_time'])) {
        $time_taken = time() - $_SESSION['form_start_time'];
        if ($time_taken < 2) {
            $errors[] = "Слишком быстрое заполнение формы";
        }
    }
    
    // 5. Проверка JavaScript токена
    if (!isset($_POST['js_token']) || empty($_POST['js_token'])) {
        $errors[] = "JavaScript должен быть включен";
    }
    
    // 6. Проверка активности мыши
    if (!isset($_POST['mouse_moved']) || $_POST['mouse_moved'] !== 'yes') {
        $errors[] = "Не обнаружена активность мыши";
    }
    
    return $errors;
}

// Обработка перехода по ссылке
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validation_errors = validateCaptcha();
    
    if (empty($validation_errors)) {
        $target_url = $_POST['target_url'] ?? '';
        $is_valid_url = isValidUrl($target_url);
        
        // Очищаем сессию
        unset($_SESSION['form_token']);
        unset($_SESSION['form_start_time']);
        
        if ($is_valid_url) {
            // Редирект на указанный URL
            header('Location: ' . $target_url);
            exit;
        } else {
            $error = "Неверный URL для перехода!";
        }
    } else {
        $error = implode(". ", $validation_errors);
    }
}

// Проверяем наличие параметра ссылки
if (empty($link)) {
    die("Не указана ссылка для перехода!");
}

// Инициализация
$token = generateToken();
setStartTime();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Переход: <?php echo htmlspecialchars($link_name); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 100%;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 26px;
            font-weight: 600;
        }
        
        .link-name {
            color: #667eea;
            margin-bottom: 30px;
            padding: 15px;
            background: #f8f9ff;
            border-radius: 8px;
            word-break: break-all;
            border-left: 4px solid #667eea;
        }
        
        .link-url {
            font-size: 12px;
            color: #999;
            margin-top: 8px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .captcha-box {
            border: 2px solid #d3d3d3;
            border-radius: 8px;
            padding: 20px;
            margin: 30px 0;
            background: #f9f9f9;
            transition: all 0.3s ease;
        }
        
        .captcha-box.verified {
            border-color: #4caf50;
            background: #f1f8f4;
        }
        
        .captcha-content {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .checkbox-wrapper {
            position: relative;
        }
        
        .custom-checkbox {
            width: 28px;
            height: 28px;
            border: 2px solid #d3d3d3;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            transition: all 0.3s ease;
        }
        
        .custom-checkbox:hover {
            border-color: #667eea;
        }
        
        .custom-checkbox.checked {
            background: #4caf50;
            border-color: #4caf50;
        }
        
        .checkmark {
            display: none;
            color: white;
            font-size: 20px;
            font-weight: bold;
        }
        
        .custom-checkbox.checked .checkmark {
            display: block;
        }
        
        .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        .custom-checkbox.loading .spinner {
            display: block;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .captcha-text {
            font-size: 16px;
            color: #333;
            user-select: none;
        }
        
        .captcha-logo {
            margin-left: auto;
            font-size: 11px;
            color: #999;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }
        
        .logo-icon {
            width: 32px;
            height: 32px;
            margin-bottom: 4px;
        }
        
        input[type="checkbox"] {
            display: none;
        }
        
        .hidden-field {
            position: absolute;
            left: -9999px;
            width: 1px;
            height: 1px;
        }
        
        button {
            width: 100%;
            padding: 14px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        button.enabled {
            opacity: 1;
            cursor: pointer;
        }
        
        button.enabled:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .error {
            background: #ff4444;
            color: white;
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            animation: shake 0.5s;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        
        .info {
            background: #f0f0f0;
            padding: 12px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 13px;
            color: #666;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Переход по ссылке</h1>
        <div class="link-name">
            🎵 <?php echo htmlspecialchars($link_name); ?>
            <div class="link-url"><?php echo htmlspecialchars($link); ?></div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" id="downloadForm">
            <input type="hidden" name="token" value="<?php echo $token; ?>">
            <input type="hidden" name="human_check" id="humanCheck" value="">
            <input type="hidden" name="js_token" id="jsToken" value="">
            <input type="hidden" name="mouse_moved" id="mouseMoved" value="no">
            <input type="hidden" name="target_url" value="<?php echo htmlspecialchars($redirect_url); ?>">
            
            <!-- Honeypot поля -->
            <input type="text" name="website" class="hidden-field" tabindex="-1" autocomplete="off">
            <input type="email" name="email_confirm" class="hidden-field" tabindex="-1" autocomplete="off">
            
            <div class="captcha-box" id="captchaBox">
                <div class="captcha-content">
                    <div class="checkbox-wrapper">
                        <div class="custom-checkbox" id="customCheckbox">
                            <span class="checkmark">✓</span>
                            <div class="spinner"></div>
                        </div>
                    </div>
                    <span class="captcha-text">Я не робот</span>
                    <div class="captcha-logo">
                        <svg class="logo-icon" viewBox="0 0 24 24" fill="#999">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                        <span>reCAPTCHA</span>
                    </div>
                </div>
            </div>
            
            <button type="submit" id="continueBtn" disabled>Продолжить</button>
        </form>
        
        <div class="info">
            🔒 Защита от автоматических переходов. Подтвердите, что вы человек, поставив галочку.
        </div>
    </div>

    <script>
        document.getElementById('jsToken').value = Math.random().toString(36).substring(2);
        
        let mouseMoved = false;
        document.addEventListener('mousemove', function() {
            if (!mouseMoved) {
                mouseMoved = true;
                document.getElementById('mouseMoved').value = 'yes';
            }
        });
        
        const checkbox = document.getElementById('customCheckbox');
        const captchaBox = document.getElementById('captchaBox');
        const continueBtn = document.getElementById('continueBtn');
        const humanCheck = document.getElementById('humanCheck');
        
        checkbox.addEventListener('click', function() {
            if (this.classList.contains('checked')) {
                return;
            }
            
            this.classList.add('loading');
            
            setTimeout(function() {
                checkbox.classList.remove('loading');
                checkbox.classList.add('checked');
                captchaBox.classList.add('verified');
                humanCheck.value = 'verified';
                continueBtn.disabled = false;
                continueBtn.classList.add('enabled');
            }, 1500);
        });
        
        document.getElementById('continueForm').addEventListener('submit', function(e) {
            if (!checkbox.classList.contains('checked')) {
                e.preventDefault();
                alert('Пожалуйста, подтвердите, что вы не робот');
            }
        });
    </script>
</body>
</html>