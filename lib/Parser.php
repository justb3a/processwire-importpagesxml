<?php

namespace Jos\Lib;

class Parser {

  /**
   * @field array Default config values
   */
  protected static $preConfigFields = array(
    'xpTemplate', 'xpParent', 'xpMode', 'xpImgPath'
  );

 /**
  * construct
  */
  public function __construct() {
    $this->data = wire('modules')->getModuleConfigData(\ImportPagesXml::MODULE_NAME);
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

      // case Image, save description as well
      if ($tfield->type->className === FieldtypeImage && $tfield->descriptionRows > 0) {
        $name = $name . 'Description';
        $toJson[$name] = wire('input')->post->$name;
      }
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
    wire('modules')->saveModuleConfigData(\ImportPagesXml::MODULE_NAME, $this->data);
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
    $createdCount = 0;
    $updatedCount = 0;
    $deletedCount = 0;

    // get xml file, configuration and image path
    $xml = simplexml_load_file($this->getUploadDir() . $this->data['xmlfile']);
    $conf = json_decode($this->data['xpFields']);
    $imgPath = $this->data['xpImgPath'] . '/';

    // get mode, update or delete/recreate pages
    $mode = (int)$this->data['xpMode'];
    if ($mode === 2) $deletedCount = $this->deletePages(); // delete pages

    // get identifier
    $fieldIdName = wire('fields')->get($this->data['xpId'])->name; // unique template field, identifier
    $fieldIdMapping = $conf->$fieldIdName; // unique field is mapped by ..

    // execute
    $items = $xml->xpath($this->data['xpContext']);
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

      // set page title and url
      $titleExist = reset($item->xpath('title'));
      if ($titleExist) {
        $titleValue = $titleExist->__toString();
        $set['title'] = $titleValue;
        $set['name'] = wire('sanitizer')->pageNameTranslate($titleValue);
      }

      // loop through template fields
      $template = wire('templates')->get($this->data['xpTemplate']);
      foreach ($template->fields as $tfield) {
        if (!($conf->{$tfield->name})) continue; // no value - skip
        if ($tfield->name === 'title') continue; // equals title field - skip

        // case Image
        if ($tfield->type->className === FieldtypeImage) {
          $isImg = $item->xpath($conf->{$tfield->name});

          if (!$isImg) continue; // xml node `image` does not exist - skip
          foreach ($isImg as $key => $img) {
            // add image
            $imgName = $imgPath . $img->__toString();
            if (!file_exists($imgName)) continue; // file does not exist - skip
            $page->{$tfield->name}->add($imgName);

            // add description
            if ($tfield->descriptionRows > 0) {
              $descName = $tfield->name . 'Description';
              $isDesc = $item->xpath($conf->$descName);
              if (!isset($isDesc[$key])) continue; // xml node `image description` does not exist - skip
              $desc = $isDesc[$key]->__toString();
              $page->{$tfield->name}->last()->description = $desc;
            }
          }

        } else {
          // add all other fields
          $set[$tfield->name] = reset($item->xpath($conf->{$tfield->name}))->__toString();
        }
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
