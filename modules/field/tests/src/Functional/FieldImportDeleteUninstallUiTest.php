<?php

declare(strict_types=1);

namespace Drupal\Tests\field\Functional;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests deleting field storage when a module in uninstalled through the UI.
 *
 * @group field
 * @see \Drupal\field\ConfigImporterFieldPurger
 * @see field_config_import_steps_alter()
 * @see field_form_config_admin_import_form_alter()
 */
class FieldImportDeleteUninstallUiTest extends FieldTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'entity_test',
    'telephone',
    'config',
    'filter',
    'datetime',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalLogin($this->drupalCreateUser(['synchronize configuration']));
  }

  /**
   * Tests deleting field storages and fields as part of config import.
   */
  public function testImportDeleteUninstall() {
    // Create a telephone field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_tel',
      'entity_type' => 'entity_test',
      'type' => 'telephone',
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
    ])->save();

    // Create a text field.
    $date_field_storage = FieldStorageConfig::create([
      'field_name' => 'field_date',
      'entity_type' => 'entity_test',
      'type' => 'datetime',
    ]);
    $date_field_storage->save();
    FieldConfig::create([
      'field_storage' => $date_field_storage,
      'bundle' => 'entity_test',
    ])->save();

    // Create an entity which has values for the telephone and text field.
    $entity = EntityTest::create();
    $value = '+0123456789';
    $entity->field_tel = $value;
    $entity->field_date = time();
    $entity->name->value = $this->randomMachineName();
    $entity->save();

    // Delete the text field before exporting configuration so that we can test
    // that deleted fields that are provided by modules that will be uninstalled
    // are also purged and that the UI message includes such fields.
    $date_field_storage->delete();

    // Verify entity has been created properly.
    $id = $entity->id();
    $entity = EntityTest::load($id);
    $this->assertEquals($value, $entity->field_tel->value);
    $this->assertEquals($value, $entity->field_tel[0]->value);

    $active = $this->container->get('config.storage');
    $sync = $this->container->get('config.storage.sync');
    $this->copyConfig($active, $sync);

    // Stage uninstall of the Telephone module.
    $core_extension = $this->config('core.extension')->get();
    unset($core_extension['module']['telephone']);
    $sync->write('core.extension', $core_extension);

    // Stage the field deletion including its dependencies.
    $sync->delete('field.storage.entity_test.field_tel');
    $sync->delete('field.field.entity_test.entity_test.field_tel');
    $sync->delete('core.entity_form_display.entity_test.entity_test.default');
    $this->drupalGet('admin/config/development/configuration');
    // Test that the message for one field being purged during a configuration
    // synchronization is correct.
    $this->assertSession()->pageTextContains('This synchronization will delete data from the field entity_test.field_tel.');

    // Stage an uninstall of the datetime module to test the message for
    // multiple fields.
    unset($core_extension['module']['datetime']);
    $sync->write('core.extension', $core_extension);

    $this->drupalGet('admin/config/development/configuration');
    $this->assertSession()->pageTextContains('This synchronization will delete data from the fields: entity_test.field_tel, entity_test.field_date.');

    // This will purge all the data, delete the field and uninstall the
    // Telephone and Text modules.
    $this->submitForm([], 'Import all');
    $this->assertSession()->pageTextNotContains('Field data will be deleted by this synchronization.');
    $this->rebuildContainer();
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('telephone'));
    $this->assertNull(\Drupal::service('entity.repository')->loadEntityByUuid('field_storage_config', $field_storage->uuid()), 'The telephone field has been deleted by the configuration synchronization');
    $deleted_storages = \Drupal::state()->get('field.storage.deleted', []);
    $this->assertFalse(isset($deleted_storages[$field_storage->uuid()]), 'Telephone field has been completed removed from the system.');
    $this->assertFalse(isset($deleted_storages[$field_storage->uuid()]), 'Text field has been completed removed from the system.');
  }

  /**
   * Tests if the synchronization form is available when the core.extension.yml is missing.
   */
  public function testSynchronizeForm(): void {
    $sync = $this->container->get('config.storage.sync');
    $this->copyConfig($this->container->get('config.storage'), $sync);

    $sync->delete('core.extension');
    $this->drupalGet('admin/config/development/configuration');
    $assertSession = $this->assertSession();
    $this->assertSession()->elementExists('css', 'input[value="Import all"]')->click();
    $assertSession->pageTextContains('The core.extension configuration does not exist.');
  }

}
