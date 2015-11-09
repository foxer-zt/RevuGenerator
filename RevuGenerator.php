<?php
use PhpParser\Lexer\Emulative as Lexer;
use PhpParser\Parser;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node;
use PhpParser\Node\FunctionLike;

class RevuGenerator
{
    /**
     * @constructor
     */
    public function __construct()
    {
        $occurences = 0;
        if (is_file(Settings::FILE_PATH)) {
            $occurences = $this->processFile(Settings::FILE_PATH);
        } else {
            $iterator = $this->getFiles();
            foreach ($iterator as $file) {
                $occurences += $this->processFile($file->getRealPath());
            }
        }

        $issue = $occurences !== 1 ? 'issues were' : 'issue was';
        echo "$occurences $issue added to revu.";
    }

    /**
     * Get files by path.
     *
     * @return RecursiveIteratorIterator
     */
    protected function getFiles()
    {
        $directoryIterator = new RecursiveDirectoryIterator(realpath(Settings::FILE_PATH),
            RecursiveDirectoryIterator::SKIP_DOTS);

        return new RecursiveIteratorIterator($directoryIterator);
    }

    /**
     * Parse file.
     *
     * @param string $path
     * @return int
     */
    protected function processFile($path)
    {
        $data = [];
        $code = file_get_contents(realpath($path));
        $parser = new Parser(new Lexer());
        $statements = $parser->parse($code);
        $data['filePath'] = $this->getFilePath($path);
        $data['className'] = $this->getClassName($statements);
        $statements = $data['className'] !== '' ? $statements[0]->stmts : $statements;
        $occurences = $this->findOccurences($path);
        foreach ($occurences as $line) {
            $data['methodName'] = $this->getIssueParentMethod($statements, $line);
            $data['lineStart'] = $data['lineEnd'] = $line - 1; //Revu line numbers stratring from 0
            $this->addIssuesToRevu($data);
        }

        return count($occurences);
    }

    /**
     * Get file path.
     *
     * @param string $path
     * @return string
     */
    protected function getFilePath($path)
    {
        return strpos($path, 'app\code') !== false ? preg_replace('@.*(app\\\\code.*)@', '$1', $path) : $path;
    }

    /**
     * Get issue parent method.
     *
     * @param array $statements
     * @param int $line
     * @return string
     */
    protected function getIssueParentMethod(array $statements, $line)
    {
        foreach ($statements as $statement) {
            if ($statement instanceof FunctionLike) {
                $attributes = $statement->getAttributes();
                $startLine = $attributes['startLine'];
                $endLine = $attributes['endLine'];
                if ($line >= $startLine && $line <= $endLine) {
                    return $statement->name;
                }
            }
        }

        return '';
    }


    /**
     * Retrieve class name.
     *
     * @param array $statements
     * @return string
     */
    protected function getClassName(array $statements)
    {
        foreach ($statements as $statement) {
            if ($statement instanceof Class_) {
                $className = $statement->name;
                break;
            }
        }

        return isset($className) ? $className : '';
    }

    /**
     * Add issue node to revu document.
     *
     * @param array $data
     * @return void
     */
    protected function addIssuesToRevu(array $data)
    {
        $xml = simplexml_load_file(Settings::REVU_PATH);
        /** @var SimpleXMLElement $issues */
        $issues = $xml->issues;
        $this->createIssue($issues, $data);
        $xml->saveXML(Settings::REVU_PATH);
    }

    /**
     * Create issue node.
     *
     * @param SimpleXMLElement $parentNode
     * @param array $data
     * @return void
     */
    protected function createIssue(SimpleXMLElement $parentNode, array $data)
    {
        $time = gmdate('Y-m-d h:m:s O');
        $issue = $parentNode->addChild('issue');
        $issue->addAttribute('filePath', $data['filePath']);
        $issue->addAttribute('summary', Settings::SUMMARY);
        $issue->addAttribute('className', $data['className']);
        $issue->addAttribute('methodName', $data['methodName']);
        $issue->addAttribute('lineStart', $data['lineStart']);
        $issue->addAttribute('lineEnd', $data['lineEnd']);
        $issue->addAttribute('hash', '-' . rand(1000000000, 9999999999));
        $issue->addAttribute('tags', Settings::TAG);
        $issue->addAttribute('priority', Settings::PRIORITY);
        $issue->addAttribute('issueName', Settings::ISSUE_NAME);
        $issue->addAttribute('status', 'to_resolve');
        $history = $issue->addChild('history');
        $history->addAttribute('createdBy', Settings::CREATED_BY);
        $history->addAttribute('lastUpdatedBy', Settings::CREATED_BY);
        $history->addAttribute('createdOn', $time);
        $history->addAttribute('lastUpdatedOn', $time);
        $issue->addChild('desc', Settings::RECOMMENDATION);
    }

    /**
     * Get line numbers of lines with matched pattern.
     *
     * @param string $file
     * @return array
     */
    protected function findOccurences($file)
    {
        $lineNumbers = [];
        if ($handle = fopen($file, "r")) {
            $count = 0;
            while (($line = fgets($handle, 4096)) !== false) {
                $count++;
                preg_match("@" . Settings::PATTERN . "@", $line, $matches);
                if (isset($matches[0])) {
                    $lineNumbers[] = $count;
                }
            }
            fclose($handle);
        }

        return $lineNumbers;
    }
}