<?php

/**
 * LayoutAssets - Layout extension for the Contao Open Source CMS
 *
 * Copyright (C) 2013 bit3 UG <http://bit3.de>
 *
 * @package    LayoutAssets
 * @author     Marko Cupic <m.cupic@gmx.ch>
 * @license    http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */


/**
 * Palettes
 */
$GLOBALS['TL_DCA']['tl_layout']['palettes']['default'] = preg_replace(
    array(
         '#external#',
         '#analytics#'
    ),
    array(
         'external,layout_assets_stylesheets',
         'layout_assets_javascripts,analytics',
    ),
    $GLOBALS['TL_DCA']['tl_layout']['palettes']['default']
);


/**
 * Fields
 */
// add field layout_assets_javascripts
$GLOBALS['TL_DCA']['tl_layout']['fields']['layout_assets_javascripts'] = array
( 'label'                   => &$GLOBALS['TL_LANG']['tl_layout']['layout_assets_javascripts'],
  'exclude'                 => true,
  'inputType'               => 'fileTree',
  'eval'                    => array('multiple'=>true, 'fieldType'=>'checkbox', 'extensions' => 'js', 'orderField'=>'layout_assets_javascripts_order', 'filesOnly'=>true),
  'sql'                     => "blob NULL",
);


$GLOBALS['TL_DCA']['tl_layout']['fields']['layout_assets_javascripts_order'] = array
(
    'label'                   => &$GLOBALS['TL_LANG']['tl_content']['layout_assets_javascripts_order'],
    'sql'                     => "blob NULL"
);

// add field theme_plus_javascripts
$GLOBALS['TL_DCA']['tl_layout']['fields']['layout_assets_stylesheets'] = array
( 'label'                   => &$GLOBALS['TL_LANG']['tl_layout']['layout_assets_stylesheets'],
  'exclude'                 => true,
  'inputType'               => 'fileTree',
  'eval'                    => array('multiple'=>true, 'fieldType'=>'checkbox', 'extensions' => 'css', 'orderField'=>'layout_assets_stylesheets_order', 'filesOnly'=>true),
  'sql'                     => "blob NULL",
);


$GLOBALS['TL_DCA']['tl_layout']['fields']['layout_assets_stylesheets_order'] = array
(
    'label'                   => &$GLOBALS['TL_LANG']['tl_content']['layout_assets_stylesheets_order'],
    'sql'                     => "blob NULL"
);

