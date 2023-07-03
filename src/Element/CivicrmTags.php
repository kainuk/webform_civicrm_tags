<?php

namespace Drupal\webform_civicrm_tags\Element;

use Drupal\Core\Render\Element\Select;
use Drupal\Core\Render\Element\Textfield;



/**
 * @FormElement("civicrm_tags")
 */
class CivicrmTags extends  Textfield  {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    $info = parent::getInfo();
    $info['#pre_render'][] = [$class, 'preRenderTags'];
    $info['#theme'] = 'select';
    //$info['#theme_wrappers'] = 'form_element';
    //$info['#process'] []= [$class, 'removeOptions'];
    return $info;
  }

  public static function preRenderTags($element) {

  }


}
