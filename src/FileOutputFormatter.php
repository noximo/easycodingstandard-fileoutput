<?php
declare(strict_types=1);

namespace noximo\EasyCodingStandardFileoutput;

use Nette\Utils\FileSystem;
use Nette\Utils\RegexpException;
use Nette\Utils\Strings;
use Symplify\EasyCodingStandard\Contract\Console\Output\OutputFormatterInterface;
use Symplify\EasyCodingStandard\Error\Error;
use Symplify\EasyCodingStandard\Error\ErrorAndDiffCollector;
use Symplify\EasyCodingStandard\Error\FileDiff;
use Symplify\PackageBuilder\Console\ShellCode;
use Webmozart\PathUtil\Path;

class FileOutputFormatter implements OutputFormatterInterface
{
    public const ERROR = 'error';

    public const LINK = 'link';

    public const LINE = 'line';

    public const FILES = 'files';

    public const FILE = 'file';

    public const UNKNOWN = 'unknown';

    public const NAME = 'file';

    public const DIFFS = 'diffs';

    public const SOURCE_CLASS = 'sourceClass';

    public const SOURCE_CLASS_LINK = 'sourceClassLink';

    public const DIFF = 'diff';

    /** @var string */
    private $link = 'editor://open/?file=%file&line=%line';

    /**
     * @var OutputFormatterInterface|null
     */
    private $defaultFormatter;

    /**
     * @var string
     */
    private $outputFile;

    /**
     * @var string
     */
    private $template;

    /** @var ErrorAndDiffCollector */
    private $errorAndDiffCollector;

    /** @var string */
    private $cwd;

    /**
     * FileOutput constructor.
     *
     * @param OutputFormatterInterface|null $defaultFormatter
     * @param string|null $customTemplate
     *
     * @throws \Safe\Exceptions\DirException
     */
    public function __construct(string $outputFile, ErrorAndDiffCollector $errorAndDiffCollector, ?OutputFormatterInterface $defaultFormatter = null, ?string $customTemplate = null)
    {
        $this->cwd = \Safe\getcwd() . DIRECTORY_SEPARATOR;
        $this->defaultFormatter = $defaultFormatter;

        try {
            $outputFile = Strings::replace($outputFile, '{time}', (string) time());
        } catch (RegexpException $e) {
        }

        $this->outputFile = Path::canonicalize($this->cwd . $outputFile);
        $customTemplateFile = $customTemplate !== null ? realpath($customTemplate) : false;
        if ($customTemplateFile !== false) {
            $this->template = $customTemplateFile;
        } else {
            $this->template = __DIR__ . '/table.phtml';
        }
        $this->errorAndDiffCollector = $errorAndDiffCollector;
    }

    /**
     * @param mixed[] $data
     */
    public function getTable(array $data): string
    {
        ob_start(function (): void {
        });
        require $this->template;

        $output = ob_get_clean();

        return $output !== false ? $output : 'Output failed.';
    }

    /**
     * @throws \Safe\Exceptions\ArrayException
     * @throws RegexpException
     */
    public function report(int $processedFilesCount): int
    {
        $this->generateFile();

        if ($this->defaultFormatter !== null) {
            return $this->defaultFormatter->report($processedFilesCount);
        }
        if ($this->errorAndDiffCollector->getErrorCount() === 0 && $this->errorAndDiffCollector->getFileDiffsCount() === 0) {
            return ShellCode::SUCCESS;
        }

        return ShellCode::ERROR;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * @throws \Safe\Exceptions\ArrayException
     * @throws RegexpException
     */
    private function generateFile(): void
    {
        $output = [
            self::UNKNOWN => [],
            self::FILES => [],
        ];
        if ($this->errorAndDiffCollector->getErrorCount() > 0 || $this->errorAndDiffCollector->getFileDiffsCount() > 0) {
            $output[self::DIFFS] = $this->reportFileDiffs($this->errorAndDiffCollector->getFileDiffs());

            foreach ($this->errorAndDiffCollector->getErrors() as $filename => $file) {
                /** @var Error $error */
                foreach ($file as $error) {
                    $line = $error->getLine();
                    $link = $this->prepareLink($this->normalizeFilename($filename), $line);
                    $output[self::FILES][$filename][] = [
                        self::ERROR => $this->formatMessage($error->getMessage()),
                        self::SOURCE_CLASS => $error->getSourceClass(),
                        self::LINK => $link,
                        self::LINE => $line,
                    ];
                }
            }

            foreach ($output[self::FILES] as &$file) {
                usort($file, function ($a, $b) {
                    return -1 * ($a[self::LINE] <=> $b[self::LINE]);
                });
            }
            unset($file);

            FileSystem::write($this->outputFile, $this->getTable($output));
        }
    }

    private function formatMessage(string $message): string
    {
        $words = explode(' ', $message);
        $words = array_map(function ($word) {
            if (Strings::match($word, '/[^a-zA-Z,.]|(string)|(bool)|(boolean)|(int)|(integer)|(float)/')) {
                $word = '<b>' . $word . '</b>';
            }

            return $word;
        }, $words);

        return implode(' ', $words);
    }

    /**
     * @param FileDiff[][] $fileDiffPerFile
     *
     * @return mixed[]
     * @throws \Safe\Exceptions\ArrayException
     * @throws RegexpException
     */
    private function reportFileDiffs(array $fileDiffPerFile): array
    {
        if (!count($fileDiffPerFile)) {
            return [];
        }

        $files = [];

        foreach ($fileDiffPerFile as $file => $fileDiffs) {
            $diffs = [];

            foreach ($fileDiffs as $fileDiff) {
                $diff = [];
                $diff[self::DIFF] = $this->processDiff($fileDiff->getDiffConsoleFormatted());

                $diff[self::SOURCE_CLASS] = array_map(function ($checker) {
                    return [
                        self::SOURCE_CLASS => $checker,
                    ];
                }, $fileDiff->getAppliedCheckers());

                $diffs[] = $diff;
            }

            $files[$file][self::DIFFS] = $diffs;
            $files[$file][self::FILE] = $this->normalizeFilename($file);
            $files[$file][self::LINK] = $this->prepareLink($this->normalizeFilename($file), 1);
        }

        return $files;
    }

    /**
     * @throws RegexpException
     */
    private function processDiff(string $diff): string
    {
        $diff = Strings::replace($diff, '/<fg=cyan>/', "<span class='gitinfo'>");
        $diff = Strings::replace($diff, '/<\/fg=cyan>/', '</span>');

        $diff = Strings::replace($diff, '/<fg=red>/', "<span class='deletion'>");
        $diff = Strings::replace($diff, '/<\/fg=red>/', '</span>');

        $diff = Strings::replace($diff, '/<fg=green>/', "<span class='addition'>");
        return Strings::replace($diff, '/<\/fg=green>/', '</span>');
    }

    private function normalizeFilename(string $filename): string
    {
        return Path::canonicalize($this->cwd . $filename);
    }

    private function prepareLink(string $filename, int $line): string
    {
        return strtr($this->link, ['%file' => $filename, '%line' => $line]);
    }
}
