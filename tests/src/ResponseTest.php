<?php

namespace Mindbreeze;

class ResponseTest extends BaseTest
{
  public function createHTTPMock($body, $code = 200)
  {
    $mock = $this->getMockBuilder('\\HttpExchange\\Adapters\\Guzzle6')
      ->disableOriginalConstructor()
      ->setMethods(['getStatusCode', 'getBody'])
      ->getMock();

    $mock->expects($this->any())
      ->method('getStatusCode')
      ->will($this->returnValue($code));

    $mock->expects($this->any())
      ->method('getBody')
      ->will($this->returnValue($body));

    return $mock;
  }

  public function testParse__400Response()
  {
    $http = $this->createHTTPMock(null, 400);

    $this->setExpectedException("\\Mindbreeze\\Exceptions\\ResponseException");
    new Response($http);
  }

  public function testParse__noResults()
  {
    $http = $this->createHTTPMock($this->arrayToObject([
      'resultset' => []
    ]));

    new Response($http);

    $this->assertEquals(null, $_SESSION['search_qeng']);
  }

  public function testParse__withResultsNoSuggestion()
  {
    $http = $this->createHTTPMock($this->arrayToObject([
      'resultset' => [
        'result_pages' => [
          'qeng_ids' => 'QengIds'
        ],
        'results' => [
          $this->arrayToObject([
            'properties' => [
              $this->arrayToObject([
                'id' => 'Title',
                'data' => ['data at index 0']
              ])
            ]
          ])
        ],
        'prev_avail' => false,
        'next_avail' => true
      ],
      'estimated_count' => 20,
      'alternatives' => [
        $this->arrayToObject(['name' => 'user_query']),
        $this->arrayToObject(['name' => 'query_spelling', 'entries' => [
          $this->arrayToObject(['html' => '<em>biology</em>'])
        ]]),
        $this->arrayToObject(['name' => 'user_query'])
      ]
    ]));

    $response = new Response($http);

    $this->assertEquals('QengIds', $_SESSION['search_qeng']);

    $this->assertEquals([
      $this->arrayToObject([
        'data' => [
          'title' => 'data at index 0'
        ]
      ])
    ], $response->records);

    $this->assertEquals([
      'prev' => false,
      'next' => true,
      'total' => 20
    ], $response->pagination);

    $this->assertEquals('biology', $response->suggestion);
  }
}
