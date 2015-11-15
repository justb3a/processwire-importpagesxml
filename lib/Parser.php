<?php

namespace Jos;

class Parser {

 /**
  * construct
  */
  public function __construct() {
    $this->data = wire('modules')->getModuleConfigData(\XmlParser::MODULE_NAME);
  }

  public function isPreconfigured() {
    $state = false;
    if ($this->data['xpTemplate'] && $this->data['xpParent']) {
      $state = true;
    }
    return $state;
  }

  public function setPreconfiguration() {
    if (wire('input')->post->xpTemplate) $this->data['xpTemplate'] = wire('input')->post->xpTemplate;
    if (wire('input')->post->xpParent) $this->data['xpParent'] = wire('input')->post->xpParent;
    if (wire('input')->post->xpMode) $this->data['xpMode'] = wire('input')->post->xpMode;
    $this->save();
  }

  public function setConfiguration() {
    $template = wire('templates')->get($this->data['xpTemplate']);
    $toJson = array();
    foreach ($template->fields as $tfield) {
      $name = $tfield->name;
      $toJson[$name] = wire('input')->post->$name;
    }

    $this->data['xpFields'] = json_encode($toJson);
    $this->save();
  }

  public function save() {
    wire('modules')->saveModuleConfigData(\XmlParser::MODULE_NAME, $this->data);
  }

}
