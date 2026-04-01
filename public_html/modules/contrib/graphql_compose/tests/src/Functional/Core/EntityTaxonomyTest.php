<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose\Functional\Core;

use Drupal\Tests\graphql_compose\Functional\GraphQLComposeBrowserTestBase;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\VocabularyInterface;

/**
 * Tests specific to GraphQL Compose entity type: Taxonomy.
 *
 * @group graphql_compose
 */
class EntityTaxonomyTest extends GraphQLComposeBrowserTestBase {

  use TaxonomyTestTrait;

  /**
   * The test vocab.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected VocabularyInterface $vocabulary;

  /**
   * The test term.
   *
   * @var \Drupal\taxonomy\TermInterface[]
   */
  protected array $terms;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'taxonomy',
    'path',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->vocabulary = Vocabulary::create([
      'name' => 'Test',
      'vid' => 'test',
    ]);

    $this->vocabulary->save();

    $this->terms[1] = $this->createTerm($this->vocabulary, [
      'name' => 'Test term A',
      'weight' => 99,
    ]);

    $this->terms[2] = $this->createTerm($this->vocabulary, [
      'name' => 'Test term B',
      'weight' => 100,
    ]);

    $this->terms[3] = $this->createTerm($this->vocabulary, [
      'name' => 'Test term A A',
      'parent' => $this->terms[1]->id(),
    ]);

    $this->setEntityConfig('taxonomy_term', 'test', [
      'enabled' => TRUE,
      'query_load_enabled' => TRUE,
    ]);
  }

  /**
   * Test load entity by id.
   */
  public function testTermLoadByUuid(): void {
    $query = <<<GQL
      query {
        term(id: "{$this->terms[2]->uuid()}") {
          ... on TermInterface {
            id
            name
            path
            status
            weight
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $term = $content['data']['term'];

    $this->assertEquals($this->terms[2]->uuid(), $term['id']);
    $this->assertEquals('Test term B', $term['name']);
    $this->assertIsString($term['path']);
    $this->assertTrue($term['status']);
    $this->assertIsInt($term['weight']);
    $this->assertEquals(100, $term['weight']);
  }

  /**
   * Test taxonomy term parents.
   */
  public function testTermParents(): void {

    // Test expected parent.
    $query = <<<GQL
      query {
        term(id: "{$this->terms[3]->uuid()}") {
          ... on TermInterface {
            id
            parent {
              ... on TermTest {
                id
              }
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $term = $content['data']['term'];

    $this->assertEquals($this->terms[3]->uuid(), $term['id']);
    $this->assertEquals($this->terms[1]->uuid(), $term['parent']['id']);

    // Test empty.
    $query = <<<GQL
      query {
        term(id: "{$this->terms[2]->uuid()}") {
          ... on TermInterface {
            id
            parent {
              ... on TermInterface {
                id
              }
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $term = $content['data']['term'];

    $this->assertEquals($this->terms[2]->uuid(), $term['id']);
    $this->assertNull($term['parent']);
  }

}
