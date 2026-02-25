<?php

declare(strict_types=1);

namespace Genaker\MagentoMcpAi\RAG;

/**
 * RAG-StopWords-Helper-Class
 *
 * @package Genaker\MagentoMcpAi\RAG
 */
final class StopWords
{
  /**
   * @var array
   */
  private static $availableLanguages = array(
      'ar',
      'bg',
      'ca',
      'cz',
      'da',
      'de',
      'el',
      'en',
      'eo',
      'es',
      'et',
      'fi',
      'fr',
      'hi',
      'hr',
      'hu',
      'id',
      'it',
      'ka',
      'lt',
      'lv',
      'nl',
      'no',
      'pl',
      'pt',
      'ro',
      'ru',
      'sk',
      'sv',
      'tr',
      'uk',
      'vi'
  );

  /**
   * @var array
   */
  private $stopWords = array();

  /**
   * Load language-data from one language.
   *
   * @param string $language
   *
   * @throws StopWordsLanguageNotExists
   */
  private function loadLanguageData(string $language = 'de')
  {
    if (\in_array($language, self::$availableLanguages, true) === false) {
      throw new StopWordsLanguageNotExists('language not supported: ' . $language);
    }

    $this->stopWords[$language] = $this->getData($language);
  }

  /**
   * Get data from "stopwords/*.php".
   *
   * @param string $file
   *
   * @return array <p>Will return an empty array on error.</p>
   */
  private function getData(string $file): array
  {
    static $RESULT_STOP_WORDS_CACHE = array();

    if (isset($RESULT_STOP_WORDS_CACHE[$file])) {
      return $RESULT_STOP_WORDS_CACHE[$file];
    }

    $file = __DIR__ . '/stopwords/' . $file . '.php';
    if (file_exists($file)) {
      /** @noinspection PhpIncludeInspection */
      $RESULT_STOP_WORDS_CACHE[$file] = require $file;
    } else {
      $RESULT_STOP_WORDS_CACHE[$file] = array();
    }

    return $RESULT_STOP_WORDS_CACHE[$file];
  }

  /**
   * Get the stop-words from one language.
   *
   * @param string $language
   *
   * @return array
   *
   * @throws StopWordsLanguageNotExists
   */
  public function getStopWordsFromLanguage(string $language = 'de'): array
  {
    if (\in_array($language, self::$availableLanguages, true) === false) {
      throw new StopWordsLanguageNotExists('language not supported: ' . $language);
    }

    if (!isset($this->stopWords[$language])) {
      $this->loadLanguageData($language);
    }

    return $this->stopWords[$language];
  }

  private function loadLanguageDataAll()
  {
    foreach (self::$availableLanguages as $language) {
      if (!isset($this->stopWords[$language])) {
        $this->loadLanguageData($language);
      }
    }
  }


  /**
   * Remove stop-words from a text.
   *
   * @param string $text
   * @param string $lng
   *
   * @return string
   */
  public function removeStopWords($text, $lng = 'en'){
    $stopWords = array_flip($this->getStopWordsFromLanguage($lng));

    // Split into “words” (including punctuation)
    $tokens = preg_split('/(\W+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

    $filtered = array_filter($tokens, function($tok) use ($stopWords) {
        $lower = mb_strtolower($tok);
        // keep punctuation and words not in stop‑list
        return isset($stopWords[$lower]) === false || preg_match('/\W/u', $tok);
    });

    return implode('', $filtered);
  }
  /**
   * Get all stop-words from all languages.
   *
   * @return array
   *
   * @throws StopWordsLanguageNotExists
   */
  public function getStopWordsAll(): array
  {
    $this->loadLanguageDataAll();

    return $this->stopWords;
  }
}
