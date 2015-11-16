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
    $this->data['xpContext'] = wire('input')->post->xpContext;
    $this->data['xpId'] = wire('input')->post->xpId;

    $template = wire('templates')->get($this->data['xpTemplate']);
    $toJson = array();
    foreach ($template->fields as $tfield) {
      $name = $tfield->name;
      $toJson[$name] = wire('input')->post->$name;
    }

    $this->data['xpFields'] = json_encode($toJson);
    $this->save();
  }

  public function setXmlFile($form) {
    // new WireUpload
    $ul = new \WireUpload('xmlfile');
    $ul->setValidExtensions(array('xml'));
    $ul->setMaxFiles(1);
    $ul->setOverwrite(true);
    $ul->setDestinationPath($this->getUploadDir());
    $ul->setLowercase(false);
    $files = $ul->execute();

    if (count($files)) {
      $form->message(__('XML upload sucessfull'));

      $this->data['xmlfile'] = reset($files);
      $this->save();
      wire('session')->redirect($this->page->url . '?action=parse');
    } else {
      $form->error(__('The file could not be uploaded, please try again.'));
    }
  }

  public function save() {
    wire('modules')->saveModuleConfigData(\XmlParser::MODULE_NAME, $this->data);
  }

  protected function getUploadDir() {
    // create upload directory if it isn't there already
    $uploadDir = wire('config')->paths->assets . 'files/' . $this->page->id . '/';
    if (!is_dir($uploadDir)) {
      if (!wireMkdir($uploadDir)) throw new WireException('No upload path!');
    }

    return $uploadDir;
  }

  public function parse() {
    $xml = simplexml_load_file($this->getUploadDir() . $this->data['xmlfile']);
    $context = $this->data['xpContext'];
    $template = wire('templates')->get($this->data['xpTemplate']);
    $conf = json_decode($this->data['xpFields']);

    $counter = 0;
    $items = $xml->xpath($context);
    foreach ($items as $item) {
      $page = new \Page;
      $page->template = $this->data['xpTemplate'];
      $page->parent = $this->data['xpParent'];
      $page->save();

      $set = array();
      foreach ($template->fields as $tfield) {
        if (!($conf->{$tfield->name})) continue;
        $set[$tfield->name] = reset($item->xpath($conf->{$tfield->name}))->__toString();
      }

      $page->setAndSave($set);
      $counter++;
    }

    return $counter;
  }

}
