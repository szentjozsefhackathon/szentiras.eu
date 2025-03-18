<?php

namespace SzentirasHu\Service\Sphinx;

use PDO;

class SphinxSearch
{
  protected $_index_name;
  protected $_search_string;
  protected $_config;
  protected $_time;
  private int $_limit = 100;
  private bool $_groupGepi = false;
  private bool $_countOnly = false;
  private array $_filters = [];
  private PDO $_pdo;

  public function __construct($string)
  {
    $host = \Config::get('settings.sphinxHost');
    $port = \Config::get('settings.sphinxPort');
    $dsn = "mysql:host=$host;port=$port";
    $this->_pdo = new PDO($dsn);
    $this->_search_string = $string;
  }

  public function limit($limit)
  {
    $this->_limit = $limit;
    return $this;
  }

  public function filter($field, $value)
  {
    $this->_filters[] = [$field, $value];
    return $this;
  }

  public function groupGepi($groupGepi)
  {
    $this->_groupGepi = $groupGepi;
    return $this;
  }

  public function countOnly($countOnly)
  {
    $this->_countOnly = $countOnly;
    return $this;
  }

  /**
   * @return array
   * @throws \ErrorException
   */
  public function get() : array
  {
    foreach ($this->_filters as $filter) {
      if (is_array($filter[1])) {
        $placeholders = [];
        foreach ($filter[1] as $i => $value) {
          $placeholders[] = "?";
        }
        $filters[] = "{$filter[0]} IN (" . implode(',', $placeholders) . ")";
      } else {
        $filters[] = "{$filter[0]} = ?";
      }
    }
    $this->_filters[] = ["MATCH(?)", $this->_search_string];
    $filters[] = "MATCH(?)";
    $filterString = implode(' AND ', $filters);
    $query = $this->_pdo->prepare("SELECT " . ($this->_countOnly ? "count(*)" : " *, WEIGHT() ") . " FROM verse, verseroot WHERE {$filterString} " . ($this->_groupGepi ? "GROUP BY gepi" : "") . " ORDER BY WEIGHT() DESC, gepi ASC LIMIT {$this->_limit} OPTION field_weights=(verse=100,verseroot=10),index_weights=(verse=2,verseroot=1)");
    $i = 1;
    foreach ($this->_filters as $filter) {
      if (is_array($filter[1])) {
        foreach ($filter[1] as $index => $value) {
          $query->bindValue($i, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
          $i++;
        }
      } else {
        $query->bindValue($i, $filter[1], is_int($filter[1]) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $i++;
      }
    }

    $query->execute();
    $result = $query->fetchAll(\PDO::FETCH_ASSOC);

    return $result;
  }

    /**
   * @param $verses
   * @param $text
   * @return array|false
   */
  public function buildExcerpts($verses, $text)
  {
    // It seems that Sphinx 3 doesn't handle this API correctly, so we go with a simpler approach
    // Before we had this:  $excerpts = $this->_connection->buildExcerpts($verses, $index, $words, $opts);
    // split the $text to words. Go through $verses, and in each $verse, if any word is found, replace it with <b>$word</b>
    $words = explode(' ', $text);
    $excerpts = [];
    foreach ($verses as $id => $verse) {
      $excerpt = $this->boldWords($words, $verse);
      $excerpts[$id] = $excerpt;
    }

    return $excerpts;
  }

  function boldWords(array $words, string $text): string {
    // Sort the words descending by length.
    // This ensures that if one word is a substring of another,
    // the longest one is matched first.
    usort($words, function($a, $b) {
        return strlen($b) - strlen($a);
    });

    // Escape each word for safe regex use (using '/' as our delimiter).
    $escapedWords = array_map(function($word) {
        return preg_quote($word, '/');
    }, $words);

    // Create a pattern by joining the words with alternation.
    // For example, "/planet|plane|net/".
    $pattern = '/' . implode('|', $escapedWords) . '/';

    // Use preg_replace_callback to wrap each match with <b> tags.
    $result = preg_replace_callback($pattern, function($match) {
        return '<b>' . $match[0] . '</b>';
    }, $text);

    return $result;
}
}
