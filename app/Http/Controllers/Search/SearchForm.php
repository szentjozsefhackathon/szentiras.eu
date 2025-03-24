<?php

namespace SzentirasHu\Http\Controllers\Search;

/**
 * Description of SearchForm
 *
 * @author berti
 */
class SearchForm {
    
    public $textToSearch;
    public $greekTranslit;
    public $greekText;
    public $translation;
    public $book;
    public $grouping;
    public $mode;
    public $limit = 100;
    public $offset = 0;

}
