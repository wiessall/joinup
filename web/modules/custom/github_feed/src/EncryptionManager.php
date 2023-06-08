<?php

declare(strict_types = 1);

namespace Drupal\github_feed;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\encrypt\EncryptServiceInterface;
use Drupal\encrypt\EncryptionProfileInterface;
use Drupal\encrypt\EncryptionProfileManagerInterface;

/**
 * Service description.
 */
class EncryptionManager {

  /**
   * Constructs an EncryptionManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\encrypt\EncryptionProfileManagerInterface $encryptionProfileManager
   *   The encrypt.encryption_profile.manager service.
   * @param \Drupal\encrypt\EncryptServiceInterface $encryption
   *   The encryption service.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file handler.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager, protected EncryptionProfileManagerInterface $encryptionProfileManager, protected EncryptServiceInterface $encryption, protected FileSystemInterface $fileSystem) {}

  /**
   * Encrypts a string using the associated encryption profile.
   *
   * @param string $string
   *   The string to encrypt.
   * @param string $encryptionProfile
   *   The encryption profile to use.
   *
   * @return string
   *   The encrypted string.
   */
  public function encrypt(string $string, string $encryptionProfile): string {
    $encryptionProfile = $this->encryptionProfileManager->getEncryptionProfile($encryptionProfile);
    $this->ensureEncryptionKey($encryptionProfile);
    return $this->encryption->encrypt($string, $encryptionProfile);
  }

  /**
   * Decrypts a string using the associated encryption profile.
   *
   * @param string $string
   *   The string to decrypt.
   * @param string $encryptionProfile
   *   The encryption profile to use.
   *
   * @return string
   *   The decrypted string.
   */
  public function decrypt(string $string, string $encryptionProfile): string {
    $encryptionProfile = $this->encryptionProfileManager->getEncryptionProfile($encryptionProfile);
    $this->ensureEncryptionKey($encryptionProfile);
    return $this->encryption->decrypt($string, $encryptionProfile);
  }

  /**
   * Ensures a key exists and generates it if not.
   *
   * @param \Drupal\encrypt\EncryptionProfileInterface $encryptionProfile
   *   The encryption profile.
   *
   * @return \Drupal\key\Entity\Key
   *   The encryption key.
   */
  protected function ensureEncryptionKey(EncryptionProfileInterface $encryptionProfile) {
    // The key is installed through configuration. We do not need to check if it
    // exists, but we do need to ensure it is valid.
    $key = $encryptionProfile->getEncryptionKey();

    // Get the path to the key file.
    $keySettings = $key->getKeyProvider()->getConfiguration();
    $fileLocation = $keySettings['file_location'];
    $base64 = $keySettings['base64_encoded'];
    $realPath = $this->fileSystem->realpath($fileLocation);
    if (!file_exists($realPath)) {
      // Extract the directory out of the file location and ensure it exists.
      $directory = dirname($realPath);
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      // Generate a key for real AES encryption - 256 bits (32 bytes). Maintain
      // the settings from the key provider.
      $command = $base64 ? 'dd if=/dev/urandom bs=32 count=1 | base64 -i' : 'dd if=/dev/urandom bs=32 count=1';
      $command .= ' > ' . $realPath;
      shell_exec($command);
    }

    return $key;
  }

}
