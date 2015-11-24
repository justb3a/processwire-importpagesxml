<?php

namespace Jos\Lib;

class Parser {

  /**
   * @field array Default config values
   */
  protected static $preConfigFields = array(
    'xpTmplate', 'xpParent', 'xpMode', 'xpImgPath'
  );

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
    foreach (self::$preConfigFields as $field) {
      if (wire('input')->post->$field)
        $this->data[$field] = wire('input')->post->$field;
    }
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

    $mode = (int)$this->data['xpMode'];

    // delete pages
    if ($mode === 2) $deletedCount = $this->deletePages();

    $fieldIdName = wire('fields')->get($this->data['xpId'])->name; // field track name
    $fieldIdMapping = $conf->$fieldIdName; // @track

    $createdCount = 0;
    $updatedCount = 0;
    $deletedCount = 0;
    $items = $xml->xpath($context);
    foreach ($items as $item) {
      $idValue = reset($item->xpath($fieldIdMapping))->__toString();

      // check whether a page with this identifier already exists
      $page = wire('pages')->get("$fieldIdName=$idValue");

      // if not, create new page
      if (!$page->id) {
        $page = new \Page;
        $page->template = $this->data['xpTemplate'];
        $page->parent = $this->data['xpParent'];
        $page->save();
        $createdCount++;
      } else {
        $updatedCount++;
      }

      $set = array();

      // set title and url
      $titleExist = reset($item->xpath('title'));
      if ($titleExist) {
        $titleValue = $titleExist->__toString();
        $set['title'] = $titleValue;
        $set['name'] = wire('sanitizer')->pageNameTranslate($titleValue);
      }

      foreach ($template->fields as $tfield) {
        if (!($conf->{$tfield->name})) continue; // no value? continue
        if ($tfield->name === 'title') continue; // equals title field? continue
        $set[$tfield->name] = reset($item->xpath($conf->{$tfield->name}))->__toString();
      }

      $page->setAndSave($set);
    }

    return array('created' => $createdCount, 'deleted' => $deletedCount, 'updated' => $updatedCount);
  }

  protected function deletePages() {
    $trashPages = wire('pages')->find('has_parent=' . $this->data['xpParent'] . ', template=' . $this->data['xpTemplate']);
    $count = 0;
    if ($trashPages->count() > 0) {
      foreach ($trashPages as $trashPage) {
        if ($trashPage instanceof \NullPage) continue;
        wire('pages')->delete($trashPage, true);
        $count++;
      }
    }
    return $count;
  }

}
