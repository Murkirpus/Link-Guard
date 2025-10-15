<?php
// re.php - Рекапча для защиты переходов по ссылкам
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Получаем URL или путь
$link = isset($_GET['file']) ? $_GET['file'] : '';

// Функция для проверки URL (поддерживает кириллицу и процентную кодировку)
function isValidUrl($url) {
    if (!preg_match('/^https?:\/\//i', $url)) {
        return false;
    }
    $parsed = parse_url($url);
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
    $redirect_url = $link;
    $parsed_path = parse_url($link, PHP_URL_PATH);
    $link_name = $parsed_path ? basename(urldecode($parsed_path)) : parse_url($link, PHP_URL_HOST);
} elseif ($is_absolute_path) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $redirect_url = $protocol . '://' . $host . $link;
    $link_name = basename(urldecode($link));
} else {
    die("Неверный формат ссылки! Используйте полный URL или абсолютный путь от корня сайта.");
}

// Генерация токена (совместимо с PHP 5.x)
function generateToken() {
    if (!isset($_SESSION['form_token'])) {
        if (function_exists('random_bytes')) {
            $_SESSION['form_token'] = bin2hex(random_bytes(32));
        } else {
            $_SESSION['form_token'] = md5(uniqid(mt_rand(), true));
        }
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
    $errors = array();
    
    if (!isset($_POST['token']) || !isset($_SESSION['form_token']) || 
        $_POST['token'] !== $_SESSION['form_token']) {
        $errors[] = "Неверный токен безопасности";
    }
    
    if (!isset($_POST['human_check']) || $_POST['human_check'] !== 'verified') {
        $errors[] = "Пожалуйста, подтвердите, что вы не робот";
    }
    
    if (!empty($_POST['website']) || !empty($_POST['email_confirm'])) {
        $errors[] = "Обнаружена подозрительная активность";
    }
    
    if (isset($_SESSION['form_start_time'])) {
        $time_taken = time() - $_SESSION['form_start_time'];
        if ($time_taken < 2) {
            $errors[] = "Слишком быстрое заполнение формы";
        }
    }
    
    if (!isset($_POST['js_token']) || empty($_POST['js_token'])) {
        $errors[] = "JavaScript должен быть включен";
    }
    
    if (!isset($_POST['mouse_moved']) || $_POST['mouse_moved'] !== 'yes') {
        $errors[] = "Не обнаружена активность мыши";
    }
    
    return $errors;
}

// Обработка перехода по ссылке
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validation_errors = validateCaptcha();
    
    if (empty($validation_errors)) {
        $target_url = isset($_POST['target_url']) ? $_POST['target_url'] : '';
        $is_valid_url = isValidUrl($target_url);
        
        unset($_SESSION['form_token']);
        unset($_SESSION['form_start_time']);
        
        if ($is_valid_url) {
            header('Location: ' . $target_url);
            exit;
        } else {
            $error = "Неверный URL для перехода!";
        }
    } else {
        $error = implode(". ", $validation_errors);
    }
}

if (empty($link)) {
    die("Не указана ссылка для перехода!");
}

$token = generateToken();
setStartTime();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Защищенный переход - <?php echo htmlspecialchars($link_name); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: moveBackground 20s linear infinite;
        }
        
        @keyframes moveBackground {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }
        
        .container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 45px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 550px;
            width: 100%;
            position: relative;
            z-index: 1;
            animation: slideUp 0.6s ease-out;
            margin: auto;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .shield-icon {
            width: 70px;
            height: 70px;
            margin: 0 auto 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .shield-icon svg {
            width: 40px;
            height: 40px;
            fill: white;
        }
        
        h1 {
            color: #2d3748;
            margin-bottom: 8px;
            font-size: 28px;
            font-weight: 700;
        }
        
        .subtitle {
            color: #718096;
            font-size: 14px;
        }
        
        .link-card {
            background: linear-gradient(135deg, #f6f8fb 0%, #e9ecef 100%);
            margin: 25px 0;
            padding: 20px;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .link-card:hover {
            border-color: #667eea;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
        }
        
        .link-icon {
            font-size: 24px;
            margin-bottom: 8px;
        }
        
        .link-title {
            color: #2d3748;
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 8px;
            word-break: break-word;
        }
        
        .link-url {
            color: #667eea;
            font-size: 12px;
            opacity: 0.8;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .cookie-warning {
            display: none;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            text-align: center;
            animation: shake 0.5s;
            box-shadow: 0 8px 25px rgba(255, 107, 107, 0.3);
        }
        
        .cookie-warning.show {
            display: block;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        
        .cookie-warning h3 {
            margin-bottom: 12px;
            font-size: 20px;
            font-weight: 700;
        }
        
        .cookie-warning p {
            margin-bottom: 15px;
            line-height: 1.6;
        }
        
        .instructions {
            background: rgba(255, 255, 255, 0.15);
            padding: 15px;
            border-radius: 8px;
            font-size: 13px;
            margin-top: 12px;
            text-align: left;
            line-height: 1.8;
        }
        
        .instructions strong {
            display: block;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .reload-btn {
            margin-top: 15px;
            padding: 12px 24px;
            background: white;
            color: #ff6b6b;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .reload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .captcha-box {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin: 25px 0;
            background: #f7fafc;
            transition: all 0.3s ease;
        }
        
        .captcha-box.verified {
            border-color: #48bb78;
            background: linear-gradient(135deg, #f0fff4 0%, #c6f6d5 100%);
            box-shadow: 0 4px 15px rgba(72, 187, 120, 0.2);
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
            width: 32px;
            height: 32px;
            border: 2.5px solid #cbd5e0;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .custom-checkbox:hover {
            border-color: #667eea;
            transform: scale(1.05);
        }
        
        .custom-checkbox.checked {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            border-color: #48bb78;
            animation: checkboxSuccess 0.5s ease;
        }
        
        @keyframes checkboxSuccess {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .checkmark {
            display: none;
            color: white;
            font-size: 22px;
            font-weight: bold;
        }
        
        .custom-checkbox.checked .checkmark {
            display: block;
            animation: checkmarkAppear 0.3s ease;
        }
        
        @keyframes checkmarkAppear {
            from {
                transform: scale(0) rotate(-45deg);
            }
            to {
                transform: scale(1) rotate(0deg);
            }
        }
        
        .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid #e2e8f0;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        .custom-checkbox.loading .spinner {
            display: block;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .captcha-text {
            font-size: 17px;
            color: #2d3748;
            font-weight: 500;
            user-select: none;
        }
        
        .captcha-logo {
            margin-left: auto;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            opacity: 0.7;
        }
        
        .logo-icon {
            width: 36px;
            height: 36px;
            margin-bottom: 4px;
            opacity: 0.8;
        }
        
        .logo-text {
            font-size: 11px;
            color: #718096;
            font-weight: 500;
        }
        
        .hidden-field {
            position: absolute;
            left: -9999px;
            width: 1px;
            height: 1px;
        }
        
        .submit-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #cbd5e0 0%, #a0aec0 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 17px;
            font-weight: 700;
            cursor: not-allowed;
            transition: all 0.3s;
            opacity: 0.6;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .submit-btn.enabled {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            opacity: 1;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .submit-btn.enabled:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
        }
        
        .submit-btn.enabled:active {
            transform: translateY(0);
        }
        
        .error {
            background: linear-gradient(135deg, #fc8181 0%, #f56565 100%);
            color: white;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
            animation: shake 0.5s;
            box-shadow: 0 4px 15px rgba(252, 129, 129, 0.3);
            font-weight: 500;
        }
        
        .info {
            background: linear-gradient(135deg, #e6f3ff 0%, #cfe8fc 100%);
            padding: 15px;
            border-radius: 12px;
            margin-top: 25px;
            font-size: 13px;
            color: #2c5282;
            line-height: 1.7;
            border-left: 4px solid #4299e1;
        }
        
        .form-disabled {
            opacity: 0.5;
            pointer-events: none;
        }
        
        @media (max-width: 600px) {
            body {
                padding: 15px;
            }
            
            body::before {
                display: none;
            }
            
            .container {
                padding: 30px 20px;
                margin: 20px auto;
            }
            
            h1 {
                font-size: 24px;
            }
            
            .shield-icon {
                width: 60px;
                height: 60px;
                margin-bottom: 12px;
            }
            
            .header {
                margin-bottom: 25px;
            }
            
            .cookie-warning h3 {
                font-size: 18px;
            }
            
            .instructions {
                font-size: 12px;
            }
            
            .link-card {
                padding: 18px;
                margin: 20px 0;
            }
            
            .captcha-box {
                padding: 18px;
                margin: 20px 0;
            }
        }
        
        @media (max-height: 700px) {
            .shield-icon {
                width: 55px;
                height: 55px;
                margin-bottom: 10px;
            }
            
            .header {
                margin-bottom: 20px;
            }
            
            .container {
                padding: 30px;
                margin: 15px auto;
            }
            
            .link-card {
                margin: 18px 0;
                padding: 15px;
            }
            
            .captcha-box {
                margin: 18px 0;
                padding: 15px;
            }
        }
        
        @media (max-width: 600px) and (max-height: 700px) {
            body {
                padding: 10px;
            }
            
            .container {
                padding: 25px 18px;
                margin: 15px auto;
            }
            
            .shield-icon {
                width: 50px;
                height: 50px;
            }
            
            h1 {
                font-size: 22px;
            }
            
            .header {
                margin-bottom: 18px;
            }
            
            .link-card {
                margin: 15px 0;
            }
            
            .captcha-box {
                margin: 15px 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="shield-icon">
                <svg viewBox="0 0 24 24">
                    <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"/>
                </svg>
            </div>
            <h1>Защищенный переход</h1>
            <p class="subtitle">Проверка безопасности перед переходом</p>
        </div>
        
        <div class="cookie-warning" id="cookieWarning">
            <h3>⚠️ Cookies отключены</h3>
            <p>Для защиты от ботов требуется включить cookies в вашем браузере.</p>
            <div class="instructions">
                <strong>Как включить cookies:</strong>
                <strong>Chrome/Edge:</strong> Настройки → Конфиденциальность → Разрешить все cookies<br>
                <strong>Firefox:</strong> Настройки → Приватность → Стандартная защита<br>
                <strong>Safari:</strong> Настройки → Конфиденциальность → Снять "Блокировать все cookies"
            </div>
            <button onclick="location.reload()" class="reload-btn">
                🔄 Обновить страницу
            </button>
        </div>
        
        <div class="link-card">
            <div class="link-icon">🔗</div>
            <div class="link-title"><?php echo htmlspecialchars($link_name); ?></div>
            <div class="link-url"><?php echo htmlspecialchars($link); ?></div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="?file=<?php echo urlencode($link); ?>" id="continueForm" style="display: none;">
            <input type="hidden" name="token" value="<?php echo $token; ?>">
            <input type="hidden" name="human_check" id="humanCheck" value="">
            <input type="hidden" name="js_token" id="jsToken" value="">
            <input type="hidden" name="mouse_moved" id="mouseMoved" value="no">
            <input type="hidden" name="target_url" value="<?php echo htmlspecialchars($redirect_url); ?>">
            
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
                        <svg class="logo-icon" viewBox="0 0 24 24" fill="#718096">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                        <span class="logo-text">reCAPTCHA</span>
                    </div>
                </div>
            </div>
            
            <button type="submit" id="continueBtn" class="submit-btn" disabled>Продолжить</button>
        </form>
        
        <div class="info">
            🔒 <strong>Защита активирована.</strong> Подтвердите, что вы человек, поставив галочку выше. Это защищает от автоматических переходов и ботов.
        </div>
    </div>

    <script>
        function checkCookies() {
            document.cookie = "test_cookie=1; path=/";
            const cookiesEnabled = document.cookie.indexOf("test_cookie") !== -1;
            
            if (!cookiesEnabled) {
                document.getElementById('cookieWarning').classList.add('show');
                document.getElementById('continueForm').classList.add('form-disabled');
                return false;
            } else {
                document.getElementById('continueForm').style.display = 'block';
            }
            return true;
        }
        
        window.addEventListener('DOMContentLoaded', function() {
            checkCookies();
        });
        
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
            if (!checkCookies()) {
                e.preventDefault();
                alert('Пожалуйста, включите cookies для продолжения');
                return false;
            }
            
            if (!checkbox.classList.contains('checked')) {
                e.preventDefault();
                alert('Пожалуйста, подтвердите, что вы не робот');
                return false;
            }
        });
    </script>
</body>
</html>
