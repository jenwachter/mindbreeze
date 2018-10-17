<?php

namespace Mindbreeze;

class RequestTest extends BaseTest
{
  public function createHTTPMock()
  {
    return $this->getMockBuilder('\\HttpExchange\\Adapters\\Guzzle6')
      ->disableOriginalConstructor()
      ->getMock();
  }

  public function getDefaultData($query)
  {
    return [
      'content_sample_length' => 300,
      'user' => [
        'query' => [
          'and' => ['unparsed' => $query]
        ],
        'constraints' => []
      ],
      'count' => 10,
      'max_page_count' => 10,
      'alternatives_query_spelling_max_estimated_count' => 10,
      'properties' => [],
      'facets' => [],
      'order_direction' => 'DESCENDING',
      'orderby' => 'mes:relevance'
    ];
  }

  public function testCompile__defaultData()
  {
    $query = 'hopkins';

    $request = new Request($this->createHTTPMock());
    $request->setQuery($query);

    $expected = $this->getDefaultData($query);
    $this->assertEquals($expected, $request->compileData());
  }

  public function testCompileData__withProperties()
  {
    $query = 'hopkins';

    $request = new Request($this->createHTTPMock());
    $request->setQuery($query);
    $request->properties = ['title', 'Section'];

    $expected = $this->getDefaultData($query);

    $expected['properties'] = [
      [
        'formats' => ['HTML', 'VALUE'],
        'name' => 'title'
      ],
      [
        'formats' => ['HTML', 'VALUE'],
        'name' => 'Section'
      ]
    ];

    $this->assertEquals($expected, $request->compileData());
  }

  public function testCompileData__invalidConstraints()
  {
    $query = 'hopkins';

    $request = new Request($this->createHTTPMock());
    $request->datasources = ['Web:Gazette', 'Web:JHU', 'Web:Hub'];
    $request->constraints = ['gazette' => ['Web:Gazette']];

    $request->setQuery($query);
    $request->addDatasourceConstraint('hub');

    $expected = $this->getDefaultData($query);
    $this->assertEquals($expected, $request->compileData());
  }

  public function testCompileData__withConstraints()
  {
    $query = 'hopkins';

    $request = new Request($this->createHTTPMock());
    $request->datasources = ['Web:Gazette', 'Web:JHU', 'Web:Hub'];
    $request->validDatasourceConstraints = ['gazette' => ['Web:Gazette']];

    $request->setQuery($query);
    $request->addDatasourceConstraint('gazette');

    $expected = $this->getDefaultData($query);

    $expected['source_context'] = [
      'constraints' => [
        'label' => 'fqcategory',
        'filter_base' => [
          [
            'label' => 'fqcategory',
            'or' => [
              [
                'quoted_term' => 'Web:Gazette'
              ]
            ]
          ]
        ]
      ]
    ];

    $this->assertEquals($expected, $request->compileData());
  }

  public function testCompileData__page2_noQeng()
  {
    $query = 'hopkins';

    $_SESSION['search_qeng'] = null;

    $request = new Request($this->createHTTPMock());
    $request->setQuery($query);
    $request->setPage(2);

    $this->expectException("\\Mindbreeze\\Exceptions\\RequestException");
    $request->compileData();
  }

  public function testCompileData__page2_qengNoMatch()
  {
    $query = 'hopkins';

    $_SESSION['search_qeng'] = [
      'query' => 'blah'
    ];

    $request = new Request($this->createHTTPMock());
    $request->setQuery($query);
    $request->setPage(2);

    $this->expectException("\\Mindbreeze\\Exceptions\\RequestException");
    $request->compileData();
  }

  public function testCompileData__page2()
  {
    $query = 'hopkins';
    $_SESSION['search_qeng'] = [
      'query' => base64_encode($query),
      'vars' => 'qeng'
    ];

    $request = new Request($this->createHTTPMock());
    $request->setQuery($query);
    $request->setPage(2);

    $expected = $this->getDefaultData($query);

    $expected['result_pages'] = [
      'qeng_ids' => 'qeng',
      'pages' => [
        'starts' => [10],
        'counts' => [10],
        'current_page' => true,
        'page_number' => 2
      ]
    ];

    $this->assertEquals($expected, $request->compileData());
  }
}
