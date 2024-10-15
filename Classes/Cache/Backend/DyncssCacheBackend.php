<?php

declare(strict_types=1);

namespace KayStrobach\DyncssLess\Cache\Backend;

/*
 * This file is part of the b13 TYPO3 extensions family.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Core\Cache\Backend\TransientBackendInterface;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class DyncssCacheBackend extends Typo3DatabaseBackend implements TransientBackendInterface
{
    public function flush()
    {
        $files = $this->getAllCachedFiles();
        foreach ($files as $file) {
            $this->removeFile($file);
        }
        parent::flush();
    }

    /**
     * @param string $tag The tag the entries must have
     */
    public function flushByTag($tag)
    {
        $identifiers = $this->findIdentifiersByTag($tag);
        foreach ($identifiers as $entryIdentifier) {
            $file = $this->get($entryIdentifier);
            if ($file) {
                $this->removeFile($file);
            }
        }
        parent::flushByTag($tag);
    }

    /**
     * @param string[] $tags
     */
    public function flushByTags(array $tags)
    {
        $identifiers = [];
        foreach ($tags as $tag) {
            $identifiers = array_merge($identifiers, $this->findIdentifiersByTag($tag));
        }
        $identifiers = array_unique($identifiers);

        $files = [];
        foreach ($identifiers as $entryIdentifier) {
            $files[] = $this->get($entryIdentifier);
        }
        $files = array_unique($files);
        foreach ($files as $file) {
            $this->removeFile($file);
        }
        parent::flushByTags($tags);
    }

    protected function removeFile(string $file): void
    {
        $path = Environment::getPublicPath() . '/' . $file;
        if (file_exists($path)) {
            unlink($path);
        }
    }

    protected function getAllCachedFiles(): array
    {
        $urls = [];
        $conn = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($this->cacheTable);
        $stmt = $conn->select(['content'], $this->cacheTable);
        while ($url = $stmt->fetchOne()) {
            $urls[] = $url;
        }
        return $urls;
    }
}
