<?php

declare(strict_types=1);

namespace KayStrobach\DyncssLess\Hooks;

use KayStrobach\DyncssLess\Service\DyncssService;
use TYPO3\CMS\Core\Page\PageRenderer;

/***************************************************************
* Copyright notice
*
* (c) 2012 Kay Strobach <typo3@kay-strobach.de>
*
* All rights reserved
*
* This script is part of the TYPO3 project. The TYPO3 project is
* free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
*
* The GNU General Public License can be found at
* http://www.gnu.org/copyleft/gpl.html.
*
* This script is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/**
 * @author Kay Strobach
 */
class T3libPageRendererRenderPreProcessHook
{
    public function __construct(protected DyncssService $dyncssService) {}

    public function execute(array &$params, PageRenderer $pagerenderer)
    {
        if (!is_array($params['cssFiles'] ?? null)) {
            return;
        }
        $cssFilesArray = [];
        foreach ($params['cssFiles'] as $cssFile => $cssFileSettings) {
            if ($this->dyncssService->shouldBeParsed($cssFile)) {
                $compiledFile = $this->dyncssService->getCompiledFile($cssFile);
                if ($compiledFile !== null) {
                    $cssFileSettings['file'] = $compiledFile;
                    $cssFileSettings['compress'] = 0;
                    $cssFilesArray[$compiledFile] = $cssFileSettings;
                }
            } else {
                $cssFilesArray[$cssFile] = $cssFileSettings;
            }
        }
        $params['cssFiles'] = $cssFilesArray;
    }
}
