<?php

declare(strict_types=1);

namespace KayStrobach\DyncssLess\Service;

use KayStrobach\DyncssLess\Parser\LessParser;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class DyncssService
{
    protected string $cachePath = 'typo3temp/DynCss/';

    public function __construct(protected LoggerInterface $logger) {}

    public function shouldBeParsed(string $inputFile): bool
    {
        $splFileInfo = new \SplFileInfo($inputFile);
        return $splFileInfo->getExtension() === 'less';
    }

    protected function getCacheIdentifier(string $inputFilename, array $overrides): string
    {
        return hash('crc32b', $inputFilename) . '-' . hash('crc32b', serialize($overrides)) . '-' . hash('crc32b', (string)filemtime($inputFilename));
    }

    public function getCompiledFile(string $inputFile): ?string
    {
        $currentFile = $this->fixPathForInput($inputFile);
        try {
            $this->logger->debug('try to compile ' . $inputFile);
            $this->prepareEnvironment($currentFile);
            $overrides = $this->getOverrides();
            $cacheIdentifier = $this->getCacheIdentifier($currentFile, $overrides);
            $this->logger->debug('cacheIdentifier: ' . $cacheIdentifier);
            $parser = new LessParser($this->cachePath, $this->logger);
            $parser->setOverrides($overrides);
            $outputFile = $this->getOutputFileName($currentFile);
            $outputFile = $parser->compileFile($currentFile, $outputFile, $cacheIdentifier);
            $outputFile = $this->fixPathForOutput($outputFile);
            return $outputFile;
        } catch (\Exception $e) {
            $this->logger->error('cannot parse with: ' . $e->getMessage());
            return null;
        }
    }

    protected function getOutputFileName(string $inputFilename): string
    {
        return Environment::getPublicPath() . '/' . $this->cachePath . basename($inputFilename);
    }

    public function prepareEnvironment(string $fname): void
    {
        GeneralUtility::mkdir_deep(Environment::getPublicPath() . '/' . $this->cachePath);
        if (!is_dir(Environment::getPublicPath() . '/' . $this->cachePath)) {
            throw new \RuntimeException('Can´t create cache directory PATH_site/' . $this->cachePath, 1728991326);
        }
        if (!is_file($fname)) {
            throw new \RuntimeException('inputfile not exists: ' . $fname, 1728991327);
        }
    }

    /**
     * Just makes path absolute.
     */
    protected function fixPathForInput(string $file): string
    {
        return GeneralUtility::getFileAbsFileName($file);
    }

    /**
     * Fixes the path for fe or be usage.
     */
    protected function fixPathForOutput(string $file): string
    {
        $file = str_replace(Environment::getPublicPath() . '/', '', $file);
        return $file;
    }

    /**
     * Gets the overrides (replacements) for the less file as array().
     */
    protected function getOverrides(): array
    {
        $request = $this->getServerRequest();
        if ($request === null) {
            return [];
        }
        /** @var ?FrontendTypoScript $typoScript */
        $typoScript = $request->getAttribute('frontend.typoscript');
        if ($typoScript === null) {
            return [];
        }
        $setup = $typoScript->getSetupArray();
        $configs = $setup['plugin.']['tx_dyncss.']['overrides.'] ?? [];
        $overrides = [];
        $contentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        foreach ($configs as $key => $config) {
            if (substr($key, -1, 1) === '.') {
                continue;
            }
            if ($config === 'TEXT' && isset($configs[$key . '.']['value']) && str_contains($configs[$key . '.']['value'], 'typo3conf/ext/')) {
                $value = trim($configs[$key . '.']['value'], '\'');
                $extPath = 'EXT:' . preg_replace('/.*typo3conf\/ext\//', '', $value);
                try {
                    $path = PathUtility::getPublicResourceWebPath($extPath);
                    $overrides[$key] = '\'' . $path . '\'';
                } catch (\Exception $e) {
                    $overrides[$key] = $contentObjectRenderer->cObjGetSingle($config, $configs[$key . '.'] ?? []);
                }
            } else {
                $overrides[$key] = $contentObjectRenderer->cObjGetSingle($config, $configs[$key . '.'] ?? []);
            }
        }
        return $overrides;
    }

    protected function getServerRequest(): ?ServerRequest
    {
        return $GLOBALS['TYPO3_REQUEST'] ?? null;
    }
}
