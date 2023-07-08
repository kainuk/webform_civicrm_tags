/*
 * @author Klaas Eikelboom  <klaas.eikelboom@civicoop.org>
 * @date 02-Jul-2023
 * @license  AGPL-3.0
 */

(function (D, $, drupalSettings, once) {
  D.behaviors.webform_civicrmSelectTree = {
    attach: function (context) {
      if( $("select[name='properties[root_tags][]']").length>0) {
        $(once('roottags',"select[name='properties[root_tags][]']",context)).select2ToTree({
          treeData: {dataArr: drupalSettings.alltags},
          multiple: true
        });
      }
      if(drupalSettings.allowedtagsform_key) {
        $(once('select2tree',"select[select2tree='"+drupalSettings.allowedtagsform_key+"']",context)).select2ToTree({
          treeData: {dataArr: drupalSettings.allowedtags},
          multiple: true
        });
      }
    }}}
)(Drupal, jQuery, drupalSettings, once);
