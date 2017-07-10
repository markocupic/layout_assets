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

namespace Markocupic\LayoutAssets;


/**
 * Class ThemePlus
 *
 * Adding files to the page layout.
 */
class LayoutAssets
{

    /**
     * @see \Contao\Controller::replaceDynamicScriptTags
     *
     * @param $strBuffer
     */
    public function hookReplaceDynamicScriptTags($strBuffer)
    {
        global $objPage;

        if ($objPage !== null)
        {


            // search for the layout
            $layout = \LayoutModel::findByPk($objPage->layout);

            if ($layout !== null)
            {
                $multiSRC = deserialize($layout->layout_assets_javascripts, true);
                if (!empty($multiSRC))
                {
                    // Get the file entries from the database
                    $objFiles = \FilesModel::findMultipleByUuids($multiSRC);

                    if ($objFiles !== null)
                    {
                        if (\Validator::isUuid($multiSRC[0]))
                        {
                            while ($objFiles->next())
                            {
                                if (file_exists(TL_ROOT . '/' . $objFiles->path))
                                {
                                    $GLOBALS['TL_JAVASCRIPT'][] = $objFiles->path . '|static';
                                }
                            }
                        }
                    }
                }

                $multiSRC = deserialize($layout->layout_assets_stylesheets, true);
                if (!empty($multiSRC))
                {
                    // Get the file entries from the database
                    $objFiles = \FilesModel::findMultipleByUuids($multiSRC);

                    if ($objFiles !== null)
                    {
                        if (\Validator::isUuid($multiSRC[0]))
                        {
                            while ($objFiles->next())
                            {
                                if (file_exists(TL_ROOT . '/' . $objFiles->path))
                                {
                                    $GLOBALS['TL_CSS'][] = $objFiles->path . '|static';
                                }
                            }
                        }
                    }
                }
            }
        }
        return $strBuffer;
    }

}
