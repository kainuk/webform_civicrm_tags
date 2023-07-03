/*
 * @author Klaas Eikelboom  <klaas.eikelboom@civicoop.org>
 * @date 02-Jul-2023
 * @license  AGPL-3.0
 */

(function (D, $, drupalSettings, once) {
  D.behaviors.webform_civicrmSelectTree = {
    attach: function (context) {
      $("select[name='properties[root_tags][]']").find('option').remove();
      $("select[name='properties[root_tags][]']").select2ToTree({treeData: {dataArr:drupalSettings.alltags},multiple:true});
      $("select[data-civicrm-field-key='civicrm_1_contact_1_other_tag']").find('option').remove();
      $("select[data-civicrm-field-key='civicrm_1_contact_1_other_tag']").select2ToTree({treeData: {dataArr:drupalSettings.alltags},multiple:true});
    }}}
)(Drupal, jQuery, drupalSettings, once);
