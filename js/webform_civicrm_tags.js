/*
 * @author Klaas Eikelboom  <klaas.eikelboom@civicoop.org>
 * @date 02-Jul-2023
 * @license  AGPL-3.0
 */

(function (D, $, drupalSettings, once) {
  D.behaviors.webform_civicrmSelectTree = {
    attach: function (context) {
      if(drupalSettings.allowedtags) {
        let keys =  Object.keys(drupalSettings.allowedtags);
        keys.forEach(function(key,index){
        $(once('select2tree',"select[select2tree='"+key+"']",context)).select2ToTree({
          treeData: {dataArr: drupalSettings.allowedtags[key]},
          multiple: true
        })}
      )
      }
    }}}
)(Drupal, jQuery, drupalSettings, once);
