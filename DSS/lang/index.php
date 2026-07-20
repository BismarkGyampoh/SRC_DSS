<?php

$availableLanguages = [
    'en' => 'English',
    'gh' => 'Fante',
];

function getCurrentLanguage(): string
{
    if (isset($_SESSION['language'])) {
        return $_SESSION['language'];
    }
    if (isset($_COOKIE['language'])) {
        return $_COOKIE['language'];
    }
    return 'en';
}

function setLanguage(string $lang): void
{
    $lang = strtolower($lang);
    if (!isset($GLOBALS['availableLanguages'][$lang])) {
        $lang = 'en';
    }
    $_SESSION['language'] = $lang;
    setcookie('language', $lang, time() + 365 * 24 * 60 * 60, '/');
}

function __(string $key): string
{
    $lang = getCurrentLanguage();
    $langFile = __DIR__ . '/' . $lang . '.php';
    if (!file_exists($langFile)) {
        $langFile = __DIR__ . '/en.php';
    }
    $translations = require $langFile;
    return $translations[$key] ?? $key;
}

function languageSwitcher(): string
{
    $current = getCurrentLanguage();
    $html = '<select id="languageSwitcher" style="background:none;border:none;color:#fff;font-size:0.8rem;cursor:pointer;outline:none;">';
    foreach ($GLOBALS['availableLanguages'] as $code => $name) {
        $selected = $code === $current ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    $html .= '</select>';
    return $html;
}
