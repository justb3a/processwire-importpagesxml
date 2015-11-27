<?php

namespace Jos\Lib;

/**
 * Class Parser
 *
 * Performs parser tasks
 *
 * @package ImportPagesXml
 * @author Tabea David <td@justonestep.de>
 */
class Parser {

  /**
   * @field array Default config values
   */
  protected static $preConfigFields = array(
    'xpTemplate', 'xpParent', 'xpMode', 'xpImgPath'
  );

  /**
   * counts for update, create and delete
   *
   */
  protected $createdCount = 0;
  protected $updatedCount = 0;
  protected $deletedCount = 0;

 /**
  * construct
  */
  public function __construct() {
    $this->data = wire('modules')->getModuleConfigData(\ImportPagesXml::MODULE_NAME);
  }

  /**
   * Checks wheter this module is already pre-configured
   *
   * @return boolean
   *
   */
  public function isPreconfigured() {
    $state = false;
    if ($this->data['xpTemplate'] && $this->data['xpParent']) {
      $state = true;
    }
    return $state;
  }

  /**
   * Save pre configuration
   *
   */
  public function setPreconfiguration() {
    foreach (self::$preConfigFields as $field) {
      if (wire('input')->post->$field)
        $this->data[$field] = wire('input')->post->$field;
    }
    $this->save();
  }

  /**
   * Save configuration
   *
   */
  public function setConfiguration() {
    $this->data['xpContext'] = wire('input')->post->xpContext;
    $this->data['xpId'] = wire('input')->post->xpId;

    $template = wire('templates')->get($this->data['xpTemplate']);
    $toJson = array();
    foreach ($template->fields as $tfield) {
      $name = $tfield->name;
      $toJson[$name] = wire('input')->post->$name;

      // case Image, save description and tags as well
      if ($tfield->type->className === FieldtypeImage) {
        if ($tfield->descriptionRows > 0) {
          $nameDesc = $name . 'Description';
          $toJson[$nameDesc] = wire('input')->post->$nameDesc;
        }

        if ($tfield->useTags) {
          $nameTags = $name . 'Tags';
          $toJson[$nameTags] = wire('input')->post->$nameTags;
        }
      }
    }

    $this->data['xpFields'] = json_encode($toJson);
    $this->save();
  }

  /**
   * WireUpload xml file
   *
   * @param \InputfieldForm $form
   *
   */
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

  /**
   * Save module config data
   *
   */
  protected function save() {
    wire('modules')->saveModuleConfigData(\ImportPagesXml::MODULE_NAME, $this->data);
  }

  /**
   * Get and create upload directory if it isn't there already
   *
   * @return string
   *
   */
  protected function getUploadDir() {
    $uploadDir = wire('config')->paths->assets . 'files/' . $this->page->id . '/';
    if (!is_dir($uploadDir)) {
      if (!wireMkdir($uploadDir)) throw new WireException('No upload path!');
    }

    return $uploadDir;
  }

  /**
   * XML parse - handle Fieltype Image
   *
   * @param array $value
   * @param Page $page
   * @param Field $tfield
   * @param object $conf
   * @param array $item
   *
   */
  protected function handleFieldtypeImage($value, $page, $tfield, $conf, $item) {
    $imgPath = $this->data['xpImgPath'] . '/';
    foreach ($value as $key => $img) {
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

      // add tags
      if ($tfield->useTags) {
        $tagsName = $tfield->name . 'Tags';
        $isTagged = $item->xpath($conf->$tagsName);
        if (!isset($isTagged[$key])) continue; // xml node `image description` does not exist - skip
        $tag = $isTagged[$key]->__toString();
        $page->{$tfield->name}->last()->tags = $tag;
      }
    }
  }

  /**
   * Get SimpleXMLElement
   *
   * @return SimpleXMLElement
   *
   */
  protected function getSimpleXmlElement() {
    $xmlStringBase = file_get_contents($this->getUploadDir() . $this->data['xmlfile']);
    $xmlString = str_replace('xmlns=', 'ns=', $xmlStringBase); // deactivate xml namespaces to be able to use xpath without prefixes
    return new \SimpleXMLElement($xmlString);
  }

  /**
   * Delete matching pages depending on mode
   *
   */
  protected function deletePagesDependingOnMode() {
    // get mode, update or delete/recreate pages
    $mode = (int)$this->data['xpMode'];
    if ($mode === 2) $this->deletedCount = $this->deletePages(); // delete pages
  }

  /**
   * Get current page
   *
   * @param string $selector
   * @return Page
   *
   */
  protected function getCurrentPage($selector) {
    // check whether a page with this identifier already exists
    $page = wire('pages')->get($selector);

    // if not, create new page
    if (!$page->id) {
      $page = new \Page;
      $page->template = $this->data['xpTemplate'];
      $page->parent = $this->data['xpParent'];
      $page->save();
      $this->createdCount++;
    } else {
      $this->updatedCount++;
    }

    return $page;
  }

  /**
   * Get page title as well as name
   *
   * @param array $title
   * @return array
   *
   */
  protected function getPageTitleAndName($title) {
    $set = array();
    $containsTitle = reset($title);
    if ($containsTitle) {
      $titleValue = $containsTitle->__toString();
      $set['title'] = $titleValue;
      $set['name'] = wire('sanitizer')->pageNameTranslate($titleValue);
    }

    return $set;
  }

  /**
   * Get xpath - check for variables and substitute them
   *
   * @param string $xpath
   * @param array $set
   * @return $string
   */
  protected function getXpath($xpath, $set) {
    if (preg_match('/@(.*?)\=["\'](\$field_)(.*?)["\']/', $xpath, $matches)) {
      do {
        if (!isset($matches[3])) break; // no match found - break
        $key = $matches[3];
        if (!isset($set[$key])) break; // no related value was found - break
        $val = $set[$key];
        $xpath = preg_replace('/(\$field_)(.*?)(?=["\'])/', $val, $xpath);
      } while (0);
    }

    return $xpath;
  }

  /**
   * Parse XML
   *
   * @return array
   *
   */
  public function parse() {
    $xml = $this->getSimpleXmlElement();
    $conf = json_decode($this->data['xpFields']);
    $context = $this->data['xpContext'];
    $this->deletePagesDependingOnMode();

    // get identifier
    $fieldIdName = wire('fields')->get($this->data['xpId'])->name; // unique template field, identifier
    $fieldIdMapping = $conf->$fieldIdName; // unique field is mapped by ..

    // execute
    $items = $xml->xpath($context);
    foreach ($items as $item) {
      $idValue = reset($item->xpath($fieldIdMapping));
      if (!$idValue) break; // id value doesn't exist

      $page = $this->getCurrentPage("$fieldIdName={$idValue->__toString()}");
      $set = $this->getPageTitleAndName($item->xpath($conf->title)); // array containing page values

      // loop through template fields
      $template = wire('templates')->get($this->data['xpTemplate']);
      foreach ($template->fields as $tfield) {
        if (!($conf->{$tfield->name})) continue; // no value - skip
        if ($tfield->name === 'title') continue; // equals title field - skip

        // check if there is an entry
        $value = $item->xpath($this->getXpath($conf->{$tfield->name}, $set));
        if (!$value) continue; // no value in xml - skip

        // case Image
        if ($tfield->type->className === FieldtypeImage) {
          $this->handleFieldtypeImage($value, $page, $tfield, $conf, $item);
        } else {
          // add all other fields
          $set[$tfield->name] = reset($value)->__toString();
        }
      }

      $page->setAndSave($set);
    }

    return array('created' => $this->createdCount, 'deleted' => $this->deletedCount, 'updated' => $this->updatedCount);
  }

  /**
   * Delete pages
   *
   * @return integer
   *
   */
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
