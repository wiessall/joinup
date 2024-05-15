<?php

declare(strict_types=1);

namespace Drupal\joinup\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\joinup_licence\Entity\Licence;
use Drupal\joinup_licence\Entity\LicenceInterface;
use Drupal\sparql_entity_storage\Driver\Database\sparql\ConnectionInterface;
use Drupal\sparql_entity_storage\Entity\Query\Sparql\SparqlArg;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A reporting page that presents solutions indexed by related licences.
 */
class SolutionsByLicenceForm extends FormBase {

  /**
   * The items per page.
   */
  protected const ITEMS_PER_PAGE = 50;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'solutions_by_licence_form';
  }

  /**
   * Constructs a SolutionsByLicenceController.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\sparql_entity_storage\Driver\Database\sparql\ConnectionInterface $connection
   *   The SPARQL connection.
   * @param \Drupal\Core\Pager\PagerManagerInterface $pagerManager
   *   The pager manager.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $linkGenerator
   *   The link generator.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConnectionInterface $connection,
    protected PagerManagerInterface $pagerManager,
    protected LinkGeneratorInterface $linkGenerator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('sparql.endpoint'),
      $container->get('pager.manager'),
      $container->get('link_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if (empty($form_state->get('licence_options'))) {
      $form_state->set('licence_options', $this->getLicenceOptions());
    }

    $form['licence_option'] = [
      '#type' => 'select',
      '#title' => $this->t('Licence'),
      '#options' => $form_state->get('licence_options'),
      '#default_value' => $form_state->getValue('licence_option'),
      '#empty_option' => ' - All - ',
    ];
    $form['filter'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
    ];

    $licence_id = $form_state->getValue('licence_option');

    // Under some unknown circumstances the result returned can be a string with
    // the value 'binding' instead of the expected count. This has been causing
    // random test failures, and has also been observed during debugging on a
    // development environment. It appears that calling ::getArrayCopy() works
    // around this somehow and the problem can no longer be replicated.
    $results = $this->connection->query($this->getCountQuery($licence_id))->getArrayCopy();
    $result = reset($results);
    $total = $result->total->getValue();
    $page = $this->pagerManager->createPager($total, self::ITEMS_PER_PAGE)->getCurrentPage();
    $offset = $page * self::ITEMS_PER_PAGE;
    $items = $this->connection->query($this->getQuery($offset, $licence_id));

    $rows = [];
    foreach ($items as $item) {
      $licence = Licence::loadByUri($item->licence->getUri());
      $solution_url = Url::fromRoute('entity.rdf_entity.canonical', [
        'rdf_entity' => $item->solution->getUri(),
      ]);

      $rows[] = [
        $this->linkGenerator->generate($licence->label(), $licence->toUrl()),
        $this->linkGenerator->generate($item->solution_label, $solution_url),
      ];
    }

    $form['table'] = [
      '#theme' => 'table',
      '#header' => ['Licence', 'Solution'],
      '#rows' => $rows,
      '#empty' => $this->t('No solutions available.'),
    ];
    $form['pager'] = [
      '#type' => 'pager',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // The only case where the form is submitted is by clicking the filter.
    // Rebuild to filter by licence.
    $form_state->setRebuild();
  }

  /**
   * Returns the full list of licences in the website.
   *
   * @return array
   *   An array of licence labels indexed by licence id.
   */
  protected function getLicenceOptions(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $nids = $storage->getQuery()->accessCheck(FALSE)
      ->condition('type', 'licence')
      ->execute();
    if (!$nids) {
      return [];
    }
    return array_map(
      fn(LicenceInterface $licence): string => $licence->label(),
      $storage->loadMultiple($nids),
    );
  }

  /**
   * Returns the query that counts total of the results.
   *
   * @param string|null $licence_id
   *   (Optional) A licence id to narrow down the results with.
   *
   * @return string
   *   The count query.
   */
  protected function getCountQuery(?string $licence_id = NULL): string {
    $query = <<<QUERY
SELECT COUNT(*) AS ?total
WHERE {
  SELECT DISTINCT ?solution ?licence_label ?licence ?solution_label (COUNT(*) as ?total)
  WHERE {
    {
      {
        ?solution <http://www.w3.org/ns/dcat#distribution> ?distribution .
        ?distribution <http://purl.org/dc/terms/license> ?licence .
        FILTER NOT EXISTS { ?solution <http://purl.org/dc/terms/isVersionOf> ?version } .
      }
      UNION
      {
        ?solution <http://purl.org/dc/terms/hasVersion> ?release .
        ?release <http://www.w3.org/ns/dcat#distribution> ?distribution .
        ?distribution <http://purl.org/dc/terms/license> ?licence .
      }
    } .
    {
      ?solution a <http://www.w3.org/ns/dcat#Dataset> .
      ?solution <http://purl.org/dc/terms/title> ?solution_label .
      ?licence a <http://purl.org/dc/terms/LicenseDocument> .
      ?licence <http://www.w3.org/2000/01/rdf-schema#label> ?licence_label .
    } .
    @extra_condition
  }
}
QUERY;

    $licence_uri = $licence_id ? $this->entityTypeManager->getStorage('node')->load($licence_id)->getUri() : NULL;
    $extra_condition = $licence_uri ? 'VALUES ?licence { ' . SparqlArg::uri($licence_uri) . ' } ' : '';
    $query = str_replace('@extra_condition', $extra_condition, $query);
    return $query;
  }

  /**
   * Returns the query that fetches licence and solution labels.
   *
   * @param int $offset
   *   The offset number.
   * @param string|null $licence_id
   *   (Optional) A licence id to narrow down the results with.
   *
   * @return string
   *   The query string.
   */
  protected function getQuery(int $offset, ?string $licence_id = NULL): string {

    $query = <<<QUERY
SELECT DISTINCT ?solution ?licence_label ?licence ?solution_label
WHERE {
  {
    {
      ?solution <http://www.w3.org/ns/dcat#distribution> ?distribution .
      ?distribution <http://purl.org/dc/terms/license> ?licence .
      FILTER NOT EXISTS { ?solution <http://purl.org/dc/terms/isVersionOf> ?version } .
    }
    UNION
    {
      ?solution <http://purl.org/dc/terms/hasVersion> ?release .
      ?release <http://www.w3.org/ns/dcat#distribution> ?distribution .
      ?distribution <http://purl.org/dc/terms/license> ?licence .
    }
  } .
  {
    ?solution a <http://www.w3.org/ns/dcat#Dataset> .
    ?solution <http://purl.org/dc/terms/title> ?solution_label .
    ?licence a <http://purl.org/dc/terms/LicenseDocument> .
    ?licence <http://www.w3.org/2000/01/rdf-schema#label> ?licence_label .
  } .
  @extra_condition
}
ORDER BY ASC(?licence_label) ASC(?solution_label)
LIMIT @limit
OFFSET {$offset}
QUERY;

    $licence_uri = $licence_id ? $this->entityTypeManager->getStorage('node')->load($licence_id)->getUri() : NULL;
    $extra_condition = $licence_uri ? 'VALUES ?licence { ' . SparqlArg::uri($licence_uri) . ' } ' : NULL;
    $search = ['@extra_condition', '@limit'];
    $replace = [$extra_condition, self::ITEMS_PER_PAGE];
    $query = str_replace($search, $replace, $query);
    return $query;
  }

}
