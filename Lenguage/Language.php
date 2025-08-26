<?php
/**
 * Clase Language - Manejo de traducciones multiidioma
 */
class Language {
    private $translations = [];
    private $currentLang = 'es';
    private $supportedLangs = ['es', 'en', 'de', 'zh']; // Agregado alemán (de)

    public function __construct($lang = null) {
        if (!$lang) {
            $lang = $this->detectLanguage();
        }
        $this->setLanguage($lang);
    }

    private function detectLanguage() {
        // 1. Verificar si hay idioma en sesión
        if (isset($_SESSION['language']) && in_array($_SESSION['language'], $this->supportedLangs)) {
            return $_SESSION['language'];
        }

        // 2. Verificar parámetro GET/POST
        if (isset($_GET['lang']) && in_array($_GET['lang'], $this->supportedLangs)) {
            return $_GET['lang'];
        }
        if (isset($_POST['lang']) && in_array($_POST['lang'], $this->supportedLangs)) {
            return $_POST['lang'];
        }

        // 3. Detectar del navegador
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
            if (in_array($browserLang, $this->supportedLangs)) {
                return $browserLang;
            }
        }

        // 4. Idioma por defecto
        return 'es';
    }

    /**
     * Establece el idioma actual
     */
    public function setLanguage($lang) {
        if (!in_array($lang, $this->supportedLangs)) {
            $lang = 'es'; // Fallback al español
        }

        $file = __DIR__ . "/languages/{$lang}.php";
        if (file_exists($file)) {
            $this->translations = include $file;
            $this->currentLang = $lang;
            $_SESSION['language'] = $lang;
        } else {
            // Si no existe el archivo, cargar español por defecto
            $this->loadDefaultLanguage();
        }
    }

    /**
     * Carga el idioma por defecto (español)
     */
    private function loadDefaultLanguage() {
        $file = __DIR__ . "/languages/es.php";
        if (file_exists($file)) {
            $this->translations = include $file;
            $this->currentLang = 'es';
            $_SESSION['language'] = 'es';
        }
    }

    /**
     * Obtiene una traducción
     */
    public function get($key, $default = null, $params = []) {
        $translation = $this->translations[$key] ?? $default ?? $key;

        // Si hay parámetros, aplicar sprintf
        if (!empty($params)) {
            return vsprintf($translation, $params);
        }

        return $translation;
    }

    /**
     * Alias corto para get()
     */
    public function t($key, $default = null, $params = []) {
        return $this->get($key, $default, $params);
    }

    /**
     * Obtiene el idioma actual
     */
    public function getCurrentLanguage() {
        return $this->currentLang;
    }

    /**
     * Obtiene todos los idiomas soportados
     */
    public function getSupportedLanguages() {
        return $this->supportedLangs;
    }

    public function __($key, $default = null, $params = []) {
        return $this->get($key, $default, $params);
    }

    /**
     * Verifica si una clave de traducción existe
     */
    public function has($key) {
        return isset($this->translations[$key]);
    }

    /**
     * Obtiene el nombre del idioma actual en su idioma nativo
     */
    public function getLanguageName($lang = null) {
        $lang = $lang ?? $this->currentLang;
        $names = [
            'es' => 'Español',
            'en' => 'English',
            'de' => 'Deutsch', // Agregado alemán
            'zh' => '中文'
        ];
        return $names[$lang] ?? $lang;
    }

    /**
     * Genera el selector de idiomas HTML
     */
    public function getLanguageSelector($currentUrl = null) {
        $currentUrl = $currentUrl ?? $_SERVER['REQUEST_URI'];
        $selector = '<div class="language-selector">';

        foreach ($this->supportedLangs as $lang) {
            $isActive = $lang === $this->currentLang;
            $activeClass = $isActive ? 'active' : '';
            $url = $this->addLangParam($currentUrl, $lang);

            $selector .= sprintf(
                '<a href="%s" class="lang-option %s" data-lang="%s">%s</a>',
                htmlspecialchars($url),
                $activeClass,
                $lang,
                $this->getLanguageName($lang)
            );
        }

        $selector .= '</div>';
        return $selector;
    }

    /**
     * Agrega parámetro de idioma a URL
     */
    private function addLangParam($url, $lang) {
        $separator = strpos($url, '?') !== false ? '&' : '?';
        return $url . $separator . 'lang=' . $lang;
    }

    /**
     * Obtiene información específica del idioma para configuración regional
     */
    public function getLanguageInfo($lang = null) {
        $lang = $lang ?? $this->currentLang;
        $info = [
            'es' => [
                'name' => 'Español',
                'code' => 'es',
                'locale' => 'es_ES',
                'direction' => 'ltr',
                'flag' => '🇪🇸'
            ],
            'en' => [
                'name' => 'English',
                'code' => 'en',
                'locale' => 'en_US',
                'direction' => 'ltr',
                'flag' => '🇺🇸'
            ],
            'de' => [
                'name' => 'Deutsch',
                'code' => 'de',
                'locale' => 'de_DE',
                'direction' => 'ltr',
                'flag' => '🇩🇪'
            ],
            'zh' => [
                'name' => '中文',
                'code' => 'zh',
                'locale' => 'zh_CN',
                'direction' => 'ltr',
                'flag' => '🇨🇳'
            ]
        ];
        return $info[$lang] ?? $info['es'];
    }

    /**
     * Detecta si el idioma usa caracteres especiales (como chino)
     */
    public function usesSpecialCharacters($lang = null) {
        $lang = $lang ?? $this->currentLang;
        $specialCharLangs = ['zh', 'ja', 'ko', 'ar', 'he'];
        return in_array($lang, $specialCharLangs);
    }

    /**
     * Obtiene la configuración de formato de fecha según el idioma
     */
    public function getDateFormat($lang = null) {
        $lang = $lang ?? $this->currentLang;
        $formats = [
            'es' => 'd/m/Y',
            'en' => 'm/d/Y',
            'de' => 'd.m.Y', // Formato alemán
            'zh' => 'Y年m月d日'
        ];
        return $formats[$lang] ?? $formats['es'];
    }

    /**
     * Obtiene la configuración de formato de fecha y hora según el idioma
     */
    public function getDateTimeFormat($lang = null) {
        $lang = $lang ?? $this->currentLang;
        $formats = [
            'es' => 'd/m/Y H:i:s',
            'en' => 'm/d/Y H:i:s',
            'de' => 'd.m.Y H:i:s', // Formato alemán
            'zh' => 'Y年m月d日 H:i:s'
        ];
        return $formats[$lang] ?? $formats['es'];
    }

    /**
     * Obtiene traducciones para JavaScript
     */
    public function getJSTranslations() {
        $jsKeys = [
            'state_text_high',
            'state_text_medium',
            'state_text_low',
            'no_applicable_questions_js',
            'progress_info_title',
            'points_abbr',
            'excluded_na_text',
            'answer_all_required',
            'unanswered_questions_list',
            'processing_audit',
            'audit_processing_error',
            'could_not_start_audit'
        ];

        $jsTranslations = [];
        foreach ($jsKeys as $key) {
            $jsTranslations[$key] = $this->get($key);
        }

        return json_encode($jsTranslations, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Obtiene el código de idioma para HTML lang attribute
     */
    public function getHtmlLangCode($lang = null) {
        $lang = $lang ?? $this->currentLang;
        $codes = [
            'es' => 'es',
            'en' => 'en',
            'de' => 'de', // Código HTML para alemán
            'zh' => 'zh-CN'
        ];
        return $codes[$lang] ?? 'es';
    }

    /**
     * Verifica si el idioma se lee de derecha a izquierda
     */
    public function isRTL($lang = null) {
        $lang = $lang ?? $this->currentLang;
        $rtlLangs = ['ar', 'he', 'fa', 'ur'];
        return in_array($lang, $rtlLangs);
    }

    /**
     * Obtiene la configuración de números según el idioma
     */
    public function getNumberFormat($lang = null) {
        $lang = $lang ?? $this->currentLang;
        $formats = [
            'es' => [
                'decimal_separator' => ',',
                'thousands_separator' => '.'
            ],
            'en' => [
                'decimal_separator' => '.',
                'thousands_separator' => ','
            ],
            'de' => [
                'decimal_separator' => ',',
                'thousands_separator' => '.'
            ], // Formato alemán (similar al español)
            'zh' => [
                'decimal_separator' => '.',
                'thousands_separator' => ','
            ]
        ];
        return $formats[$lang] ?? $formats['es'];
    }

    /**
     * Formatea un número según las convenciones del idioma
     */
    public function formatNumber($number, $decimals = 2, $lang = null) {
        $format = $this->getNumberFormat($lang);
        return number_format(
            $number,
            $decimals,
            $format['decimal_separator'],
            $format['thousands_separator']
        );
    }

    /**
     * Obtiene el saludo apropiado según el idioma y la hora del día
     */
    public function getGreeting($lang = null) {
        $lang = $lang ?? $this->currentLang;
        $hour = date('H');

        $greetings = [
            'es' => [
                'morning' => 'Buenos días',
                'afternoon' => 'Buenas tardes',
                'evening' => 'Buenas noches'
            ],
            'en' => [
                'morning' => 'Good morning',
                'afternoon' => 'Good afternoon',
                'evening' => 'Good evening'
            ],
            'de' => [
                'morning' => 'Guten Morgen',
                'afternoon' => 'Guten Tag',
                'evening' => 'Guten Abend'
            ],
            'zh' => [
                'morning' => '早上好',
                'afternoon' => '下午好',
                'evening' => '晚上好'
            ]
        ];

        $timeOfDay = 'morning';
        if ($hour >= 12 && $hour < 18) {
            $timeOfDay = 'afternoon';
        } elseif ($hour >= 18) {
            $timeOfDay = 'evening';
        }

        return $greetings[$lang][$timeOfDay] ?? $greetings['es'][$timeOfDay];
    }

    /**
     * Obtiene la moneda predeterminada según el idioma/región
     */
    public function getDefaultCurrency($lang = null) {
        $lang = $lang ?? $this->currentLang;
        $currencies = [
            'es' => 'EUR',
            'en' => 'USD',
            'de' => 'EUR',
            'zh' => 'CNY'
        ];
        return $currencies[$lang] ?? 'USD';
    }

    /**
     * Verifica si el idioma requiere fuentes especiales
     */
    public function requiresSpecialFonts($lang = null) {
        $lang = $lang ?? $this->currentLang;
        $specialFontLangs = ['zh', 'ja', 'ko', 'ar', 'he', 'hi', 'th'];
        return in_array($lang, $specialFontLangs);
    }

    /**
     * Obtiene el conjunto de caracteres recomendado para el idioma
     */
    public function getCharset($lang = null) {
        $lang = $lang ?? $this->currentLang;
        // La mayoría de idiomas modernos usan UTF-8
        return 'UTF-8';
    }
}

// Función global de conveniencia para traducir
function __($key, $default = null, $params = []) {
    global $lang;
    if (!$lang) {
        return $key;
    }
    return $lang->get($key, $default, $params);
}

// Función global para obtener traducción con parámetros
function _t($key, $params = []) {
    global $lang;
    if (!$lang) {
        return $key;
    }
    return $lang->get($key, null, $params);
}

// Función global para verificar si existe una traducción
function _has($key) {
    global $lang;
    if (!$lang) {
        return false;
    }
    return $lang->has($key);
}
?>