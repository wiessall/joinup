<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue_platform_gitlab\Plugin\eu_oss_catalogue\HostingPlatform;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\eu_oss_catalogue\EuOssCatalogueGitHostingPlatformPluginBase;
use Gitlab\Client;

/**
 * Git code hosting platform for GitLab repositories.
 *
 * @EuOssCatalogueHostingPlatform(
 *   id = "gitlab",
 *   label = @Translation("GitLab"),
 * )
 */
class GitLab extends EuOssCatalogueGitHostingPlatformPluginBase {

  /**
   * Static cache for GitLab client.
   */
  protected Client $client;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'url' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $formState): array {
    $state = [
      ':input[name="platformSettings[self_hosted]"]' => ['checked' => TRUE],
    ];
    return [
      'self_hosted' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Self-hosted GitLab'),
        '#default_value' => !empty($this->getConfiguration()['url']),
      ],
      'url' => [
        '#type' => 'url',
        '#title' => $this->t('URL'),
        '#default_value' => $this->getConfiguration()['url'],
        '#states' => [
          'required' => $state,
          'visible' => $state,
        ],
      ],
    ] + parent::buildConfigurationForm($form, $formState);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $formState): void {
    parent::validateConfigurationForm($form, $formState);
    if (empty($formState->getValue('self_hosted'))) {
      $formState->setValue('url', NULL);
    }
    $formState->unsetValue('self_hosted');
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $formState): void {
    parent::submitConfigurationForm($form, $formState);
    $this->configuration['url'] = $formState->getValue('url');
  }

  /**
   * Returns the GitLab client.
   *
   * @return \Gitlab\Client
   *   The GitLab client.
   */
  protected function getClient(): Client {
    if (!isset($this->client)) {
      $this->client = new Client();
      if ($url = $this->getConfiguration()['url']) {
        // Self-hosted GitLab.
        $this->client->setUrl($url);
      }
      $token = Settings::get('eu_oss_catalogue', [])['platform_gitlab']['access_token'][(string) $url] ?? NULL;
      if (!$token) {
        // @todo Should we show a message to user or it's only developer stuff?
        throw new \InvalidArgumentException('GitLab access token is not set');
      }
      $this->client->authenticate($token, Client::AUTH_HTTP_TOKEN);
    }
    return $this->client;
  }

  /**
   * {@inheritdoc}
   */
  protected function getProjects(?string $filePath = NULL): array {
    $projects = [];
//    // Build a search query string.
//    // @see https://docs.github.com/search-github
//    $search = ['is:public', 'archived:false'];
//    foreach ($this->getConfiguration()['projects'] as $candidate) {
//      if ($candidate['name']) {
//        $search[] = "repo:{$candidate['vendor']}/{$candidate['name']}";
//      }
//      else {
//        $search[] = "owner:{$candidate['vendor']}";
//      }
//    }
//
//    $query = new Query('search', [
//      'type' => SearchType::REPOSITORY,
//      'query' => implode(' ', $search),
//      'first' => 100,
//    ]);
//    $fragment = $query->nodes([])
//      ->on('Repository')
//      ->use('id', 'description', 'name')
//      ->use((new Node('owner'))->use('login'))
//      ->use((new Node('defaultBranchRef'))->use('name'));
//
//    if ($filePath) {
//      $file = (new Node('object', [
//        'expression' => "HEAD:$filePath",
//      ]))->alias('file');
//      $file->on('Blob')->use('text');
//      $fragment->use($file);
//    }
//
//    $graphQL = new GraphQL($this->getClient());
//    foreach ($graphQL->execute($query->toString())['data']['search']['nodes'] as $node) {
//      // Repositories without code don't have a default branch.
//      if (!empty($node['defaultBranchRef'])) {
//        $projects[$node['owner']['login']][$node['name']] = new HostingPlatformResult(
//          $node['owner']['login'],
//          $node['name'],
//          $node['defaultBranchRef']['name'],
//          $node['id'],
//          $node['description'],
//          $node['file']['text'] ?? NULL,
//        );
//      }
//    }
    return $projects;
  }

}
