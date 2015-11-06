<?php
use PhpParser\Lexer\Emulative as Lexer;
use PhpParser\Parser;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node;
use PhpParser\Node\FunctionLike;

class RevuGenerator
{
    /**
     * Issue base information. Change it to correspond before running application.
     */
    const SUMMARY = 'Store collection is already loaded.';

    const TAG = 'Performance';

    const PRIORITY = 'Medium';

    const RECOMMENDATION = 'Store collection is already loaded. Please avoid data loading which is already loaded.';

    const CREATED_BY = 'username';

    /**
     * Path to revu xml file.
     *
     * @var string
     */
    protected $revuPath;

    /**
     * Regexp pattern for issue.
     *
     * @var string
     */
    protected $pattern;

    /**
     * @param string $filePath
     * @param string $revuPath
     * @param string $pattern
     */
    public function __construct($filePath, $revuPath, $pattern)
    {
        $this->revuPath = $revuPath;
        $this->pattern = $pattern;
        $this->processFile($filePath);
    }

    /**
     * Parse file.
     *
     * @param string $path
     * @return void
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
            $data['lineStart'] = $data['lineEnd'] = $line;
            $this->addIssuesToRevu($data);
        }
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
        $xml = simplexml_load_file($this->revuPath);
        /** @var SimpleXMLElement $issues */
        $issues = $xml->issues;
        $this->createIssue($issues, $data);
        $xml->saveXML($this->revuPath);
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
        $issue->addAttribute('summary', self::SUMMARY);
        $issue->addAttribute('className', $data['className']);
        $issue->addAttribute('methodName', $data['methodName']);
        $issue->addAttribute('lineStart', $data['lineStart']);
        $issue->addAttribute('lineEnd', $data['lineEnd']);
        $issue->addAttribute('hash', '-' . uniqid());
        $issue->addAttribute('tags', self::TAG);
        $issue->addAttribute('priority', self::PRIORITY);
        $issue->addAttribute('status', 'to_resolve');
        $history = $issue->addChild('history');
        $history->addAttribute('createdBy', self::CREATED_BY);
        $history->addAttribute('lastUpdatedBy', self::CREATED_BY);
        $history->addAttribute('createdOn', $time);
        $history->addAttribute('lastUpdatedOn', $time);
        $issue->addChild('desc', self::RECOMMENDATION);
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
                preg_match("@" . $this->pattern . "@", $line, $matches);
                if (isset($matches[0])) {
                    $lineNumbers[] = $count;
                }
            }
            fclose($handle);
        }

        return $lineNumbers;
    }
}