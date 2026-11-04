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
  /** @var array<int, string> */
  private array $_groupBy = [];
  private ?string $_groupTieBreak = null;
  private ?string $_orderTieBreak = null;
  private ?string $_ranker = null;
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

  public function match(string $string): self
  {
    $this->_search_string = $string;
    return $this;
  }

  /**
   * Collapses the hits to one per distinct value of the given attributes, keeping the best
   * weighted hit of each group; among equally weighted hits the one with the lowest tie break
   * expression.
   *
   * @param  array<int, string>  $attributes
   */
  public function groupBy(array $attributes, ?string $tieBreak = null): self
  {
    $this->_groupBy = $attributes;
    $this->_groupTieBreak = $tieBreak;
    return $this;
  }

  /**
   * Orders equally weighted hits by the given expression, lowest first, before their place.
   */
  public function orderTieBreak(string $expression): self
  {
    $this->_orderTieBreak = $expression;
    return $this;
  }

  /**
   * A ranking expression over Sphinx's ranking factors instead of the default ranker.
   */
  public function ranker(string $expression): self
  {
    $this->_ranker = $expression;
    return $this;
  }

  /**
   * The words of the text as the index holds them, in order, each with the share of the
   * indexed verses it stands in.
   *
   * @return array<int, array{word: string, share: float}>
   */
  public function keywords(string $text): array
  {
    $status = $this->_pdo->query("SHOW INDEX verse STATUS")->fetchAll(PDO::FETCH_KEY_PAIR);
    $documentCount = max(1, (int) ($status['indexed_documents'] ?? 0));
    $query = $this->_pdo->prepare("CALL KEYWORDS(?, 'verse', 1)");
    $query->execute([$text]);
    return array_map(fn (array $keyword): array => [
      'word' => (string) $keyword['normalized'],
      'share' => (int) $keyword['docs'] / $documentCount,
    ], $query->fetchAll(PDO::FETCH_ASSOC));
  }

  /**
   * The number of hits (or groups) of the last query, regardless of its limit.
   */
  public function totalFound(): int
  {
    $meta = $this->_pdo->query("SHOW META LIKE 'total_found'")->fetch(PDO::FETCH_ASSOC);
    return (int) ($meta['Value'] ?? 0);
  }

  /**
   * Sphinx's ranking factors of the given verses against a match of the verse field: the
   * longest run of the matched words in query order, the number of distinct words matched,
   * and whether the field is exactly the query. Verses the match does not hit are missing.
   *
   * @param  array<int, int>  $ids
   * @return array<int, array{lcs: int, word_count: int, exact_hit: int}>
   */
  public function verseFieldFactors(string $match, array $ids): array
  {
    if ($ids === []) {
      return [];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $query = $this->_pdo->prepare("SELECT id, PACKEDFACTORS() AS factors FROM verse WHERE MATCH(?) AND id IN ({$placeholders}) LIMIT " . count($ids) . " OPTION ranker=expr('sum(lcs)')");
    $query->bindValue(1, $match, PDO::PARAM_STR);
    foreach (array_values($ids) as $i => $id) {
      $query->bindValue($i + 2, $id, PDO::PARAM_INT);
    }
    $query->execute();
    $factors = [];
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $field = json_decode($row['factors'], true)['fields'][0] ?? [];
      $factors[(int) $row['id']] = [
        'lcs' => (int) ($field['lcs'] ?? 0),
        'word_count' => (int) ($field['word_count'] ?? 0),
        'exact_hit' => (int) ($field['exact_hit'] ?? 0),
      ];
    }
    return $factors;
  }

  /**
   * @return array
   * @throws \ErrorException
   */
  public function get(): array
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
    if ($this->_countOnly) {
      $queryString = "SELECT count(distinct id) as hitcount FROM verse, verseroot WHERE {$filterString}";
    } else {
      $columns = "*, WEIGHT()";
      if ($this->_groupBy !== []) {
        $grouping = "GROUP BY " . implode(', ', $this->_groupBy) . " WITHIN GROUP ORDER BY WEIGHT() DESC";
        if ($this->_groupTieBreak !== null) {
          $columns .= ", {$this->_groupTieBreak} AS tie_break";
          $grouping .= ", tie_break ASC";
        }
      } else {
        $grouping = $this->_groupGepi ? "GROUP BY gepi" : "";
      }
      $ordering = "WEIGHT() DESC, gepi ASC";
      if ($this->_orderTieBreak !== null) {
        $columns .= ", {$this->_orderTieBreak} AS order_tie_break";
        $ordering = "WEIGHT() DESC, order_tie_break ASC, gepi ASC";
      }
      $ranker = $this->_ranker !== null ? "ranker=expr('{$this->_ranker}'), " : "";
      $queryString = "SELECT {$columns} FROM verse, verseroot WHERE {$filterString} {$grouping} ORDER BY {$ordering} LIMIT {$this->_limit} OPTION {$ranker}field_weights=(verse=100,verseroot=10),index_weights=(verse=2,verseroot=1)";
    }
    $query = $this->_pdo->prepare($queryString);
    
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

  public function getGreekNormalizations()
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
    if ($this->_countOnly) {
      $queryString = "SELECT count(distinct id) as hitcount FROM greekverse WHERE {$filterString}";
    } else {
      $queryString = "SELECT *, WEIGHT() FROM greekverse WHERE {$filterString} " . ($this->_groupGepi ? "GROUP BY gepi" : "") . " ORDER BY WEIGHT() DESC, gepi ASC LIMIT {$this->_limit}";
    }
    
    $query = $this->_pdo->prepare($queryString);
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
    $text = preg_replace('/\s+/', ' ', $text);
    $words = explode(' ', trim($text));
    $excerpts = [];
    foreach ($verses as $id => $verse) {
      $excerpt = $this->boldWords($words, $verse);
      $excerpts[$id] = $excerpt;
    }

    return $excerpts;
  }

  function boldWords(array $words, string $text): string
  {
    // Sort the words descending by length.
    // This ensures that if one word is a substring of another,
    // the longest one is matched first.
    usort($words, function ($a, $b) {
      return strlen($b) - strlen($a);
    });

    // Escape each word for safe regex use (using '/' as our delimiter).
    $escapedWords = array_map(function ($word) {
      return preg_quote($word, '/');
    }, $words);

    // Create a pattern by joining the words with alternation.
    // For example, "/planet|plane|net/".
    $pattern = '/' . implode('|', $escapedWords) . '/i';

    // Use preg_replace_callback to wrap each match with <b> tags.
    $result = preg_replace_callback($pattern, function ($match) {
      return '<b>' . $match[0] . '</b>';
    }, $text);

    return $result;
  }
}
