<?php
/*
 * @author Klaas Eikelboom  <klaas.eikelboom@civicoop.org>
 * @date 03-Jul-2023
 * @license  AGPL-3.0
 */

namespace Drupal\webform_civicrm_tags\Plugin\WebformElement;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\webform\Plugin\WebformElement\OptionsBase;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Provides a 'civicrm_tags' element.
 *
 * @WebformElement(
 *   id = "civicrm_tags",
 *   label = @Translation("CiviCRM Tags"),
 *   description = @Translation("Provides a CiviCRM powered tags."),
 *   category = @Translation("Options elements"),
 * )
 *
 * @see \Drupal\webform_example_element\Element\WebformExampleElement
 * @see \Drupal\webform\Plugin\WebformElementBase
 * @see \Drupal\webform\Plugin\WebformElementInterface
 * @see \Drupal\webform\Annotation\WebformElement
 */
class CivicrmTags extends OptionsBase {

  /**
   * {@inheritdoc}
   */
  public function getDefaultProperties() {
    return [
        'form_key' => '',
        'pid' => 0,
        'value' => '',
        'empty_option' => '',
        'empty_value' => '',
        'root_tags' => [],
        'exposed_empty_option' => '- ' . t('Automatic') . ' -',
        'default_option' => '',
        'data_type' => NULL,
        'extra' => [
          'multiple' => FALSE,
        ],
      ] + parent::getDefaultProperties();
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    // Get element properties.
    $element_properties = $form_state->getValues() ?: $form_state->get('element_properties');

    $form['options'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Element options'),
      '#open' => TRUE,
      '#prefix' => '<div id="webform-civicrm-options-wrapper">',
      '#suffix' => '</div>',
    ];

    $form['options']['root_tags'] = [
       '#type' => 'select',
       '#theme' => 'select2tree',
       '#title' => $this->t('Root Tags'),
       '#multiple' => true,
       '#options' => $this->tags(),
       '#attributes' => ['select2tree' =>  $element_properties['form_key']]
    ];

    $form['extra'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Extra'),
      '#open' => TRUE,
      '#access' => TRUE,
      '#parents' => ['properties', 'extra'],
    ];
    $form['extra']['multiple'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Multiple'),
      '#description' => $this->t('Check this option if multiple options can be selected for the input field.'),
      '#access' => TRUE,
      '#default_value' => $element_properties['extra']['multiple'] ?? FALSE,
      '#parents' => ['properties', 'extra', 'multiple'],
    ];
    $form['options']['#attached']['library'][] = 'webform_civicrm_tags/select_tree';
    $form['options']['#attached']['drupalSettings']['allowedtags'][$element_properties['form_key']] =$this->rootTags($element_properties['root_tags']);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationFormProperties(array &$form, FormStateInterface $form_state) {
    $properties = parent::getConfigurationFormProperties($form, $form_state);
    if ($this->isNumberField($properties)) {
      return $properties;
    }
    if (!empty($form['properties'])) {
      // Get additional properties off of the options element.
      $select_options = $form['properties']['options']['options'];
      $properties['#default_option'] = $select_options['#default_option'];
      if (empty($properties['#default_value'])) {
        $properties['#default_value'] = $select_options['#default_option'];
      }
    }
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function prepare(array &$element, WebformSubmissionInterface $webform_submission = NULL) {
    \Drupal::service('civicrm')->initialize();

    $is_multiple = !empty($element['#extra']['multiple']);
    $data = [];
    if ($webform_submission && $webform_submission->getWebform()->getHandlers()->has('webform_civicrm')) {
      $data = $webform_submission->getWebform()->getHandler('webform_civicrm')->getConfiguration()['settings']['data'] ?? [];
    }

    if (empty($element['#default_value']) && !empty($element['#default_option'])) {
      $element['#default_value'] = $element['#default_option'];
    }

    $element['#type'] = 'select';
    $element['#theme'] = 'select2tree';
    $allowedTagsFlatten = $this->allowedTagsFlatten($this->allowedTags($element['#root_tags'], []));

    $element['#options'] = $allowedTagsFlatten;
    if ($is_multiple) {
      $element['#multiple'] = TRUE;
    }
    $element['#attached']['library'][] = 'webform_civicrm_tags/select_tree';
    $defaults = $this->findContactTags($this->findCurrentContact());
    $defaults = array_intersect($defaults,$allowedTagsFlatten);
    $element['#attached']['drupalSettings']['allowedtags'][$element['#form_key']] = $this->allowedTags($element['#root_tags'],$defaults);
    $element['#attributes']['select2tree']=$element['#form_key'];
    parent::prepare($element, $webform_submission);
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareElementValidateCallbacks(array &$element, WebformSubmissionInterface $webform_submission = NULL) {
    parent::prepareElementValidateCallbacks($element, $webform_submission);
    // Disable default form validation on state select field, since options are loaded via js.
    if (strpos($element['#form_key'], 'state_province_id') !== false) {
      unset($element['#needs_validation']);
      $element['#validated'] = TRUE;
    }
  }

  /**
   * If this is a CiviCRM Number element.
   *
   * @param array $element
   *
   * @return bool
   */
  protected function isNumberField($element) {
    $form_key = $element['form_key'] ?? $element['#form_key'] ?? NULL;
    if (!empty($form_key)) {
      $field = \Drupal::service('webform_civicrm.utils')->wf_crm_get_field($form_key);
      if (isset($field['type']) && $field['type'] == 'civicrm_number') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Ajax callback.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The properties element.
   */
  public static function ajaxCallback(array $form, FormStateInterface $form_state) {
    $radio = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($radio['#array_parents'], 0, -2));
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function hasMultipleWrapper() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function hasMultipleValues(array $element) {
    return \Drupal::service('webform_civicrm.utils')->hasMultipleValues($element);
  }

  /**
   * {@inheritdoc}
   */
  public function getRelatedTypes(array $element) {
    $types = [];
    $has_multiple_values = $this->hasMultipleValues($element);

    $supportedTypes = [
      'checkboxes',
      'radios',
      'webform_radios_other',
      'select',
      'webform_select_other',
      'civicrm_number'
    ];
    $elements = $this->elementManager->getInstances();
    foreach ($elements as $element_name => $element_instance) {
      if (in_array($element_name, $supportedTypes)) {
        $types[$element_name] = $element_instance->getPluginLabel();
      }
    }

    asort($types);
    return $types;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $properties = $this->getConfigurationFormProperties($form, $form_state);
    if ($this->isNumberField($properties)) {
      foreach ($properties['#options'] as $key => $option) {
        if (!is_numeric($key)) {
          $form_state->setErrorByName('options', $this->t('This is a CiviCRM number field. @field keys must be numeric.', ['@field' => $properties['#title']]));
          break;
        }
      }
    }
  }

  /**
   * @param array $defaults
   *
   * @return array
   */
  private function rootTags(array $defaults) {
    $result = \Drupal::service('webform_civicrm.utils')-> wf_civicrm_api('Tag','get',[
      'used_for' => 'civicrm_contact',
      'options' => ['limit' => 0]
    ]);
    $tags = [];
    foreach($result['values'] as $key => $value){
      $tags[] = ['id' => (int) $value['id'], 'text' => $value['name'], 'parent_id' => ($value['parent_id'] ?? FALSE)];
    }
    $parents = [];
    foreach($tags as $key => $tag){
      if(empty($tag['parent_id'])){
        $parents[$key] = [
          'id' => $tag['id'],
          'text' => $tag['text']
          ];
        if (in_array($tag['id'],$defaults)){
          $parents[$key]['selected'] = "true";
        }
      }
    }
    foreach($parents as $key => $parent){
      $this->expand($parents[$key],$tags,$defaults);
    }
    return $parents;
  }

  private function allowedTags(array $allowed, array $defaults) {
    $result = \Drupal::service('webform_civicrm.utils')-> wf_civicrm_api('Tag','get',[
      'used_for' => 'civicrm_contact',
      'options' => ['limit' => 0]
    ]);
    $tags = [];
    foreach($result['values'] as $key => $value){
      $tags[] = ['id' => (int) $value['id'], 'text' => $value['name'], 'parent_id' => ($value['parent_id'] ?? FALSE)];
    }
    $at = [];
    foreach($tags as $key => $tag){
      if(in_array($tag['id'],$allowed)){
        $allowedTag= [
          'id' => $tag['id'],
          'text' => $tag['text']
        ];
        if (in_array($tag['id'],$defaults)){
          $allowedTag['selected'] = "true";
        }
        $at[]=$allowedTag;
      }
    }
    foreach($at as $key => $parent){
      $this->expand($at[$key],$tags,$defaults);
    }
    return $at;
  }

  /**
   * @return array
   */
  private function tags() {
    $result = \Drupal::service('webform_civicrm.utils')
      ->wf_civicrm_api('Tag', 'get', [
        'used_for' => 'civicrm_contact',
        'options' => ['limit' => 0]
      ]);
    $tags = [];
    foreach ($result['values'] as $key => $value) {
      $tags[$value['id']] = $value['name'];
    }
    return $tags;
  }

  /**
   * @param $parent
   * @param $tags
   * @param $defaults
   *
   * @return void
   */
  private function expand(&$parent, $tags, $defaults){
    $children = [];
    foreach($tags as $key=> $tag){
      if(!empty($tag['parent_id']) && $tag['parent_id']==$parent['id']){
        $child = [
          'id' => $tag['id'],
          'text' => $tag['text']
        ];
        if (in_array($tag['id'],$defaults)){
           $child['selected'] = "true";
        };
        $children[] = $child;
      }
    }
    foreach($children as $key=> $child){
      $this->expand($children[$key],$tags,$defaults);
    }
    if(count($children)>0) {
      $parent['inc'] = $children;
    }
  }

  protected function findCurrentContact(){
    $query = \Drupal::request()->query;
    if ($query->has("cid2") || ($query->has('cid'))) {
      $cid = $query->has("cid2") ? $query->get("cid2") : $query->get('cid');
      return $cid;
    } else {
      $cid = \Drupal::service('webform_civicrm.utils')->wf_crm_user_cid();
      $contacts = \Civi\Api4\Contact::get()
                                    ->addSelect('employer_id')
                                    ->addWhere('id', '=', $cid)
                                    ->execute();
      foreach ($contacts as $contact) {
        $cid_e = $contact['employer_id'];
      }
      return $cid_e;
    }
  }

  protected function findContactTags($cid) {
    $contactTags = [];

    $result = \Drupal::service('webform_civicrm.utils')
      ->wf_civicrm_api('EntityTag', 'get', [
        "entity_id" => $cid,
        "entity_table" => "civicrm_contact",
        'options' => ['limit' => 0],
      ]);
    foreach ($result['values'] as $value) {
      $contactTags[] = $value['tag_id'];
    }
    return $contactTags;
  }

  public function postSave(array &$element, WebformSubmissionInterface $webform_submission, $update = TRUE) {
    $contact_id = $webform_submission->getElementData('civicrm_2_contact_1_contact_existing');
    $storedtags = $this->findContactTags($contact_id);
    // findall storedtags
    $storedtags = array_intersect($storedtags,$this->allowedTagsFlatten($this->allowedTags($element['#root_tags'],[])));

    // filter on allowag then we have all stored allowd tags
    $newtags = array_diff($webform_submission->getElementData($element['#form_key']),$storedtags);

    foreach($newtags as $tagId){
      $result = \Drupal::service('webform_civicrm.utils')
        ->wf_civicrm_api('EntityTag', 'create', [
        'entity_id' =>    $contact_id ,
        'tag_id' => $tagId,
        'entity_table' => "civicrm_contact",
      ]);
    }

    // new tags are the tags in this element that are in the storedallowed tags.
    $obsoletetags =  array_diff($storedtags,$webform_submission->getElementData($element['#form_key']));
    foreach($obsoletetags as $tagId){
      $result = \Drupal::service('webform_civicrm.utils')
        ->wf_civicrm_api('EntityTag', 'delete', [
        'entity_id' =>    $contact_id ,
        'tag_id' => $tagId,
        'entity_table' => "civicrm_contact",
      ]);
    }

    // store the new tags

    // delete the obsolete tags

  }

  private function allowedTagsFlatten($allowodTags){
    $options = [];
    foreach($allowodTags as $value){
      $options[$value['id']]=$value['id'];
      if(isset($value['inc'])){
        $inc = $this->allowedTagsFlatten($value['inc']);
        $options = $options+$inc;
      }
    }
    return $options;
  }

}
