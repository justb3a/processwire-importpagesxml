<?php

namespace Jos;

class View {

 /**
  * construct
  */
  public function __construct() {
    $this->data = wire('modules')->getModuleConfigData(\XmlParser::MODULE_NAME);
  }

  protected function getForm() {
    $form = wire('modules')->get('InputfieldForm');
    $form->action = './';
    $form->method = 'post';

    return $form;
  }

  protected function getWrapper($title) {
    $wrapper = new \InputfieldWrapper();
    $wrapper->attr('title', $title);

    return $wrapper;
  }

  protected function getFieldset($label) {
    $set = wire('modules')->get('InputfieldFieldset');
    $set->label = $label;

    return $set;
  }

  /**
   * Add a submit button, moved to a function so we don't have to do this several times
   */
  protected function addSubmit(\InputfieldForm $form, $name = 'submit') {
    $f = wire('modules')->get('InputfieldSubmit');
    $f->name = $name;
    $f->value = __('Submit');
    $form->add($f);
  }

  protected function getField($type, $label, $name, $value, $description = '', $columnWidth = 50, $required = false) {
    $field = wire('modules')->get($type);
    $field->label = $label;
    $field->description = $description;
    $field->name = $name;
    $field->attr('value', $value);
    $field->columnWidth = $columnWidth;
    $field->required = $required;

    return $field;
  }

  protected function getAssignedFields($field) {
    $template = wire('templates')->get($this->data['xpTemplate']);
    foreach ($template->fields as $tfield) {
      $field->addOption($tfield->id, $tfield->name);
    }

    return $field;
  }

  protected function getPreconfiguration() {
    return array(
      array(
        'name' => __('Template'),
        'val' => wire('templates')->get($this->data['xpTemplate'])->name
      ),
      array(
        'name' => __('Parent'),
        'val' => wire('pages')->get($this->data['xpParent'])->title
      )
    );
  }

  protected function getConfiguration() {
    return json_decode($this->data['xpFields']);
  }

  public function renderMappingForm() {
    $form = $this->getForm();
    $wrapper = $this->getWrapper(__('XPATH Parser Settings'));
    $set1 = $this->getFieldset(__('XPATH Parser Settings'));
    $set2 = $this->getFieldset(__('Mapping'));

    // field context
    $fieldC = $this->getField(
      'InputfieldSelect',
      __('Context'),
      'xpcontext',
      $values->xpcontext,
      __('This is the base query, all other queries will run in this context.'),
      50,
      true
    );
    $this->getAssignedFields($fieldC);

    // field title
    $fieldT = $this->getField(
      'InputfieldSelect',
      __('Title'),
      'xptitle',
      $values->xptitle,
      __('Field Title is mandatory and considered unique: only one item per Title value will be created.'),
      50,
      true
    );
    $this->getAssignedFields($fieldT);

    // mapping fields
    $template = wire('templates')->get($this->data['xpTemplate']);
    $values = $this->getConfiguration();
    foreach ($template->fields as $tfield) {
      // @todo: implement repeater handling
      // @todo: implement image extra fields (description, tags..) handling
      $field = $this->getField('InputfieldText', $tfield->name, $tfield->name, $values->{$tfield->name});
      $field->size = 30;
      $set2->add($field);
    }

    $set1->add($fieldC)->add($fieldT);
    $wrapper->add($set1)->add($set2);
    $form->add($wrapper);
    $this->addSubmit($form, 'mappingSubmit');

    return $form->render();
  }

  public function render() {
    $this->output = '<dl class="nav">';
    $this->output .= $this->renderPreconfigurationView();
    $this->output .= $this->renderConfigurationView();
    $this->output .= '</dl>';
    $this->renderUploadForm();

    return $this->output;
  }

  protected function renderPreconfigurationView() {
    $edit = $this->page->url . '?action=edit-preconf';
    foreach ($this->getPreconfiguration() as $config) {
      $this->output .= "<dt><a class='label' href='$edit'>{$config['name']}</a></dt>";
      $this->output .= "<dd><div class='actions'>{$config['val']} <a href='$edit'>" . __('Edit') . "</a></div></dd>";
    }
  }

  protected function renderConfigurationView() {
    $edit = $this->page->url . '?action=edit-conf';
    $this->output .= "<dt><a class='label' href='$edit'>" . __('Mapping') . "</a></dt>";
    $this->output .= "<dd><div class='actions content'><table><tr><th>" . __('Field') . "</th><th>" . __('Mapping') . "</th></tr>";

    foreach ($this->getConfiguration() as $field => $config) {
      $this->output .= "<tr><td style='padding-right: 1.5rem;'>{$field}</td><td>{$config}</td></tr>";
    }

    $this->output .= "</table><a href='$edit'>" . __('Edit') . "</a></div></dd>";
  }

  protected function renderUploadForm() {
    $form = $this->getForm();
    $wrapper = $this->getWrapper(__('Upload XML'));

    $field = $this->getField(
      'InputfieldFile',
      __('XML File'),
      'xmlfile',
      '',
      __('Upload your xml file')
    );
    $field->destinationPath = $this->config->uploadTmpDir;
    $field->extensions = 'xml';
    $field->maxFiles = 1;
    $field->maxFilesize = 2*1024*1024; // 2mb

    $wrapper->add($field);
    $form->add($wrapper);
    $this->addSubmit($form, 'uploadSubmit');

    $this->output .= $form->render();
  }

  public function renderPreconfigurationForm() {
    $form = $this->getForm();
    $wrapper = $this->getWrapper(__('Overview'));
    $set = $this->getFieldset(__('Settings'));

    $fieldTemplate = $this->getField(
      'InputfieldSelect',
      __('Template'),
      'xpTemplate',
      $this->data['xpTemplate'],
      '',
      50,
      true
    );

    foreach (wire('templates') as $template) {
      if ($template->flags & \Template::flagSystem) continue;
      $fieldTemplate->addOption($template->id, (!empty($template->label) ? $template->label : $template->name));
    }

    $fieldPage = $this->getField(
      'InputfieldPageListSelect',
      __('Parent Page'),
      'xpParent',
      $this->data['xpParent'],
      '',
      50,
      true
    );

    $set->add($fieldTemplate)->add($fieldPage);
    $wrapper->add($set);
    $form->add($wrapper);
    $this->addSubmit($form, 'preconfigSubmit');

    return $form->render();
  }

}
