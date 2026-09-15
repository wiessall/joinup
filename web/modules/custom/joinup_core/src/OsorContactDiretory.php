<?php

declare(strict_types=1);

namespace Drupal\joinup_core;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\custom_page\Entity\CustomPageInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Class to perform content updates to OSOR contact directory.
 */
final readonly class OsorContactDiretory implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    protected FileSystemInterface $fileSystem,
    protected EntityRepositoryInterface $entityRepository,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleExtensionList $moduleExtensionList,
  ) {}

  private const array CONTACT_ITEMS = [
    'Bulgary' => [
      'lastItemId' => 91594,
      'itemsToAdd' => [
        [
          'title' => 'Data for Good',
          'description' => 'Data for Good is a non-profit, open data association that develops software for analysing data intended for Wikipedia and its community. It carries out data analysis and engineering for other organisations, and campaigns with public bodies to encourage them to make more, higher-quality data available. The organisation also teaches public bodies how to publish data. Data for Good seeks to link government data with community data, promoting open licences for data sharing in the process.',
          'imagePath' => 'bg-data-for-good.png',
          'weburl' => 'https://data-for-good.bg/en/',
          'email' => 'mailto:info@data-for-good.bg',
        ],
      ],
    ],

    'France' => [
      'lastItemId' => 118074,
      'itemsToAdd' => [
        [
          'title' => 'OW2',
          'description' => 'OW2 is a non-profit organisation that acts as both an independent open source software community and an ecosystem platform that facilitates interaction between open source code producers and consumers. Founded in 2007, its objective is to host and promote open source projects, and to manage a community of academics, companies, public bodies, NGOs and individuals involved in open source software development. OW2 regularly organises events to promote open source software and campaign for open models at political and social levels.',
          'imagePath' => 'fr-ow2.png',
          'weburl' => 'https://www.ow2.org/',
          'email' => 'https://www.ow2.org/view/About/Contacts',
        ],
      ],
    ],

    'Germany' => [
      'lastItemId' => 92053,
      'itemsToAdd' => [
        [
          'title' => 'DigitalService',
          'description' => 'DigitalService is a state-owned company acting as a digitalisation partner for German public administrations. It designs and implements digital solutions in close coordination with the federal government and public bodies to help digitise administrative processes. To this end, the company develops several open source projects which are publicly available on its GitHub page.',
          'imagePath' => 'de-digital-service.png',
          'weburl' => 'https://digitalservice.bund.de/en',
        ],
        [
          'title' => 'Open Knowledge Foundation',
          'description' => 'Open Knowledge Foundation is an independent, non-profit organisation that promotes transparency, openness, and technological sovereignty in Germany by developing open source tools and organising educational programmes and campaigns. The organisation also aims to provide policymakers with information and expertise. It runs an open source fund for software prototypes (the Prototype Fund), has created a coding programme for young people (Jugend hackt) and leads a European platform to strengthen freedom of information (FragDenStaat).',
          'imagePath' => 'de-open-knowledge-foundation.png',
          'weburl' => 'https://okfn.org/en/',
          'email' => 'info@okfn.org',
        ],
      ],
    ],

    'Poland' => [
      'lastItemId' => 9992,
      'itemsToAdd' => [
        [
          'title' => 'Reszka Free Software Foundation (Fundacja Wolnego Oprogramowania Reszka)',
          'description' => 'Reszka Free Software Foundation is a non-profit organisation that advocates for the adoption of open source technologies within governmental and local administrations, businesses, and other non-governmental organisations. The foundation is a member of the SPOIWO alliance.',
          'imagePath' => 'pl-reszka.png',
          'weburl' => 'https://reszka.org/',
        ],
        [
          'title' => 'Instrat',
          'description' => 'Instrat is a think tank that supports public policies regarding open data and research. It advocates for a fair, green and digital economy and supports the implementation of open source software in public administration.',
          'imagePath' => 'pl-instrat.png',
          'weburl' => 'https://instrat.pl/o-fundacji-instrat/',
          'email' => 'mailto:patryk.berus@instrat.pl',
        ],
      ],
    ],
  ];

  /**
   * Updates the OSOR contact page.
   */
  public function performContentUpdate(): void {
    $node = $this->entityTypeManager->getStorage('node')->load(150548);
    if (!$node instanceof CustomPageInterface) {
      throw new \Exception("Cannot perform update: OSOR contact directory page with ID 150548 was not found");
    }
    $fieldItemList = $node->field_paragraphs_body;
    assert($fieldItemList instanceof EntityReferenceFieldItemListInterface);

    $this->addNewItemsToPreexisingSections($fieldItemList);
    $this->updateToc($fieldItemList);
    $this->addUkraine($fieldItemList);

    $node->setNewRevision();
    $node->setRevisionLogMessage('Updated programmatically.');
    $node->save();
  }

  /**
   * Adds items to prexisting sections, from data in the CONTACT_ITEMS constant.
   *
   * @param \Drupal\Core\Field\EntityReferenceFieldItemListInterface $fieldItemList
   *   The paragraph field of the OSOR contact page.
   */
  private function addNewItemsToPreexisingSections(EntityReferenceFieldItemListInterface $fieldItemList): void {
    foreach (static::CONTACT_ITEMS as ['lastItemId' => $lastItemId, 'itemsToAdd' => $itemsToAddPerCountry]) {
      foreach (array_reverse($itemsToAddPerCountry) as $itemData) {
        $itemsToAdd = $this->createItem(...$itemData);
        $this->insertItemsAfterParagraph($fieldItemList, $lastItemId, $itemsToAdd);
      }
    }
  }

  /**
   * Updates the last table of contents column with an anchor link to Ukraine.
   *
   * @param \Drupal\Core\Field\EntityReferenceFieldItemListInterface $fieldItemList
   *   The paragraph field of the OSOR contact page.
   */
  private function updateToc(EntityReferenceFieldItemListInterface $fieldItemList): void {
    $tocParagraphKey = key(array_filter(
      $fieldItemList->getValue(),
      fn (array $val) => $val['target_id'] === '9781',
    ));
    $tocParagraphText = $fieldItemList[$tocParagraphKey]->entity->field_body->get(0)->value;
    if (str_contains($tocParagraphText, '<a href="#Ukraine">')) {
      return;
    }

    $fieldItemList[$tocParagraphKey]->entity->field_body->get(0)->value = preg_replace(
      '/(<a href="#United Kingdom")/',
      '<a href="#Ukraine">Ukraine</a></p><p>$1',
      $tocParagraphText,
    );
  }

  /**
   * Adds Ukraine and their first organisation listed on the OSOR contact page.
   *
   * @param \Drupal\Core\Field\EntityReferenceFieldItemListInterface $fieldItemList
   *   The paragraph field object of the OSOR contact page.
   */
  private function addUkraine(EntityReferenceFieldItemListInterface $fieldItemList): void {
    // Layout of the spacer, the country logo / flag and the country name.
    $titleLayout = Paragraph::create(['type' => 'layout']);
    $titleLayout->setBehaviorSettings(
      'layout_paragraphs',
      [
        'layout' => 'layout_onecol',
        'config' => ['label' => ''],
        'parent_uuid' => NULL,
        'region' => NULL,
      ],
    );

    $spacer = Paragraph::create([
      'type' => 'image',
      'paragraph_image' => $this->createFileFromImage('divider-216.png'),
    ]);
    $spacer->setBehaviorSettings('alignment', [
      'alignment' => 'center',
    ]);
    $spacer->setBehaviorSettings('layout_paragraphs', [
      'parent_uuid' => $titleLayout->uuid(),
      'region' => 'content',
    ]);

    $flagMedia = $this->createMediaFromImageFile(
      $this->createFileFromImage('ukraine-circle.png'),
      'Ukraine circle flag',
    );
    $title = Paragraph::create([
      'type' => 'simple_paragraph',
      'field_body' => [
        'format' => 'content_editor',
        'value' => <<<EOS
<p><a id="Ukraine"></a></p>
<drupal-media alt="Flag of Ukraine" data-entity-type="media" data-entity-uuid="{$flagMedia->uuid()}" data-align="left">&nbsp;</drupal-media>
<h2>&nbsp; Ukraine</h2>
EOS
        ,
      ],
    ]);
    $title->setBehaviorSettings('layout_paragraphs', [
      'parent_uuid' => $titleLayout->uuid(),
      'region' => 'content',
    ]);

    $contactItemComponents = $this->createItem(
      'Kitsoft',
      'Kitsoft is a tech company that has been working in governance technology for over ten years and facilitates Ukraine’s digital transformation through the development of open source projects. Kitsoft works with governmental and non-governmental actors to create dialogue on relevant digital developments for citizens, businesses and the state. Among other things, Kitsoft worked under the commission of the Ministry of Digital Transformation to develop the open source platform for state services Diia. It has also created the open source platform Liquio.',
      'ua-kitsoft.png',
      'https://kitsoft.ua/',
      'https://kitsoft.ua/contacts',
    );

    // 10070 represents Türkiye (Turkey formerly), so we keep the alphabetical
    // order.
    $this->insertItemsAfterParagraph($fieldItemList, 10070, array_merge([
      $titleLayout,
      $spacer,
      $title,
    ], $contactItemComponents));
  }

  /**
   * Creates an organisation item components, inc. layout, for OSOR contact.
   *
   * @param string $title
   *   The title of the org.
   * @param string $description
   *   Description of the org.
   * @param string $imagePath
   *   Path to the image, relative to the deploy/osor-logos directory.
   * @param string $weburl
   *   Web URL of the org.
   * @param string|null $email
   *   Contact of the org. Might be just a web address.
   *
   * @return \Drupal\paragraphs\ParagraphInterface[]
   *   Paragraphs representing the org.
   */
  private function createItem(
    string $title,
    string $description,
    string $imagePath,
    string $weburl,
    ?string $email = NULL,
  ): array {
    // Example: 9870.
    $layout = Paragraph::create(['type' => 'layout']);
    $layout->setBehaviorSettings(
      'layout_paragraphs',
      [
        'layout' => 'layout_twocol_section',
        'config' => ['label' => '', 'column_widths' => '25-75'],
        'parent_uuid' => NULL,
        'region' => NULL,
      ],
    );

    // Image, example: 9871.
    $imageFile = $this->createFileFromImage($imagePath);
    $image = Paragraph::create([
      'type' => 'image',
      'paragraph_image' => $imageFile,
      'paragraph_action_link' => $weburl,
    ]);
    $image->setBehaviorSettings('alignment', [
      'alignment' => 'center',
    ]);
    $image->setBehaviorSettings('layout_paragraphs', [
      'parent_uuid' => $layout->uuid(),
      'region' => 'first',
    ]);

    // Text, example: 9872.
    $contact = '';
    if ($email) {
      $contactLink = static::linkFromString($email);
      $contact .= "Email: $contactLink</br>";
    }
    $weburlLink = $this->linkFromString($weburl);
    $contact .= "Web: $weburlLink";
    $textComponents = [
      "<strong>$title</strong>",
      $contact,
      $description,
    ];
    $text = Paragraph::create([
      'type' => 'text',
      'field_body' => [
        'format' => 'content_editor',
        'value' => '<p>' . implode("</p>\n<p>", $textComponents) . "</p>\n",
      ],
    ]);
    $text->setBehaviorSettings('layout_paragraphs', [
      'parent_uuid' => $layout->uuid(),
      'region' => 'second',
    ]);

    return [$layout, $image, $text];
  }

  /**
   * Constructs a link from the given URL.
   *
   * Very naive method, which creates a mailto link if the given string contains
   * an at sign "@", or a normal web url if it doesn't.
   *
   * @param string $url
   *   A string which will be used as a url and as a link text.
   *
   * @return string
   *   The HTML link.
   */
  private static function linkFromString(string $url): string {
    $target = str_contains($url, '@') ? "mailto:$url" : $url;
    return "<a href=\"$target\">$url</a>";
  }

  /**
   * Inserts components after the paragraph with the given ID.
   *
   * @param \Drupal\Core\Field\EntityReferenceFieldItemListInterface $fieldItemList
   *   The OSOR paragraph field item list object.
   * @param string|int $afterParaId
   *   ID of the reference paragraph.
   * @param array $items
   *   Items to add after the given paragraph.
   */
  private function insertItemsAfterParagraph(EntityReferenceFieldItemListInterface $fieldItemList, string|int $afterParaId, array $items): void {
    $afterKey = key(array_filter(
      $fieldItemList->getValue(),
      function (array $val) use ($afterParaId) {
        return $val['target_id'] === (string) $afterParaId;
      },
    ));
    if ($afterKey === NULL) {
      throw new \Exception("Could not find paragraph id in the list: $afterParaId");
    }
    $list = $fieldItemList->getValue();
    $list = array_merge(
      array_slice($list, 0, $afterKey + 1),
      $items,
      array_slice($list, $afterKey + 1),
    );

    $fieldItemList->setValue($list);
  }

  /**
   * Creates a File entity from the given image.
   *
   * @param string $imagePath
   *   Path to the image, relative to the deploy/osor-logos directory.
   *
   * @return \Drupal\file\FileInterface
   *   The file entity.
   */
  private function createFileFromImage(string $imagePath): FileInterface {
    $imageSourcePath = $this->moduleExtensionList->getPath('joinup_core') .
      "/deploy/osor-logos/$imagePath";
    $imageRealPath = $this->fileSystem->realpath($imageSourcePath);
    $imagePath = $this->fileSystem->copy($imageRealPath, 'public://');
    $imageFile = File::create(['uri' => $imagePath]);
    return $imageFile;
  }

  /**
   * Creates and saves a new image media from the file entity.
   *
   * @param \Drupal\file\FileInterface $file
   *   An image file.
   * @param string $name
   *   Name of the media.
   *
   * @return \Drupal\media\MediaInterface
   *   The image media created from the file.s
   */
  private function createMediaFromImageFile(FileInterface $file, string $name): MediaInterface {
    $media = Media::create([
      'bundle' => 'image',
      'name' => $name,
      'image' => $file,
      'og_audience' => $this->entityRepository->loadEntityByUuid('node', '0a36ace2-03a2-4d39-bf5c-97abf25f4ce5'),
    ]);
    $media->save();
    return $media;
  }

}
