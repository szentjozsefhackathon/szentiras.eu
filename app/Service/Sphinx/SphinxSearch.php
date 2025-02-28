<?php

namespace SzentirasHu\Service\Sphinx;

class SphinxSearch
{
  protected $_connection;
  protected $_index_name;
  protected $_search_string;
  protected $_config;
  protected $_total_count;
  protected $_time;

  public function __construct()
  {
    $host = \Config::get('settings.sphinxHost');
    $port = \Config::get('settings.sphinxPort');
    $this->_connection = new \Sphinx\SphinxClient();
    $this->_connection->setServer($host, $port);
    $this->_connection->setMatchMode(\Sphinx\SphinxClient::SPH_MATCH_ANY);
    $this->_connection->setSortMode(\Sphinx\SphinxClient::SPH_SORT_RELEVANCE);
    $this->_config = \Config::get('settings.sphinxIndexes');
    reset($this->_config);
    $this->_index_name = isset($this->_config['name']) ? implode(',', $this->_config['name']) : key($this->_config);
  }

  /**
   * @param $string
   * @param null $index_name
   * @return $this
   */
  public function search($string, $index_name = NULL)
  {
    $this->_search_string = $string;
    if (NULL !== $index_name) {
      //if index name contains , or ' ', multiple index search
      if (strpos($index_name, ' ') || strpos($index_name, ',')) {
        if (!isset($this->_config['mapping'])) {
          $this->_config['mapping'] = false;
        }
      }
      $this->_index_name = $index_name;
    }

    $this->_connection->resetFilters();

    return $this;
  }

  /**
   * @param $weights
   * @return $this
   */
  public function setFieldWeights($weights)
  {
    $this->_connection->setFieldWeights($weights);
    return $this;
  }

  /**
   * @param $weights
   * @return $this
   */
  public function setIndexWeights($weights)
  {
    $this->_connection->setFieldWeights($weights);
    return $this;
  }

  /**
   * @param $mode
   * @return $this
   */
  public function setMatchMode($mode)
  {
    $this->_connection->setMatchMode($mode);
    return $this;
  }

  /**
   * @param $mode
   * @return $this
   */
  public function setRankingMode($mode)
  {
    $this->_connection->setRankingMode($mode);
    return $this;
  }

  /**
   * @param $mode
   * @param null $par
   * @return $this
   */
  public function setSortMode($mode, $par = NULL)
  {
    $this->_connection->setSortMode($mode, $par);
    return $this;
  }

  /**
   * @param $attribute
   * @param $min
   * @param $max
   * @param bool $exclude
   * @return $this
   */
  public function setFilterFloatRange($attribute, $min, $max, $exclude = false)
  {
    $this->_connection->setFilterFloatRange($attribute, $min, $max, $exclude);
    return $this;
  }

  /**
   * @param $attrlat
   * @param $attrlong
   * @param null $lat
   * @param null $long
   * @return $this
   */
  public function setGeoAnchor($attrlat, $attrlong, $lat = null, $long = null)
  {
    $this->_connection->setGeoAnchor($attrlat, $attrlong, $lat, $long);
    return $this;
  }

  /**
   * @param $limit
   * @param int $offset
   * @param int $max_matches
   * @param int $cutoff
   * @return $this
   */
  public function limit($limit, $offset = 0, $max_matches = 1000, $cutoff = 1000)
  {
    $this->_connection->setLimits($offset, $limit, $max_matches, $cutoff);
    return $this;
  }

  /**
   * @param $attribute
   * @param $values
   * @param bool $exclude
   * @return $this
   */
  public function filter($attribute, $values, $exclude = FALSE)
  {
    if (is_array($values)) {
      $val = array();
      foreach ($values as $v) {
        $val[] = (int) $v;
      }
    } else {
      $val = array((int) $values);
    }
    $this->_connection->setFilter($attribute, $val, $exclude);

    return $this;
  }

  /**
   * @param $attribute
   * @param $min
   * @param $max
   * @param bool $exclude
   * @return $this
   */
  public function range($attribute, $min, $max, $exclude = FALSE)
  {
    $this->_connection->setFilterRange($attribute, $min, $max, $exclude);
    return $this;
  }

  /**
   * @param bool $respect_sort_order
   * @return array|false|static[]
   * @throws \ErrorException
   */
  public function get($respect_sort_order = FALSE)
  {
    $this->_total_count = 0;
    $result             = $this->_connection->query($this->_search_string, $this->_index_name);

    // Process results.
    if ($result) {
      // Get total count of existing results.
      $this->_total_count = (int) $result['total_found'];
      // Get time taken for search.
      $this->_time = $result['time'];

      if ($result['total'] > 0 && isset($result['matches'])) {
        // Get results' id's and query the database.
        $matchids = array_keys($result['matches']);

        $config = isset($this->_config['mapping']) ? $this->_config['mapping'] : $this->_config[$this->_index_name];
        if ($config) {
          if (isset($config['modelname'])) {
            $result = call_user_func_array($config['modelname'] . "::whereIn", array($config['column'], $matchids))->get();
          } else {
            $result = \DB::table($config['table'])->whereIn($config['column'], $matchids)->get();
          }
        }
      } else {
        $result = array();
      }
    }

    if ($respect_sort_order) {
      if (isset($matchids)) {
        $return_val = array();
        foreach ($matchids as $matchid) {
          $key = self::getResultKeyByID($matchid, $result);
          $return_val[] = $result[$key];
        }
        return $return_val;
      }
    }

    return $result;
  }

  /**
   * @return mixed
   */
  public function getTotalCount()
  {
    return $this->_total_count;
  }

  /**
   * @return mixed
   */
  public function getTime()
  {
    return $this->_time;
  }

  /**
   * @return string
   */
  public function getErrorMessage()
  {
    return $this->_connection->getLastError();
  }

  /**
   * @param $id
   * @param $result
   * @return bool|int|string
   */
  private function getResultKeyByID($id, $result)
  {
    if (count($result) > 0) {
      foreach ($result as $k => $result_item) {

        if ($result_item->id == $id) {
          return $k;
        }
      }
    }
    return false;
  }

  public function setGroupBy($attr, $func, $groupsort = '@group desc')
  {
    $this->_connection->setGroupBy($attr, $func, $groupsort);
    return $this;
  }

  /**
   * @param $verses
   * @param $index
   * @param $words
   * @param array $opts
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
      $excerpts[$id] = preg_replace_callback('/(' . implode('|', array_map('preg_quote', $words)) . ')/iu', function ($matches) {
        return '<b>' . $matches[0] . '</b>';
      }, $verse);
    }
    return $excerpts;
  }
}
