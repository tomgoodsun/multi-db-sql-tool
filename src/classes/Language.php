<?php

/**
 * 言語管理クラス
 * PSR-12準拠
 */
class Language
{
    private static $currentLanguage = 'en';
    private static $translations = [];
    private static $availableLanguages = [
        'en' => 'English',
        'ja' => '日本語'
    ];

    /**
     * 現在の言語を設定
     */
    public static function setLanguage(string $language): void
    {
        if (array_key_exists($language, self::$availableLanguages)) {
            self::$currentLanguage = $language;
            self::loadTranslations();
            
            // セッションに保存
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['language'] = $language;
            
            // デバッグ情報
            error_log('Language::setLanguage - Set to: ' . $language);
            error_log('Language::setLanguage - Session saved: ' . $_SESSION['language']);
            error_log('Language::setLanguage - Translations loaded: ' . count(self::$translations));
        } else {
            error_log('Language::setLanguage - Invalid language: ' . $language);
        }
    }

    /**
     * 現在の言語を取得
     */
    public static function getCurrentLanguage(): string
    {
        return self::$currentLanguage;
    }

    /**
     * 利用可能な言語一覧を取得
     */
    public static function getAvailableLanguages(): array
    {
        return self::$availableLanguages;
    }

    /**
     * 翻訳を取得
     */
    public static function get(string $key, array $replace = []): string
    {
        if (empty(self::$translations)) {
            self::loadTranslations();
        }

        $translation = self::$translations[$key] ?? $key;

        // プレースホルダーの置換
        if (!empty($replace)) {
            foreach ($replace as $search => $replacement) {
                $translation = str_replace(':' . $search, $replacement, $translation);
            }
        }

        return $translation;
    }

    /**
     * 翻訳ファイルを読み込み
     */
    private static function loadTranslations(): void
    {
        $langFile = __DIR__ . '/../lang/' . self::$currentLanguage . '.php';
        
        error_log('Language::loadTranslations - Loading: ' . $langFile);
        error_log('Language::loadTranslations - File exists: ' . (file_exists($langFile) ? 'yes' : 'no'));
        
        if (file_exists($langFile)) {
            self::$translations = require $langFile;
            error_log('Language::loadTranslations - Loaded ' . count(self::$translations) . ' translations');
        } else {
            // フォールバック: 英語
            $fallbackFile = __DIR__ . '/../lang/en.php';
            error_log('Language::loadTranslations - Using fallback: ' . $fallbackFile);
            if (file_exists($fallbackFile)) {
                self::$translations = require $fallbackFile;
                error_log('Language::loadTranslations - Fallback loaded: ' . count(self::$translations) . ' translations');
            } else {
                error_log('Language::loadTranslations - Fallback file not found!');
            }
        }
    }

    /**
     * セッションから言語設定を復元
     */
    public static function initFromSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // デバッグ情報
        error_log('Language init - Session language: ' . ($_SESSION['language'] ?? 'not set'));
        
        if (isset($_SESSION['language']) && array_key_exists($_SESSION['language'], self::$availableLanguages)) {
            self::setLanguage($_SESSION['language']);
            error_log('Language set to: ' . self::$currentLanguage);
        } else {
            // デフォルトは英語
            self::setLanguage('en');
            error_log('Language set to default: en');
        }
    }

    /**
     * 言語切り替えのAPIエンドポイント
     */
    public static function handleLanguageChange(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_language') {
            $language = $_POST['language'] ?? 'en';
            self::setLanguage($language);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'language' => self::getCurrentLanguage(),
                'message' => self::get('language_changed')
            ]);
            exit;
        }
    }
}
