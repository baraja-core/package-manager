<?php

declare(strict_types=1);

namespace Symplify\SetConfigResolver;

use Nette\Utils\ObjectHelpers;
use Nette\Utils\Strings;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symplify\SetConfigResolver\Console\Option\OptionName;
use Symplify\SetConfigResolver\Console\OptionValueResolver;
use Symplify\SetConfigResolver\Exception\SetNotFoundException;

final class SetResolver
{
    /**
     * @var string[]
     */
    private $optionNames = [];

    /**
     * @var OptionValueResolver
     */
    private $optionValueResolver;

    /**
     * @param string[] $optionNames
     */
    public function __construct(array $optionNames = OptionName::SET)
    {
        $this->optionNames = $optionNames;
        $this->optionValueResolver = new OptionValueResolver();
    }

    public function detectFromInputAndDirectory(InputInterface $input, string $setsDirectory): ?string
    {
        $setName = $this->optionValueResolver->getOptionValue($input, $this->optionNames);
        if ($setName === null) {
            return null;
        }

        return $this->detectFromNameAndDirectory($setName, $setsDirectory);
    }

    public function detectFromNameAndDirectory(string $setName, string $configDirectory): string
    {
        $nearestMatches = $this->findNearestMatchingFiles($configDirectory, $setName);
        if (count($nearestMatches) === 0) {
            $this->reportSetNotFound($configDirectory, $setName);
        }

        /** @var SplFileInfo $nearestMatch */
        $nearestMatch = array_shift($nearestMatches);

        return $nearestMatch->getPathname();
    }

    /**
     * @return SplFileInfo[]
     */
    private function findNearestMatchingFiles(string $configDirectory, string $setName): array
    {
        $configFiles = Finder::create()
            ->files()
            ->in($configDirectory)
            ->getIterator();

        $nearestMatches = [];

        $setName = Strings::lower($setName);

        // the version must match, so 401 is not compatible with 40
        $setVersion = $this->matchVersionInTheEnd($setName);

        foreach ($configFiles as $configFile) {
            // only similar configs, not too far
            // this allows to match "Symfony.40" to "symfony40" config
            $fileNameWithoutExtension = pathinfo($configFile->getFilename(), PATHINFO_FILENAME);
            $distance = levenshtein($fileNameWithoutExtension, $setName);
            if ($distance > 2) {
                continue;
            }

            if ($setVersion) {
                $fileVersion = $this->matchVersionInTheEnd($fileNameWithoutExtension);
                if ($setVersion !== $fileVersion) {
                    // not a version match
                    continue;
                }
            }

            $nearestMatches[$distance] = $configFile;
        }

        ksort($nearestMatches);

        return $nearestMatches;
    }

    private function reportSetNotFound(string $configDirectory, string $setName): void
    {
        $allSets = $this->findAllSetsInDirectory($configDirectory);

        $suggestedSet = ObjectHelpers::getSuggestion($allSets, $setName);

        [$versionedSets, $unversionedSets] = $this->separateVersionedAndUnversionedSets($allSets);

        /** @var string[] $unversionedSets */
        /** @var string[][] $versionedSets */
        $setsListInString = $this->createSetListInString($unversionedSets, $versionedSets);

        $setNotFoundMessage = sprintf(
            'Set "%s" was not found.%s%s',
            $setName,
            PHP_EOL,
            $suggestedSet ? sprintf('Did you mean "%s"?', $suggestedSet) . PHP_EOL : ''
        );

        $pickOneOfMessage = sprintf('Pick one of:%s%s', PHP_EOL . PHP_EOL, $setsListInString);

        throw new SetNotFoundException($setNotFoundMessage . PHP_EOL . $pickOneOfMessage);
    }

    private function matchVersionInTheEnd(string $setName): ?string
    {
        $match = Strings::match($setName, '#(?<version>[\d\.]+$)#');
        if (! $match) {
            return null;
        }

        $version = $match['version'];
        return Strings::replace($version, '#\.#');
    }

    /**
     * @return string[]
     */
    private function findAllSetsInDirectory(string $configDirectory): array
    {
        $finder = Finder::create()
            ->files()
            ->in($configDirectory);

        $sets = [];
        foreach ($finder->getIterator() as $fileInfo) {
            $sets[] = $fileInfo->getBasename('.' . $fileInfo->getExtension());
        }

        sort($sets);

        return array_unique($sets);
    }

    /**
     * @param string[] $allSets
     * @return string[][]|string[][][]
     */
    private function separateVersionedAndUnversionedSets(array $allSets): array
    {
        $versionedSets = [];
        $unversionedSets = [];

        foreach ($allSets as $set) {
            $hasVersion = (bool) Strings::match($set, '#\d#');

            if (! $hasVersion) {
                $unversionedSets[] = $set;
                continue;
            }

            $match = Strings::match($set, '#^(?<set>[A-Za-z\-]+)#');
            $setWithoutVersion = $match['set'];

            if ($setWithoutVersion !== $set) {
                $versionedSets[$setWithoutVersion][] = $set;
            }
        }

        return [$versionedSets, $unversionedSets];
    }

    /**
     * @param string[] $unversionedSets
     * @param string[][] $versionedSets
     */
    private function createSetListInString(array $unversionedSets, array $versionedSets): string
    {
        $setsListInString = '';

        foreach ($unversionedSets as $unversionedSet) {
            $setsListInString .= ' * ' . $unversionedSet . PHP_EOL;
        }

        foreach ($versionedSets as $groupName => $configNames) {
            $setsListInString .= ' * ' . $groupName . ': ' . implode(', ', $configNames) . PHP_EOL;
        }

        return $setsListInString;
    }
}
