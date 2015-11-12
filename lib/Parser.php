<?php

namespace Jos;

class Parser {

 /**
  * construct
  */
  public function __construct() {
    $this->data = wire('modules')->getModuleConfigData(\XmlParser::MODULE_NAME);
    $this->input = wire('input');
    $this->templates = wire('templates');
    $this->pages = wire('pages');
  }

  public function isPreconfigured() {
    $state = false;
    if ($this->data['xpTemplate'] && $this->data['xpParent']) {
      $state = true;
    }
    return $state;
  }

  public function isPreconfigurationRunning() {
    $state = false;
    if ($this->input->post->xpTemplate || $this->input->post->xpParent) {
      $state = true;
    }
    return $state;
  }

  public function setPreconfiguration() {
    if ($this->input->post->xpTemplate) $this->data['xpTemplate'] = $this->input->post->xpTemplate;
    if ($this->input->post->xpParent) $this->data['xpParent'] = $this->input->post->xpParent;
    $this->save();
  }

  public function getPreconfiguration() {
    return array(
      array(
        'name' => __('Template'),
        'val' => $this->templates->get($this->data['xpTemplate'])->name
      ),
      array(
        'name' => __('Parent'),
        'val' => $this->pages->get($this->data['xpParent'])->title
      )
    );
  }

  public function save() {
    wire('modules')->saveModuleConfigData(\XmlParser::MODULE_NAME, $this->data);
  }

}
