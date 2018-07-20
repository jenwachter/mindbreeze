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
  public $constraints = [];

  /**
   * Compiled data
   * @var array
   */
  protected $data = [];

  public function __construct($http)
  {
    $this->http = $http;
    $this->generateDefaultData();
  }

  protected function generateDefaultData()
  {
    $this->data = [
      // how many characters long the snippets are
      'content_sample_length' => $this->contentSampleLength,

      // user query
      'user' => [
        'query' => [
          'and' => ['unparsed' => '']
        ],
        'constraints' => []
      ],

      // how many results to return
      'count' => $this->perPage,

      // how many 'pages' to return in 'result_pages' -- helps you present page navigation
      'max_page_count' => $this->pageCount,

      // how many alternative queries to return
      'alternatives_query_spelling_max_estimated_count' => $this->alternatives,

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

  public function setQuery($query)
  {
    $this->data['user']['query']['and']['unparsed'] = $query;
    $this->encodedQuery = base64_encode($query);
    return $this;
  }

  public function setPage($page)
  {
    if ($page > 1) {
      $this->data['result_pages'] = [
        'qeng_ids' => $this->getQeng(),
        'pages' => [
          'starts' => [($page - 1) * $this->perPage],
          'counts' => [$this->perPage],
          'current_page' => true,
          'page_number' => $page
        ]
      ];

    }

    return $this;
  }

  /**
   * Add a datasource constraint to the query
   * @param string $constraint A defined constraint
   */
  public function addDatasourceConstraint($constraint)
  {
    if (!isset($this->constraints[$constraint])) {
      return [];
    }

    $this->data['source_context'] = [
      'constraints' => $this->createConstraint('fqcategory', 'term', $this->constraints[$constraint])
    ];

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
    $this->data['user']['constraints'][] = $this->createConstraint($label, $type, $data);
    return $this;
  }

  protected function createConstraint($label, $type, $data)
  {
    $types = [
      'between' => 'BetweenValues',
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
    // add pagination to data

    if ($this->page > 1) {
      $this->data['result_pages'] = [
        'qeng_ids' => $this->getQeng(),
        'pages' => [
          'starts' => [($this->page - 1) * $this->perPage],
          'counts' => [$this->perPage],
          'current_page' => true,
          'page_number' => $this->page
        ]
      ];
    }

    return $this->data;
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
