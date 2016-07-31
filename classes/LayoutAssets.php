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

namespace MCupic;


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


    /**
     * Parse all stylesheets and add them to the search and replace array.
     *
     * @param \LayoutModel $layout
     * @param array $sr The search and replace array.
     *
     * @return mixed
     */
    protected function parseStylesheets(\LayoutModel $layout, array &$sr)
    {
        global $objPage;

        // html mode
        $xhtml = ($objPage->outputFormat == 'xhtml');
        $tagEnding = $xhtml ? ' />' : '>';

        // default filter
        $defaultFilters = AsseticFactory::createFilterOrChain($layout->asseticStylesheetFilter, ThemePlusEnvironment::isDesignerMode());

        // list of non-static stylesheets
        $stylesheets = array();

        // collection of static stylesheets
        $collection = new AssetCollection(array(), array(), TL_ROOT);

        // Add the CSS framework style sheets
        if (is_array($GLOBALS['TL_FRAMEWORK_CSS']) && !empty($GLOBALS['TL_FRAMEWORK_CSS']))
        {
            $this->addAssetsToCollectionFromArray(array_unique($GLOBALS['TL_FRAMEWORK_CSS']), 'css', null, $collection, $stylesheets, $defaultFilters);
        }
        $GLOBALS['TL_FRAMEWORK_CSS'] = array();

        // Add the internal style sheets
        if (is_array($GLOBALS['TL_CSS']) && !empty($GLOBALS['TL_CSS']))
        {
            $this->addAssetsToCollectionFromArray($GLOBALS['TL_CSS'], 'css', true, $collection, $stylesheets, $defaultFilters);
        }
        $GLOBALS['TL_CSS'] = array();

        // Add the user style sheets
        if (is_array($GLOBALS['TL_USER_CSS']) && !empty($GLOBALS['TL_USER_CSS']))
        {
            $this->addAssetsToCollectionFromArray(array_unique($GLOBALS['TL_USER_CSS']), 'css', true, $collection, $stylesheets, $defaultFilters);
        }
        $GLOBALS['TL_USER_CSS'] = array();

        // Add layout files
        $stylesheet = StyleSheetModel::findByPks(deserialize($layout->theme_plus_stylesheets, true), array('order' => 'sorting'));
        if ($stylesheet)
        {
            $this->addAssetsToCollectionFromDatabase($stylesheet, 'css', $collection, $stylesheets, $defaultFilters);
        }

        // Add files from page tree
        $this->addAssetsToCollectionFromPageTree($objPage, 'stylesheets', 'Bit3\Contao\ThemePlus\Model\StylesheetModel', $collection, $stylesheets, $defaultFilters, true);

        // string contains the scripts include code
        $scripts = '';

        // add collection to list
        if (count($collection->all()))
        {
            $stylesheets[] = array('asset' => $collection);
        }

        // add files
        foreach ($stylesheets as $stylesheet)
        {
            // use proxy for development
            if (ThemePlusEnvironment::isDesignerMode() && isset($stylesheet['id']))
            {
                /** @var AssetInterface $asset */
                $asset = $stylesheet['asset'];

                $id = substr(md5($asset->getSourceRoot() . '/' . $asset->getSourcePath()), 0, 8);

                $session = unserialize($_SESSION['THEME_PLUS_ASSETS'][$id]);

                if (!$session || !$session->asset instanceof StringAsset || $asset->getLastModified() > $session->asset->getLastModified())
                {
                    $session = new \stdClass;
                    $session->page = $objPage->id;
                    $session->asset = $asset;
                    $session->filters = $defaultFilters;

                    if ($asset instanceof StringAsset)
                    {
                        $asset->load();
                    }

                    $_SESSION['THEME_PLUS_ASSETS'][$id] = serialize($session);
                }

                $pathinfo = pathinfo($stylesheet['name']);

                $url = sprintf('system/modules/theme-plus/web/proxy.php/css/%s', $pathinfo['filename'] . '.' . $id . '.' . $pathinfo['extension']);
                $this->files[$url] = $stylesheet;
            }

            // use asset
            else
            {
                if (isset($stylesheet['asset']))
                {
                    $url = static::storeAsset($stylesheet['asset'], 'css', $defaultFilters);
                    $url = \Controller::addStaticUrlTo($url);
                    $this->files[$url] = $stylesheet;
                }

                // use url
                else
                {
                    if (isset($stylesheet['url']))
                    {
                        $url = $stylesheet['url'];
                        $url = \Controller::addStaticUrlTo($url);
                        $this->files[$url] = $stylesheet;
                    }

                    // continue if file have no source
                    else
                    {
                        continue;
                    }
                }
            }

            // generate html
            $html = '<link' . ($xhtml ? ' type="text/css"' : '') . ' rel="stylesheet" href="' . $url . '"' . ((isset($stylesheet['media']) && $stylesheet['media'] != 'all') ? ' media="' . $stylesheet['media'] . '"' : '') . (ThemePlusEnvironment::isDesignerMode() ? ' id="' . md5($url) . '"' : '') . $tagEnding;

            // wrap cc around
            $html = static::wrapCc($html, $stylesheet['cc']) . "\n";

            // add debug information
            if (ThemePlusEnvironment::isDesignerMode())
            {
                // use asset
                if (isset($stylesheet['asset']))
                {
                    $html = static::getDebugComment($stylesheet['asset']) . $html;
                }

                // use url
                else
                {
                    if (isset($stylesheet['url']))
                    {
                        $html = '<!-- url { ' . $stylesheet['url'] . ' } -->' . "\n" . $html;
                    }
                }
            }

            $scripts .= $html;
        }

        $scripts .= '[[TL_CSS]]';
        $sr['[[TL_CSS]]'] = $scripts;
    }

    /**
     * Parse all javascripts and add them to the search and replace array.
     *
     * @param \LayoutModel $layout
     * @param array $sr The search and replace array.
     *
     * @return mixed
     */
    protected function parseJavaScripts(\LayoutModel $layout, array &$sr)
    {
        global $objPage;

        // html mode
        $xhtml = ($objPage->outputFormat == 'xhtml');

        // default filter
        $defaultFilters = AsseticFactory::createFilterOrChain($layout->asseticJavaScriptFilter, ThemePlusEnvironment::isDesignerMode());

        // list of non-static javascripts
        $javascripts = array();

        // collection of static javascript
        $collection = new AssetCollection(array(), array(), TL_ROOT);

        // Add the internal scripts
        if (is_array($GLOBALS['TL_JAVASCRIPT']) && !empty($GLOBALS['TL_JAVASCRIPT']))
        {
            $this->addAssetsToCollectionFromArray($GLOBALS['TL_JAVASCRIPT'], 'js', false, $collection, $javascripts, $defaultFilters, $layout->theme_plus_default_javascript_position);
        }
        $GLOBALS['TL_JAVASCRIPT'] = array();

        // Add layout files
        $javascript = JavaScriptModel::findByPks(deserialize($layout->theme_plus_javascripts, true), array('order' => 'sorting'));
        if ($javascript)
        {
            $this->addAssetsToCollectionFromDatabase($javascript, 'js', $collection, $javascripts, $defaultFilters, $layout->theme_plus_default_javascript_position);
        }

        // Add files from page tree
        $this->addAssetsToCollectionFromPageTree($objPage, 'javascripts', 'Bit3\Contao\ThemePlus\Model\JavaScriptModel', $collection, $javascripts, $defaultFilters, true, $layout->theme_plus_default_javascript_position);

        // string contains the scripts include code
        $head = '';
        $body = '';

        // add collection to list
        if (count($collection->all()))
        {
            $javascripts[] = array(
                'asset'    => $collection,
                'position' => $layout->theme_plus_default_javascript_position,
            );
        }

        // add files
        foreach ($javascripts as $javascript)
        {
            // use proxy for development
            if (ThemePlusEnvironment::isDesignerMode() && isset($javascript['id']))
            {
                /** @var AssetInterface $asset */
                $asset = $javascript['asset'];

                $id = substr(md5($asset->getSourceRoot() . '/' . $asset->getSourcePath()), 0, 8);

                $session = unserialize($_SESSION['THEME_PLUS_ASSETS'][$id]);

                if (!$session || !$session->asset instanceof StringAsset || $asset->getLastModified() > $session->asset->getLastModified())
                {
                    $session = new \stdClass;
                    $session->page = $objPage->id;
                    $session->asset = $asset;
                    $session->filters = $defaultFilters;

                    if ($asset instanceof StringAsset)
                    {
                        $asset->load();
                    }

                    $_SESSION['THEME_PLUS_ASSETS'][$id] = serialize($session);
                }

                $pathinfo = pathinfo($javascript['name']);

                $url = sprintf('system/modules/theme-plus/web/proxy.php/js/%s', $pathinfo['filename'] . '.' . $id . '.' . $pathinfo['extension']);
                $this->files[$url] = $javascript;
            }

            // use asset
            else
            {
                if (isset($javascript['asset']))
                {
                    $url = static::storeAsset($javascript['asset'], 'js', $defaultFilters);
                    $url = \Controller::addStaticUrlTo($url);
                    $this->files[$url] = $javascript;
                }

                // use url
                else
                {
                    if (isset($javascript['url']))
                    {
                        $url = $javascript['url'];
                        $url = \Controller::addStaticUrlTo($url);
                        $this->files[$url] = $javascript;
                    }

                    // continue if file have no source
                    else
                    {
                        continue;
                    }
                }
            }

            // generate html
            if ($layout->theme_plus_javascript_lazy_load)
            {
                $html = '<script' . ($xhtml ? ' type="text/javascript"' : '') . (ThemePlusEnvironment::isDesignerMode() ? ' id="' . md5($url) . '"' : '') . '>window.loadAsync(' . json_encode($url) . (ThemePlusEnvironment::isDesignerMode() ? ', ' . json_encode(md5($url)) : '') . ');</script>';
            }
            else
            {
                $html = '<script' . ($xhtml ? ' type="text/javascript"' : '') . (ThemePlusEnvironment::isDesignerMode() ? ' id="' . md5($url) . '"' : '') . ' src="' . $url . '"' . (ThemePlusEnvironment::isDesignerMode() ? sprintf(' onload="window.themePlusDevTool && window.themePlusDevTool.triggerAsyncLoad(this, \'%s\');"', md5($url)) : '') . '></script>';
            }

            // wrap cc
            $html = static::wrapCc($html, $javascript['cc']) . "\n";

            // add debug information
            if (ThemePlusEnvironment::isDesignerMode())
            {
                if (isset($javascript['asset']))
                {
                    $html = static::getDebugComment($javascript['asset']) . $html;
                }
                else
                {
                    if (isset($javascript['url']))
                    {
                        $html = '<!-- url { ' . $javascript['url'] . ' } -->' . "\n" . $html;
                    }
                }
            }

            if (isset($javascript['position']) && $javascript['position'] == 'body')
            {
                $body .= $html;
            }
            else
            {
                $head .= $html;
            }
        }

        // add async.js script
        if ($layout->theme_plus_javascript_lazy_load)
        {
            $async = new FileAsset(TL_ROOT . '/system/modules/theme-plus/assets/js/async.js', $defaultFilters);
            $async->setTargetPath($this->getAssetPath($async, 'js'));
            $async = '<script' . ($xhtml ? ' type="text/javascript"' : '') . '>' . $async->dump() . '</script>' . "\n";

            if ($head)
            {
                $head = $async . $head;
            }
            else
            {
                if ($body)
                {
                    $body = $async . $body;
                }
            }
        }

        $head .= '[[TL_HEAD]]';
        $sr['[[TL_HEAD]]'] = $head;

        $sr['[[TL_THEME_PLUS]]'] = $body;
    }

    protected function addAssetsToCollectionFromArray(array $sources,
        $type,
        $split,
        AssetCollection $collection,
        array &$array,
        $defaultFilters,
        $defaultPosition = 'head')
    {
        foreach ($sources as $source)
        {
            if ($source instanceof AssetInterface)
            {
                if (ThemePlusEnvironment::isLiveMode())
                {
                    $collection->add($source);
                }
                else
                {
                    if ($source instanceof StringAsset)
                    {
                        $data = $source->dump();
                        $data = gzcompress($data, 9);
                        $data = base64_encode($data);

                        $array[] = array(
                            'id'       => $type . ':' . 'base64:' . $data,
                            'name'     => 'string' . substr(md5($data), 0, 8) . '.' . $type,
                            'time'     => substr(md5($data), 0, 8),
                            'asset'    => $source,
                            'position' => $defaultPosition,
                        );
                    }
                    else
                    {
                        if ($source instanceof FileAsset)
                        {
                            $reflectionClass = new \ReflectionClass('Assetic\Asset\BaseAsset');
                            $sourceProperty = $reflectionClass->getProperty('sourcePath');
                            $sourceProperty->setAccessible(true);
                            $sourcePath = $sourceProperty->getValue($source);

                            if (in_array($sourcePath, $this->excludeList))
                            {
                                continue;
                            }

                            $array[] = array(
                                'id'       => $type . ':asset:' . spl_object_hash($source),
                                'name'     => basename($sourcePath, '.' . $type) . '.' . $type,
                                'time'     => filemtime($sourcePath),
                                'asset'    => $source,
                                'position' => $defaultPosition,
                            );
                            $this->excludeList[] = $sourcePath;
                        }
                        else
                        {
                            $name = get_class($source);
                            $name = strtolower($name);
                            $name = preg_replace('~^.*\\\\~', '', $name);
                            $name = preg_replace('~asset$~', '', $name);
                            $name .= '_' . standardize($source->getSourcePath());

                            $array[] = array(
                                'id'       => $type . ':asset:' . spl_object_hash($source),
                                'name'     => $name . '.' . $type,
                                'time'     => time(),
                                'asset'    => $source,
                                'position' => $defaultPosition,
                            );
                        }
                    }
                }
                continue;
            }

            if ($split === null)
            {
                // use source as source
            }
            else
            {
                if ($split === true)
                {
                    list($source, $media, $mode) = explode('|', $source);
                }
                else
                {
                    if ($split === false)
                    {
                        list($source, $mode) = explode('|', $source);
                    }
                    else
                    {
                        return;
                    }
                }
            }

            // remove static url
            $source = static::stripStaticURL($source);

            // skip file
            if (in_array($source, $this->excludeList))
            {
                continue;
            }

            $this->excludeList[] = $source;

            // if stylesheet is an absolute url...
            if (preg_match('#^\w+:#', $source))
            {
                // ...fetch the stylesheet
                if ($mode == 'static' && ThemePlusEnvironment::isLiveMode())
                {
                    $asset = new HttpAsset($source);
                    $asset->setTargetPath($this->getAssetPath($asset, $type));
                }
                // ...or add if it is not static
                else
                {
                    $array[] = array(
                        'url'   => $source,
                        'name'  => basename($source),
                        'time'  => time(),
                        'media' => $media,
                    );
                    continue;
                }
            }
            else
            {
                if ($source)
                {
                    $asset = new FileAsset(TL_ROOT . '/' . $source, $defaultFilters, TL_ROOT, $source);
                    $asset->setTargetPath($this->getAssetPath($asset, $type));
                }
                else
                {
                    continue;
                }
            }

            if (($mode == 'static' || $mode === null) && ThemePlusEnvironment::isLiveMode())
            {
                $collection->add($asset);
            }
            else
            {
                $array[] = array(
                    'id'       => $type . ':' . $source,
                    'name'     => basename($source),
                    'time'     => filemtime($source),
                    'asset'    => $asset,
                    'media'    => $media,
                    'position' => $defaultPosition,
                );
            }
        }
    }

    protected function addAssetsToCollectionFromDatabase(\Model\Collection $data,
        $type,
        AssetCollection $collection,
        array &$array,
        $defaultFilters,
        $defaultPosition = 'head')
    {
        if ($data)
        {
            while ($data->next())
            {
                if (static::checkBrowserFilter($data))
                {
                    $asset = null;
                    $filter = array();

                    if ($data->asseticFilter)
                    {
                        $temp = AsseticFactory::createFilterOrChain($data->asseticFilter, ThemePlusEnvironment::isDesignerMode());
                        if ($temp)
                        {
                            $filter = array($temp);
                        }
                    }

                    $filter[] = $defaultFilters;

                    if ($data->position)
                    {
                        $position = $data->position;
                    }
                    else
                    {
                        $position = $defaultPosition;
                    }

                    switch ($data->type)
                    {
                        case 'code':
                            $name = ($data->code_snippet_title ? $data->code_snippet_title : ('string' . substr(md5($data->code), 0, 8))) . '.' . $type;
                            $time = $data->tstamp;
                            $asset = new StringAsset($data->code, $filter, TL_ROOT, 'assets/' . $type . '/' . $data->code_snippet_title . '.' . $type);
                            $asset->setTargetPath($this->getAssetPath($asset, $type));
                            $asset->setLastModified($data->tstamp);
                            break;

                        case 'url':
                            // skip file
                            if (in_array($data->url, $this->excludeList))
                            {
                                break;
                            }

                            $this->excludeList[] = $data->url;

                            $name = basename($data->url);
                            $time = $data->tstamp;
                            if ($data->fetchUrl)
                            {
                                $asset = new HttpAsset($data->url, $filter);
                                $asset->setTargetPath($this->getAssetPath($asset, $type));
                            }
                            else
                            {
                                $array[] = array(
                                    'name'     => $name,
                                    'url'      => $data->url,
                                    'media'    => $data->media,
                                    'cc'       => $data->cc,
                                    'position' => $position,
                                );
                            }
                            break;

                        case 'file':
                            $filepath = false;
                            if ($data->filesource == $GLOBALS['TL_CONFIG']['uploadPath'] && version_compare(VERSION, '3', '>='))
                            {
                                $file = (version_compare(VERSION, '3.2', '>=') ? \FilesModel::findByUuid($data->file) : \FilesModel::findByPk($data->file));
                                if ($file)
                                {
                                    $filepath = $file->path;
                                }
                            }
                            else
                            {
                                $filepath = $data->file;
                            }

                            if ($filepath)
                            {
                                // skip file
                                if (in_array($filepath, $this->excludeList))
                                {
                                    break;
                                }

                                $this->excludeList[] = $filepath;

                                $name = basename($filepath, '.' . $type) . '.' . $type;
                                $time = filemtime($filepath);
                                $asset = new FileAsset(TL_ROOT . '/' . $filepath, $filter, TL_ROOT, $filepath);
                                $asset->setTargetPath($this->getAssetPath($asset, $type));
                            }
                            break;
                    }

                    if ($asset)
                    {
                        if (ThemePlusEnvironment::isLiveMode() && $defaultPosition == $position)
                        {
                            $collection->add($asset);
                        }
                        else
                        {
                            $array[] = array(
                                'id'       => $type . ':' . $data->id,
                                'name'     => $name,
                                'time'     => $time,
                                'asset'    => $asset,
                                'position' => $position,
                            );
                        }
                    }
                }
            }
        }
    }

    protected function addAssetsToCollectionFromPageTree($objPage,
        $type,
        $model,
        AssetCollection $collection,
        array &$array,
        $defaultFilters,
        $local = false,
        $defaultPosition = 'head')
    {
        // inherit from parent page
        if ($objPage->pid)
        {
            $objParent = \PageModel::findWithDetails($objPage->pid);
            $this->addAssetsToCollectionFromPageTree($objParent, $type, $model, $collection, $array, $defaultFilters, false, $defaultPosition);
        }

        // add local (not inherited) files
        if ($local)
        {
            $trigger = 'theme_plus_include_' . $type . '_noinherit';

            if ($objPage->$trigger)
            {
                $key = 'theme_plus_' . $type . '_noinherit';

                $data = call_user_func(array($model, 'findByPks'), deserialize($objPage->$key, true), array('order' => 'sorting'));
                if ($data)
                {
                    $this->addAssetsToCollectionFromDatabase($data, $type == 'stylesheets' ? 'css' : 'js', $collection, $array, $defaultFilters, $defaultPosition);
                }
            }
        }

        // add inherited files
        $trigger = 'theme_plus_include_' . $type;

        if ($objPage->$trigger)
        {
            $key = 'theme_plus_' . $type;

            $data = call_user_func(array($model, 'findByPks'), deserialize($objPage->$key, true), array('order' => 'sorting'));
            if ($data)
            {
                $this->addAssetsToCollectionFromDatabase($data, $type == 'stylesheets' ? 'css' : 'js', $collection, $array, $defaultFilters, $defaultPosition);
            }
        }
    }

    /**
     * Render a variable to css code.
     */
    static public function renderVariable(VariableModel $variable)
    {
        // HOOK: create framework code
        if (isset($GLOBALS['TL_HOOKS']['renderVariable']) && is_array($GLOBALS['TL_HOOKS']['renderVariable']))
        {
            foreach ($GLOBALS['TL_HOOKS']['renderVariable'] as $callback)
            {
                $object = \System::importStatic($callback[0]);
                $varResult = $object->$callback[1]($variable);
                if ($varResult !== false)
                {
                    return $varResult;
                }
            }
        }

        switch ($variable->type)
        {
            case 'text':
                return $variable->text;

            case 'url':
                return sprintf('url("%s")', str_replace('"', '\\"', $variable->url));

            case 'file':
                return sprintf('url("../../%s")', str_replace('"', '\\"', $variable->file));

            case 'color':
                return '#' . $variable->color;

            case 'size':
                $arrSize = deserialize($variable->size);
                $arrTargetSize = array();
                foreach (array('top', 'right', 'bottom', 'left') as $k)
                {
                    if (strlen($arrSize[$k]))
                    {
                        $arrTargetSize[] = $arrSize[$k] . $arrSize['unit'];
                    }
                    else
                    {
                        $arrTargetSize[] = '';
                    }
                }
                while (count($arrTargetSize) > 0 && empty($arrTargetSize[count($arrTargetSize) - 1]))
                {
                    array_pop($arrTargetSize);
                }
                foreach ($arrTargetSize as $k => $v)
                {
                    if (empty($v))
                    {
                        $arrTargetSize[$k] = '0';
                    }
                }
                return implode(' ', $arrTargetSize);
        }
    }


    /**
     * Get the variables.
     */
    public function getVariables($varTheme, $strPath = false)
    {
        $objTheme = $this->findTheme($varTheme);

        if (!isset($this->arrVariables[$objTheme->id]))
        {
            $this->arrVariables[$objTheme->id] = array();

            $objVariable = \Database::getInstance()->prepare("SELECT * FROM tl_theme_plus_variable WHERE pid=?")->execute($objTheme->id);

            while ($objVariable->next())
            {
                $this->arrVariables[$objTheme->id][$objVariable->name] = $this->renderVariable($objVariable, $strPath);
            }
        }

        return $this->arrVariables[$objTheme->id];
    }


    /**
     * Replace variables.
     */
    public function replaceVariables($strCode, $arrVariables = false, $strPath = false)
    {
        if (!$arrVariables)
        {
            $arrVariables = $this->getVariables(false, $strPath);
        }
        $objVariableReplace = new VariableReplacer($arrVariables);
        return preg_replace_callback('#\$([[:alnum:]_\-]+)#', array(&$objVariableReplace, 'replace'), $strCode);
    }


    /**
     * Replace variables.
     */
    public function replaceVariablesByTheme($strCode, $varTheme, $strPath = false)
    {
        $objVariableReplace = new VariableReplacer($this->getVariables($varTheme, $strPath));
        return preg_replace_callback('#\$([[:alnum:]_\-]+)#', array(&$objVariableReplace, 'replace'), $strCode);
    }


    /**
     * Replace variables.
     */
    public function replaceVariablesByLayout($strCode, $varLayout, $strPath = false)
    {
        $objVariableReplace = new VariableReplacer($this->getVariables($this->findThemeByLayout($varLayout), $strPath));
        return preg_replace_callback('#\$([[:alnum:]_\-]+)#', array(&$objVariableReplace, 'replace'), $strCode);
    }


    /**
     * Calculate a variables hash.
     */
    public function getVariablesHash($arrVariables)
    {
        $strVariables = '';
        foreach ($arrVariables as $k => $v)
        {
            $strVariables .= $k . ':' . $v . "\n";
        }
        return md5($strVariables);
    }


    /**
     * Calculate a variables hash.
     */
    public function getVariablesHashByTheme($varTheme)
    {
        return $this->getVariablesHash($this->getVariables($varTheme));
    }


    /**
     * Calculate a variables hash.
     */
    public function getVariablesHashByLayout($varLayout)
    {
        return $this->getVariablesHash($this->getVariables($this->findThemeByLayout($varLayout)));
    }


    /**
     * Wrap a javascript src for lazy include.
     *
     * @return string
     */
    public function wrapJavaScriptLazyInclude($strSrc)
    {
        return 'loadAsync(' . json_encode($strSrc) . (ThemePlus::getInstance()->isDesignerMode() ? ', ' . json_encode(md5($strSrc)) : '') . ');';
    }


    /**
     * Wrap a javascript src for lazy embedding.
     *
     * @return string
     */
    public function wrapJavaScriptLazyEmbedded($strSource)
    {
        $strBuffer = 'var f=(function(){';
        $strBuffer .= $strSource;
        $strBuffer .= '});';
        $strBuffer .= 'if (window.attachEvent){';
        $strBuffer .= 'window.attachEvent("onload",f);';
        $strBuffer .= '}else{';
        $strBuffer .= 'window.addEventListener("load",f,false);';
        $strBuffer .= '}';
        return $strBuffer;
    }


    /**
     * Generate the html code.
     *
     * @param array $arrFileIds
     * @param bool $blnAbsolutizeUrls
     * @param object $objAbsolutizePage
     *
     * @return string
     */
    public function includeFiles($arrFileIds,
        $blnAggregate = null,
        $blnAbsolutizeUrls = false,
        $objAbsolutizePage = null)
    {
        $arrResult = array();

        // add css files
        $arrFiles = $this->getCssFiles($arrFileIds, $blnAggregate, $blnAbsolutizeUrls, $objAbsolutizePage);
        foreach ($arrFiles as $objFile)
        {
            $arrResult[] = $objFile->getIncludeHtml();
        }

        // add javascript files
        $arrFiles = $this->getJavaScriptFiles($arrFileIds);
        foreach ($arrFiles as $objFile)
        {
            $arrResult[] = $objFile->getIncludeHtml();
        }
        return $arrResult;
    }


    /**
     * Generate the html code.
     *
     * @param array $arrFileIds
     *
     * @return array
     */
    public function embedFiles($arrFileIds, $blnAggregate = null, $blnAbsolutizeUrls = false, $objAbsolutizePage = null)
    {
        $arrResult = array();

        // add css files
        $arrFiles = $this->getCssFiles($arrFileIds, $blnAbsolutizeUrls, $objAbsolutizePage);
        foreach ($arrFiles as $objFile)
        {
            $arrResult[] = $objFile->getEmbeddedHtml();
        }

        // add javascript files
        $arrFiles = $this->getJavaScriptFiles($arrFileIds);
        foreach ($arrFiles as $objFile)
        {
            $arrResult[] = $objFile->getEmbeddedHtml();
        }
        return $arrResult;
    }


    /**
     * Hook
     *
     * @param string $strTag
     *
     * @return mixed
     */
    public function hookReplaceInsertTags($strTag)
    {
        $arrParts = explode('::', $strTag);
        $arrIds = explode(',', $arrParts[1]);
        switch ($arrParts[0])
        {
            case 'include_theme_file':
                return implode("\n", $this->includeFiles($arrIds)) . "\n";

            case 'embed_theme_file':
                return implode("\n", $this->embedFiles($arrIds)) . "\n";

            // @deprecated
            case 'insert_additional_sources':
                return implode("\n", $this->includeFiles($arrIds)) . "\n";

            // @deprecated
            case 'include_additional_sources':
                return implode("\n", $this->embedFiles($arrIds)) . "\n";
        }

        return false;
    }


    /**
     * Helper function that filter out all non integer values.
     */
    public function filter_int($string)
    {
        if (is_numeric($string))
        {
            return true;
        }
        return false;
    }


    /**
     * Helper function that filter out all integer values.
     */
    public function filter_string($string)
    {
        if (is_numeric($string))
        {
            return false;
        }
        return true;
    }
}


/**
 * Sorting helper.
 */
class SortingHelper
{
    /**
     * Sorted array of ids and paths.
     */
    protected $arrSortedIds;


    /**
     * Constructor
     */
    public function __construct($arrSortedIds)
    {
        $this->arrSortedIds = array_values($arrSortedIds);
    }


    /**
     * uksort callback
     */
    public function cmp($a, $b)
    {
        $a = array_search($a, $this->arrSortedIds);
        $b = array_search($b, $this->arrSortedIds);

        // both are equals or not found
        if ($a === $b)
        {
            return 0;
        }

        // $a not found
        if ($a === false)
        {
            return -1;
        }

        // $b not found
        if ($b === false)
        {
            return 1;
        }

        return $a - $b;
    }
}


/**
 * A little helper class that work as callback for preg_replace_callback.
 */
class VariableReplacer
{
    /**
     * The variables and there values.
     */
    protected $variables;


    /**
     * Constructor
     */
    public function __construct($variables)
    {
        $this->variables = $variables;
    }


    /**
     * Callback function for preg_replace_callback.
     * Searching the variable in $this->variables and return the value
     * or a comment, that the variable does not exists!
     */
    public function replace($m)
    {
        if (isset($this->variables[$m[1]]))
        {
            return $this->variables[$m[1]];
        }

        // HOOK: replace undefined variable
        if (isset($GLOBALS['TL_HOOKS']['replaceUndefinedVariable']) && is_array($GLOBALS['TL_HOOKS']['replaceUndefinedVariable']))
        {
            foreach ($GLOBALS['TL_HOOKS']['replaceUndefinedVariable'] as $callback)
            {
                $object = \System::importStatic($callback[0]);
                $varResult = $object->$callback[1]($m[1]);
                if ($varResult !== false)
                {
                    return $varResult;
                }
            }
        }

        return $m[0];
    }
}
