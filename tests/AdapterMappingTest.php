<?php

namespace imarc\sitesearch\tests;

use imarc\sitesearch\adapters\AddSearchAdapter;
use imarc\sitesearch\adapters\GoogleCustomSearchAdapter;
use imarc\sitesearch\adapters\VertexSearchAdapter;
use PHPUnit\Framework\TestCase;

class AdapterMappingTest extends TestCase
{
    private function fixture(string $name): object
    {
        return json_decode(file_get_contents(__DIR__ . '/fixtures/' . $name . '.json'));
    }

    public function testGcsMapping(): void
    {
        $response = $this->fixture('gcs-response');
        $results = GoogleCustomSearchAdapter::mapResponse($response, 1, 10);

        $this->assertSame(1, $results->page);
        $this->assertSame(10, $results->perPage);
        $this->assertSame(1, $results->start);
        $this->assertSame(2, $results->end);
        // totalResults capped at 100
        $this->assertSame(100, $results->totalResults);
        $this->assertSame($response, $results->raw);
        $this->assertCount(2, $results->results);

        $first = $results->results[0];
        $this->assertSame('First Result', $first->title);
        $this->assertSame('Plain snippet one', $first->snippet);
        $this->assertSame('Plain <b>snippet</b> one', $first->htmlSnippet);
        $this->assertSame('https://example.com/one', $first->link);
        $this->assertSame('https://example.com/one.jpg', $first->image);
        $this->assertSame('https://example.com/one-thumb.jpg', $first->thumbnail);

        $second = $results->results[1];
        $this->assertSame('', $second->snippet);
        $this->assertSame('', $second->image);
        $this->assertSame('', $second->thumbnail);
    }

    public function testVertexMapping(): void
    {
        $response = $this->fixture('vertex-response');
        $results = VertexSearchAdapter::mapResponse($response, 2, 10);

        $this->assertSame(2, $results->page);
        $this->assertSame(11, $results->start);
        $this->assertSame(12, $results->end);
        $this->assertSame(57, $results->totalResults);
        $this->assertSame($response, $results->raw);
        $this->assertCount(2, $results->results);

        $first = $results->results[0];
        $this->assertSame('Vertex Result', $first->title);
        $this->assertSame('https://example.com/vertex', $first->link);
        $this->assertSame('A <b>vertex</b> snippet &amp; more', $first->htmlSnippet);
        $this->assertSame('A vertex snippet & more', $first->snippet);

        $second = $results->results[1];
        $this->assertSame('', $second->snippet);
        $this->assertSame('', $second->htmlSnippet);
    }

    public function testAddSearchMapping(): void
    {
        $response = $this->fixture('addsearch-response');
        $results = AddSearchAdapter::mapResponse($response, 2, 10);

        $this->assertSame(2, $results->page);
        $this->assertSame(11, $results->start);
        $this->assertSame(12, $results->end);
        $this->assertSame(12, $results->totalResults);
        $this->assertSame($response, $results->raw);
        $this->assertCount(2, $results->results);

        $first = $results->results[0];
        $this->assertSame('AddSearch Result', $first->title);
        $this->assertSame('https://example.com/add', $first->link);
        $this->assertSame('An <em>addsearch</em> highlight', $first->htmlSnippet);
        $this->assertSame('An addsearch highlight', $first->snippet);
        $this->assertSame('https://example.com/add.jpg', $first->image);
        $this->assertSame('https://example.com/add-capture.jpg', $first->thumbnail);

        $second = $results->results[1];
        $this->assertSame('Only meta', $second->snippet);
        $this->assertSame('Only meta', $second->htmlSnippet);
        $this->assertSame('', $second->image);
    }

    public function testEmptyResponses(): void
    {
        $gcsEmpty = json_decode('{"queries":{"request":[{"startIndex":1,"count":0,"totalResults":0}]}}');
        $this->assertSame([], GoogleCustomSearchAdapter::mapResponse($gcsEmpty, 1, 10)->results);

        $vertexEmpty = json_decode('{}');
        $mapped = VertexSearchAdapter::mapResponse($vertexEmpty, 1, 10);
        $this->assertSame([], $mapped->results);
        $this->assertSame(0, $mapped->totalResults);

        $addEmpty = json_decode('{"total_hits":0,"hits":[]}');
        $this->assertSame([], AddSearchAdapter::mapResponse($addEmpty, 1, 10)->results);
    }
}
