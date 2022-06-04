<?php

declare(strict_types = 1);

namespace Joinup\TaskRunner\Commands;

use OpenEuropa\TaskRunner\Commands\AbstractCommands;
use Robo\Collection\CollectionBuilder;
use Symfony\Component\Console\Input\InputOption;

/**
 * Replaces the Toolkit download command.
 */
class CloneCommands extends AbstractCommands {

  /**
   * Path to file that hold the input information.
   */
  public const TEMP_INPUTFILE = 'temporary_inputfile.txt';

  /**
   * Replaces the toolkit:download-dump original Toolkit command.
   *
   * The current Toolkit command dropped the possibility to pass ASDA URL as
   * command line option. Instead, it implemented a more complex system which,
   * unfortunately, doesn't work for Joinup with ASDA but only with NEXTCLOUD.
   * We cannot switch to NEXTCLOUD until devops are not granting our EU Login
   * with proper permission. This is a temporary command replacement until
   * ISAICP-7151 lands.
   *
   * @param array $options
   *   The command line options.
   *
   * @return \Robo\Collection\CollectionBuilder
   *   The Robo collection builder.
   *
   * @hook replace-command toolkit:download-dump
   *
   * @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7151
   * @todo Drop this replacement in ISAICP-7151.
   */
  public function downloadDump(array $options = [
    'asda-url' => InputOption::VALUE_REQUIRED,
    'asda-user' => InputOption::VALUE_REQUIRED,
    'asda-password' => InputOption::VALUE_REQUIRED,
    'dumpfile' => InputOption::VALUE_REQUIRED,
  ]): CollectionBuilder {
    $tasks = [];
    $config = $this->getConfig();

    $options['asda-user'] = $config->get('env.ASDA_USER');
    $options['asda-password'] = $config->get('env.ASDA_PASSWORD');

    // Download the .sha file.
    $this->generateAsdaWgetInputFile('latest.sh1', $options);
    $this->downloadChecksumFile($options);
    $fileContent = file_get_contents('latest.sh1');
    $filename = trim(explode('  ', $fileContent)[1]);

    // Download the .sql file.
    $this->generateAsdaWgetInputFile($filename, $options);

    // Display information about ASDA creation date.
    $dumpData = substr(substr(file_get_contents('latest.sh1'), strpos(file_get_contents('latest.sh1'), ' ') + 2), 0, 15);
    $dumpDate = date_parse_from_format("Ymd-His", $dumpData);
    if (
      is_array($dumpDate) &&
      is_int($dumpDate['hour']) &&
      is_int($dumpDate['minute']) &&
      is_int($dumpDate['second']) &&
      is_int($dumpDate['month']) &&
      is_int($dumpDate['day']) &&
      is_int($dumpDate['year'])
    ) {
      $dumpTimestamp = mktime(
        $dumpDate['hour'],
        $dumpDate['minute'],
        $dumpDate['second'],
        $dumpDate['month'],
        $dumpDate['day'],
        $dumpDate['year']
      );
      $dumpHrdate = 'ASDA DATE: ' . $dumpDate['day'] . ' ' . date('M', $dumpTimestamp) . ' ' . $dumpDate['year'] . ' at ' . $dumpDate['hour'] . ':' . $dumpDate['minute'];
      $this->io()->title($dumpHrdate);
    }

    $tasks[] = $this->taskExec('wget')
      ->option('-O', $options['dumpfile'] . '.gz')
      ->option('-i', self::TEMP_INPUTFILE)
      ->option('-A', 'sql.gz')
      ->option('-P', './');

    // Unzip the file.
    $tasks[] = $this->taskExec('gunzip')
      ->arg($options['dumpfile'] . '.gz');

    // Remove temporary files.
    $tasks[] = $this->taskExec('rm')
      ->arg('latest.sh1')
      ->arg(self::TEMP_INPUTFILE);

    // Build and return task collection.
    return $this->collectionBuilder()->addTaskList($tasks);
  }

  /**
   * Download Checksum file.
   *
   * Make use checksum file in order to detect the proper file to download.
   *
   * @param array $options
   *   Command options.
   */
  private function downloadChecksumFile(array $options = [
    'asda-url' => InputOption::VALUE_REQUIRED,
    'asda-user' => InputOption::VALUE_REQUIRED,
    'asda-password' => InputOption::VALUE_REQUIRED,
    'dumpfile' => InputOption::VALUE_REQUIRED,
  ]): void {
    $tmpDir = $this->getConfig()->get("toolkit.tmp_folder");

    // Create temp folder to prepare dist build in.
    $this->taskFilesystemStack()
      ->mkdir($tmpDir)
      ->run();

    $this->taskExec('wget')
      ->option('-i', self::TEMP_INPUTFILE)
      ->option('-O', 'latest.sh1')
      ->option('-A', '.sh1')
      ->option('-P', './')
      ->run();
  }

  /**
   * Created file for usage in wget --input-file argument in ::downloadDump().
   *
   * @param string $filename
   *   Name of filename to append to url.
   * @param array $options
   *   Command options.
   */
  private function generateAsdaWgetInputFile(string $filename, array $options = [
    'asda-url' => InputOption::VALUE_REQUIRED,
    'asda-user' => InputOption::VALUE_REQUIRED,
    'asda-password' => InputOption::VALUE_REQUIRED,
  ]): void {
    // Workaround to EWPP projects.
    $url = getenv('ASDA_URL') ?: $options['asda-url'];

    $disallowed = ['http://', 'https://'];
    foreach ($disallowed as $d) {
      if (strpos($url, $d) === 0) {
        $url = str_replace($d, '', $options['asda-url']);
      }
    }

    $downloadLink = 'https://' . $options['asda-user'] . ':' . $options['asda-password'] . '@' . $url . '/' . $filename;

    $this->taskFilesystemStack()
      ->taskWriteToFile(self::TEMP_INPUTFILE)
      ->line($downloadLink)
      ->run();
  }

  /**
   * Replaces the original toolkit:install-dump Toolkit command.
   *
   * @hook replace-command toolkit:install-dump
   */
  public function installDump(array $options = [
    'dumpfile' => InputOption::VALUE_REQUIRED,
  ]): CollectionBuilder {
    $tasks = [];

    if (!file_exists($options['dumpfile'])) {
      if (!getenv('CI')) {
        $this->say('"' . $options['dumpfile'] . '" file not found, use the command "toolkit:download-dump --dumpfile ' . $options['dumpfile'] . '".');
        return $this->collectionBuilder()->addTaskList($tasks);
      }
    }

    // Unzip and dump database file.
    $drush_bin = $this->getConfig()->get('runner.bin_dir') . '/drush';
    $tasks[] = $this->taskExecStack()
      ->stopOnFail()
      ->exec($drush_bin . ' sql-drop -y')
      ->exec($drush_bin . ' sql-create -y')
      ->exec($drush_bin . ' sqlc < ' . $options['dumpfile']);

    // Build and return task collection.
    return $this->collectionBuilder()->addTaskList($tasks);
  }

}
