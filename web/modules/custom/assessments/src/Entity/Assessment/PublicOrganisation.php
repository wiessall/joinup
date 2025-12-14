<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity\Assessment;

use Drupal\assessments\Entity\AssessmentSubEntity;

/**
 * Defines the public organization entity class.
 */
final class PublicOrganisation extends AssessmentSubEntity {

  /**
   * {@inheritdoc}
   */
  public function getRelatedReport(): ?Report {
    $ids = $this->entityTypeManager()->getStorage('assessment')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'report')
      ->condition('provider', $this->id())
      // Sort by status DESC so published (status=1)
      // come before unpublished (status=0).
      ->sort('status', 'DESC')
      ->range(0, 1)
      ->execute();

    if (empty($ids)) {
      return NULL;
    }

    $id = reset($ids);
    $report = $this->entityTypeManager()->getStorage('assessment')->load($id);
    return $report instanceof Report ? $report : NULL;
  }

  /**
   * Returns an array of institution options.
   *
   * @return array{string, \Stringable}
   *   An associative array of institution options.
   */
  public static function getEuropeanInstitutionOptions(): array {
    return [
      'European Parliament' => t('European Parliament'),
      'European Council' => t('European Council'),
      'Council of the European Union' => t('Council of the European Union'),
      'European Commission' => t('European Commission'),
      'Court of Justice of the European Union' => t('Court of Justice of the European Union'),
      'European Central Bank' => t('European Central Bank'),
      'European Court of Auditors' => t('European Court of Auditors'),
    ];
  }

  /**
   * Returns an array of body options.
   *
   * @return array{string, \Stringable}
   *   An associative array of body options.
   */
  public static function getEuropeanBodyOptions(): array {
    return [
      'European External Action Service' => t('European External Action Service'),
      'European Economic and Social Committee' => t('European Economic and Social Committee'),
      'European Committee of the Regions' => t('European Committee of the Regions'),
      'European Investment Bank' => t('European Investment Bank'),
      'European Ombudsman' => t('European Ombudsman'),
      'European Data Protection Supervisor' => t('European Data Protection Supervisor'),
      'European Data Protection Board' => t('European Data Protection Board'),
      "Eruopean Public Prosecutor's Office" => t("Eruopean Public Prosecutor's Office"),
    ];
  }

  /**
   * Returns an array of agency options.
   *
   * @return array{string, \Stringable}
   *   An associative array of agency options.
   */
  public static function getEuropeanAgencyOptions(): array {
    return [
      'Agency for Support for BEREC' => t('Agency for Support for BEREC'),
      'Agency for the Cooperation of Energy Regulators' => t('Agency for the Cooperation of Energy Regulators'),
      'Authority for European Political Parties and European Political Foundations' => t('Authority for European Political Parties and European Political Foundations'),
      'Community Plant Variety Office' => t('Community Plant Variety Office'),
      'Eurojust' => t('Eurojust'),
      'European Agency for Safety and Health at Work' => t('European Agency for Safety and Health at Work'),
      'European Asylum Support Office' => t('European Asylum Support Office'),
      'European Banking Authority' => t('European Banking Authority'),
      'European Border and Coast Guard Agency' => t('European Border and Coast Guard Agency'),
      'European Centre for Disease Prevention and Control' => t('European Centre for Disease Prevention and Control'),
      'European Centre for the Development of Vocational Training' => t('European Centre for the Development of Vocational Training'),
      'European Chemicals Agency' => t('European Chemicals Agency'),
      'European Data Protection Board' => t('European Data Protection Board'),
      'European Environment Agency' => t('European Environment Agency'),
      'European Fisheries Control Agency' => t('European Fisheries Control Agency'),
      'European Food Safety Authority' => t('European Food Safety Authority'),
      'European Foundation for the Improvement of Living and Working Conditions' => t('European Foundation for the Improvement of Living and Working Conditions'),
      'European GNSS Agency' => t('European GNSS Agency'),
      'European Institute for Gender Equality' => t('European Institute for Gender Equality'),
      'European Insurance and Occupational Pensions Authority' => t('European Insurance and Occupational Pensions Authority'),
      'European Maritime Safety Agency' => t('European Maritime Safety Agency'),
      'European Medicines Agency' => t('European Medicines Agency'),
      'European Monitoring Centre for Drugs and Drug Addiction' => t('European Monitoring Centre for Drugs and Drug Addiction'),
      "European Public Prosecutor's Office" => t("European Public Prosecutor's Office"),
      'European Securities and Markets Authority' => t('European Securities and Markets Authority'),
      'European Training Foundation' => t('European Training Foundation'),
      'European Union Agency for Cybersecurity' => t('European Union Agency for Cybersecurity'),
      'European Union Agency for Fundamental Rights' => t('European Union Agency for Fundamental Rights'),
      'European Union Agency for Law Enforcement Cooperation' => t('European Union Agency for Law Enforcement Cooperation'),
      'European Union Agency for Law Enforcement Training' => t('European Union Agency for Law Enforcement Training'),
      'European Union Agency for Railways' => t('European Union Agency for Railways'),
      'European Union Agency for the Operational Management of Large-Scale IT Systems in the Area of Freedom, Security and Justice' => t('European Union Agency for the Operational Management of Large-Scale IT Systems in the Area of Freedom, Security and Justice'),
      'European Union Aviation Safety Agency' => t('European Union Aviation Safety Agency'),
      'European Union Intellectual Property Office' => t('European Union Intellectual Property Office'),
      'Single Resolution Board' => t('Single Resolution Board'),
      'Translation Centre for the Bodies of the European Union' => t('Translation Centre for the Bodies of the European Union'),
    ];
  }

}
