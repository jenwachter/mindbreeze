<?php

namespace Mindbreeze;

class Request
{
  protected $http;

  /**
   * API endpoint
   * @var string
   */
  public $url;

  /**
   * Array of properties to fetch from Mindbreeze
   * @var array
   */
  public $properties = [];

  /**
   * Array of facets to fetch
   * @var array
   */
  public $facets = [];

  /**
   * Query term
   * @var string
   */
  public $query;

  /**
   * Encoded version of query term used
   * when looking up qeng variables
   * @var string
   */
  public $encodedQuery;

  /**
   * Page from which to return results
   * @var integer
   */
  public $page = 1;

  /**
   * Number of documents to retrieve per page
   * @var integer
   */
  public $perPage = 10;

  /**
   * Number of pages to retrieve in `result_pages`
   * @var integer
   */
  public $pageCount = 10;

  /**
   * Max number of alternative queries to return
   * @var integer
   */
  public $alternatives = 10;

  /**
   * Length of content snippet
   * @var integer
   */
  public $contentSampleLength = 300;

  /**
   * Array of constraints based on datasources.
   * For example, 'gazette' => ['Web:GazetteArchivesPages', ['Web:GazetteArchivesWP']]
   * creates a gazette constraint that limits search to the two defined datasources
   * @var array
   */
  public $validDatasourceConstraints = [];

  /**
   * Datasource cnstraints added to the query
   * @var array
   */
  public $datasourceConstraints = [];

  /**
   * Constraints added to the query
   * @var array
   */
  public $queryConstraints = [];

  /**
   * Metadata by which results can be ordered by.
   * @var array
   */
  public $validOrderby = [
    'relevance' => 'mes:relevance',
    'date' => 'mes:date'
  ];

  /**
   * Valid orders
   * @var array
   */
  public $validOrder = [
    'asc' => 'ASCENDING',
    'desc' => 'DESCENDING'
  ];

  /**
   * Default order setting
   * @var string
   *
   */
  public $order = 'DESCENDING';

  /**
   * Default orderby setting
   * @var string
   */
  public $orderby = 'mes:relevance';

  public function __construct($http)
  {
    $this->http = $http;
  }

  public function setQuery($query)
  {
    $this->query = $query;
    $this->encodedQuery = base64_encode($query);
    return $this;
  }

  public function setPage($page)
  {
    $page = (int) $page;
    $this->page = $page > 0 ? $page : 1;

    return $this;
  }

  public function setOrder($orderby, $order = 'desc')
  {
    $orderby = strtolower($orderby);
    $order = strtolower($order);

    if (!array_key_exists($orderby, $this->validOrderby)) {
      throw new \Mindbreeze\Exceptions\RequestException($orderby . ' is not a valid field to order by. Please use one of the following: ' . implode(', ', array_keys($this->validOrderby)));
    }

    if (!array_key_exists($order, $this->validOrder)) {
      throw new \Mindbreeze\Exceptions\RequestException($orderby . ' is not a valid order. Please use one of the following: ' . implode(', ', array_keys($this->validOrder)));
    }

    $this->orderby = $this->validOrderby[$orderby];
    $this->order = $this->validOrder[$order];

    return $this;
  }

  /**
   * Add a datasource constraint to the query
   * @param string $constraint A defined constraint
   */
  public function addDatasourceConstraint($constraint)
  {
    if (!isset($this->validDatasourceConstraints[$constraint])) {
      return [];
    }

    $this->datasourceConstraint = $this->createConstraint('fqcategory', 'term', $this->validDatasourceConstraints[$constraint]);

    return $this;
  }

  /**
   * Add a date constraint to the query
   * @param integer $from Beginning timestamp
   * @param integer $end  Ending timestamp
   */
  public function addDateConstraint($from, $to)
  {
    $this->queryConstraints[] = $this->createConstraint('mes:date', 'between_dates', [$from, $to]);
    return $this;
  }

  /**
   * Add a constraing to the query
   * @param string $label Constraint label
   * @param string $type  Constraint type (see $types)
   * @param array  $data  Data to send to the constraint (varies between types)
   * @param string $key   Where to place this constraint in the request data
   */
  public function addConstraint($label, $type, $data)
  {
    $this->queryConstraints[] = $this->createConstraint($label, $type, $data);
    return $this;
  }

  protected function createConstraint($label, $type, $data)
  {
    $types = [
      'between_dates' => 'BetweenDates',
      'regex' => 'Regex',
      'term' => 'Term'
    ];

    if (!isset($types[$type])) {
      throw new \Mindbreeze\Exceptions\RequestException('Constraint type does not exist');
    }

    $className = '\\Mindbreeze\\Constraints\\' . $types[$type] . 'Constraint';
    $constraint = new $className($label);
    return $constraint->create($data)->compile();
  }

  /**
   * Compile data to send to Mindbreeze
   * @return array Data
   */
  public function compileData()
  {
    $data = [
      // how many characters long the snippets are
      'content_sample_length' => $this->contentSampleLength,

      // user query
      'user' => [
        'query' => [
          'and' => ['unparsed' => $this->query]
        ],
        'constraints' => $this->queryConstraints
      ],

      // how many results to return
      'count' => $this->perPage,

      // how many 'pages' to return in 'result_pages' -- helps you present page navigation
      'max_page_count' => $this->pageCount,

      // how many alternative queries to return
      'alternatives_query_spelling_max_estimated_count' => $this->alternatives,

      'order_direction' => $this->order,
      'orderby' => $this->orderby,

      // which properties to return with each search result
      'properties' => array_map(function ($property) {
        return [
          'formats' => ['HTML', 'VALUE'],
          'name' => $property
        ];
      }, $this->properties),

      'facets' => array_map(function ($facet) {
        return [
          'formats' => ['HTML'],
          'name' => $facet
        ];
      }, $this->facets)
    ];

    // add a datasource constraint, if there is one
    if (!empty($this->datasourceConstraint)) {
      $data['source_context'] = [
        'constraints' => $this->datasourceConstraint
      ];
    }

    // add pagination to data
    if ($this->page > 1) {
      $data['result_pages'] = [
        'qeng_ids' => $this->getQeng(),
        'pages' => [
          'starts' => [($this->page - 1) * $this->perPage],
          'counts' => [$this->perPage],
          'current_page' => true,
          'page_number' => $this->page
        ]
      ];
    }

    return $data;
  }

  /**
   * Send the request to Mindbreeze
   * @return object Mindbreeze\Response
   */
  public function send()
  {
    $response = $this->http->post($this->url, [
      'body' => json_encode($this->compileData()),
      'headers' => ['Content-Type' => 'application/json']
    ]);

    return new \Mindbreeze\Response($this->encodedQuery, $response);
  }

  /**
   * Retrieve Qeng variables from previous page to use in
   * paginated request to Mindbreeze. Set in Mindbreeze\Response.
   * @return mixed Array (if qeng variables set); otherwise FALSE
   */
  protected function getQeng()
  {
    if (!isset($_SESSION['search_qeng']) || !$_SESSION['search_qeng']) {
      throw new \Mindbreeze\Exceptions\RequestException('On page 2+ of search and QENG variables not set.');
    }

    if (!isset($_SESSION['search_qeng']['query']) || $_SESSION['search_qeng']['query'] != $this->encodedQuery) {
      throw new \Mindbreeze\Exceptions\RequestException('On page 2+ of search and QENG variables do not match queried term.');
    }

    return $_SESSION['search_qeng']['vars'];
  }
}
