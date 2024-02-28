<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Installer;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests installing the Testing profile with update notifications on.
 *
 * @group Installer
 */
class TestingProfileInstallTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $profile = 'testing';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Ensure the Update module is installed.
   */
  public function testUpdateModuleInstall() {
    $this->assertTrue(\Drupal::moduleHandler()->moduleExists('update'));
  }

  /**
   * {@inheritdoc}
   */
  protected function installParameters() {
    $params = parent::installParameters();
    $params['forms']['install_configure_form']['enable_update_status_module'] = TRUE;
    return $params;
  }

}
