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

// Hooks
$GLOBALS['TL_HOOKS']['replaceDynamicScriptTags'][] = array('MCupic\LayoutAssets', 'hookReplaceDynamicScriptTags');


