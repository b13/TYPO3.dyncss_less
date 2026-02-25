<?php

namespace KayStrobach\DyncssLess\Parser;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class tx_DyncssLess_Parser
 *
 * Adapts the Less.php Parser to compile less files
 */
class LessParser
{
    protected array $overrides = [];
    protected string $cacheFilename = '';
    protected string $inputFilename = '';
    protected string $outputFilename = '';

    public function __construct(protected string $cachePath, protected LoggerInterface $logger)
    {
        if (!class_exists('Less_Cache')) {
            require_once(ExtensionManagementUtility::extPath('dyncss_less') . 'Resources/Private/Php/less.php/Autoloader.php');
            \Less_Autoloader::register();
        }
    }

    protected function _compileFile($inputFilename): string
    {
        $options = [
            'import_dirs' => [
                dirname($inputFilename) => dirname($inputFilename),
                Environment::getPublicPath() . '/' => Environment::getPublicPath() . '/',
            ],
            'cache_dir' => GeneralUtility::getFileAbsFileName($this->cachePath . 'Cache'),
        ];

        $files = [
            $inputFilename => '',
        ];

        $compiledFile = $options['cache_dir'] . '/' . \Less_Cache::Get($files, $options, $this->overrides);
        return file_get_contents($compiledFile);
    }

    public function _postCompile($string)
    {
        /*
         * find all matches of url() and adjust uris
         */
        preg_match_all('|url[\s]*\([\s]*(?<url>[^\)]*)[\s]*\)[\s]*|Ui', $string, $matches, PREG_SET_ORDER);

        if (is_array($matches) && count($matches)) {
            foreach ($matches as $key => $value) {
                // Don't modify inline SVGs
                if (!str_contains($value['url'], 'data:image')) {
                    $url = trim($value[0], '\'"');
                    $orgPath = trim($value['url'], '\'"');
                    $newPath = $this->resolveUrlInCss($orgPath);
                    $string = str_replace($url, 'url("' . $newPath . '")', $string);
                }
            }
        }

        /*
         * find all matches of src= and adjust uris
         */
        preg_match_all('|src=([\'"])(?<url>[^\'"]*)\1|Ui', $string, $matches, PREG_SET_ORDER);

        if (is_array($matches) && count($matches)) {
            foreach ($matches as $key => $value) {
                $url = trim($value['url'], '\'"');
                $newPath = $this->resolveUrlInCss($url);
                $string = str_replace($url, $newPath, $string);
            }
        }

        return $string;
    }

    public function resolveUrlInCss($url)
    {
        if (substr($url, 0, 2) === '//') {
            // double slashed indicate a fully fledged url like //typo3.org
            return $url;
        }
        if (str_contains($url, '://')) {
            // http://, ftp:// etc. should not be touched
            return $url;
        }
        if (substr($url, 0, 1) === '/') {
            if (substr($url, 0, strlen(Environment::getPublicPath() . '/')) === Environment::getPublicPath() . '/') {
                return '../../' . substr($url, strlen(Environment::getPublicPath() . '/'));
            }

            return $url;
        }
        if (substr($url, 0, 5) === 'data:') {
            // data:image/svg+xml;base64,... should not be touched
            return $url;
        }
        // anything inside TYPO3 has to be adjusted
        return '../../../../' . dirname($this->removePrefixFromString(Environment::getPublicPath() . '/', $this->inputFilename)) . '/' . $url;
    }

    public function removePrefixFromString(string $prefix, string $string): string
    {
        if (str_starts_with($string, $prefix)) {
            return substr($string, strlen($prefix));
        }
        return $string;

    }

    public function setOverrides(array $overwrites): void
    {
        foreach ($overwrites as $key => $overwrite) {
            if (empty($overwrite)) {
                unset($overwrites[$key]);
            }
        }
        $this->overrides = array_replace_recursive($this->overrides, $overwrites);
    }

    public function compileFile(string $inputFilename, string $outputFilename, string $cacheIdentifier): string
    {
        $outputFilenamePathInfo = pathinfo($outputFilename);
        $noExtensionFilename = $outputFilename . '-' . $cacheIdentifier;

        $preparedFilename = $noExtensionFilename . '.' . $outputFilenamePathInfo['extension'];

        $cacheFilename = $noExtensionFilename . '.cache';
        $outputFilename = $noExtensionFilename . '.css';

        $this->inputFilename = $inputFilename;
        $this->outputFilename = $outputFilename;
        $this->cacheFilename = $cacheFilename;

        // exit if a precompiled version already exists
        if (file_exists($outputFilename)) {
            $this->logger->debug('use cached file ' . $outputFilename);
            return $outputFilename;
        }
        $this->logger->debug('compile ' . $outputFilename);

        file_put_contents($preparedFilename, file_get_contents($inputFilename));

        $fileContent = $this->_postCompile(
            $this->_compileFile($inputFilename)
        );

        if ($fileContent !== false) {
            file_put_contents($outputFilename, $fileContent);
            GeneralUtility::fixPermissions($outputFilename);
            // important for some cache clearing scenarios
            if (file_exists($preparedFilename)) {
                unlink($preparedFilename);
            }
        }

        return $outputFilename;
    }
}
