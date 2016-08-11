<?php

namespace Jos\Lib;

use \ProcessWire\ImportPagesXML;
use \ProcessWire\InputfieldWrapper;
use \ProcessWire\InputfieldForm;
use \ProcessWire\Template;

/**
 * Class View
 *
 * Handles the views
 *
 * @package ImportPagesXml
 * @author Tabea David <td@justonestep.de>
 */
class View extends \ProcessWire\Wire {

  /**
   * Available update modes
   *
   */
  const MODE_1 = 'Update existing pages';
  const MODE_2 = 'Delete and recreate pages';

  /**
   * @field array Default config values
   */
  protected static $fieldtypeExcludes = array(
    'FieldtypeFieldsetTabOpen', 'FieldtypeFieldsetOpen', 'FieldtypeFieldsetClose'
  );

 /**
  * construct
  */
  public function __construct() {
    $this->setData();
  }

  /**
   * Get module config data
   *
   */
  public function setData() {
    $this->data = $this->wire('modules')->getModuleConfigData(ImportPagesXml::MODULE_NAME);
  }

  /**
   * Render headline
   *
   * @return string
   *
   */
  public function renderHeadline() {
    return '<h2>' . $this->_('Import Pages From XML') . '</h2><hr>';
  }

  /**
   * Get basic form
   *
   * @param boolean $isUpload
   * @return InputfieldForm
   *
   */
  protected function getForm($isUpload = false) {
    $form = $this->wire('modules')->get('InputfieldForm');
    $form->action = './';
    $form->method = 'post';

    if ($isUpload) $form->attr('enctype', 'multipart/form-data');

    return $form;
  }

  /**
   * Get basic wrapper
   *
   * @param string $title
   * @return \InputfieldWrapper
   *
   */
  protected function getWrapper($title) {
    $wrapper = new InputfieldWrapper();
    $wrapper->attr('title', $title);

    return $wrapper;
  }

  /**
   *  Get basic fieldset
   *
   *  @param string $label
   *  @return \InputfieldFieldset
   *
   */
  protected function getFieldset($label) {
    $set = $this->wire('modules')->get('InputfieldFieldset');
    $set->label = $label;

    return $set;
  }

  /**
   * Add a submit button, moved to a function so we don't have to do this several times
   *
   * @param \InputfieldForm $form
   * @param string $name
   *
   */
  protected function addSubmit(InputfieldForm $form, $name = 'submit') {
    $f = $this->wire('modules')->get('InputfieldSubmit');
    $f->name = $name;
    $f->value = $this->_('Submit');
    $form->add($f);
  }

  /**
   * Get basic field
   *
   * @param string $type
   * @param string $label
   * @param string $name
   * @param string $value
   * @param string $description
   * @param integer $columnWidth
   * @param boolean $required
   * @return \Field
   *
   */
  protected function getField($type, $label, $name, $value, $description = '', $columnWidth = 50, $required = false) {
    $field = $this->wire('modules')->get($type);
    $field->label = $label;
    $field->description = $description;
    $field->name = $name;
    $field->attr('value', $value);
    $field->columnWidth = $columnWidth;
    $field->required = $required;

    return $field;
  }

  /**
   * Get assigned fields
   *
   * @param \Field $field
   * @return \Field $field
   *
   */
  protected function getAssignedFields($field) {
    $template = $this->wire('templates')->get($this->data['xpTemplate']);
    foreach ($template->fields as $tfield) {
      $field->addOption($tfield->id, $tfield->name);
    }

    return $field;
  }

  /**
   * Get pre-configuration
   *
   * @return array
   *
   */
  protected function getPreconfiguration() {
    return array(
      array(
        'name' => $this->_('Template'),
        'val' => $this->wire('templates')->get($this->data['xpTemplate'])->name
      ),
      array(
        'name' => $this->_('Parent'),
        'val' => $this->wire('pages')->get($this->data['xpParent'])->title
      ),
      array(
        'name' => $this->_('Update mode'),
        'val' => constant('self::MODE_' . $this->data['xpMode'])
      ),
      array(
        'name' => $this->_('Path to images'),
        'val' => $this->data['xpImgPath']
      )
    );
  }

  /**
   * Get configuration
   *
   * @return array
   *
   */
  protected function getConfiguration() {
    return json_decode($this->getData('xpFields'));
  }

  /**
   * Get data if exists
   *
   * @param string $key
   * @return string
   *
   */
  protected function getData($key) {
    return array_key_exists($key, $this->data) ? $this->data[$key] : '';
  }

  /**
   * Render mapping form - step 2
   *
   * @return string
   *
   */
  public function renderMappingForm() {
    $form = $this->getForm();
    $form->description = $this->_("Step 2: Mapping Settings");
    $wrapper = $this->getWrapper($this->_('XPATH Parser Settings'));
    $set1 = $this->getFieldset($this->_('XPATH Parser Settings'));
    $set2 = $this->getFieldset($this->_('Mapping'));

    // field context
    $fieldC = $this->getField(
      'InputfieldText',
      $this->_('Context'),
      'xpContext',
      $this->getData('xpContext'),
      $this->_('This is the base query, all other queries will run in this context.'),
      50,
      true
    );

    // field title
    $fieldT = $this->getField(
      'InputfieldSelect',
      $this->_('Title'),
      'xpId',
      $this->getData('xpId'),
      $this->_('Field Id is mandatory and considered unique: only one item per Title value will be created.'),
      50,
      true
    );
    $this->getAssignedFields($fieldT);

    // mapping fields
    $template = $this->wire('templates')->get($this->data['xpTemplate']);
    $values = $this->getConfiguration();

    if (is_object($this->getConfiguration())) {
      foreach ($template->fields as $tfield) {
        if (in_array($tfield->type->className, self::$fieldtypeExcludes)) continue; // skip some fields
        $label = $tfield->label ? $tfield->label : $tfield->name;
        $field = $this->getField('InputfieldText', $label, $tfield->name, $values->{$tfield->name});
        $field->size = 30;
        $set2->add($field);

        // case Image add description and tags
        if ($tfield->type->className === 'FieldtypeImage') {
          // add description
          if ($tfield->descriptionRows > 0) {
            $descName = $tfield->name . 'Description';
            $fieldDesc = $this->getField(
              'InputfieldText',
              $label . ' Description',
              $descName,
              $values->$descName
            );
            $fieldDesc->size = 30;
            $set2->add($fieldDesc);
          }

          // add tags
          if ($tfield->useTags) {
            $tagsName = $tfield->name . 'Tags';
            $fieldTags = $this->getField(
              'InputfieldText',
              $label . ' Tags',
              $tagsName,
              $values->$tagsName
            );
            $fieldTags->size = 30;
            $set2->add($fieldTags);
          }
        }
      }
    }

    $set1->add($fieldC)->add($fieldT);
    $wrapper->add($set1)->add($set2);
    $form->add($wrapper);
    $this->addSubmit($form, 'mappingSubmit');

    return $form->render();
  }

  /**
   * Render configuration views
   *
   * @param boolean $isAdmin
   * @return string
   *
   */
  public function render($isAdmin) {
    $this->output = '<dl class="nav">';
    $this->output .= $this->renderPreconfigurationView($isAdmin);
    if ($isAdmin) $this->output .= $this->renderConfigurationView();
    $this->output .= '</dl>';

    return $this->output;
  }

  /**
   * Render pre-configuration
   *
   * @param boolean $isAdmin
   * @return string
   *
   */
  protected function renderPreconfigurationView($isAdmin) {
    $edit = $this->wire('page')->url . '?action=edit-preconf';
    $this->output .= "<dt><a class='label' href='$edit'>" . $this->_('Configuration') . "</a></dt><dd><table>";

    foreach ($this->getPreconfiguration() as $count => $config) {
      if (!$isAdmin && $count !== count($config) + 1) continue;
      $this->output .= "<tr><th style='padding-right: 1.5rem;'>{$config['name']}</th>";
      $this->output .= "<td>{$config['val']}</td></tr>";
    }
    $this->output .= "</table><a href='$edit' class='ui-button  ui-button-text'>" . $this->_('Edit') . "</a></dd>";
  }

  /**
   * Render configuration view
   *
   * @return string
   *
   */
  protected function renderConfigurationView() {
    $edit = $this->wire('page')->url . '?action=edit-conf';
    $fieldId = $this->getData('xpId') ?  $this->wire('fields')->get($this->data['xpId'])->name : '';

    $this->output .= "<dt><a class='label' href='$edit'>" . $this->_('Mapping') . "</a></dt>";
    $this->output .= "<dd><table><tr><th style='padding-right: 1.5rem;'>" . $this->_('Context') . "</th><td>" . $this->getData('xpContext') . "</td></tr>";
    $this->output .= "<tr><th style='padding-right: 1.5rem;'>" . $this->_('Id') . "</th><td>" . $fieldId . "</td></tr></table>";
    $this->output .= "<table><tr><th>" . $this->_('Field') . "</th><th>" . $this->_('Mapping') . "</th></tr>";

    if (is_object($this->getConfiguration())) {
      foreach ($this->getConfiguration() as $field => $config) {
        if (!$config) continue;
        $this->output .= "<tr><td style='padding-right: 1.5rem;'>{$field}</td><td>{$config}</td></tr>";
      }
    }

    $this->output .= "</table><a href='$edit' class='ui-button  ui-button-text'>" . $this->_('Edit') . "</a></dd>";
  }

  /**
   * Render reparse uploaded XML file
   *
   * @return $output
   *
   */
  public function renderUploadedFile() {
    $output = '';
    if ($this->getData('xmlfile')) {
      $output .= '<p><strong>' . $this->_('Selected File') . ':</strong> ' . $this->data['xmlfile'];
      $output .= '<a href="' . $this->wire('page')->url . '?action=parse" style="margin-left: 10px;" class="ui-button  ui-button-text">' . $this->_('Reparse file')  . '</a></p>';
    }

    return $output;
  }

  /**
   * Render upload form
   *
   * @return \InputfieldForm
   *
   */
  public function renderUploadForm() {
    $form = $this->getForm(true);
    $wrapper = $this->getWrapper($this->_('Upload XML'));

    $field = $this->wire('modules')->get('InputfieldFile');
    $field->extensions = 'xml';
    $field->maxFiles = 1;
    $field->descriptionRows = 0;
    $field->overwrite = true;
    $field->attr('id+name', 'xmlfile');
    $field->label = $this->_('XML File');
    $field->description = $this->_('Upload a XML file.');

    $wrapper->add($field);
    $form->add($wrapper);
    $this->addSubmit($form, 'uploadSubmit');

    return $form;
  }

  /**
   * Render pre-configuration form
   *
   * @param boolean $isAdmin
   * @return \InputfieldForm
   */
  public function renderPreconfigurationForm($isAdmin) {
    $form = $this->getForm();
    $form->description = $this->_("Step 1: Configuration Settings");
    $wrapper = $this->getWrapper($this->_('Overview'));
    $set = $this->getFieldset($this->_('Settings'));

    if ($isAdmin) {
      $fieldTemplate = $this->getField(
        'InputfieldSelect',
        $this->_('Template'),
        'xpTemplate',
        $this->getData('xpTemplate'),
        '',
        50,
        true
      );

      foreach ($this->wire('templates') as $template) {
        if ($template->flags & Template::flagSystem) continue;
        $fieldTemplate->addOption($template->id, (!empty($template->label) ? $template->label : $template->name));
      }

      $fieldPage = $this->getField(
        'InputfieldPageListSelect',
        $this->_('Parent Page'),
        'xpParent',
        $this->getData('xpParent'),
        '',
        50,
        true
      );

      $fieldMode = $this->getField(
        'InputfieldSelect',
        $this->_('Update Mode'),
        'xpMode',
        $this->getData('xpMode'),
        $this->_('Existing pages will be determined using mappings that are a "unique target".'),
        50,
        true
      );

      $fieldMode
        ->addOption(1, $this->_(self::MODE_1))
        ->addOption(2, $this->_(self::MODE_2));

      $set->add($fieldTemplate)->add($fieldPage)->add($fieldMode);
    }

    $fieldImgPath = $this->getField(
      'InputfieldText',
      $this->_('Path to images'),
      'xpImgPath',
      $this->getData('xpImgPath'),
      $this->_('Path where the images are placed, without ending `/`.'),
      50
    );

    $set->add($fieldImgPath);
    $wrapper->add($set);
    $form->add($wrapper);
    $this->addSubmit($form, 'preconfigSubmit');

    return $form->render();
  }

}
