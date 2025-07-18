<?php
/*
  All Emoncms code is released under the GNU Affero General Public License.
  See COPYRIGHT.txt and LICENSE.txt.

  ---------------------------------------------------------------------
  Emoncms - open source energy visualisation
  Part of the OpenEnergyMonitor project:
  http://openenergymonitor.org
*/

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

// Return all locale directory from all modules.
// If one module has a language it will be detected
function directoryLocaleScan($dir)
{
    $directoryList = array();

    if (isset($dir) && is_readable($dir)) {
        $dir = realpath($dir);

        $directoryList = glob($dir."/Modules/*/locale/*", GLOB_ONLYDIR);
        $directoryList = array_merge($directoryList, glob($dir."/Theme/*/locale/*", GLOB_ONLYDIR));

        $directoryList = array_map(
            function ($item) {
                return basename($item);
            },
            $directoryList
        );
    }

    return array_unique($directoryList);
}

function get_available_languages()
{
    return directoryLocaleScan(dirname(__FILE__));
}

function get_available_languages_with_names()
{
    $available_languages = get_available_languages();
    
    static $language_names = null;
    if ($language_names === null) {
        $json_data = file_get_contents(__DIR__.'/Lib/language_country.json');
        $language_names = json_decode($json_data, true);
    }

    
    $available_languages_with_names = array();
    
    foreach ($available_languages as $code) {
        $available_languages_with_names[$code] = $language_names[$code];
    }
    return $available_languages_with_names;
}

function get_translation_status()
{
    // Load translation status if it exists
    if (file_exists(__DIR__ . '/Lib/translation_status.json')) {
        $status = json_decode(file_get_contents(__DIR__ . '/Lib/translation_status.json'), true);
        // Calculate the percentage of completion for each language
        foreach ($status as $lang => $data) {
            if (isset($data['total']) && $data['total'] > 0) {
                $status[$lang]['prc_complete'] = round(($data['translated'] / $data['total']) * 100, 0);
            } else {
                $status[$lang]['prc_complete'] = 0; // No translations available
            }
        }
        return $status;
    }
    return [];
}

function languagecode_to_name($langs)
{
    static $lang_names = null;
    if ($lang_names === null) {
        $json_data = file_get_contents(__DIR__.'/Lib/language_country.json');
        $lang_names = json_decode($json_data, true);
    }
    foreach ($langs as $key => $val) {
        $lang[$key]=$lang_names[$val];
    }
    asort($lang);
    return $lang;
}


function lang_http_accept()
{
    $langs = array();

    if (!$http_accept_language = server('HTTP_ACCEPT_LANGUAGE')) {
        return $langs;
    }
    
    foreach (explode(',', $http_accept_language) as $lang) {
        $pattern = '/^(?P<primarytag>[a-zA-Z]{2,8})'.
        '(?:-(?P<subtag>[a-zA-Z]{2,8}))?(?:(?:;q=)'.
        '(?P<quantifier>\d\.\d))?$/';

        $splits = array();

        if (preg_match($pattern, $lang, $splits)) {
            $langs[] = !empty($splits['subtag']) ? $splits["primarytag"] . "_" . $splits['subtag'] : $splits["primarytag"];
        }
    }
    return $langs;
}

/***
 * take the values from the given list and save it as the user's language
 * only takes supported language values.
 * @param array $language - array returned by lang_http_accept() - without the validating values
 */
function set_lang($language)
{
    global $settings;
    // DEFAULT - from settings.php (if not in file use 'en_GB')
    $fallback_language = $settings['interface']['default_language'];

    $supported_languages = array(
        'cy' => 'cy_GB',
        'da' => 'da_DK',
        'es' => 'es_ES',
        'fr' => 'fr_FR',
        'it' => 'it_IT',
        'nl' => 'nl_NL',
        'en' => 'en_GB'
    );

/**
 * ORDER OF PREFERENCE WITH LANGUAGE SELECTION
 * -------------------------------------------
 * 1. non logged in users use the browser's language
 * 2. logged in users use their saved language preference
 * 3. logged in users without language saved uses `$default_language` from settings.php
 * 4. else fallback is set to 'en_GB'
*/

    $lang = $fallback_language; // if not found use fallback

    // loop through all given $language values
    // if given language is a key or value in the above list use it
    foreach ($language as $lang_code) {
        $lang_code = htmlspecialchars($lang_code);
        if (isset($supported_languages[$lang_code])) { // key check
            $lang = $supported_languages[$lang_code];
            break;
        } elseif (in_array($lang_code, $supported_languages)) { // value check
            $lang = $lang_code;
            break;
        }
    }
    set_lang_by_user($lang);
}

function set_lang_by_user($lang)
{
    $GLOBALS['language'] = $lang; // set the global language variable
}

function set_emoncms_lang($lang)
{
    // If no language defined use the browser language
    if ($lang == '') {
        $browser_languages = lang_http_accept();
        set_lang($browser_languages);
    } else {
        set_lang_by_user($lang);
    }
}
